# After Show Hours Radio

An internet radio for **Falcon Player (FPP) 10.4**. Based on Jason Cross's [fpp-after-hours](https://github.com/jcrossbdn/fpp-after-hours), with a new radio interface, station controls and discovery. Original MIT license and copyright are retained in [LICENSE](LICENSE).

## Install in FPP

Open **Content Setup → Plugin Manager**, paste this URL into its GitHub/plugin URL field, and install:

```text
https://raw.githubusercontent.com/ravenridgelights-turtle/After-Show-Hours-Radio/main/pluginInfo.json
```

Repository URL (also accepted by FPP 10.4):

```text
https://github.com/ravenridgelights-turtle/After-Show-Hours-Radio
```

If the URL field is hidden, switch FPP's UI level to Developer. You can return to your usual UI level afterward. Open **Content Setup → After Show Hours Radio** after installing.

**No controller reboot is requested.** The installer starts only this plugin's services. FPP will show its normal **Restart FPPD** banner to register the new FPP commands; use it once when your show is idle. The installer never restarts FPPD or FPP's audio stack itself.

### Moving from the original After Hours plugin

1. Stop the original plugin and remove its old scheduled Start commands. Do not run both radios at once.
2. Install this plugin. On first install it copies valid internet station entries from `plugindata/fpp-after-hours-config.json`, if that file exists. It preserves names, URLs, enabled flags, ordering and station volumes. The original settings file is left untouched.
3. Set up your new preset pages and replace the old playlist commands with the commands below.
4. You may uninstall the original plugin. This edition uses its own audio services and config, so the original plugin's uninstall cannot remove this edition's files. FPP manages shared package dependencies.

Local music playback from older plugin versions is outside this edition's scope. Export a backup before a real uninstall of this edition. **Reinstall** keeps the radio settings; **Uninstall** removes this edition's settings and directory cache.

## Radio controls

- **Play on any station:** tune directly without stopping first, reordering the list, or changing enabled checkboxes.
- **Next / Previous:** cycle enabled saved stations in your chosen order, wrapping at the ends.
- **Seek forward / backward:** check up to five enabled saved stations, one at a time, and tune the first that returns an audio sample. Checks use at most 4 KB and a 2-second HTTP budget per station. Stop cancels a pending Seek; the current audio continues during checks.
- **Named preset pages:** 12 preset buttons per page. Christmas, Halloween and Everyday are created initially. Add, rename or remove pages; each has independent assignments. Up to 64 pages are supported to keep backups bounded on small devices. Removing a page does not delete stations.
- **Volume and Mute:** control the radio's software mixer. They never change FPP's show volume. Unmute restores the prior level.
- **Station volumes:** optionally override the level when tuning a particular station. A blank station volume keeps the radio's current level; starting from Stop uses your configured starting volume.
- **Favorites, search and station order:** star stations, filter locally, and move stations up or down.
- **Sleep timer:** stop after 15/30/60/120 minutes, checked by the monitor with roughly one-minute precision.
- **Backup/import:** export stations and all named preset pages as JSON. Import merges stations by exact stream URL and fills empty slots on matching page names; existing assignments are preserved.

An enabled checkbox controls inclusion in Next, Previous, Seek and recovery. Direct Play and presets can still select a disabled station. Seek works over saved stations, not the entire internet. Live radio cannot rewind or fast-forward.

### What switching means

The next URL is added before the player switches to it; there is no Stop/Clear cycle and no audio-service restart between stations. A rejected queue addition leaves the current station playing. Internet station connection and buffering can still produce a short gap. This is a single-player design, without two simultaneous decoders or a crossfade.

MPD handles normal HTTP(S) MP3, AAC and Ogg streams according to the decoders installed on the player. Use direct audio URLs. A successful network Seek is an audio-response check, not a guarantee of content or future availability. Public internet URLs can be checked by Seek; private/LAN URLs can be saved and played directly but are not probed by the station finder.

## FPP commands and scheduling

After restarting FPPD once, these commands appear in FPP's command picker:

| Command | Purpose |
|---|---|
| `After Show Hours Radio - Start` | Resume the last enabled station, or the first enabled station |
| `After Show Hours Radio - Stop` | Stop radio and cancel pending Seek/recovery |
| `After Show Hours Radio - Play Preset` | Preset number **1–12**, followed by optional preset **page name** |
| `After Show Hours Radio - Next Station` | Tune the next enabled station |
| `After Show Hours Radio - Previous Station` | Tune the previous enabled station |

**Recommended show playlist:**

1. **Lead In:** add `FPP Command → After Show Hours Radio - Stop`.
2. Add a **1-second Pause** immediately after Stop.
3. **Lead Out:** add `After Show Hours Radio - Start`, or `Play Preset` with e.g. `3` and `Christmas`.
4. Schedule your playlist normally. The same commands can be run directly from FPP Scheduler.

The page name is matched without regard to capitalization; page IDs are also accepted. A blank page argument uses the selected page. The page name in a schedule must be updated if you rename that page. Commands do not silently stop an active show; explicit scheduled Start/Play Preset commands are trusted so they can run in a playlist's lead-out before FPP becomes idle.

**Protect show**, enabled by default, blocks manual tuning while FPP reports an active show. As a fallback, the once-a-minute monitor also stops radio if a show starts. Use the lead-in Stop command for an immediate stop; the monitor is not a replacement for it. If FPP status cannot be checked, protected manual Play is blocked with an explanation. Stop always remains available.

## Find stations

Sources in the dropdown:

- [StreamURL Christmas](https://streamurl.link/s/christmas)
- [StreamURL Halloween](https://streamurl.link/s/halloween)
- [StreamURL New Year](https://streamurl.link/s/new+year)
- [Halloweenradio.net](https://halloweenradio.net/stations), including public MP3 and AAC+ options

Select a source and **Load stations**. Use **Save station** or **Play** on a result; Play saves the station first. A supported StreamURL station detail result has **Find stream links** to resolve that page on demand.

### Small-controller request limits

- No automatic crawling, browser engine, background directory searches or opening every stream.
- One directory/detail request at a time; at least **10 seconds** between live requests.
- **6-hour cache**; one requested page at a time, at most 60 results per page and 10 directory pages.
- Bounded HTTP connection/time limits and a **1.5 MB** directory response cap. Stream checks stop after a small audio sample.
- Provider `Retry-After` is respected up to one day. Failed sources back off at least 10 minutes; clearing cache does not clear backoff.
- No stream checks during routine UI polling. The monitor reads local playback progress about once a minute and attempts at most one recovery per check.
- The browser polls local status every 5 seconds during radio playback and every 15 seconds when stopped; polling pauses in hidden tabs.

Halloweenradio's public station-details layout was checked against the live page during development. **StreamURL returned HTTP 403 with a 2-hour Retry-After in the build environment**, so its live HTML integration could not be verified. Its adapter handles public HTML/audio links, common stream data attributes, supported station detail links and JSON-LD; it does not execute JavaScript or bypass access controls. If the site blocks the player or changes its markup, the page explains the failure and retains cached results. Bundled Christmas/Halloween starter links are explicitly marked as unverified; New Year requires live directory results or a manually entered stream.

## FPP 10.4 audio and installation behavior

This edition has its own MPD instance, accessed through a **Unix socket only**, and an isolated PipeWire-Pulse bridge into FPP's existing graph. It follows `PipeWireSinkName`, defaulting to `fpp_group_default`. It neither opens a TCP audio control port nor takes the ALSA device directly.

The installer does not change `/etc/mpd.conf`, the packaged `mpd.service`, or FPP's PipeWire configuration/services. No per-user PipeWire/WirePlumber stack is started. If you change the FPP audio output/group, **Reinstall** this plugin to refresh its sink configuration. A PipeWire restart by FPP restarts only this plugin's bridge/player connection; radio recovery remains bounded by the monitor.

Packages (`mpd`, `pipewire-pulse`, `php-curl`, `php-xml`) are declared in `pluginInfo.json` and installed/released by FPP Plugin Manager. The plugin does not run apt or fetch executables itself. It requests only FPP's restart banner to register commands, never a reboot.

Plugin-owned paths outside its repository:

- `/etc/after-show-hours-radio/`: private audio configuration
- `after-show-hours-radio.service`, `after-show-hours-radio-pulse.service`, `after-show-hours-radio-monitor.service` and `.timer`
- `/run/after-show-hours-radio/` and `/run/after-show-hours-radio-pulse/`: volatile sockets and runtime state
- FPP's resolved media directory, `plugindata/After-Show-Hours-Radio/`: stations, preset pages and bounded directory results
- FPP's resolved logs directory, `plugin-After-Show-Hours-Radio.log`

Uninstall removes this edition's services/config/runtime paths and its own data (except on Reinstall). It does not uninstall packages itself or touch the original plugin's files. The FPP-managed log remains in FPP's log directory. Radio playback does not automatically resume after reboot; schedule a Start command if needed.

## Validation and limits

This release targets **FPP 10.4 only** in its compatibility metadata (through 10.x). It was checked against FPP's `10.4` source tag, including its plugin metadata, command loader, API endpoint registration, PipeWire runtime, and sink names. Hardware audio routing and installation still need a first test on an actual Pi/BeagleBone running FPP 10.4; the development environment is not a running FPP controller.

Run deterministic control tests with PHP CLI, PHP XML/cURL, and Python 3:

```sh
php tests/run.php
```

Browser smoke tests require Node and Playwright:

```sh
npm install --no-save playwright@1.62.1
npx playwright install chromium
node tests/ui-smoke.cjs
```

Set `CHROMIUM_PATH` to use a system Chromium. Set `UI_SCREENSHOT_DIR` to choose where test screenshots are written. Tests run separately from plugin install and are never executed by FPP.

## Provenance

Original project: `jcrossbdn/fpp-after-hours`, MIT, upstream commit `5cd18ea8148a793623d81607a5360f4bb86d72be`. This edition retains the original icon, MIT license, and PipeWire bridge approach. Radio control, dedicated MPD service, interface, presets, discovery and scheduling wrappers were reworked for this edition. It is independently maintained by `ravenridgelights-turtle`.
