#!/usr/bin/php
<?php
require_once __DIR__.'/../src/bootstrap.php';
$store=\AfterShow\store();
$count=$store->migrateLegacy(dirname($store->dataDir).'/fpp-after-hours-config.json');
if (!is_file($store->dataDir.'/config.json')) $store->saveConfig($store->config());
if ($count) echo "Imported $count saved stations from the original After Hours plugin.\n";
$sink=function_exists('ReadSettingFromFile') ? ReadSettingFromFile('PipeWireSinkName') : '';
$sink=trim((string)$sink);if ($sink==='') $sink='fpp_group_default';
require_once __DIR__.'/../src/AudioConfig.php';
$log=($settings['logDirectory'] ?? dirname(dirname($store->dataDir)).'/logs').'/plugin-After-Show-Hours-Radio.log';
$config=\AfterShow\AudioConfig::build($store->dataDir,$log,$sink);
if (file_put_contents('/etc/after-show-hours-radio/mpd.conf',$config)===false) throw new RuntimeException('Could not write the radio audio configuration.');
