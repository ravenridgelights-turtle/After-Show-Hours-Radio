<?php
namespace AfterShow;

/** FPP 10.4 status: a sequence alone does not occupy the media player. */
final class FppStatus
{
    // true = protected, false = radio allowed, null = unverified status.
    public static function audioBusy($status)
    {
        if (!is_array($status) || !is_string($status['status_name'] ?? null)) return null;
        if (isset($status['fppd']) && $status['fppd']!=='running') return null;
        $name=strtolower(trim($status['status_name']));
        foreach (['current_song','media_filename'] as $key) {
            if (!array_key_exists($key,$status)) continue;
            if (!is_string($status[$key])) return null;
            if (trim($status[$key])!=='') return true;
        }
        if (array_key_exists('media_playing',$status)) {
            if (in_array($status['media_playing'],[true,1,'1'],true)) return true;
            if (!in_array($status['media_playing'],[false,0,'0'],true)) return null;
        }
        if (array_key_exists('streamSlots',$status)) {
            if (!is_array($status['streamSlots'])) return null;
            foreach ($status['streamSlots'] as $slot) {
                if (!is_array($slot) || !is_string($slot['status'] ?? null)) return null;
                if (strtolower(trim($slot['status']))!=='idle') return true;
                if (isset($slot['mediaFilename']) && (!is_string($slot['mediaFilename']) || trim($slot['mediaFilename'])!=='')) return true;
            }
        }
        $playlist=$status['current_playlist'] ?? [];
        if (!is_array($playlist) || (isset($playlist['type']) && !is_string($playlist['type']))) return null;
        $type=strtolower(trim($playlist['type'] ?? ''));
        if (in_array($type,['media','both'],true)) return true;
        if (!isset($status['status']) || !in_array($status['status'],[0,1,2,3,4,5,'0','1','2','3','4','5'],true)) return null;
        $number=(int)$status['status'];
        if (in_array($name,['idle','stopped'],true)) return $number===0 ? false : null;
        $playing=['playing'=>1,'stopping gracefully'=>2,'stopping gracefully after loop'=>3];
        if (!isset($playing[$name])) return true;
        if ($number!==$playing[$name]) return null;
        // Require positive evidence of a sequence-only item, not just an
        // empty song at a playlist transition, pause, command or script.
        if (!array_key_exists('current_song',$status) || !is_string($status['current_sequence'] ?? null)) return null;
        if (!preg_match('/\.fseq$/i',trim($status['current_sequence']))) return true;
        if ($type==='sequence') return false;
        // Direct/remote sequence playback has no current playlist item.
        if ($type==='' && empty($playlist['playlist'])) return false;
        return true;
    }
}
