#!/usr/bin/php
<?php
require_once __DIR__.'/../src/bootstrap.php';
try {
    $radio=\AfterShow\radio();$action=$argv[1] ?? '';
    switch ($action) {
        // Lead-out commands run before the playlist status becomes idle.
        // Explicit scheduling commands bypass only the manual show guard.
        case 'start': $result=$radio->play(null,true);break;
        case 'stop': $result=$radio->stop();break;
        case 'next': $result=$radio->move(1,false,true);break;
        case 'previous': $result=$radio->move(-1,false,true);break;
        case 'preset': $result=$radio->preset($argv[2] ?? 0,true,$argv[3] ?? null);break;
        default: throw new InvalidArgumentException('Unknown command: '.$action);
    }
    echo json_encode($result,JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    error_log('After Show Hours Radio command failed: '.$e->getMessage());
    fwrite(STDERR,$e->getMessage()."\n");exit(1);
}
