(() => {
  'use strict';
  function init() {
    if(document.getElementById('ash-status-radio') || typeof window.OnSystemStatusChange!=='function' || typeof window.AfterShowRadioStatus?.audioBusy!=='function')return;
    const anchor=document.querySelector('#playerStatusTop .playerStatusRow');if(!anchor)return;
    const element=(tag,text,className)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(className)e.className=className;return e;};
    const panel=element('section');panel.id='ash-status-radio';panel.hidden=true;panel.setAttribute('aria-label','After Show Hours Radio playback');
    const heading=element('div',undefined,'ash-status-heading');
    const live=element('span','● After Show Hours Radio · Playing','ash-status-live');
    const link=element('a','Radio controls');link.href='plugin.php?plugin=After-Show-Hours-Radio&page=after-show-hours-radio.php';heading.append(live,link);
    const body=element('div',undefined,'ash-status-body'),copy=element('div',undefined,'ash-status-copy');
    const station=element('div',undefined,'ash-status-station'),track=element('p',undefined,'ash-status-track'),extra=element('div',undefined,'ash-status-extra');copy.append(station,track,extra);
    const transport=element('div',undefined,'ash-status-transport');transport.setAttribute('role','group');transport.setAttribute('aria-label','Radio playback controls');
    const controlButtons=[];
    const makeButton=(label,action,icon,color)=>{
      const b=element('button',undefined,'btn btn-rounded '+color);b.type='button';b.dataset.radioAction=action;
      const i=element('i',undefined,'fas fa-fw '+icon);i.setAttribute('aria-hidden','true');b.append(i,document.createTextNode(' '+label));
      b.addEventListener('click',()=>sendControl(action));controlButtons.push(b);return b;
    };
    transport.append(makeButton('Previous','previous','fa-step-backward','btn-pleasant'),makeButton('Stop','stop','fa-stop','btn-danger'),makeButton('Next','next','fa-step-forward','btn-pleasant'));
    body.append(copy,transport);
    const presetRow=element('div',undefined,'ash-status-preset-row'),bankLabel=element('label','Presets','ash-status-bank-label');
    const bankSelect=element('select',undefined,'ash-status-bank');bankSelect.setAttribute('aria-label','Radio preset page');bankLabel.append(bankSelect);
    const presets=element('div',undefined,'ash-status-presets');presets.setAttribute('role','group');presets.setAttribute('aria-label','Radio presets 1 through 12');presetRow.append(bankLabel,presets);
    const errorText=element('p',undefined,'ash-status-error');errorText.hidden=true;errorText.setAttribute('role','status');
    panel.append(heading,body,presetRow,errorText);anchor.insertAdjacentElement('afterend',panel);
    let timer=null,controller=null,generation=0,selectedBank='',presetHash='',banksHash='',actionBusy=false,actionGeneration=0;
    try{selectedBank=sessionStorage.getItem('ash-radio-status-bank') || '';}catch(error){}
    function radioAllowed(){return !document.hidden && window.AfterShowRadioStatus.audioBusy(window.lastStatusJSON)===false;}
    function hide(){generation++;actionGeneration++;actionBusy=false;panel.hidden=true;errorText.hidden=true;clearTimeout(timer);timer=null;if(controller)controller.abort();controller=null;}
    function refreshNow(){generation++;clearTimeout(timer);timer=null;if(controller)controller.abort();controller=null;if(radioAllowed())poll();else hide();}
    function disableControls(){
      controlButtons.forEach(b=>b.disabled=actionBusy && b.dataset.radioAction!=='stop');
      presets.querySelectorAll('button').forEach(b=>b.disabled=actionBusy || b.dataset.assigned!=='true');bankSelect.disabled=actionBusy;
    }
    function renderPresets(data){
      const banks=Array.isArray(data.banks)?data.banks:[],slots=Array.isArray(data.presets)?data.presets:[];
      const nextHash=JSON.stringify(banks);
      if(nextHash!==banksHash){banksHash=nextHash;bankSelect.replaceChildren(...banks.map(bank=>{const o=element('option',bank.name);o.value=bank.id;return o;}));}
      selectedBank=data.bank || '';bankSelect.value=selectedBank;
      const hash=JSON.stringify([selectedBank,slots]);if(hash===presetHash){disableControls();return;}presetHash=hash;
      const buttons=[];
      for(let slot=1;slot<=12;slot++){
        const preset=slots.find(s=>Number(s.slot)===slot);const b=element('button',String(slot),'btn btn-rounded '+(preset?.current?'btn-success':'btn-pleasant'));b.type='button';
        b.dataset.slot=String(slot);b.dataset.assigned=String(!!preset?.assigned);b.title='Preset '+slot+': '+(preset?.name || 'Empty');b.setAttribute('aria-label',b.title);b.setAttribute('data-bs-html','false');b.setAttribute('aria-pressed',String(!!preset?.current));
        const pageId=data.bank;b.addEventListener('click',()=>sendControl('preset',{slot,bank:pageId}));buttons.push(b);
      }
      presets.replaceChildren(...buttons);disableControls();
    }
    async function sendControl(action,args={}){
      if(!radioAllowed()){hide();return;}if(actionBusy && action!=='stop')return;
      const mine=++actionGeneration;actionBusy=true;errorText.hidden=true;disableControls();
      const request=new AbortController(),timeout=setTimeout(()=>request.abort(),8000);
      try{
        const response=await fetch('/api/plugin/After-Show-Hours-Radio/control',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,...args}),signal:request.signal});
        if(!response.ok)throw new Error('Radio control returned HTTP '+response.status+'.');const result=await response.json();if(!result.status)throw new Error(result.error || 'Radio control failed.');
        if(mine!==actionGeneration)return;
        if(action==='stop')hide();else refreshNow();
      }catch(error){if(mine===actionGeneration && radioAllowed()){errorText.textContent=error.name==='AbortError'?'Radio control timed out.':error.message;errorText.hidden=false;}}
      finally{clearTimeout(timeout);if(mine===actionGeneration || action==='stop'){actionBusy=false;disableControls();}}
    }
    bankSelect.addEventListener('change',()=>{
      selectedBank=bankSelect.value;try{sessionStorage.setItem('ash-radio-status-bank',selectedBank);}catch(error){}
      presets.querySelectorAll('button').forEach(b=>b.disabled=true);presetHash='';refreshNow();
    });
    async function poll(){
      timer=null;if(!radioAllowed()){hide();return;}if(controller)return;
      const mine=++generation,request=new AbortController();controller=request;const timeout=setTimeout(()=>request.abort(),4500);let wait=15000;
      try{
        const url='/api/plugin/After-Show-Hours-Radio/nowPlaying'+(selectedBank?'?bank='+encodeURIComponent(selectedBank):'');
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:request.signal});if(!response.ok)throw new Error('Status unavailable');const result=await response.json();
        if(mine!==generation || !radioAllowed())return;const data=result.status && result.data;panel.hidden=!(data && data.active);
        if(data && data.active){station.textContent=data.station;track.textContent=data.track || 'Live internet radio';extra.textContent=[data.muted?'Muted':'Radio volume '+data.volume+'%',data.bitrate?data.bitrate+' kbps':''].filter(Boolean).join(' · ');renderPresets(data);wait=5000;}
      }catch(error){if(mine===generation)panel.hidden=true;}
      finally{clearTimeout(timeout);if(controller===request)controller=null;if(mine===generation && radioAllowed())timer=setTimeout(poll,wait);}
    }
    function changed(){if(!radioAllowed()){hide();return;}if(!timer && !controller)poll();}
    window.OnSystemStatusChange(changed);document.addEventListener('visibilitychange',changed);window.addEventListener('pagehide',hide);changed();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
