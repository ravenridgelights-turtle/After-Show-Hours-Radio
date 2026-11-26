<?php
require_once __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/MediaCallback.php';
use AfterShow\{Store,Mpd,Radio,MediaCallback};
$dir=sys_get_temp_dir().'/ash-media-callback-'.bin2hex(random_bytes(5));mkdir($dir);
$history=$dir.'/commands.jsonl';
$process=proc_open(['python3',__DIR__.'/mpd_fixture.py',$dir.'/port',$history],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start MPD fixture.');
$count=0;
function verify($condition,$label){global $count;if(!$condition)throw new RuntimeException('FAIL '.$label);$count++;echo 'PASS '.$label."\n";}
try {
    for($i=0;$i<100 && !is_file($dir.'/port');$i++)usleep(10000);
    $mpd=new Mpd('tcp://127.0.0.1:'.trim(file_get_contents($dir.'/port')),0.4);
    $store=new Store($dir.'/data',$dir.'/run');$store->saveConfig($store->config());
    $probe=function(){return true;};
    $radio=new Radio($store,$mpd,function(){throw new RuntimeException('Callback must not query FPP while FPP waits for it.');},function($url)use(&$probe){return $probe($url);});
    $a=$radio->saveStation(['name'=>'Radio A','url'=>'https://radio.example/a.mp3']);
    $b=$radio->saveStation(['name'=>'Radio B','url'=>'https://radio.example/b.mp3']);
    $event=['callbacks.php','--type','media','--data',json_encode(['type'=>'both','Sequence'=>'Show.fseq','Media'=>"/music/Don't Stop (Live).mp3"] )];
    $stop=function()use($radio){return $radio->stopForMedia();};
    $list=[];$code=0;exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg(__DIR__.'/../callbacks.php').' --list',$list,$code);
    verify($code===0 && implode("\n",$list)==='media','FPP can discover the media callback without audio or web initialization');
    $radio->play($a['uid'],true);$before=$store->state();
    verify(time()-$before['startedAt']<15,'regression begins inside the old monitor startup grace');
    verify(MediaCallback::dispatch($event,$stop)===true,'real FPP media-open payload invokes protected Stop');
    verify(!$store->state()['wanted'] && $mpd->status()['player']['state']==='stop','radio stops before the simulated show starts, without a monitor tick or browser');
    verify($store->state()['generation']>$before['generation'],'media handoff invalidates pending station changes');
    $radio->monitor();verify(!$store->state()['wanted'],'health monitor cannot restart radio after media handoff');
    verify(MediaCallback::dispatch($event,$stop)===false,'later track notifications do nothing while radio is already stopped');
    $radio->play($a['uid'],true);
    foreach ([['type'=>'media','Media'=>''],['type'=>'sequence','Media'=>''],['title'=>'Metadata only']] as $payload) {
        verify(MediaCallback::dispatch(['callbacks.php','--type','media','--data',json_encode($payload)],$stop)===false && $store->state()['wanted'],'media-close and sequence-only notifications leave radio playing');
    }
    verify(MediaCallback::dispatch(['callbacks.php','--type','playlist','--data','{}'],$stop)===false,'unrelated callback types leave radio playing');
    $radio->settings(['protectShow'=>false]);
    verify(MediaCallback::dispatch($event,$stop)===false && $mpd->status()['player']['state']==='play','explicitly disabled media protection is respected');
    $radio->settings(['protectShow'=>true]);
    $probe=function()use($event,$stop){MediaCallback::dispatch($event,$stop);return true;};
    try {$radio->move(1,true,true);throw new RuntimeException('Seek restarted radio after media callback');}
    catch(RuntimeException $e){verify(strpos($e->getMessage(),'cancel')!==false && !$store->state()['wanted'] && $mpd->status()['player']['state']==='stop','media-start callback cancels an in-flight Seek and wins the race');}
    $offline=new Radio($store,new Mpd('tcp://127.0.0.1:1',0.05),function(){return null;},function(){return false;});
    verify($offline->stopForMedia()===false,'already-stopped fast path never contacts MPD');
    $radio->play($b['uid'],true);
    $radio->stopForMedia();verify($store->state()['uid']===$b['uid'] && !$store->state()['wanted'],'handoff preserves the selected station for a later scheduled Start');
    echo "\n$count media callback checks passed.\n";
} finally {proc_terminate($process);proc_close($process);}
