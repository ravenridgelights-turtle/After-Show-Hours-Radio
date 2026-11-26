#!/usr/bin/php
<?php
// FPP calls this before opening playlist media. Never fetch FPP status here:
// its playlist thread is waiting for this process and may hold playlist locks.
if (in_array('--list',$argv,true)) { echo "media\n"; exit(0); }
require_once __DIR__.'/src/MediaCallback.php';
try {
    \AfterShow\MediaCallback::dispatch($argv,function() {
        $media=getenv('MEDIADIR'); // supplied by FPP's scripts/eventScript
        if (!$media || !is_dir($media)) throw new RuntimeException('FPP media directory is unavailable to the radio callback.');
        $data=rtrim($media,'/').'/plugindata/After-Show-Hours-Radio';
        if (!is_file($data.'/config.json')) return false;
        // A minimal callback: no common.php, directory access, status polling,
        // audio reconfiguration or service restarts on FPP's media-start path.
        require_once __DIR__.'/src/Store.php';
        require_once __DIR__.'/src/Mpd.php';
        require_once __DIR__.'/src/Radio.php';
        $radio=new \AfterShow\Radio(new \AfterShow\Store($data,'/run/after-show-hours-radio'),
            new \AfterShow\Mpd('unix:///run/after-show-hours-radio/mpd.sock',0.4),
            function(){return null;},function(){return false;});
        return $radio->stopForMedia();
    });
} catch (Throwable $e) {
    error_log('After Show Hours Radio media callback: '.$e->getMessage());
    exit(1);
}
