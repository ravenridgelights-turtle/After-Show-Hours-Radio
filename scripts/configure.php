#!/usr/bin/php
<?php
require_once __DIR__.'/../src/bootstrap.php';
$store=\AfterShow\store();
$count=$store->migrateLegacy(dirname($store->dataDir).'/fpp-after-hours-config.json');
if (!is_file($store->dataDir.'/config.json')) $store->saveConfig($store->config());
if ($count) echo "Imported $count saved stations from the original After Hours plugin.\n";
$sink=function_exists('ReadSettingFromFile') ? ReadSettingFromFile('PipeWireSinkName') : '';
$sink=trim((string)$sink);if ($sink==='') $sink='fpp_group_default';
if (!preg_match('/^[a-zA-Z0-9_.:-]{1,160}$/',$sink)) throw new RuntimeException('Unexpected FPP audio sink name.');
$data=$store->dataDir;
$quote=function($v) { return '"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"'; };
$config='music_directory '.$quote($data.'/music')."\n".
    'log_file '.$quote(($settings['logDirectory'] ?? dirname(dirname($data)).'/logs').'/plugin-After-Show-Hours-Radio.log')."\n".
    'playlist_directory '.$quote($data.'/playlists')."\n".
    'db_file '.$quote($data.'/mpd.db')."\n".
    "pid_file \"/run/after-show-hours-radio/mpd.pid\"\n".
    "bind_to_address \"/run/after-show-hours-radio/mpd.sock\"\n".
    "port \"0\"\nlog_level \"error\"\nauto_update \"no\"\nrestore_paused \"yes\"\n".
    "audio_buffer_size \"2048\"\nmax_playlist_length \"250\"\nzeroconf_enabled \"no\"\n".
    "audio_output {\n type \"pulse\"\n name \"After Show Hours Radio\"\n server \"/run/after-show-hours-radio-pulse/pulse-native\"\n sink ".$quote($sink)."\n mixer_type \"software\"\n format \"44100:16:2\"\n}\n";
if (file_put_contents('/etc/after-show-hours-radio/mpd.conf',$config)===false) throw new RuntimeException('Could not write the radio audio configuration.');
