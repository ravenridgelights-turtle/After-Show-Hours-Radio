<?php
namespace AfterShow;

final class Store
{
    public $dataDir;
    public $runDir;
    public function __construct($dataDir, $runDir)
    {
        $this->dataDir = $dataDir;
        $this->runDir = $runDir;
        foreach ([$dataDir, $runDir] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new \RuntimeException('Radio data directory is unavailable. Reinstall the plugin.');
            }
        }
    }
    public static function read($file, $default = [])
    {
        if (!is_file($file)) return $default;
        $value = json_decode(file_get_contents($file), true);
        if (!is_array($value)) throw new \RuntimeException('A radio settings file is invalid: '.basename($file));
        return $value;
    }
    public static function write($file, $value)
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = tempnam(dirname($file), '.radio-');
        if ($tmp === false) throw new \RuntimeException('Could not create radio settings file.');
        try {
            if (file_put_contents($tmp, $json."\n") === false) throw new \RuntimeException('Could not write radio settings.');
            chmod($tmp, 0660);
            @chgrp($tmp, 'fpp');
            if (!rename($tmp, $file)) throw new \RuntimeException('Could not save radio settings.');
        } finally { if (is_file($tmp)) unlink($tmp); }
    }
    public function lock($name, $callback, $wait = 3.0)
    {
        $file = $this->runDir.'/'.$name.'.lock';
        $fp = fopen($file, 'c');
        if (!$fp) throw new \RuntimeException('Radio lock is unavailable.');
        @chmod($file, 0660);
        @chgrp($file, 'fpp');
        $until = microtime(true) + $wait;
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $until) { fclose($fp); throw new \RuntimeException('Radio is busy. Try again shortly.'); }
            usleep(20000);
        }
        try { return $callback(); } finally { flock($fp, LOCK_UN); fclose($fp); }
    }
    public function config()
    {
        return self::read($this->dataDir.'/config.json', [
            'version'=>1, 'stations'=>[], 'activeBank'=>'christmas',
            'banks'=>['christmas'=>['name'=>'Christmas','presets'=>[]], 'halloween'=>['name'=>'Halloween','presets'=>[]], 'everyday'=>['name'=>'Everyday','presets'=>[]]],
            'settings'=>['defaultVolume'=>70, 'protectShow'=>true, 'autoRecover'=>true]
        ]);
    }
    public function saveConfig($config) { self::write($this->dataDir.'/config.json', $config); }
    public function state()
    {
        return self::read($this->runDir.'/state.json', ['wanted'=>false, 'generation'=>0, 'uid'=>null, 'volume'=>70, 'muted'=>false]);
    }
    public function saveState($state) { self::write($this->runDir.'/state.json', $state); }
    public static function validUrl($url)
    {
        if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) return false;
        $p = parse_url($url);
        return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true)
            && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']);
    }
    public static function text($value, $max = 160)
    {
        if (!is_scalar($value)) throw new \InvalidArgumentException('Expected text.');
        return substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', '', (string)$value)), 0, $max);
    }
    public function migrateLegacy($legacyFile)
    {
        if (is_file($this->dataDir.'/config.json') || !is_file($legacyFile)) return 0;
        $old = self::read($legacyFile);
        $config = $this->config();
        $streams = $old['streams'] ?? [];
        uasort($streams, function($a,$b) { return ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999); });
        foreach ($streams as $name=>$stream) {
            if (!self::validUrl($stream['url'] ?? null)) continue;
            $config['stations'][] = [
                'uid'=>bin2hex(random_bytes(8)), 'name'=>self::text($name), 'url'=>$stream['url'],
                'enabled'=>filter_var($stream['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'favorite'=>false, 'volume'=>max(0,min(100,(int)($stream['volume'] ?? 70))),
                'category'=>'Imported', 'source'=>'Original After Hours plugin'
            ];
        }
        $this->saveConfig($config);
        return count($config['stations']);
    }
}
