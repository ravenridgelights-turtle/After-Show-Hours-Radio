<?php
namespace AfterShow;
// CLI entry points load FPP helpers at file scope so their globals remain global.
// Web/API requests already loaded common.php; require_once is then a no-op.
$skipJSsettings=1;
if (is_file('/opt/fpp/www/common.php')) require_once '/opt/fpp/www/common.php';
require_once __DIR__.'/Store.php';
require_once __DIR__.'/Mpd.php';
require_once __DIR__.'/Http.php';
require_once __DIR__.'/DirectorySources.php';
require_once __DIR__.'/Catalog.php';
require_once __DIR__.'/Radio.php';
function store()
{
    static $store;
    if ($store) return $store;
    global $settings;
    $media=rtrim($settings['mediaDirectory'] ?? '/home/fpp/media','/');
    if (!empty($settings['logDirectory'])) {
        ini_set('log_errors','1');ini_set('error_log',$settings['logDirectory'].'/plugin-After-Show-Hours-Radio.log');
    }
    $store=new Store($media.'/plugindata/After-Show-Hours-Radio','/run/after-show-hours-radio');
    return $store;
}
function showBusy()
{
    $context=stream_context_create(['http'=>['timeout'=>0.8,'ignore_errors'=>true]]);
    $raw=@file_get_contents('http://127.0.0.1:32322/fppd/status',false,$context,0,131072);
    $status=$raw ? json_decode($raw,true) : null;
    if (!is_array($status) || !isset($status['status_name'])) return null;
    return !in_array(strtolower($status['status_name']),['idle','stopped'],true);
}
function radio() { return new Radio(store(),new Mpd(),__NAMESPACE__.'\\showBusy',[Http::class,'probe']); }
