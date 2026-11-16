#!/bin/bash
# Idempotent FPP 10.4 install. Packages are installed by FPP's Plugin Manager.
# No reboot, no fppd restart, no changes to FPP's own PipeWire services.
set -e
. "${FPPDIR:-/opt/fpp}/scripts/common"
PLUGIN_DIR=$(cd "$(dirname "$0")/.." && pwd)
RADIO_DATA="${MEDIADIR}/plugindata/After-Show-Hours-Radio"
RADIO_LOG="${LOGDIR}/plugin-After-Show-Hours-Radio.log"
install -d -o fpp -g fpp -m 0770 "$RADIO_DATA" "$RADIO_DATA/music" "$RADIO_DATA/playlists" /run/after-show-hours-radio
install -d -m 0755 /etc/after-show-hours-radio/pipewire/pipewire-pulse.conf.d
touch "$RADIO_LOG"
chown fpp:fpp "$RADIO_LOG"
chmod 0664 "$RADIO_LOG"
for bin in /usr/bin/mpd /usr/bin/php /usr/bin/pipewire-pulse; do
    if [ ! -x "$bin" ]; then
        echo "Missing $bin. Reinstall using FPP's Plugin Manager to install dependencies." >&2
        exit 1
    fi
done
if [ ! -f /usr/share/pipewire/pipewire-pulse.conf ]; then
    echo "FPP 10.4 PipeWire configuration is unavailable." >&2
    exit 1
fi
# Confine all audio settings to this plugin. Never edit /etc/mpd.conf or FPP audio.
systemctl stop after-show-hours-radio-monitor.timer after-show-hours-radio.service 2>/dev/null || true
/usr/bin/php "$PLUGIN_DIR/scripts/configure.php"
chown -R fpp:fpp "$RADIO_DATA"
chmod 0640 /etc/after-show-hours-radio/mpd.conf
chown root:fpp /etc/after-show-hours-radio/mpd.conf
ln -sfn /usr/share/pipewire/pipewire-pulse.conf /etc/after-show-hours-radio/pipewire/pipewire-pulse.conf
install -m 0644 "$PLUGIN_DIR/templates/pipewire/90-after-show-hours-radio.conf" /etc/after-show-hours-radio/pipewire/pipewire-pulse.conf.d/90-after-show-hours-radio.conf
for unit in after-show-hours-radio.service after-show-hours-radio-pulse.service after-show-hours-radio-monitor.timer; do
    install -m 0644 "$PLUGIN_DIR/templates/$unit" "/etc/systemd/system/$unit"
done
# Quote the executable's path for relocated media directories that contain spaces.
/usr/bin/php -r '$s=file_get_contents($argv[1]); $p=str_replace(["\\", "\""], ["\\\\", "\\\""], $argv[2]); file_put_contents($argv[3], str_replace("@PLUGIN_DIR@", $p, $s));' \
    "$PLUGIN_DIR/templates/after-show-hours-radio-monitor.service" "$PLUGIN_DIR" /etc/systemd/system/after-show-hours-radio-monitor.service
chmod 0755 "$PLUGIN_DIR"/commands/*.sh "$PLUGIN_DIR"/scripts/*.sh
systemctl daemon-reload
systemctl enable after-show-hours-radio-pulse.service after-show-hours-radio.service after-show-hours-radio-monitor.timer
if systemctl is-active --quiet fpp-pipewire.service; then
    systemctl restart after-show-hours-radio-pulse.service
fi
systemctl restart after-show-hours-radio.service
systemctl start after-show-hours-radio-monitor.timer
# FPP discovers new script commands only when fppd starts. Show its normal
# restart banner and let the operator choose when; never restart it here.
setSetting restartFlag 1
echo "After Show Hours Radio installed. No reboot is needed."
echo "Use Restart FPPD when convenient to register the new FPP commands."
echo "Stop the original After Hours plugin and remove its scheduled commands before using this radio."
