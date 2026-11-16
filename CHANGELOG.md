# Changes

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
