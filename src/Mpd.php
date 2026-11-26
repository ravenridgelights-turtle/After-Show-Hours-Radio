<?php
namespace AfterShow;

/** Small, bounded MPD client. No shell processes in status polling. */
final class Mpd
{
    private $socket;
    private $timeout;
    public function __construct($address = 'unix:///run/after-show-hours-radio/mpd.sock', $timeout = 2.0)
    {
        $this->socket=$address; $this->timeout=max(0.05,min(2.0,(float)$timeout));
    }
    public function commands(array $commands)
    {
        $fp = @stream_socket_client($this->socket, $errno, $errstr, min(0.7,$this->timeout));
        if (!$fp) throw new \RuntimeException('Radio audio service is unavailable. Reinstall the plugin or check its log.');
        $seconds=(int)$this->timeout;
        stream_set_timeout($fp,$seconds,(int)round(($this->timeout-$seconds)*1000000));
        try {
            $greeting = fgets($fp, 4096);
            if (!$greeting || strpos($greeting, 'OK MPD ') !== 0) throw new \RuntimeException('Invalid audio service response.');
            $result = [];
            foreach ($commands as $command) {
                if (preg_match('/[\r\n\x00]/', $command)) throw new \InvalidArgumentException('Invalid audio command.');
                if (fwrite($fp, $command."\n") === false) throw new \RuntimeException('Audio service connection lost.');
                $rows = []; $size = 0;
                while (true) {
                    $line = fgets($fp, 8192);
                    if ($line === false) throw new \RuntimeException('Audio service timed out.');
                    $size += strlen($line);
                    if ($size > 65536) throw new \RuntimeException('Audio service reply is too large.');
                    $line = rtrim($line, "\r\n");
                    if ($line === 'OK') break;
                    if (strpos($line, 'ACK ') === 0) throw new \RuntimeException('Audio command failed: '.substr($line,0,250));
                    $pair = explode(': ', $line, 2);
                    if (count($pair) === 2) $rows[$pair[0]] = $pair[1];
                }
                $result[] = $rows;
            }
            return $result;
        } finally { fclose($fp); }
    }
    public function command($command) { return $this->commands([$command])[0]; }
    public static function quote($text) { return '"'.str_replace(['\\','"'], ['\\\\','\\"'], $text).'"'; }
    public function status() { $r=$this->commands(['status', 'currentsong']); return ['player'=>$r[0], 'song'=>$r[1]]; }
    public function tune($url)
    {
        // Append first: an invalid add never clears/stops the current station.
        $old = $this->command('status');
        $new = $this->command('addid '.self::quote($url));
        if (!isset($new['Id'])) throw new \RuntimeException('Audio service did not accept this stream.');
        try { $this->command('playid '.(int)$new['Id']); }
        catch (\Throwable $e) { $this->command('deleteid '.(int)$new['Id']); throw $e; }
        // Keep the new song and remove the preceding queue, with no stop/clear.
        $position = (int)($old['playlistlength'] ?? 0);
        if ($position > 0) $this->command('delete 0:'.$position);
    }
}
