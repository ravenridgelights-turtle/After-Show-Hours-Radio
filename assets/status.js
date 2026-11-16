(() => {
  'use strict';
  function init() {
    if (document.getElementById('ash-status-radio') || typeof window.OnSystemStatusChange !== 'function') return;
    const anchor=document.querySelector('#playerStatusTop .playerStatusRow');
    if (!anchor) return;
    const panel=document.createElement('section');panel.id='ash-status-radio';panel.hidden=true;panel.setAttribute('aria-label','After Show Hours Radio playback');
    const heading=document.createElement('div');heading.className='ash-status-heading';
    const live=document.createElement('span');live.className='ash-status-live';live.textContent='● After Show Hours Radio · Playing';
    const link=document.createElement('a');link.href='plugin.php?plugin=After-Show-Hours-Radio&page=after-show-hours-radio.php';link.textContent='Radio controls';
    heading.append(live,link);
    const station=document.createElement('div');station.className='ash-status-station';
    const track=document.createElement('p');track.className='ash-status-track';
    const extra=document.createElement('div');extra.className='ash-status-extra';panel.append(heading,station,track,extra);
    anchor.insertAdjacentElement('afterend',panel);
    let timer=null,controller=null,generation=0;
    function idle() {
      const status=window.lastStatusJSON;
      return !document.hidden && status && String(status.status_name).toLowerCase()==='idle' && Number(status.status)===0;
    }
    function hide() {
      generation++;panel.hidden=true;clearTimeout(timer);timer=null;
      if(controller)controller.abort();controller=null;
    }
    async function poll() {
      timer=null;if(!idle()){hide();return;}if(controller)return;
      const mine=++generation;const request=new AbortController();controller=request;
      const timeout=setTimeout(()=>request.abort(),4500);let wait=15000;
      try {
        const response=await fetch('/api/plugin/After-Show-Hours-Radio/nowPlaying',{credentials:'same-origin',cache:'no-store',signal:request.signal});
        if(!response.ok)throw new Error('Status unavailable');const result=await response.json();
        if(mine!==generation || !idle())return;
        const data=result.status && result.data;
        panel.hidden=!(data && data.active);
        if(data && data.active){
          station.textContent=data.station;track.textContent=data.track || 'Live internet radio';
          extra.textContent=[data.muted ? 'Muted' : 'Radio volume '+data.volume+'%',data.bitrate ? data.bitrate+' kbps' : ''].filter(Boolean).join(' · ');
          wait=5000;
        }
      } catch(error) {if(mine===generation)panel.hidden=true;}
      finally {
        clearTimeout(timeout);if(controller===request)controller=null;
        if(mine===generation && idle())timer=setTimeout(poll,wait);
      }
    }
    function changed() {
      // FPP's own status callback is read only: never change lastStatusJSON,
      // its renderer, player labels, playlist data or show controls.
      if(!idle()){hide();return;}
      if(!timer && !controller)poll();
    }
    window.OnSystemStatusChange(changed);
    document.addEventListener('visibilitychange',changed);window.addEventListener('pagehide',hide);changed();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
