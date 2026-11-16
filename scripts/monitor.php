#!/usr/bin/php
<?php
require_once __DIR__.'/../src/bootstrap.php';
try { \AfterShow\store()->lock('monitor',function() {
    // MPD reopens its log on SIGHUP, following FPP's log rotation.
    $pidFile=\AfterShow\store()->runDir.'/mpd.pid';
    if (function_exists('posix_kill') && is_file($pidFile)) {
        $pid=(int)trim(file_get_contents($pidFile));
        if ($pid>1 && @file_get_contents('/proc/'.$pid.'/comm')==="mpd\n") @posix_kill($pid,1);
    }
    \AfterShow\radio()->monitor();
},0); }
catch (Throwable $e) { error_log('After Show Hours Radio monitor: '.$e->getMessage()); }
