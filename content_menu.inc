<?php
$pages = ['name'=>'After Show Hours Radio','type'=>'content','page'=>'after-show-hours-radio.php'];
?>
<li><a href="plugin.php?plugin=After-Show-Hours-Radio&amp;page=after-show-hours-radio.php">After Show Hours Radio</a></li>
<?php
// FPP's normal plugin menu include is loaded on Status as well. Load only our
// own display assets there; no FPP file or global status value is changed.
if (basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '') === 'index.php') {
    $ashStatusRef = (string)filemtime(__DIR__.'/assets/status.js');
    echo '<link rel="stylesheet" href="plugin.php?plugin=After-Show-Hours-Radio&amp;file=assets/status.css&amp;nopage=1&amp;ref='.$ashStatusRef.'">';
    echo '<script defer src="plugin.php?plugin=After-Show-Hours-Radio&amp;file=assets/status.js&amp;nopage=1&amp;ref='.$ashStatusRef.'"></script>';
}
?>
