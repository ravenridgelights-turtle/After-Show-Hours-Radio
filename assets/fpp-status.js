// Keep the classification in sync with src/FppStatus.php. Both are checked
// against tests/fixtures/fpp-status.json; no network requests occur here.
(() => {
  'use strict';
  function audioBusy(status) {
    if(!status || typeof status!=='object' || Array.isArray(status) || typeof status.status_name!=='string')return null;
    if(status.fppd!==undefined && status.fppd!==null && status.fppd!=='running')return null;
    const name=status.status_name.trim().toLowerCase();
    for(const key of ['current_song','media_filename']){
      if(!Object.hasOwn(status,key))continue;
      if(typeof status[key]!=='string')return null;
      if(status[key].trim()!=='')return true;
    }
    if(Object.hasOwn(status,'media_playing')){
      if([true,1,'1'].includes(status.media_playing))return true;
      if(![false,0,'0'].includes(status.media_playing))return null;
    }
    if(Object.hasOwn(status,'streamSlots')){
      if(!Array.isArray(status.streamSlots))return null;
      for(const slot of status.streamSlots){
        if(!slot || typeof slot.status!=='string')return null;
        if(slot.status.trim().toLowerCase()!=='idle')return true;
        if(slot.mediaFilename!==undefined && slot.mediaFilename!==null && (typeof slot.mediaFilename!=='string' || slot.mediaFilename.trim()!==''))return true;
      }
    }
    const playlist=status.current_playlist ?? {};
    if(typeof playlist!=='object' || Array.isArray(playlist) || (playlist.type!==undefined && playlist.type!==null && typeof playlist.type!=='string'))return null;
    const type=(playlist.type ?? '').trim().toLowerCase();
    if(['media','both'].includes(type))return true;
    if(![0,1,2,3,4,5,'0','1','2','3','4','5'].includes(status.status))return null;
    const number=Number(status.status);
    if(['idle','stopped'].includes(name))return number===0?false:null;
    const playing={'playing':1,'stopping gracefully':2,'stopping gracefully after loop':3};
    if(!Object.hasOwn(playing,name))return true;
    if(number!==playing[name])return null;
    if(!Object.hasOwn(status,'current_song') || typeof status.current_sequence!=='string')return null;
    if(!/\.fseq$/i.test(status.current_sequence.trim()))return true;
    if(type==='sequence')return false;
    if(type==='' && !playlist.playlist)return false;
    return true;
  }
  if(typeof module==='object' && module.exports)module.exports={audioBusy};
  else window.AfterShowRadioStatus={audioBusy};
})();
