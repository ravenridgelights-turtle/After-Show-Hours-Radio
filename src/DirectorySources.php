<?php
namespace AfterShow;

/** Small provider adapters. They parse one bounded response, never execute scripts. */
final class DirectorySources
{
    const PAGE_SIZE=25;
    const SOURCES=[
        'radiobrowser-christmas'=>['label'=>'Radio Browser · Christmas','url'=>'https://de1.api.radio-browser.info/json/stations/search','kind'=>'radiobrowser','term'=>'christmas'],
        'radiobrowser-halloween'=>['label'=>'Radio Browser · Halloween','url'=>'https://de1.api.radio-browser.info/json/stations/search','kind'=>'radiobrowser','term'=>'halloween'],
        'radiobrowser-newyear'=>['label'=>'Radio Browser · New Year / Party','url'=>'https://de1.api.radio-browser.info/json/stations/search','kind'=>'radiobrowser','term'=>'party','field'=>'tag'],
        'radiobrowser-search'=>['label'=>'Radio Browser · Search','url'=>'https://de1.api.radio-browser.info/json/stations/search','kind'=>'radiobrowser','custom'=>true],
        '181fm-christmas'=>['label'=>'181.FM · Christmas','url'=>'https://www.181.fm/legacy.html','kind'=>'181fm'],
        '181fm-all'=>['label'=>'181.FM · All stations','url'=>'https://www.181.fm/legacy.html','kind'=>'181fm'],
        'internet-radio-christmas'=>['label'=>'Internet-Radio.com · Christmas','url'=>'https://www.internet-radio.com/stations/christmas/','kind'=>'internet-radio','term'=>'christmas'],
        'internet-radio-genre'=>['label'=>'Internet-Radio.com · Browse a genre','url'=>'https://www.internet-radio.com/stations/','kind'=>'internet-radio','custom'=>true],
        'christmas'=>['label'=>'StreamURL · Christmas','url'=>'https://streamurl.link/s/christmas','kind'=>'streamurl'],
        'halloween'=>['label'=>'StreamURL · Halloween','url'=>'https://streamurl.link/s/halloween','kind'=>'streamurl'],
        'newyear'=>['label'=>'StreamURL · New Year','url'=>'https://streamurl.link/s/new+year','kind'=>'streamurl'],
        'halloweenradio'=>['label'=>'Halloweenradio.net','url'=>'https://halloweenradio.net/stations','kind'=>'halloweenradio']
    ];
    public static function request($source,$page,$query='',$searchBy='name')
    {
        if (!isset(self::SOURCES[$source])) throw new \InvalidArgumentException('Unknown station source.');
        $s=self::SOURCES[$source];$kind=$s['kind'];$url=$s['url'];$key=$source.'-'.$page;
        $term=isset($s['term']) ? $s['term'] : Store::text($query,80);
        $field=empty($s['custom']) ? ($s['field'] ?? 'name') : ($kind==='radiobrowser' && $searchBy==='tag' ? 'tag' : 'name');
        if (!empty($s['custom'])) {
            if ($term==='') throw new \InvalidArgumentException($kind==='radiobrowser' ? 'Enter a station name or genre/tag to search.' : 'Enter a genre such as jazz, oldies, country or classic rock.');
            $key=$source.'-'.substr(hash('sha256',$field.'|'.strtolower($term)),0,20).'-'.$page;
        }
        if ($kind==='radiobrowser') {
            $params=['limit'=>self::PAGE_SIZE+1,'offset'=>($page-1)*self::PAGE_SIZE,'hidebroken'=>'true','order'=>'clickcount','reverse'=>'true',$field=>$term];
            $url.='?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
        } elseif ($kind==='internet-radio') {
            $term=strtolower(preg_replace('/\s+/',' ',trim($term)));
            if (!preg_match('/^[a-z0-9][a-z0-9 -]{0,39}$/',$term)) throw new \InvalidArgumentException('Use a genre name with letters, numbers, spaces or hyphens, up to 40 characters.');
            $url='https://www.internet-radio.com/stations/'.rawurlencode($term).'/'.($page>1 ? 'page'.$page : '');
        } elseif ($kind==='streamurl' && $page>1) $url.='?page='.$page;
        elseif ($kind==='181fm') $key='181fm-full'; // Both views share a single six-hour fetch.
        $host=parse_url($url,PHP_URL_HOST);
        $hosts=[$host];if ($kind==='181fm') $hosts=['www.181.fm','181.fm'];
        return ['source'=>$source,'kind'=>$kind,'url'=>$url,'hosts'=>$hosts,'group'=>$kind,'cacheKey'=>$key,'term'=>$term,'searchBy'=>$field,'page'=>$page];
    }
    public static function parseRadioBrowser($body,$category)
    {
        $rows=json_decode($body,true,32);
        if (!is_array($rows) || array_values($rows)!==$rows) throw new \RuntimeException('Radio Browser returned an invalid station list.');
        $stations=[];$seen=[];
        foreach (array_slice($rows,0,self::PAGE_SIZE) as $row) {
            if (!is_array($row) || empty($row['lastcheckok']) || !empty($row['hls'])) continue;
            $url=$row['url_resolved'] ?? '';
            if (!$url) $url=$row['url'] ?? '';
            if (!Store::validUrl($url) || isset($seen[$url]) || preg_match('/\.(pls|m3u8?)(\?|$)/i',$url)) continue;
            $codec=strtoupper(Store::text($row['codec'] ?? '',20));
            if ($codec && !in_array($codec,['MP3','AAC','AAC+','AAC-HE','OGG','VORBIS','OPUS','FLAC'],true)) continue;
            $seen[$url]=true;
            $stations[]=['name'=>Store::text($row['name'] ?? parse_url($url,PHP_URL_HOST)),'url'=>$url,'category'=>Store::text($category),'detail'=>false,
                'country'=>Store::text($row['country'] ?? '',80),'codec'=>$codec,'bitrate'=>max(0,min(2000,(int)($row['bitrate'] ?? 0)))];
        }
        return ['stations'=>$stations,'hasMore'=>count($rows)>self::PAGE_SIZE];
    }
    private static function document($html)
    {
        if (!class_exists('DOMDocument')) throw new \RuntimeException('PHP XML is missing. Reinstall the plugin.');
        $doc=new \DOMDocument();$old=libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors();libxml_use_internal_errors($old); }
        return new \DOMXPath($doc);
    }
    public static function parse181($html)
    {
        $xp=self::document($html);$stations=[];$seen=[];
        foreach ($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," legacy-section ")]') as $section) {
            $heading=$xp->query('.//h2',$section)->item(0);$category=Store::text($heading ? $heading->textContent : '181.FM');
            foreach ($xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," legacy-row ")]',$section) as $row) {
                $cells=$xp->query('./*[contains(concat(" ",normalize-space(@class)," ")," legacy-cell ")]',$row);
                if ($cells->length<3) continue;
                $text=$cells->item(2)->textContent;
                if (!preg_match('#https?://listen\.181fm\.com/[A-Za-z0-9_-]+\.mp3#',$text,$m) && !preg_match('#https?://listen\.181fm\.com/[A-Za-z0-9_-]+\.aac#',$text,$m)) continue;
                $url=$m[0];if (isset($seen[$url]) || count($stations)>=250) continue;
                $seen[$url]=true;$name=Store::text($cells->item(0)->textContent);
                $stations[]=['name'=>'181.FM · '.$name,'url'=>$url,'category'=>$category,'detail'=>false,'codec'=>substr($url,-4)==='.mp3' ? 'MP3' : 'AAC'];
            }
        }
        if (!$stations) throw new \RuntimeException('181.FM did not expose its published stream list. Try another source.');
        return ['stations'=>$stations,'hasMore'=>false];
    }
    public static function parseInternetRadio($html,$term,$page)
    {
        $xp=self::document($html);$streams=[];$stations=[];$seen=[];
        // Read only literal URL data used by the page's players. Never run JavaScript.
        preg_match_all('/\bvar\s+stream([0-9]{1,4})\s*=\s*\{\s*(?:mp3|m4a|oga)\s*:\s*("(?:[^"\\\\]|\\\\.)*")\s*\}/s',$html,$matches,PREG_SET_ORDER);
        foreach ($matches as $m) {
            $url=json_decode($m[2],true);if (Store::validUrl($url)) $streams[$m[1]]=$url;
        }
        foreach ($xp->query('//tr[.//h4]') as $row) {
            $heading=$xp->query('.//h4',$row)->item(0);$idNode=$xp->query('.//*[@id]',$row);$url=null;
            foreach ($idNode as $item) {
                if (preg_match('/^(?:jquery_jplayer_|play_|play_nohtml5_)([0-9]+)$/',$item->getAttribute('id'),$m) && isset($streams[$m[1]])) { $url=$streams[$m[1]];break; }
            }
            if (!$url || isset($seen[$url]) || count($stations)>=60) continue;
            $seen[$url]=true;$stations[]=['name'=>Store::text($heading->textContent),'url'=>$url,'category'=>Store::text(ucwords($term)),'detail'=>false];
        }
        $hasMore=false;
        foreach ($xp->query('//ul[contains(concat(" ",normalize-space(@class)," ")," pagination ")]//a[@href]') as $link) {
            if (preg_match('#/page([0-9]+)$#',$link->getAttribute('href'),$m) && (int)$m[1]>$page) $hasMore=true;
        }
        // An empty real directory is valid. A JS challenge or changed layout is not.
        if (!$stations && (!$xp->query('//h1')->length || stripos($xp->query('//h1')->item(0)->textContent,'Radio Stations')===false)) throw new \RuntimeException('Internet-Radio.com did not return a station directory. Try again later.');
        return ['stations'=>$stations,'hasMore'=>$hasMore];
    }
    public static function view181($source,$cache,$page)
    {
        $stations=$cache['stations'];
        if ($source==='181fm-christmas') $stations=array_values(array_filter($stations,function($s) { return stripos($s['category'],'christmas')!==false || stripos($s['name'],'christmas')!==false; }));
        $start=($page-1)*self::PAGE_SIZE;
        return array_replace($cache,['stations'=>array_slice($stations,$start,self::PAGE_SIZE),'page'=>$page,'hasMore'=>count($stations)>$start+self::PAGE_SIZE]);
    }
}
