<?php
namespace AfterShow;

final class Radio
{
    private $store;
    private $mpd;
    private $showCheck;
    private $probe;
    public function __construct(Store $store, Mpd $mpd, callable $showCheck, callable $probe)
    {
        $this->store=$store; $this->mpd=$mpd; $this->showCheck=$showCheck; $this->probe=$probe;
    }
    private function guard($scheduled)
    {
        if (!$scheduled && ($this->store->config()['settings']['protectShow'] ?? true)) {
            $busy=call_user_func($this->showCheck);
            if ($busy === null) throw new \RuntimeException('Cannot check FPP show status. Use the FPP Start command, or turn off Protect show in radio settings.');
            if ($busy) throw new \RuntimeException('FPP is running a show. Radio playback is protected until the show ends.');
        }
    }
    private function find($uid, $config = null)
    {
        foreach (($config ?? $this->store->config())['stations'] as $station) if ($station['uid'] === $uid) return $station;
        throw new \InvalidArgumentException('Station was not found.');
    }
    public function details()
    {
        $config=$this->store->config(); $state=$this->store->state(); $error=null;
        try { $status=$this->mpd->status(); }
        catch (\Throwable $e) { $status=['player'=>['state'=>'unavailable'],'song'=>[]]; $error=$e->getMessage(); }
        return ['config'=>$config, 'state'=>$state, 'audio'=>$status, 'error'=>$error];
    }
    public function nowPlaying($bank=null)
    {
        $inactive=['active'=>false,'pollAfter'=>15];$state=$this->store->state();
        if (!$state['wanted'] || call_user_func($this->showCheck)!==false) return $inactive;
        try { $status=$this->mpd->status(); } catch (\Throwable $e) { return $inactive; }
        if (($status['player']['state'] ?? '')!=='play') return $inactive;
        $station=null;
        $config=$this->store->config();$names=array_column($config['stations'],'name','uid');
        foreach ($config['stations'] as $s) if ($s['uid']===($state['uid'] ?? null)) $station=$s;
        if (!$station) return $inactive;
        $bank=Store::text($bank ?? '',50);
        if (!isset($config['banks'][$bank])) $bank=$config['activeBank'];
        $banks=[];foreach ($config['banks'] as $id=>$page) $banks[]=['id'=>$id,'name'=>$page['name']];
        $presets=[];for ($slot=1;$slot<=12;$slot++) {
            $uid=$config['banks'][$bank]['presets'][$slot] ?? null;
            $presets[]=['slot'=>$slot,'name'=>$names[$uid] ?? '', 'assigned'=>isset($names[$uid]), 'current'=>$uid!==null && $uid===$state['uid']];
        }
        $song=$status['song'];$title=Store::text($song['Title'] ?? '',300);$artist=Store::text($song['Artist'] ?? '',160);
        if ($artist && $title && stripos($title,$artist)===false) $title=$artist.' — '.$title;
        return ['active'=>true,'pollAfter'=>5,'station'=>$station['name'],'track'=>$title,'bank'=>$bank,'banks'=>$banks,'presets'=>$presets,
            'volume'=>(int)($status['player']['volume'] ?? $state['volume']),'muted'=>(bool)($state['muted'] ?? false),
            'bitrate'=>max(0,(int)($status['player']['bitrate'] ?? 0))];
    }
    private function tuneLocked($station, $scheduled = false)
    {
        $this->guard($scheduled);
        $state=$this->store->state();
        $this->mpd->tune($station['url']);
        $wasRunning=$state['wanted'];
        $state['wanted']=true; $state['uid']=$station['uid']; $state['generation']++;
        $state['volume']=$station['volume'] === null ? ($wasRunning ? ($state['volume'] ?? 70) : $this->store->config()['settings']['defaultVolume']) : $station['volume'];
        $state['muted']=false; $state['startedAt']=time(); $state['retryAfter']=0;
        $this->store->saveState($state);
        $this->mpd->commands(['repeat 1','single 0','random 0','setvol '.(int)$state['volume']]);
        return ['station'=>$station['name'], 'uid'=>$station['uid']];
    }
    public function play($uid = null, $scheduled = false)
    {
        return $this->store->lock('player', function() use ($uid,$scheduled) {
            $config=$this->store->config(); $state=$this->store->state();
            if ($uid !== null && $uid !== '') return $this->tuneLocked($this->find($uid,$config),$scheduled);
            if (isset($state['uid'])) {
                foreach ($config['stations'] as $s) if ($s['uid']===$state['uid'] && $s['enabled']) return $this->tuneLocked($s,$scheduled);
            }
            foreach ($config['stations'] as $s) if ($s['enabled']) return $this->tuneLocked($s,$scheduled);
            throw new \RuntimeException('Add a station or enable one in Saved stations first.');
        });
    }
    public function stop()
    {
        return $this->store->lock('player', function() {
            $state=$this->store->state(); $state['wanted']=false; $state['generation']++; unset($state['sleepUntil']);
            // Invalidate a seek/recovery BEFORE contacting the audio service.
            $this->store->saveState($state);
            $this->mpd->command('stop');
            return ['message'=>'Radio stopped'];
        });
    }
    public function move($direction, $seek = false, $scheduled = false)
    {
        $snapshot=$this->store->lock('player', function() use ($direction,$seek,$scheduled) {
            $this->guard($scheduled);
            $config=$this->store->config(); $state=$this->store->state();
            $stations=array_values(array_filter($config['stations'], function($s) { return $s['enabled']; }));
            if (!$stations) throw new \RuntimeException('There are no enabled stations.');
            $index=$direction > 0 ? -1 : 0;
            foreach ($stations as $i=>$s) if ($s['uid'] === ($state['uid'] ?? null)) $index=$i;
            $ordered=[];
            for ($n=1;$n<=count($stations);$n++) $ordered[]=$stations[($index+$direction*$n+count($stations)*2)%count($stations)];
            if (!$seek) return ['result'=>$this->tuneLocked($ordered[0],$scheduled)];
            $state['generation']++; $this->store->saveState($state);
            return ['generation'=>$state['generation'], 'stations'=>array_slice($ordered,0,5)];
        });
        if (isset($snapshot['result'])) return $snapshot['result'];
        // Network work deliberately holds no player lock: Stop always wins.
        foreach ($snapshot['stations'] as $station) {
            if ($this->store->state()['generation'] !== $snapshot['generation']) throw new \RuntimeException('Seek was cancelled by another radio control.');
            if (!call_user_func($this->probe,$station['url'])) continue;
            return $this->store->lock('player',function() use ($snapshot,$station,$scheduled) {
                if ($this->store->state()['generation'] !== $snapshot['generation']) throw new \RuntimeException('Seek was cancelled.');
                // A station may have been removed while its URL was checked.
                $current=$this->find($station['uid']);
                if (!$current['enabled']) throw new \RuntimeException('Station was disabled during Seek.');
                return $this->tuneLocked($current,$scheduled);
            });
        }
        throw new \RuntimeException('No reachable audio stream found in the next five saved stations. Current station was kept.');
    }
    public function volume($volume = null, $mute = false)
    {
        return $this->store->lock('player',function() use ($volume,$mute) {
            $s=$this->store->state();
            if ($mute) $s['muted']=!($s['muted'] ?? false);
            else { $s['volume']=max(0,min(100,(int)$volume)); $s['muted']=false; }
            $this->mpd->command('setvol '.($s['muted'] ? 0 : $s['volume']));
            $this->store->saveState($s);
            return ['volume'=>$s['volume'],'muted'=>$s['muted']];
        });
    }
    public function sleep($minutes)
    {
        return $this->store->lock('player',function() use ($minutes) {
            $s=$this->store->state(); $minutes=max(0,min(720,(int)$minutes));
            if ($minutes) $s['sleepUntil']=time()+$minutes*60; else unset($s['sleepUntil']);
            $this->store->saveState($s); return ['minutes'=>$minutes];
        });
    }
    public function preset($slot, $scheduled = false, $bank = null)
    {
        $slot=(int)$slot; if ($slot<1 || $slot>12) throw new \InvalidArgumentException('Preset must be 1–12.');
        $c=$this->store->config(); $bank=$this->bankId($c,$bank);
        $uid=$c['banks'][$bank]['presets'][(string)$slot] ?? null;
        if (!$uid) throw new \RuntimeException('This preset is empty. Assign it in the radio page first.');
        return $this->play($uid,$scheduled);
    }
    public function saveStation(array $input)
    {
        return $this->store->lock('config',function() use ($input) {
            $c=$this->store->config(); $uid=Store::text($input['uid'] ?? '',40);
            $found=null;
            foreach ($c['stations'] as $i=>$s) if ($s['uid']===$uid) $found=$i;
            if ($uid && $found===null) throw new \InvalidArgumentException('Station was not found.');
            if (!$uid && count($c['stations'])>=250) throw new \RuntimeException('Saved station limit is 250. Remove a station first.');
            $old=$found===null ? ['uid'=>bin2hex(random_bytes(8)), 'enabled'=>true,'favorite'=>false,'volume'=>null,'category'=>'','source'=>'Manual'] : $c['stations'][$found];
            foreach (['name','url','category','source'] as $k) if (isset($input[$k])) $old[$k]=Store::text($input[$k],$k==='url' ? 2048 : 160);
            foreach (['enabled','favorite'] as $k) if (isset($input[$k])) $old[$k]=filter_var($input[$k],FILTER_VALIDATE_BOOLEAN);
            if (array_key_exists('volume',$input)) $old['volume']=($input['volume']==='' || $input['volume']===null) ? null : max(0,min(100,(int)$input['volume']));
            if (empty($old['name']) || !Store::validUrl($old['url'] ?? null)) throw new \InvalidArgumentException('A station name and an http:// or https:// stream URL are required.');
            foreach ($c['stations'] as $s) if ($s['url']===$old['url'] && $s['uid']!==$old['uid']) throw new \InvalidArgumentException('This stream is already saved as '.$s['name'].'.');
            if ($found===null) $c['stations'][]=$old; else $c['stations'][$found]=$old;
            $this->store->saveConfig($c); return $old;
        });
    }
    public function deleteStation($uid)
    {
        return $this->store->lock('player',function() use ($uid) {
            if ($this->store->state()['uid']===$uid && $this->store->state()['wanted']) throw new \RuntimeException('Stop or change station before deleting the playing station.');
            return $this->store->lock('config',function() use ($uid) {
                $c=$this->store->config(); $this->find($uid,$c);
                $c['stations']=array_values(array_filter($c['stations'],function($s) use ($uid) { return $s['uid']!==$uid; }));
                foreach ($c['banks'] as &$bank) $bank['presets']=array_filter($bank['presets'],function($p) use ($uid) { return $p!==$uid; });
                unset($bank);
                $this->store->saveConfig($c); return ['message'=>'Station removed'];
            });
        });
    }
    private function bankId($c,$bank = null)
    {
        if ($bank===null || $bank==='') $bank=$c['activeBank'];
        foreach ($c['banks'] as $id=>$data) if ($id===$bank || strcasecmp($data['name'],(string)$bank)===0) return $id;
        throw new \InvalidArgumentException('Preset page was not found.');
    }
    public function bank(array $input)
    {
        return $this->store->lock('config',function() use ($input) {
            $c=$this->store->config();$action=$input['action'] ?? '';
            if ($action==='create' || $action==='rename') {
                $name=Store::text($input['name'] ?? '',50);
                if (!$name) throw new \InvalidArgumentException('Enter a name for the preset page.');
                foreach ($c['banks'] as $id=>$b) if (strcasecmp($b['name'],$name)===0 && $id!==($input['bank'] ?? '')) throw new \InvalidArgumentException('A preset page already has that name.');
                if ($action==='create') {
                    if (count($c['banks'])>=64) throw new \RuntimeException('The limit is 64 preset pages.');
                    $id=bin2hex(random_bytes(6));$c['banks'][$id]=['name'=>$name,'presets'=>[]];$c['activeBank']=$id;
                } else { $id=$this->bankId($c,$input['bank'] ?? null);$c['banks'][$id]['name']=$name; }
            } else {
                $id=$this->bankId($c,$input['bank'] ?? null);
                if ($action==='select') $c['activeBank']=$id;
                elseif ($action==='delete') {
                    if (count($c['banks'])<2) throw new \RuntimeException('Keep at least one preset page.');
                    unset($c['banks'][$id]);if ($c['activeBank']===$id) $c['activeBank']=array_key_first($c['banks']);
                } else throw new \InvalidArgumentException('Unknown preset page action.');
            }
            $this->store->saveConfig($c);return ['activeBank'=>$c['activeBank']];
        });
    }
    public function savePreset($slot,$uid,$bank = null)
    {
        return $this->store->lock('config',function() use ($slot,$uid,$bank) {
            $c=$this->store->config(); $slot=(int)$slot;
            $bank=$this->bankId($c,$bank);
            if ($slot<1 || $slot>12) throw new \InvalidArgumentException('Preset must be 1–12.');
            if ($uid) { $this->find($uid,$c); $c['banks'][$bank]['presets'][(string)$slot]=$uid; }
            else unset($c['banks'][$bank]['presets'][(string)$slot]);
            $this->store->saveConfig($c); return ['slot'=>$slot];
        });
    }
    public function reorder(array $uids)
    {
        return $this->store->lock('config',function() use ($uids) {
            $c=$this->store->config(); $have=array_column($c['stations'],'uid'); $sorted=$uids; sort($have);sort($sorted);
            if ($have!==$sorted) throw new \InvalidArgumentException('Station list changed. Refresh and try again.');
            $map=array_column($c['stations'],null,'uid'); $c['stations']=array_map(function($id) use ($map) { return $map[$id]; },$uids);
            $this->store->saveConfig($c); return ['message'=>'Order saved'];
        });
    }
    public function settings(array $input)
    {
        return $this->store->lock('config',function() use ($input) {
            $c=$this->store->config();
            foreach (['protectShow','autoRecover'] as $key) if (isset($input[$key])) $c['settings'][$key]=filter_var($input[$key],FILTER_VALIDATE_BOOLEAN);
            if (isset($input['defaultVolume'])) $c['settings']['defaultVolume']=max(0,min(100,(int)$input['defaultVolume']));
            $this->store->saveConfig($c); return $c['settings'];
        });
    }
    public function import(array $input)
    {
        $incoming=$input['stations'] ?? null;
        if (!is_array($incoming) || count($incoming)>250) throw new \InvalidArgumentException('Choose a radio backup JSON file with up to 250 stations.');
        // Validate all rows before making any changes. Import merges; existing presets survive.
        foreach ($incoming as $s) if (!is_array($s) || !Store::validUrl($s['url'] ?? null) || empty($s['name'])) throw new \InvalidArgumentException('Backup contains an invalid station.');
        return $this->store->lock('config',function() use ($incoming,$input) {
            $c=$this->store->config(); $map=[]; $added=0;
            foreach ($incoming as $s) {
                $existing=null;
                foreach ($c['stations'] as $old) if ($old['url']===$s['url']) $existing=$old['uid'];
                $uid=$existing ?? bin2hex(random_bytes(8));
                if (isset($s['uid']) && is_string($s['uid'])) $map[$s['uid']]=$uid;
                if ($existing) continue;
                if (count($c['stations'])>=250) throw new \RuntimeException('Merged backup would exceed 250 stations.');
                $c['stations'][]=['uid'=>$uid, 'name'=>Store::text($s['name']), 'url'=>$s['url'],
                    'enabled'=>filter_var($s['enabled'] ?? true,FILTER_VALIDATE_BOOLEAN),'favorite'=>filter_var($s['favorite'] ?? false,FILTER_VALIDATE_BOOLEAN),
                    'category'=>Store::text($s['category'] ?? ''),'source'=>Store::text($s['source'] ?? 'Backup'),
                    'volume'=>isset($s['volume']) ? max(0,min(100,(int)$s['volume'])) : null]; $added++;
            }
            $banks=$input['banks'] ?? [];
            if (!is_array($banks) || count($banks)>64) throw new \InvalidArgumentException('Invalid preset pages in backup.');
            foreach ($banks as $bank) {
                if (!is_array($bank) || empty($bank['name']) || !is_array($bank['presets'] ?? null)) throw new \InvalidArgumentException('Invalid preset page in backup.');
                $name=Store::text($bank['name'],50);$id=null;
                foreach ($c['banks'] as $key=>$existing) if (strcasecmp($existing['name'],$name)===0) $id=$key;
                if ($id===null) { $id=bin2hex(random_bytes(6));$c['banks'][$id]=['name'=>$name,'presets'=>[]]; }
                if (count($c['banks'])>64) throw new \RuntimeException('Merged backup would exceed 64 preset pages.');
                foreach ($bank['presets'] as $slot=>$oldUid) if ((int)$slot>=1 && (int)$slot<=12 && is_string($oldUid) && isset($map[$oldUid]) && empty($c['banks'][$id]['presets'][$slot])) $c['banks'][$id]['presets'][$slot]=$map[$oldUid];
            }
            $this->store->saveConfig($c); return ['added'=>$added];
        });
    }
    public function monitor()
    {
        $s=$this->store->state();
        if (!$s['wanted']) return;
        if (!empty($s['sleepUntil']) && time()>=$s['sleepUntil']) { $this->stop(); return; }
        $c=$this->store->config();
        if (($c['settings']['protectShow'] ?? true) && time()-($s['startedAt'] ?? 0)>15 && call_user_func($this->showCheck)===true) { $this->stop(); return; }
        if (!($c['settings']['autoRecover'] ?? true) || time()<($s['retryAfter'] ?? 0)) return;
        $status=$this->mpd->status();
        // Compare elapsed progress, not metadata titles (some stations never change titles).
        $progress=Store::read($this->store->runDir.'/progress.json');
        $elapsed=(float)($status['player']['elapsed'] ?? -1);
        Store::write($this->store->runDir.'/progress.json',['uid'=>$s['uid'],'elapsed'=>$elapsed,'at'=>time()]);
        $stalled=($progress['uid'] ?? '')===$s['uid'] && abs($elapsed-($progress['elapsed'] ?? -2))<0.01 && time()-($progress['at'] ?? 0)>=40;
        if (($status['player']['state'] ?? '')==='play' && empty($status['player']['error']) && !$stalled) return;
        $this->store->lock('player',function() use ($s) {
            $now=$this->store->state();
            if (!$now['wanted'] || $now['generation']!==$s['generation']) return;
            $stations=array_values(array_filter($this->store->config()['stations'],function($x) { return $x['enabled']; }));
            if (!$stations) return;
            $index=-1;foreach ($stations as $i=>$x) if ($x['uid']===$now['uid']) $index=$i;
            $station=$stations[($index+1)%count($stations)];
            $this->tuneLocked($station,false);
            $now=$this->store->state();$now['retryAfter']=time()+60;$this->store->saveState($now);
        });
    }
}
