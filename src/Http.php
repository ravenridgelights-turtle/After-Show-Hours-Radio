<?php
namespace AfterShow;

final class Http
{
    /** Pin resolved public IPs, and validate every redirect before following. */
    public static function get($url, $seconds = 8.0, $maxBytes = 1572864, $probe = false, $allowedHosts = null)
    {
        if (!extension_loaded('curl')) throw new \RuntimeException('PHP cURL is missing. Reinstall the plugin.');
        $deadline=microtime(true)+$seconds;
        for ($redirect=0;$redirect<4;$redirect++) {
            if (!Store::validUrl($url)) throw new \RuntimeException('Only HTTP(S) URLs without credentials are supported.');
            $parts=parse_url($url);$host=strtolower($parts['host']);$port=$parts['port'] ?? ($parts['scheme']==='https' ? 443 : 80);
            if ($allowedHosts!==null && !in_array($host,$allowedHosts,true)) throw new \RuntimeException('Directory redirected outside its supported host.');
            $literal=trim($host,'[]');
            $ips=filter_var($literal,FILTER_VALIDATE_IP) ? [$literal] : @gethostbynamel($host);
            if (!$ips) throw new \RuntimeException('Could not resolve the station host.');
            foreach ($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Directory and Seek checks require public internet addresses.');
            $remaining=(int)(($deadline-microtime(true))*1000);
            if ($remaining<=0) throw new \RuntimeException('Station request timed out.');
            $body='';$headers=[];$limited=false;
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT_MS=>min(2000,$remaining),CURLOPT_TIMEOUT_MS=>$remaining,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_USERAGENT=>'AfterShowHoursRadio/1.1 (+https://github.com/ravenridgelights-turtle/After-Show-Hours-Radio)',
                CURLOPT_HTTPHEADER=>['Accept: '.($probe ? 'audio/*,application/ogg,*/*;q=0.2' : 'text/html,application/json;q=0.9')],
                CURLOPT_RESOLVE=>[$host.':'.$port.':'.(strpos($ips[0],':')!==false ? '['.$ips[0].']' : $ips[0])],
                CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$headers) {
                    $p=explode(':',$line,2);if (count($p)===2) $headers[strtolower(trim($p[0]))]=trim($p[1]);return strlen($line);
                },
                CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use (&$body,&$limited,$maxBytes) {
                    $remaining=$maxBytes-strlen($body);
                    if (strlen($chunk)>$remaining) { $body.=substr($chunk,0,$remaining);$limited=true;return 0; }
                    $body.=$chunk;return strlen($chunk);
                }
            ]);
            curl_exec($ch);$error=curl_errno($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if (in_array($status,[301,302,303,307,308],true) && isset($headers['location'])) {
                $url=self::absolute($headers['location'],$url);continue;
            }
            if (!$probe && $limited) throw new \RuntimeException('Directory response exceeded the 1.5 MB limit.');
            if ($error && !($probe && strlen($body)>0)) throw new \RuntimeException('Station request timed out or could not connect.');
            return ['status'=>$status,'headers'=>$headers,'body'=>$body,'url'=>$url];
        }
        throw new \RuntimeException('Too many station redirects.');
    }
    public static function absolute($link,$base)
    {
        $link=html_entity_decode(trim($link),ENT_QUOTES | ENT_HTML5,'UTF-8');
        if (preg_match('#^https?://#i',$link)) return $link;
        $p=parse_url($base);$root=$p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (strpos($link,'//')===0) return $p['scheme'].':'.$link;
        if (strpos($link,'?')===0) return $root.($p['path'] ?? '/').$link;
        if (strpos($link,'/')===0) return $root.$link;
        return $root.rtrim(dirname($p['path'] ?? '/'),'/').'/'.$link;
    }
    public static function probe($url)
    {
        try {
            $r=self::get($url,2.0,4096,true);
            $type=strtolower($r['headers']['content-type'] ?? '');
            $b=$r['body'];
            $audio=strpos($type,'audio/')===0 || strpos($type,'application/ogg')===0 || strpos($b,'OggS')===0 || strpos($b,'ID3')===0 || (strlen($b)>1 && ord($b[0])===255 && (ord($b[1]) & 224)===224);
            return $r['status']>=200 && $r['status']<300 && strlen($b)>0 && $audio;
        } catch (\Throwable $e) { return false; }
    }
}
