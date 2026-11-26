<?php
require_once __DIR__.'/../src/FppStatus.php';
$cases=json_decode(file_get_contents(__DIR__.'/fixtures/fpp-status.json'),true,512,JSON_THROW_ON_ERROR);
foreach ($cases as $case) {
    $actual=\AfterShow\FppStatus::audioBusy($case['status']);
    if ($actual!==$case['busy']) throw new RuntimeException('FAIL FPP status: '.$case['label']);
}
echo 'PASS '.count($cases)." FPP media/sequence status scenarios (PHP).\n";
