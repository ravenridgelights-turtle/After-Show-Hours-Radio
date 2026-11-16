<?php
// Keep these outside FPP's automatically loaded js/css directories. Each
// content hash gives an updated asset a fresh URL after an in-place Update.
$ashRadioScriptRef = substr(hash_file('sha256', __DIR__.'/assets/radio-app.js'), 0, 12);
$ashRadioStyleRef = substr(hash_file('sha256', __DIR__.'/assets/radio-app.css'), 0, 12);
?>
<link rel="stylesheet" href="plugin.php?plugin=After-Show-Hours-Radio&amp;file=assets/radio-app.css&amp;nopage=1&amp;ref=<?= $ashRadioStyleRef ?>">
<script defer src="plugin.php?plugin=After-Show-Hours-Radio&amp;file=assets/radio-app.js&amp;nopage=1&amp;ref=<?= $ashRadioScriptRef ?>"></script>
<div id="ash-radio" data-api="/api/plugin/After-Show-Hours-Radio/">
  <header class="ash-heading">
    <div><p class="ash-eyebrow">FPP 10.4 · RADIO 1.2.2</p><h1>After Show Hours <span>Radio</span></h1></div>
    <span class="ash-chip" id="ash-service">Connecting</span>
  </header>
  <div id="ash-notice" role="status" aria-live="polite" hidden></div>
  <section class="ash-deck" aria-label="Radio controls">
    <div class="ash-display">
      <div class="ash-display-top"><span id="ash-live">RADIO OFF</span><span id="ash-codec"></span></div>
      <h2 id="ash-station-name">Choose your soundtrack.</h2>
      <p id="ash-track">Pick a preset or play a saved station.</p>
      <div class="ash-signal" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    </div>
    <div class="ash-controls">
      <div class="ash-transport">
        <button type="button" data-control="seekPrevious" title="Seek backward: check up to five saved stations">&#171; <span>Seek</span></button>
        <button type="button" data-control="previous" title="Previous enabled saved station">&#9198; <span>Previous</span></button>
        <button type="button" class="ash-primary" data-control="play">&#9654; <span>Play</span></button>
        <button type="button" data-control="next" title="Next enabled saved station"><span>Next</span> &#9197;</button>
        <button type="button" data-control="seekNext" title="Seek forward: check up to five saved stations"><span>Seek</span> &#187;</button>
      </div>
      <div class="ash-bottom-controls">
        <button type="button" data-control="stop" class="ash-stop">&#9632; Stop</button>
        <button type="button" data-control="mute" id="ash-mute" aria-pressed="false">Mute</button>
        <label class="ash-volume">Volume <input id="ash-volume" aria-label="Radio volume" type="range" min="0" max="100" value="70"><output id="ash-volume-label">70%</output></label>
        <label class="ash-sleep">Sleep <select id="ash-sleep" aria-label="Sleep timer"><option value="0">Off</option><option value="15">15 min</option><option value="30">30 min</option><option value="60">1 hour</option><option value="120">2 hours</option></select></label>
      </div>
      <p id="ash-sleep-note" class="ash-hint"></p>
    </div>
  </section>
  <section class="ash-presets" aria-labelledby="ash-presets-title">
    <div class="ash-section-heading">
      <div><p class="ash-eyebrow" id="ash-presets-title">YOUR PRESETS</p><label class="ash-bank-label">Page <select id="ash-bank" aria-label="Preset page"></select></label></div>
      <div class="ash-toolbar"><button type="button" id="ash-bank-add">+ New page</button><button type="button" id="ash-bank-rename">Rename</button><button type="button" id="ash-bank-delete" title="Remove this preset page; saved stations are kept">Remove page</button><button type="button" id="ash-preset-edit" aria-pressed="false">Edit presets</button></div>
    </div>
    <div id="ash-preset-grid" class="ash-preset-grid"></div>
    <p class="ash-hint" id="ash-preset-hint">Tap a preset to tune in. Use Edit presets to assign or clear buttons.</p>
  </section>
  <nav class="ash-tabs" aria-label="Radio sections">
    <button type="button" data-tab="saved" class="ash-selected" aria-pressed="true">Saved stations <span id="ash-count">0</span></button>
    <button type="button" data-tab="finder" aria-pressed="false">Find stations</button>
    <button type="button" data-tab="settings" aria-pressed="false">Settings &amp; schedules</button>
  </nav>
  <section id="ash-tab-saved" class="ash-panel">
    <div class="ash-section-heading"><div class="ash-filters"><input type="search" id="ash-search" aria-label="Search saved stations" placeholder="Search your stations"><label><input type="checkbox" id="ash-only-favorites"> Favorites only</label></div><button type="button" class="ash-primary" id="ash-add">+ Add station</button></div>
    <p class="ash-hint">Next and Previous follow this order. Enabled stations are included in tuning and automatic recovery; any station can be played directly.</p>
    <div id="ash-saved-list"></div>
  </section>
  <section id="ash-tab-finder" class="ash-panel" hidden>
    <h2>A little less searching. A lot more radio.</h2>
    <p>Search for any station or music genre, browse a directory, or add a stream URL you already know.</p>
    <form id="ash-manual-form" class="ash-manual-form">
      <h3>Add a known station</h3><div class="ash-manual-fields">
        <label>Station name<input id="ash-manual-name" required maxlength="160" placeholder="My favorite station" autocomplete="off"></label>
        <label>Stream URL<input id="ash-manual-url" type="url" required maxlength="2048" placeholder="https://station.example/live.mp3"></label>
        <button type="submit" value="save">Save station</button><button type="submit" value="play" class="ash-primary">Save &amp; play</button>
      </div><p class="ash-hint">Paste a direct HTTP or HTTPS audio stream URL.</p><p id="ash-manual-feedback" role="status" aria-live="polite" hidden></p>
    </form>
    <div class="ash-directory-controls"><label>Station source <select id="ash-source">
      <optgroup label="Radio Browser">
        <option value="radiobrowser-christmas">Radio Browser · Christmas</option><option value="radiobrowser-halloween">Radio Browser · Halloween</option><option value="radiobrowser-newyear">Radio Browser · New Year / Party</option><option value="radiobrowser-search">Radio Browser · Search any music or station</option>
      </optgroup>
      <optgroup label="181.FM"><option value="181fm-christmas">181.FM · Christmas</option><option value="181fm-all">181.FM · All stations</option></optgroup>
      <optgroup label="Internet-Radio.com"><option value="internet-radio-search">Internet-Radio.com · Search any music or station</option><option value="internet-radio-christmas">Internet-Radio.com · Christmas</option><option value="internet-radio-genre">Internet-Radio.com · Browse a genre</option></optgroup>
      <optgroup label="Halloweenradio.net"><option value="halloweenradio">Halloweenradio.net</option></optgroup>
      <optgroup label="StreamURL"><option value="christmas">StreamURL · Christmas</option><option value="halloween">StreamURL · Halloween</option><option value="newyear">StreamURL · New Year</option></optgroup>
    </select></label><button type="button" class="ash-primary" id="ash-directory-load">Load stations</button><button type="button" id="ash-directory-refresh">Refresh source</button></div>
    <div id="ash-directory-query-controls" class="ash-directory-query-controls" hidden>
      <label id="ash-directory-by-label">Search by <select id="ash-directory-by"><option value="name">Station name</option><option value="tag">Genre / tag</option></select></label>
      <label><span id="ash-directory-query-label">Search</span><input id="ash-directory-query" type="search" maxlength="80" placeholder="Station name or genre"></label>
      <button type="button" id="ash-directory-query-go">Search source</button>
    </div>
    <p class="ash-hint">Radio Browser and 181.FM show up to 25 stations per page. Results stay cached for 6 hours; requests are at least 10 seconds apart. No background crawling or checks of every station.</p>
    <p id="ash-directory-status" role="status"></p><input id="ash-directory-search" type="search" aria-label="Filter directory results" placeholder="Filter these results" hidden>
    <div id="ash-directory-list" class="ash-directory-grid"></div>
    <button type="button" id="ash-directory-more" hidden>Load next page</button>
  </section>
  <section id="ash-tab-settings" class="ash-panel" hidden>
    <div class="ash-settings-grid">
      <div><h2>Radio preferences</h2><form id="ash-settings-form">
        <label class="ash-check"><input type="checkbox" id="ash-protect" checked> Protect an active FPP show</label><p class="ash-hint">Blocks manual Play and tuning while a show is running. The once-a-minute monitor also stops radio if a show starts. Always use the Stop command in your show's lead-in for an immediate stop.</p>
        <label class="ash-check"><input type="checkbox" id="ash-recover" checked> Recover stalled streams</label><p class="ash-hint">Checks local player progress about once a minute, then tries one enabled saved station. No directory scans.</p>
        <label>Starting radio volume <input type="number" id="ash-default-volume" min="0" max="100" value="70"> %</label><p class="ash-hint">Applies when starting from Stop. A station's own saved volume takes priority. FPP's show volume is unchanged.</p>
        <button type="submit" class="ash-primary">Save preferences</button>
      </form><h3>Backup &amp; restore</h3><p>Export your stations and every named preset page before uninstalling. Import merges your backup and skips duplicate stream URLs.</p><div class="ash-toolbar"><button type="button" id="ash-export">Export backup</button><label class="ash-file-button">Import backup<input id="ash-import" type="file" accept=".json,application/json"></label><button type="button" id="ash-clear-cache">Clear directory cache</button></div></div>
      <div class="ash-instructions"><h2>Let FPP run the radio</h2>
        <ol><li>After installing, use FPP's <strong>Restart FPPD</strong> banner when your show is idle. This registers the commands. <strong>No controller reboot is needed.</strong></li><li>In your show playlist's <strong>Lead In</strong>, add an <strong>FPP Command → After Show Hours Radio - Stop</strong>.</li><li>Add a <strong>1-second Pause</strong> immediately after Stop.</li><li>In <strong>Lead Out</strong>, add <strong>After Show Hours Radio - Start</strong>, or <strong>Play Preset</strong> with a preset number and page name.</li><li>Schedule the playlist normally. You can also schedule these commands directly in FPP Scheduler.</li></ol>
        <p>Example: <strong>Play Preset → 3 → Christmas</strong> selects preset 3 on your Christmas page, even if you were viewing Halloween.</p>
        <p>Next Station and Previous Station are also available as FPP commands. Explicit FPP Start and Preset commands are allowed during lead-out while FPP still reports a running playlist.</p>
        <h3>Installing alongside the old plugin</h3><p>Stop the original After Hours plugin and remove its old scheduled Start commands. Its saved internet stations are copied on first install when its settings file is present. Only run one radio at a time.</p>
        <h3>About switching &amp; Seek</h3><p>Station buttons switch the existing player without a separate Stop or an audio-service restart. Internet streams may take a moment to buffer. Seek checks a small audio sample from up to five enabled saved stations, in order; the current audio continues while it checks. Stop cancels the pending Seek.</p>
        <p>Live radio cannot rewind or fast-forward. Seek finds another saved station. Sleep stops within about a minute of the selected time. Playback does not automatically resume after a reboot.</p>
        <h3>Now Playing on FPP Status</h3><p>The main Status page shows the radio station and current track while radio is playing and FPP is idle. It hides during shows and when radio stops. No FPP core files or native show/song status are changed.</p>
        <h3>Changed your FPP audio output?</h3><p>Reinstall this plugin from Plugin Manager to follow FPP's current PipeWire output. Your settings stay intact during Reinstall.</p>
        <p class="ash-hint">Based on Jason Cross's MIT-licensed fpp-after-hours. This edition targets FPP 10.4. StreamURL availability depends on the site's public pages and request limits.</p>
      </div>
    </div>
  </section>
  <dialog id="ash-station-dialog" class="ash-dialog"><form id="ash-station-form">
    <h2 id="ash-station-dialog-title">Add station</h2><input id="ash-edit-uid" type="hidden">
    <label>Name<input id="ash-edit-name" required maxlength="160" autocomplete="off"></label>
    <label>Stream URL<input id="ash-edit-url" type="url" required maxlength="2048" placeholder="https://station.example/live.mp3"></label>
    <label>Category<input id="ash-edit-category" maxlength="160" placeholder="Christmas, Halloween, Everyday…"></label>
    <label>Station volume <input id="ash-edit-volume" type="number" min="0" max="100" placeholder="Use current radio volume"></label>
    <label class="ash-check"><input id="ash-edit-enabled" type="checkbox" checked> Include in Next, Previous, Seek and recovery</label>
    <label class="ash-check"><input id="ash-edit-favorite" type="checkbox"> Favorite station</label>
    <p id="ash-station-error" class="ash-form-error" role="alert"></p>
    <div class="ash-dialog-actions"><button type="button" data-close-dialog>Cancel</button><button type="submit" class="ash-primary">Save station</button></div>
  </form></dialog>
  <dialog id="ash-preset-dialog" class="ash-dialog"><form id="ash-preset-form"><h2 id="ash-preset-dialog-title">Assign preset</h2><input id="ash-slot" type="hidden"><label>Saved station<select id="ash-preset-station"></select></label><p class="ash-hint">Choose an empty slot to clear this preset. Stations remain in your saved list.</p><p id="ash-preset-error" class="ash-form-error" role="alert"></p><div class="ash-dialog-actions"><button type="button" data-close-dialog>Cancel</button><button type="submit" class="ash-primary">Save preset</button></div></form></dialog>
  <dialog id="ash-bank-dialog" class="ash-dialog"><form id="ash-bank-form"><h2 id="ash-bank-dialog-title">New preset page</h2><label>Page name<input id="ash-bank-name" required maxlength="50" placeholder="Christmas, Halloween, Weekend…"></label><p id="ash-bank-error" class="ash-form-error" role="alert"></p><div class="ash-dialog-actions"><button type="button" data-close-dialog>Cancel</button><button type="submit" class="ash-primary">Save page</button></div></form></dialog>
  <dialog id="ash-confirm-dialog" class="ash-dialog"><form method="dialog"><h2 id="ash-confirm-title">Confirm</h2><p id="ash-confirm-message"></p><div class="ash-dialog-actions"><button value="cancel">Cancel</button><button value="ok" class="ash-primary">Remove</button></div></form></dialog>
</div>
