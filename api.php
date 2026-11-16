<?php
require_once __DIR__.'/src/bootstrap.php';
function getEndpointsAfterShowHoursRadio()
{
    return [
        ['method'=>'GET','endpoint'=>'details','callback'=>'ashradio_details'],
        ['method'=>'POST','endpoint'=>'control','callback'=>'ashradio_control'],
        ['method'=>'POST','endpoint'=>'station','callback'=>'ashradio_station'],
        ['method'=>'POST','endpoint'=>'preset','callback'=>'ashradio_preset'],
        ['method'=>'POST','endpoint'=>'bank','callback'=>'ashradio_bank'],
        ['method'=>'POST','endpoint'=>'order','callback'=>'ashradio_order'],
        ['method'=>'POST','endpoint'=>'settings','callback'=>'ashradio_settings'],
        ['method'=>'POST','endpoint'=>'import','callback'=>'ashradio_import'],
        ['method'=>'POST','endpoint'=>'directory','callback'=>'ashradio_directory'],
        ['method'=>'POST','endpoint'=>'resolve','callback'=>'ashradio_resolve'],
        ['method'=>'POST','endpoint'=>'clearCache','callback'=>'ashradio_clearCache']
    ];
}
function ashradio_input()
{
    // State changes require same-origin JSON POST; there are no mutating GETs.
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && strcasecmp(parse_url($origin,PHP_URL_HOST) ?? '',explode(':',$_SERVER['HTTP_HOST'] ?? '')[0])!==0) throw new InvalidArgumentException('Cross-site radio request rejected.');
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')==='cross-site') throw new InvalidArgumentException('Cross-site radio request rejected.');
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '','application/json')!==0) throw new InvalidArgumentException('Send radio requests as application/json.');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>1048576) throw new InvalidArgumentException('Radio request is too large.');
    $raw=file_get_contents('php://input',false,null,0,1048577);
    if (strlen($raw)>1048576) throw new InvalidArgumentException('Radio request is too large.');
    $data=json_decode($raw,true);
    if (!is_array($data)) throw new InvalidArgumentException('Invalid JSON radio request.');
    return $data;
}
function ashradio_reply($callback)
{
    try { return json(['status'=>true,'data'=>$callback()]); }
    catch (Throwable $e) { return json(['status'=>false,'error'=>$e->getMessage()]); }
}
function ashradio_details() { return ashradio_reply(function() { return \AfterShow\radio()->details(); }); }
function ashradio_control()
{
    return ashradio_reply(function() {
        $in=ashradio_input();$radio=\AfterShow\radio();
        switch ($in['action'] ?? '') {
            case 'play': return $radio->play($in['uid'] ?? null);
            case 'stop': return $radio->stop();
            case 'next': return $radio->move(1);
            case 'previous': return $radio->move(-1);
            case 'seekNext': return $radio->move(1,true);
            case 'seekPrevious': return $radio->move(-1,true);
            case 'preset': return $radio->preset($in['slot'] ?? 0,false,$in['bank'] ?? null);
            case 'volume': return $radio->volume($in['value'] ?? 70);
            case 'mute': return $radio->volume(null,true);
            case 'sleep': return $radio->sleep($in['minutes'] ?? 0);
        }
        throw new InvalidArgumentException('Unknown radio control.');
    });
}
function ashradio_station() { return ashradio_reply(function() { $in=ashradio_input();return ($in['action'] ?? '')==='delete' ? \AfterShow\radio()->deleteStation($in['uid'] ?? '') : \AfterShow\radio()->saveStation($in); }); }
function ashradio_preset() { return ashradio_reply(function() { $in=ashradio_input();return \AfterShow\radio()->savePreset($in['slot'] ?? 0,$in['uid'] ?? null,$in['bank'] ?? null); }); }
function ashradio_bank() { return ashradio_reply(function() { return \AfterShow\radio()->bank(ashradio_input()); }); }
function ashradio_order() { return ashradio_reply(function() { $in=ashradio_input();return \AfterShow\radio()->reorder($in['uids'] ?? []); }); }
function ashradio_settings() { return ashradio_reply(function() { return \AfterShow\radio()->settings(ashradio_input()); }); }
function ashradio_import() { return ashradio_reply(function() { return \AfterShow\radio()->import(ashradio_input()); }); }
function ashradio_directory() { return ashradio_reply(function() { $in=ashradio_input();return (new \AfterShow\Catalog(\AfterShow\store()))->load($in['source'] ?? '',!empty($in['refresh']),$in['page'] ?? 1); }); }
function ashradio_resolve() { return ashradio_reply(function() { $in=ashradio_input();return (new \AfterShow\Catalog(\AfterShow\store()))->resolve($in['source'] ?? '',$in['url'] ?? ''); }); }
function ashradio_clearCache() { return ashradio_reply(function() { ashradio_input();return (new \AfterShow\Catalog(\AfterShow\store()))->clearCache(); }); }
