#!/bin/bash
. "${FPPDIR:-/opt/fpp}/scripts/common"
for unit in after-show-hours-radio-monitor.timer after-show-hours-radio-monitor.service after-show-hours-radio.service after-show-hours-radio-pulse.service; do
    systemctl disable --now "$unit" 2>/dev/null || true
    rm -f "/etc/systemd/system/$unit"
done
rm -rf /etc/after-show-hours-radio /run/after-show-hours-radio /run/after-show-hours-radio-pulse
systemctl daemon-reload
# Reinstall keeps the radio library; a real uninstall removes its own data.
if [ "${FPP_PLUGIN_REINSTALL:-0}" != "1" ]; then
    rm -rf "${MEDIADIR}/plugindata/After-Show-Hours-Radio"
fi
# Dependencies are released by FPP, which knows whether another plugin uses them.
setSetting restartFlag 1
exit 0
