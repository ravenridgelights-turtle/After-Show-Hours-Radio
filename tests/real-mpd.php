<?php
// Tests the shipped MPD config and native protocol client against a real MPD.
// The null output replaces only the hardware-dependent Pulse output.
require_once __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/AudioConfig.php';
require_once __DIR__.'/../src/MediaCallback.php';
use AfterShow\{Store,Mpd,Radio,AudioConfig};
$binary=$argv[1] ?? '/usr/bin/mpd';$tcp=in_array('--tcp',$argv,true);
$dir=sys_get_temp_dir().'/ash-mpd-'.bin2hex(random_bytes(4));mkdir($dir);mkdir($dir.'/music');mkdir($dir.'/playlists');
$samples='';for($i=0;$i<22050;$i++)$samples.=pack('v',(int)(2000*sin(2*M_PI*440*$i/22050)) & 65535);
$data=str_repeat($samples,30);$wav='RIFF'.pack('V',36+strlen($data)).'WAVEfmt '.pack('VvvVVvv',16,1,1,22050,44100,2,16).'data'.pack('V',strlen($data)).$data;
file_put_contents($dir.'/music/one.wav',$wav);file_put_contents($dir.'/music/two.wav',$wav);
$http=proc_open(['python3',__DIR__.'/audio_server.py',$dir.'/music',$dir.'/http.port'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file',$dir.'/http.log','a']],$pipes);
$mpd=null;
try {
 for($i=0;$i<100 && !is_file($dir.'/http.port');$i++)usleep(10000);
 $port=trim(file_get_contents($dir.'/http.port'));
 $config=AudioConfig::build($dir,$dir.'/mpd.log','fpp_group_default');
 if(strpos($config,'port "0"')!==false)throw new RuntimeException('Invalid MPD port zero.');
 $config=str_replace('/run/after-show-hours-radio',$dir,$config);
 $config=preg_replace('/audio_output \{.*?\}/s',"audio_output {\n type \"null\"\n name \"Integration test\"\n mixer_type \"software\"\n}",$config);
 $address='unix://'.$dir.'/mpd.sock';
 if($tcp){
  $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);$chosen=explode(':',stream_socket_get_name($listener,false))[1];fclose($listener);
  $config=str_replace('bind_to_address "'.$dir.'/mpd.sock"','bind_to_address "127.0.0.1"'."\nport \"$chosen\"",$config);$address='tcp://127.0.0.1:'.$chosen;
 }
 file_put_contents($dir.'/mpd.conf',$config);
 $mpd=proc_open([$binary,'--no-daemon',$dir.'/mpd.conf'],[0=>['file','/dev/null','r'],1=>['file',$dir.'/mpd.log','a'],2=>['file',$dir.'/mpd.log','a']],$pipes);
 $client=new Mpd($address);$ready=false;
 for($i=0;$i<100;$i++){try{$client->command('status');$ready=true;break;}catch(Throwable $e){usleep(20000);}}
 if(!$ready)throw new RuntimeException('MPD did not start: '.file_get_contents($dir.'/mpd.log'));
 $store=new Store($dir.'/data',$dir.'/run');$store->saveConfig($store->config());
 $radio=new Radio($store,$client,function(){return false;},function(){return true;});
 $a=$radio->saveStation(['name'=>'Local audio A','url'=>'http://127.0.0.1:'.$port.'/one.wav','volume'=>42]);
 $b=$radio->saveStation(['name'=>'Local audio B','url'=>'http://127.0.0.1:'.$port.'/two.wav','volume'=>37]);
 $radio->play($a['uid']);usleep(300000);
 if($client->status()['player']['state']!=='play')throw new RuntimeException('First stream is not playing.');
 $radio->move(1);usleep(300000);$status=$client->status();
 if($status['player']['state']!=='play' || $status['song']['file']!==$b['url'] || (int)$status['player']['playlistlength']!==1)throw new RuntimeException('Native station switching failed.');
 if((int)$status['player']['volume']!==37)throw new RuntimeException('Native station volume failed.');
 $radio->savePreset(4,$a['uid'],'Christmas');$radio->preset(4,true,'Christmas');usleep(100000);
 if($client->status()['song']['file']!==$a['url'])throw new RuntimeException('Native preset playback failed.');
 $radio->stop();if($client->status()['player']['state']!=='stop')throw new RuntimeException('Native Stop failed.');
 $radio->play($a['uid'],true);
 $event=['callbacks.php','--type','media','--data',json_encode(['type'=>'both','Sequence'=>'show.fseq','Media'=>'show.mp3'])];
 \AfterShow\MediaCallback::dispatch($event,function()use($radio){return $radio->stopForMedia();});
 if($store->state()['wanted'] || $client->status()['player']['state']!=='stop')throw new RuntimeException('Real MPD media-start handoff failed.');
 echo "PASS real MPD: shipped config parses, ".($tcp?'TCP test transport':'production Unix-socket transport').", HTTP audio playback, Next switching, one queue entry, station volume, named preset, Stop, native media-callback handoff without a monitor tick.\n";
} finally {if(is_resource($mpd)){proc_terminate($mpd);proc_close($mpd);}if(is_resource($http)){proc_terminate($http);proc_close($http);}}
