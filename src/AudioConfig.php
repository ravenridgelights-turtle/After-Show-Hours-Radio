<?php
namespace AfterShow;
final class AudioConfig
{
    public static function build($dataDir,$logFile,$sink)
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,160}$/',$sink)) throw new \InvalidArgumentException('Unexpected FPP audio sink name.');
        $q=function($v) {
            if (preg_match('/[\r\n\x00]/',$v)) throw new \InvalidArgumentException('Invalid radio data path.');
            return '"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';
        };
        return 'music_directory '.$q($dataDir.'/music')."\n".
            'playlist_directory '.$q($dataDir.'/playlists')."\n".
            'db_file '.$q($dataDir.'/mpd.db')."\n".
            'log_file '.$q($logFile)."\n".
            "pid_file \"/run/after-show-hours-radio/mpd.pid\"\n".
            // An explicit Unix bind is sufficient: no TCP listener is created.
            // Do not set port=0; MPD requires any configured port to be positive.
            "bind_to_address \"/run/after-show-hours-radio/mpd.sock\"\n".
            "log_level \"error\"\nauto_update \"no\"\nrestore_paused \"yes\"\n".
            "audio_buffer_size \"2048\"\nmax_playlist_length \"250\"\nzeroconf_enabled \"no\"\n".
            "audio_output {\n type \"pulse\"\n name \"After Show Hours Radio\"\n server \"/run/after-show-hours-radio-pulse/pulse-native\"\n sink ".$q($sink)."\n mixer_type \"software\"\n format \"44100:16:2\"\n}\n";
    }
}
