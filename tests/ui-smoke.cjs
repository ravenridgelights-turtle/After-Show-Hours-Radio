// Run with Playwright installed: NODE_PATH=/path/to/node_modules node tests/ui-smoke.cjs
const { chromium } = require('playwright');
const http = require('node:http'), fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process'), { createHash } = require('node:crypto');
const repo = path.resolve(__dirname,'..');
const fragment = execFileSync(process.env.PHP_PATH || 'php', ['-n',path.join(repo,'after-show-hours-radio.php')], {encoding:'utf8'});
const legacyScriptUrl='/plugin.php?plugin=After-Show-Hours-Radio&file=js/radio.js&nopage=1';
const legacyStyleUrl='/plugin.php?plugin=After-Show-Hours-Radio&file=css/radio.css&nopage=1';
let legacyAssetRequests=0;
let config={stations:[{uid:'one',name:'Happy Christmas Radio',url:'https://radio.example/christmas.mp3',volume:70,enabled:true,favorite:true,category:'Christmas'},{uid:'two',name:'Halloweenradio Main',url:'https://radio.example/halloween.mp3',volume:null,enabled:true,favorite:false,category:'Halloween'}],activeBank:'christmas',banks:{christmas:{name:'Christmas',presets:{1:'one'}},halloween:{name:'Halloween',presets:{1:'two'}},everyday:{name:'Everyday',presets:{}}},settings:{defaultVolume:70,protectShow:true,autoRecover:true}};
let state={uid:'one',wanted:false,volume:70,muted:false}, play='stop';
const directoryRequests=[],controlRequests=[],stationRequests=[];let statusCalls=0,statusDelay=0;let radioStatus={active:true,station:'Happy Christmas Radio',track:'Artist <img src=x> — Track title',volume:70,bitrate:128};
const fixture = () => ({status:true,data:{config,state,audio:{player:{state:play,bitrate:'128'},song:{Title:'Example artist — Example song'}},error:null}});
const server=http.createServer(async(req,res)=>{
 const requestUrl=new URL(req.url,'http://localhost');
 if(requestUrl.pathname==='/legacy-cache'){
  res.setHeader('Content-Type','text/html');
  return res.end('<!doctype html><html><head><link rel="stylesheet" href="'+legacyStyleUrl+'"><script src="'+legacyScriptUrl+'"></script></head><body>Old plugin cache</body></html>');
 }
 if(req.url===legacyScriptUrl || req.url===legacyStyleUrl){
  legacyAssetRequests++;res.setHeader('Cache-Control','public,max-age=31536000,immutable');
  res.setHeader('Content-Type',req.url===legacyStyleUrl?'text/css':'text/javascript');
  return res.end(req.url===legacyStyleUrl?'#ash-directory-query-controls{display:none!important}':'window.legacyRadioAssetLoaded=true;');
 }
 if(req.url.startsWith('/api/')){
  let text='';for await(const c of req)text+=c;const data=text?JSON.parse(text):{};const endpoint=new URL(req.url,'http://localhost').pathname.split('/').pop();let result={};
  if(endpoint==='nowPlaying'){statusCalls++;const selected=new URL(req.url,'http://localhost').searchParams.get('bank');const bank=config.banks[selected]?selected:config.activeBank;const payload=JSON.stringify({status:true,data:{...radioStatus,bank,banks:Object.entries(config.banks).map(([id,page])=>({id,name:page.name})),presets:Array.from({length:12},(_,index)=>{const slot=index+1,uid=config.banks[bank].presets[slot],station=config.stations.find(s=>s.uid===uid);return {slot,assigned:!!station,current:uid===state.uid,name:station?.name||''};})}});await new Promise(resolve=>setTimeout(resolve,statusDelay));res.setHeader('Content-Type','application/json');return res.end(payload);}
  if(endpoint==='details'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(fixture()));}
  if(endpoint==='bank'){
   if(data.action==='create'){config.banks.custom={name:data.name,presets:{}};config.activeBank='custom';}
   if(data.action==='rename')config.banks[data.bank].name=data.name;
   if(data.action==='select')config.activeBank=data.bank;
  }
  if(endpoint==='preset')config.banks[data.bank].presets[data.slot]=data.uid;
  if(endpoint==='station'){stationRequests.push(data);const saved={...data,uid:'manual'+stationRequests.length,favorite:false};config.stations.push(saved);result=saved;}
  if(endpoint==='control'){
   controlRequests.push(data);
   if(data.action==='stop'){state.wanted=false;play='stop';radioStatus.active=false;}
   else if(data.action==='volume')state.volume=data.value;
   else if(data.action==='mute')state.muted=!state.muted;
   else {state.wanted=true;play='play';if(data.uid)state.uid=data.uid;if(data.action==='preset')state.uid=config.banks[data.bank].presets[data.slot];if(data.action==='next')state.uid='two';if(data.action==='previous')state.uid='one';radioStatus.station=config.stations.find(s=>s.uid===state.uid)?.name || '';radioStatus.active=true;}
  }
  if(endpoint==='directory'){directoryRequests.push(data);result={stations:[{name:'Directory Fixture',url:'https://radio.example/finder'+data.page+'.mp3',category:'Christmas',detail:false,country:'Example',codec:'MP3',bitrate:128}],page:data.page,hasMore:data.source==='radiobrowser-search' && data.page<2,bundled:false,cached:true,fetchedAt:Math.floor(Date.now()/1000)};}
  res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({status:true,data:result}));
 }
 if(req.url==='/fpp-status'){
  res.setHeader('Content-Type','text/html');
  return res.end('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{--bs-body-bg:#f8f9fa;--bs-body-color:#212529;--fpp-bg-card:#fff;--fpp-border:#dee2e6;--fpp-primary:#0d6efd}.btn{display:inline-block;font:inherit;border:1px solid transparent;cursor:pointer}.btn-rounded{border-radius:2rem}.btn-pleasant{background:#596168;color:#fff}.btn-danger{background:#dc3545;color:#fff}.btn-success{background:#198754;color:#fff}.btn:disabled{opacity:.45;cursor:default}body{font:16px system-ui;margin:1rem;background:var(--bs-body-bg);color:var(--bs-body-color)}[data-bs-theme="dark"]{color-scheme:dark;--bs-body-bg:#212529;--bs-body-color:#dee2e6;--fpp-bg-card:#2c3034;--fpp-border:#495057}</style><link rel="stylesheet" href="/assets/status.css"><script>window.lastStatusJSON=Object.freeze({status:0,status_name:"idle"});window.statusHandlers=[];window.OnSystemStatusChange=fn=>statusHandlers.push(fn);window.applyFPPStatus=data=>{window.lastStatusJSON=Object.freeze(data);document.getElementById("txtPlayerStatus").textContent=data?.status_name||"unknown";document.getElementById("native-song").textContent=data?.current_song||"";document.getElementById("native-sequence").textContent=data?.current_sequence||"";statusHandlers.forEach(fn=>fn());};</script><script defer src="/assets/fpp-status.js"></script><script defer src="/assets/status.js"></script></head><body><h1>Status - Player Mode</h1><div id="playerStatusTop"><div class="playerStatusRow"><span>Player Status: </span><span id="txtPlayerStatus">idle</span><span id="native-song"></span><span id="native-sequence"></span></div><button id="native-play">Play show</button></div></body></html>');
 }
 if(req.url==='/'){
  res.setHeader('Content-Type','text/html');
  return res.end('<!doctype html><html lang="en" data-bs-theme="light"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{--bs-body-bg:#f8f9fa;--bs-body-color:#212529;--fpp-bg-card:#fff;--fpp-border:#dee2e6;--fpp-primary:#0d6efd;--fpp-text-muted:#6c757d;--bs-white:#fff}html[data-bs-theme="dark"]{color-scheme:dark;--bs-body-bg:#212529;--bs-body-color:#dee2e6;--fpp-bg-card:#2c3034;--fpp-border:#495057;--fpp-text-muted:#a1a1a1}body{margin:0;background:var(--bs-body-bg);font-family:system-ui,sans-serif;color:var(--bs-body-color)}button,input,select{font:inherit}</style></head><body>'+fragment+'</body></html>');
 }
 const relative=requestUrl.pathname==='/plugin.php'?requestUrl.searchParams.get('file'):requestUrl.pathname.slice(1);
 const file=path.resolve(repo,relative || '');
 if(!file.startsWith(path.join(repo,'assets')+path.sep) || !fs.existsSync(file)){res.statusCode=404;return res.end('Not found');}
 res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'text/javascript');
 res.setHeader('Cache-Control','public,max-age=31536000,immutable');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=await browser.newPage({viewport:{width:1440,height:1050}}), errors=[];
 const origin='http://127.0.0.1:'+server.address().port, requested=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>requested.push(r.url()));
 // Simulate updating in a browser that still has the old unversioned files.
 await page.goto(origin+'/legacy-cache');assert.equal(await page.evaluate(()=>window.legacyRadioAssetLoaded),true);assert.equal(legacyAssetRequests,2);
 const beforeUpdate=requested.length;await page.goto(origin);
 await page.waitForSelector('.ash-preset');assert.equal(await page.locator('.ash-preset').count(),12);
 assert.equal(await page.evaluate(()=>typeof window.legacyRadioAssetLoaded),'undefined','Updated page never executes cached old UI code');
 assert.equal(requested.slice(beforeUpdate).some(url=>url.includes('file=js/radio.js') || url.includes('file=css/radio.css')),false,'Updated page never requests old unversioned assets');
 for(const asset of ['radio-app.js','radio-app.css']){
  const hash=createHash('sha256').update(fs.readFileSync(path.join(repo,'assets',asset))).digest('hex').slice(0,12);
  assert.equal(requested.some(url=>url.includes('file=assets/'+asset+'&nopage=1&ref='+hash)),true,'Updated page requests the current content hash for '+asset);
 }
 await page.locator('#ash-bank-add').click();await page.locator('#ash-bank-name').fill('New Year Party');await page.locator('#ash-bank-form button[type=submit]').click();
 await page.waitForFunction(()=>document.querySelector('#ash-bank').selectedOptions[0].textContent==='New Year Party');
 await page.locator('.ash-preset').nth(11).click();await page.locator('#ash-preset-station').selectOption('two');await page.locator('#ash-preset-form button[type=submit]').click();await page.waitForSelector('#ash-preset-dialog:not([open])',{state:'attached'});
 await page.locator('.ash-preset').nth(11).click();await page.waitForFunction(()=>document.querySelector('#ash-station-name').textContent==='Halloweenradio Main');
 await page.locator('[data-control=stop]').click();await page.waitForFunction(()=>document.querySelector('#ash-live').textContent==='RADIO OFF');
 await page.locator('[data-tab=finder]').click();await page.locator('#ash-directory-load').click();await page.waitForSelector('.ash-directory-card');
 assert.equal(await page.locator('.ash-directory-card strong').textContent(),'Directory Fixture');
 await page.locator('#ash-source').selectOption('radiobrowser-search');await page.locator('#ash-directory-by').selectOption('tag');await page.locator('#ash-directory-query').fill('classic rock');await page.locator('#ash-directory-query-go').click();await page.waitForSelector('#ash-directory-more:not([hidden])');
 assert.equal(directoryRequests.at(-1).query,'classic rock');assert.equal(directoryRequests.at(-1).searchBy,'tag');
 await page.locator('#ash-directory-more').click();await page.waitForFunction(()=>document.querySelectorAll('.ash-directory-card').length===2);
 assert.equal(directoryRequests.at(-1).page,2);assert.equal(directoryRequests.at(-1).query,'classic rock');
 await page.locator('#ash-source').selectOption('internet-radio-genre');assert.equal(await page.locator('#ash-directory-by-label').isVisible(),false);
 await page.locator('#ash-directory-query').fill('jazz');await page.locator('#ash-directory-query').press('Enter');await page.waitForSelector('.ash-directory-card');assert.equal(directoryRequests.at(-1).query,'jazz');

 await page.locator('#ash-manual-name').fill('Everyday Jazz');await page.locator('#ash-manual-url').fill('https://station.example/jazz.mp3');await page.locator('#ash-manual-form button[value=save]').click();
 await page.waitForFunction(()=>document.querySelector('#ash-manual-feedback').textContent.includes('Everyday Jazz saved'));
 assert.equal(stationRequests.at(-1).url,'https://station.example/jazz.mp3');assert.equal(stationRequests.at(-1).source,'Manual');
 const savedCount=stationRequests.length;await page.locator('#ash-manual-name').fill('Everyday Jazz');await page.locator('#ash-manual-url').fill('https://station.example/jazz.mp3');await page.locator('#ash-manual-form button[value=play]').click();
 await page.waitForFunction(()=>document.querySelector('#ash-manual-feedback').textContent.includes('saved and playing'));
 assert.equal(stationRequests.length,savedCount,'Known URLs are reused for Save & play');assert.equal(controlRequests.at(-1).action,'play');
 await page.locator('#ash-source').selectOption('internet-radio-search');assert.equal(await page.locator('#ash-directory-query').isVisible(),true,'Internet-Radio search stays visible with old assets in browser cache');
 assert.equal(await page.locator('#ash-directory-query-label').textContent(),'Search any music or station');
 await page.locator('#ash-directory-query').fill('classic rock');await page.locator('#ash-directory-query-go').click();await page.waitForSelector('.ash-directory-card');
 assert.equal(directoryRequests.at(-1).source,'internet-radio-search');assert.equal(directoryRequests.at(-1).query,'classic rock');
 await page.locator('[data-tab=saved]').click();
 const out=process.env.UI_SCREENSHOT_DIR||path.join('/tmp','ash-radio-ui');fs.mkdirSync(out,{recursive:true});
 for(const theme of ['light','dark'])for(const width of [1440,390]){
  await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);await page.setViewportSize({width,height:1050});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`No horizontal overflow: ${theme} ${width}`);
  await page.screenshot({path:path.join(out,theme+'-'+width+'.png'),fullPage:true});
 }
 radioStatus.active=true;radioStatus.station='Happy Christmas Radio';state.uid='one';
 await page.goto('http://127.0.0.1:'+server.address().port+'/fpp-status');await page.waitForSelector('#ash-status-radio:not([hidden])');
 assert.equal(await page.locator('#txtPlayerStatus').textContent(),'idle');assert.equal(await page.locator('.ash-status-station').textContent(),'Happy Christmas Radio');
 assert.equal(await page.locator('#ash-status-radio img').count(),0);assert.equal(await page.locator('.ash-status-track').textContent(),radioStatus.track);
 assert.equal(await page.locator('.ash-status-presets button').count(),12);
 assert.equal(await page.locator('[data-radio-action=next]').evaluate(b=>b.classList.contains('btn-rounded') && b.classList.contains('btn-pleasant')),true);
 await page.locator('[data-radio-action=next]').click();await page.waitForFunction(()=>document.querySelector('.ash-status-station').textContent==='Halloweenradio Main');
 assert.equal(controlRequests.at(-1).action,'next');
 await page.locator('[data-radio-action=previous]').click();await page.waitForFunction(()=>document.querySelector('.ash-status-station').textContent==='Happy Christmas Radio');
 assert.equal(controlRequests.at(-1).action,'previous');
 await page.locator('.ash-status-bank').selectOption('christmas');await page.waitForFunction(()=>document.querySelector('.ash-status-presets button[data-slot="1"]').disabled===false);
 assert.equal(await page.locator('.ash-status-presets button[data-slot="12"]').isDisabled(),true);
 await page.locator('.ash-status-presets button[data-slot="1"]').click();await page.waitForFunction(()=>document.querySelector('[data-radio-action=next]').disabled===false);
 assert.equal(controlRequests.at(-1).action,'preset');assert.equal(controlRequests.at(-1).bank,'christmas');assert.equal(controlRequests.at(-1).slot,1);
 await page.locator('.ash-status-bank').selectOption('halloween');await page.waitForFunction(()=>document.querySelector('.ash-status-presets button[data-slot="1"]').title.includes('Halloweenradio Main'));
 await page.locator('.ash-status-presets button[data-slot="1"]').click();await page.waitForFunction(()=>document.querySelector('.ash-status-station').textContent==='Halloweenradio Main');
 assert.equal(controlRequests.at(-1).bank,'halloween');assert.equal(await page.locator('#txtPlayerStatus').textContent(),'idle');

 for(const theme of ['light','dark'])for(const width of [1440,390]){
  await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);await page.setViewportSize({width,height:850});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.join(out,'status-'+theme+'-'+width+'.png'),fullPage:true});
 }
 const statusCases=require('./fixtures/fpp-status.json');
 const silent=statusCases.find(test=>test.label==='static lights-only sequence playlist').status;
 await page.evaluate(status=>window.applyFPPStatus(status),silent);await page.waitForSelector('#ash-status-radio:not([hidden])');
 assert.equal(await page.locator('#native-sequence').textContent(),'christmas static.fseq');
 assert.equal(await page.locator('#txtPlayerStatus').textContent(),'playing','Radio does not replace the native sequence status');
 await page.locator('[data-radio-action=next]').click();await page.waitForFunction(()=>!document.querySelector('[data-radio-action=next]').disabled);
 assert.equal(controlRequests.at(-1).action,'next','Status Next works while a silent sequence runs');
 await page.locator('.ash-status-presets button[data-slot="1"]').click();await page.waitForFunction(()=>!document.querySelector('[data-radio-action=next]').disabled);
 assert.equal(controlRequests.at(-1).action,'preset','Status presets work while a silent sequence runs');
 assert.deepEqual(await page.evaluate(()=>window.lastStatusJSON),silent,'Radio controls leave all native animation status data untouched');
 await page.screenshot({path:path.join(out,'status-silent-sequence.png'),fullPage:true});
 for(const test of statusCases){
  await page.evaluate(status=>window.applyFPPStatus(status),test.status);
  if(test.busy===false)await page.waitForSelector('#ash-status-radio:not([hidden])');
  else assert.equal(await page.locator('#ash-status-radio').isVisible(),false,test.label);
  assert.deepEqual(await page.evaluate(()=>window.lastStatusJSON),test.status,'Native status preserved: '+test.label);
 }
 await page.evaluate(status=>window.applyFPPStatus(status),silent);await page.waitForSelector('#ash-status-radio:not([hidden])');
 await page.locator('[data-radio-action=stop]').click();await page.waitForSelector('#ash-status-radio[hidden]',{state:'attached'});assert.equal(controlRequests.at(-1).action,'stop');
 radioStatus.active=true;const resumed=page.waitForResponse(response=>response.url().includes('/nowPlaying'));await page.reload();await resumed;await page.waitForSelector('#ash-status-radio:not([hidden])');
 const show={status:1,status_name:'playing',current_song:'Actual FPP show song',current_sequence:'show.fseq',current_playlist:{playlist:'Show',type:'both'}};
 await page.evaluate(show=>window.applyFPPStatus(show),show);await page.waitForSelector('#ash-status-radio[hidden]',{state:'attached'});
 assert.equal(await page.locator('#txtPlayerStatus').textContent(),'playing');assert.equal(await page.locator('#native-song').textContent(),show.current_song);
 const busyCalls=statusCalls;await page.waitForTimeout(5300);assert.equal(statusCalls,busyCalls,'No radio polling during an active show');
 statusDelay=800;const pending=page.waitForRequest(request=>request.url().includes('/nowPlaying'));await page.evaluate(()=>window.applyFPPStatus({status:0,status_name:'idle'}));await pending;
 await page.evaluate(show=>window.applyFPPStatus(show),show);await page.waitForTimeout(1000);
 assert.equal(await page.locator('#ash-status-radio').isVisible(),false,'Late radio response cannot appear over a running show');
 assert.deepEqual(await page.evaluate(()=>window.lastStatusJSON),show,'Plugin never overwrites FPP native status JSON');
 radioStatus={active:false};statusDelay=0;const stoppedResponse=page.waitForResponse(response=>response.url().includes('/nowPlaying'));await page.goto('http://127.0.0.1:'+server.address().port+'/fpp-status');await stoppedResponse;
 assert.equal(await page.locator('#ash-status-radio').isVisible(),false,'Stopped radio leaves no status display');
 assert.deepEqual(errors,[]);console.log('PASS UI: stale-cache update regression; preset pages; manual URL Save/Play; everyday Internet-Radio search; Status transport and numbered presets; custom genre search and paging; silent-sequence coexistence and shared status scenarios; show audio priority; stale response guard; stopped radio; light/dark desktop/mobile; no browser errors.');
 await browser.close();server.close();
})().catch(e=>{console.error(e);server.close();process.exit(1)});
