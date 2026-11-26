# Changes

## 1.2.3 — November 25, 2026

- Allow radio Play, presets, Next/Previous and Seek while FPP runs a lights-only .fseq with no media. Shared status checks distinguish sequence-only playback from musical shows, media entries and active foreground/background stream slots.
- Keep the radio panel and compact controls visible on FPP Status alongside a confirmed silent animation without changing native sequence/song/player status. Media starts and late responses still hide the radio panel.
- Use the same media-aware protection in the automatic monitor. Silent animations no longer stop radio; protected radio stops on a monitor check if media starts or status cannot be verified. Existing monitor cadence and starting grace are unchanged.
- Add common PHP/JavaScript status scenarios, silent-sequence control/monitor regressions and browser checks. Updated install/use guide includes static-only playlist scheduling.
- Same FPP Update link, no new endpoints, no FPP core edits, no settings migration and no reboot or service restart.

## 1.2.2 — November 16, 2026

- Replaced the local `icon.png` as well as the metadata icon. FPP 10.4 gives the local file priority for installed plugins, so the new boombox now appears on the installed card and plugin page.
- Includes the 1.2.1 search/cache fix and the full release-page install and usage guide.

## 1.2.1 — November 16, 2026

- Fixed stale browser assets after an FPP Update, which could hide Internet-Radio.com's search field despite the new source option appearing. Plugin and Status assets now use content hashes in their URLs; normal page refresh loads the current version.
- Added a distinct amber/teal boombox icon with a new image URL so the Plugin Manager no longer reuses the original plugin's moon/music icon.
- Added release-page installation, update and usage instructions, including named presets, station discovery, Status controls and show scheduling. Validated main-branch releases are published automatically.
- Preserved saved stations, preset pages, schedules and the existing install/update URL. No FPP core edits, audio restart or reboot in this update.

## 1.2.0 — November 16, 2026

- Added compact FPP-style Previous, Stop and Next controls to the radio panel on the main Status page.
- Added a named preset-page selector and buttons 1–12 on Status, with active/empty state and station-name labels. Preset-page selection is local to the tab; the compact summary returns only the chosen page's assignments.
- Added station-name and direct-stream URL entry in Find stations, with Save station and Save & play. Existing stream URLs are reused.
- Added Internet-Radio.com's public search for station names and everyday music styles, alongside Christmas and direct genre browsing. Next-page requests follow the site's actual links, including redirected genre searches.
- Kept FPP show priority, no core-file changes, and in-place Update with no audio-service/FPPD restart or reboot.

## 1.1.0 — November 16, 2026

- Added Radio Browser searches for Christmas, Halloween, party music, and custom station names or genre tags.
- Added 181.FM's published Christmas and general stream list with shared caching and paging.
- Added Internet-Radio.com Christmas and custom genre directories; extracts names/direct player links without executing site JavaScript.
- Kept the existing StreamURL and Halloweenradio sources, request cooldowns, provider backoff, and six-hour caching.
- Added radio Now Playing below Player Status on FPP's main Status page, visible only while radio plays and FPP is idle. Show/song status remains native; stale radio responses are ignored when a show starts. No FPP core edits.
- Added a lightweight FPP Update hook: this update preserves stations/preset pages and does not restart audio, FPPD, or the controller. The same repository, branch and install URL continue to work.
- Preserved valid UTF-8 in truncated station/track metadata and capped accumulated search-cache files.
