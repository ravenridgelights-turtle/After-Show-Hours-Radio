<?php
namespace AfterShow;

final class Catalog
{
    private $store;
    const SOURCES=[
        'christmas'=>['label'=>'StreamURL · Christmas','url'=>'https://streamurl.link/s/christmas'],
        'halloween'=>['label'=>'StreamURL · Halloween','url'=>'https://streamurl.link/s/halloween'],
        'newyear'=>['label'=>'StreamURL · New Year','url'=>'https://streamurl.link/s/new+year'],
        'halloweenradio'=>['label'=>'Halloweenradio.net','url'=>'https://halloweenradio.net/stations']
    ];
    public function __construct(Store $store) { $this->store=$store; }
    public function load($source,$refresh=false,$page=1)
    {
        if (!isset(self::SOURCES[$source])) throw new \InvalidArgumentException('Unknown station source.');
        $page=max(1,min(10,(int)$page));if ($source==='halloweenradio') $page=1;
        return $this->store->lock('directory',function() use ($source,$refresh,$page) {
            $path=$this->store->dataDir.'/catalog-'.$source.'-'.$page.'.json';
            $cache=Store::read($path);$now=time();$warning=null;
            if (!$refresh && $cache && $now-($cache['fetchedAt'] ?? 0)<21600) return $this->result($source,$cache,true);
            $throttle=Store::read($this->store->runDir.'/directory-limits.json');
            if ($now<($throttle['sources'][$source] ?? 0)) $warning='This source asked us to wait. Try again after '.date('g:i a',$throttle['sources'][$source]).'.';
            elseif ($now-($throttle['last'] ?? 0)<10) $warning='Please wait 10 seconds between directory requests.';
            if (!$warning) {
                $throttle['last']=$now;
                Store::write($this->store->runDir.'/directory-limits.json',$throttle);
                try {
                    $base=self::SOURCES[$source]['url'];$url=$base.($page>1 ? '?page='.$page : '');
                    $r=Http::get($url,8,1572864,false,[$source==='halloweenradio' ? 'halloweenradio.net' : 'streamurl.link']);
                    if ($r['status']<200 || $r['status']>=300) {
                        $retry=$r['headers']['retry-after'] ?? '600';
                        $seconds=ctype_digit($retry) ? (int)$retry : max(0,(int)strtotime($retry)-$now);
                        $throttle['sources'][$source]=$now+max(60,min(86400,$seconds));
                        Store::write($this->store->runDir.'/directory-limits.json',$throttle);
                        throw new \RuntimeException('Directory returned HTTP '.$r['status'].'. Saved stations still work; its retry delay will be respected.');
                    }
                    $parsed=self::parse($r['body'],$r['url'],$source);
                    if (!$parsed['stations']) throw new \RuntimeException('No playable links found. The site may have changed or require a browser.');
                    $cache=$parsed+['fetchedAt'=>$now,'page'=>$page];Store::write($path,$cache);
                    return $this->result($source,$cache,false);
                } catch (\Throwable $e) {
                    $warning=$e->getMessage();
                    $throttle['sources'][$source]=max($throttle['sources'][$source] ?? 0,$now+600);
                    Store::write($this->store->runDir.'/directory-limits.json',$throttle);
                }
            }
            if (!$cache && $page===1) {
                $seeds=Store::read(__DIR__.'/../stations.json');
                $cache=['stations'=>$seeds[$source] ?? [],'fetchedAt'=>0,'page'=>1,'hasMore'=>false];
            }
            return $this->result($source,$cache ?: ['stations'=>[],'fetchedAt'=>0,'page'=>$page,'hasMore'=>false],true,$warning);
        },0.1);
    }
    private function result($source,$cache,$cached,$warning=null)
    {
        return ['source'=>$source,'label'=>self::SOURCES[$source]['label'],'stations'=>$cache['stations'],
            'cached'=>$cached,'fetchedAt'=>$cache['fetchedAt'],'bundled'=>empty($cache['fetchedAt']),
            'page'=>$cache['page'] ?? 1,'hasMore'=>$cache['hasMore'] ?? false,'warning'=>$warning];
    }
    public static function parse($html,$base,$source)
    {
        if (!class_exists('DOMDocument')) throw new \RuntimeException('PHP XML is missing. Reinstall the plugin.');
        $doc=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
        $xp=new \DOMXPath($doc);$stations=[];$seen=[];$hasMore=false;
        $add=function($url,$name,$category,$detail=false) use (&$stations,&$seen) {
            if (!Store::validUrl($url) || isset($seen[$url]) || count($stations)>=60) return;
            $seen[$url]=true;$name=Store::text(html_entity_decode($name,ENT_QUOTES | ENT_HTML5,'UTF-8'));
            if (!$name) $name=parse_url($url,PHP_URL_HOST);
            $stations[]=['name'=>$name,'url'=>$url,'category'=>$category,'detail'=>$detail];
        };
        if ($source==='halloweenradio') {
            foreach ($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," hr-station-detail-card ")]') as $card) {
                $heading=$xp->query('.//h3',$card)->item(0);$name=$heading ? trim($heading->textContent) : 'Halloweenradio';
                foreach ($xp->query('.//code',$card) as $code) {
                    $url=trim($code->textContent);
                    if (preg_match('#^https://stream\.halloweenradio\.net/listen/[^/]+/(mp3|aac)(\?|$)#',$url,$m)) $add($url,$name.($m[1]==='mp3' ? ' · MP3' : ' · AAC+'),'Halloween');
                }
            }
            return ['stations'=>$stations,'hasMore'=>false];
        }
        // HTML audio, download links, data attributes and public inline URL fields.
        foreach ($xp->query('//a[@href] | //audio[@src] | //source[@src] | //*[@data-stream-url or @data-stream or @data-url or @data-src] | //code') as $node) {
            $candidate='';
            foreach (['data-stream-url','data-stream','data-url','data-src','src','href'] as $attr) if ($node->hasAttribute($attr)) { $candidate=$node->getAttribute($attr);break; }
            if ($node->nodeName==='code') $candidate=trim($node->textContent);
            if (!$candidate || !preg_match('#^(https?://|/|\?)#i',$candidate)) continue;
            $url=Http::absolute($candidate,$base);
            $host=strtolower(parse_url($url,PHP_URL_HOST) ?? '');$path=parse_url($url,PHP_URL_PATH) ?? '';
            if ($host==='streamurl.link' && preg_match('/[?&]page=([0-9]+)/',$url,$pageMatch)) {
                $current=[];parse_str(parse_url($base,PHP_URL_QUERY) ?? '',$current);
                if ((int)$pageMatch[1]>(int)($current['page'] ?? 1)) $hasMore=true;continue;
            }
            $isAudio=in_array($node->nodeName,['audio','source'],true) || preg_match('#\.(mp3|aac|ogg|opus|m3u|pls)(/|\?|$)|/listen/|/stream(/|$|\?)|/radio/[0-9]+/#i',$url)
                || $node->hasAttribute('data-stream-url') || $node->hasAttribute('data-stream');
            $detail=$host==='streamurl.link' && preg_match('#^/(r|radio|station|stream)/[^/]+#',$path);
            if (!$isAudio && !$detail) continue;
            if (preg_match('/\.(png|jpe?g|webp|svg|css|js)(\?|$)/i',$url)) continue;
            $name=$node->getAttribute('data-name') ?: $node->getAttribute('title');
            $parent=$node;
            for ($depth=0;$depth<5 && !$name && $parent instanceof \DOMElement;$depth++,$parent=$parent->parentNode) {
                $heading=$xp->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[@itemprop="name"]',$parent);
                if ($heading->length===1) $name=trim($heading->item(0)->textContent);
            }
            if (!$name) $name=trim($node->textContent);
            $add($url,$name,ucfirst($source), (bool)$detail);
        }
        // JSON-LD / structured station fields; no JavaScript is executed.
        foreach ($xp->query('//script[@type="application/ld+json" or @type="application/json"]') as $script) {
            $data=json_decode($script->textContent,true);if (!is_array($data)) continue;
            $walk=function($obj,$depth=0) use (&$walk,$add,$source) {
                if (!is_array($obj) || $depth>12) return;
                $name=$obj['name'] ?? $obj['title'] ?? '';
                foreach (['contentUrl','streamUrl','stream_url','audioUrl'] as $key) if (isset($obj[$key]) && is_string($obj[$key])) $add($obj[$key],is_scalar($name) ? (string)$name : '',ucfirst($source));
                foreach ($obj as $v) if (is_array($v)) $walk($v,$depth+1);
            };$walk($data);
        }
        return ['stations'=>$stations,'hasMore'=>$hasMore];
    }
    public function resolve($source,$url)
    {
        if (!isset(self::SOURCES[$source]) || $source==='halloweenradio' || !Store::validUrl($url)) throw new \InvalidArgumentException('Unsupported station detail page.');
        $p=parse_url($url);
        if (strtolower($p['host'])!=='streamurl.link' || !preg_match('#^/(r|radio|station|stream)/[^/]+#',$p['path'] ?? '')) throw new \InvalidArgumentException('Only StreamURL station pages can be resolved.');
        // A detail lookup uses the same global cooldown and source backoff as a directory.
        return $this->store->lock('directory',function() use ($source,$url) {
            $path=$this->store->dataDir.'/detail-'.hash('sha256',$url).'.json';$cache=Store::read($path);$now=time();
            if ($cache && $now-$cache['fetchedAt']<21600) return $cache;
            $t=Store::read($this->store->runDir.'/directory-limits.json');
            if ($now<($t['sources'][$source] ?? 0) || $now-($t['last'] ?? 0)<10) throw new \RuntimeException('Directory is cooling down. Wait before resolving another station.');
            $t['last']=$now;Store::write($this->store->runDir.'/directory-limits.json',$t);
            $r=Http::get($url,8,1572864,false,['streamurl.link']);
            if ($r['status']<200 || $r['status']>=300) {
                $retry=$r['headers']['retry-after'] ?? '600';$seconds=ctype_digit($retry) ? (int)$retry : max(0,(int)strtotime($retry)-$now);
                $t['sources'][$source]=$now+max(60,min(86400,$seconds));Store::write($this->store->runDir.'/directory-limits.json',$t);
                throw new \RuntimeException('Station page returned HTTP '.$r['status'].'. Retry delay is active.');
            }
            $parsed=self::parse($r['body'],$r['url'],$source);$parsed['stations']=array_values(array_filter($parsed['stations'],function($s) { return !$s['detail']; }));
            if (!$parsed['stations']) throw new \RuntimeException('This station page did not expose a playable stream.');
            $parsed['fetchedAt']=$now;Store::write($path,$parsed);return $parsed;
        },0.1);
    }
    public function clearCache()
    {
        return $this->store->lock('directory',function() {
            foreach (['catalog-*.json','detail-*.json'] as $pattern) foreach (glob($this->store->dataDir.'/'.$pattern) as $file) unlink($file);
            // Keep backoff markers so Clear cache cannot bypass provider limits.
            return ['message'=>'Directory cache cleared'];
        },0.1);
    }
}
