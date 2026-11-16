// Run with Playwright installed: NODE_PATH=/path/to/node_modules node tests/ui-smoke.cjs
const { chromium } = require('playwright');
const http = require('node:http'), fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const repo = path.resolve(__dirname,'..');
let config={stations:[{uid:'one',name:'Happy Christmas Radio',url:'https://radio.example/christmas.mp3',volume:70,enabled:true,favorite:true,category:'Christmas'},{uid:'two',name:'Halloweenradio Main',url:'https://radio.example/halloween.mp3',volume:null,enabled:true,favorite:false,category:'Halloween'}],activeBank:'christmas',banks:{christmas:{name:'Christmas',presets:{1:'one'}},halloween:{name:'Halloween',presets:{1:'two'}},everyday:{name:'Everyday',presets:{}}},settings:{defaultVolume:70,protectShow:true,autoRecover:true}};
let state={uid:'one',wanted:false,volume:70,muted:false}, play='stop';
const fixture = () => ({status:true,data:{config,state,audio:{player:{state:play,bitrate:'128'},song:{Title:'Example artist — Example song'}},error:null}});
const server=http.createServer(async(req,res)=>{
 if(req.url.startsWith('/api/')){
  let text='';for await(const c of req)text+=c;const data=text?JSON.parse(text):{};const endpoint=req.url.split('/').pop();let result={};
  if(endpoint==='details'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(fixture()));}
  if(endpoint==='bank'){
   if(data.action==='create'){config.banks.custom={name:data.name,presets:{}};config.activeBank='custom';}
   if(data.action==='rename')config.banks[data.bank].name=data.name;
   if(data.action==='select')config.activeBank=data.bank;
  }
  if(endpoint==='preset')config.banks[data.bank].presets[data.slot]=data.uid;
  if(endpoint==='control'){
   if(data.action==='stop'){state.wanted=false;play='stop';}
   else if(data.action==='volume')state.volume=data.value;
   else if(data.action==='mute')state.muted=!state.muted;
   else {state.wanted=true;play='play';if(data.uid)state.uid=data.uid;if(data.action==='preset')state.uid=config.banks[data.bank].presets[data.slot];if(data.action==='next')state.uid='two';}
  }
  if(endpoint==='directory')result={stations:[{name:'Directory Fixture',url:'https://radio.example/finder.mp3',category:'Christmas',detail:false}],page:1,hasMore:false,bundled:false,cached:true,fetchedAt:Math.floor(Date.now()/1000)};
  res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({status:true,data:result}));
 }
 if(req.url==='/'){
  res.setHeader('Content-Type','text/html');
  return res.end('<!doctype html><html lang="en" data-bs-theme="light"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{--bs-body-bg:#f8f9fa;--bs-body-color:#212529;--fpp-bg-card:#fff;--fpp-border:#dee2e6;--fpp-primary:#0d6efd;--fpp-text-muted:#6c757d;--bs-white:#fff}html[data-bs-theme="dark"]{color-scheme:dark;--bs-body-bg:#212529;--bs-body-color:#dee2e6;--fpp-bg-card:#2c3034;--fpp-border:#495057;--fpp-text-muted:#a1a1a1}body{margin:0;background:var(--bs-body-bg);font-family:system-ui,sans-serif;color:var(--bs-body-color)}button,input,select{font:inherit}</style><link rel="stylesheet" href="/css/radio.css"><script src="/js/radio.js"></script></head><body>'+fs.readFileSync(path.join(repo,'after-show-hours-radio.php'),'utf8')+'</body></html>');
 }
 const file=path.join(repo,req.url);res.setHeader('Content-Type',req.url.endsWith('.css')?'text/css':'text/javascript');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=await browser.newPage({viewport:{width:1440,height:1050}}), errors=[];
 page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);
 await page.waitForSelector('.ash-preset');assert.equal(await page.locator('.ash-preset').count(),12);
 await page.locator('#ash-bank-add').click();await page.locator('#ash-bank-name').fill('New Year Party');await page.locator('#ash-bank-form button[type=submit]').click();
 await page.waitForFunction(()=>document.querySelector('#ash-bank').selectedOptions[0].textContent==='New Year Party');
 await page.locator('.ash-preset').nth(11).click();await page.locator('#ash-preset-station').selectOption('two');await page.locator('#ash-preset-form button[type=submit]').click();await page.waitForSelector('#ash-preset-dialog:not([open])',{state:'attached'});
 await page.locator('.ash-preset').nth(11).click();await page.waitForFunction(()=>document.querySelector('#ash-station-name').textContent==='Halloweenradio Main');
 await page.locator('[data-control=stop]').click();await page.waitForFunction(()=>document.querySelector('#ash-live').textContent==='RADIO OFF');
 await page.locator('[data-tab=finder]').click();await page.locator('#ash-directory-load').click();await page.waitForSelector('.ash-directory-card');
 assert.equal(await page.locator('.ash-directory-card strong').textContent(),'Directory Fixture');
 await page.locator('[data-tab=saved]').click();
 const out=process.env.UI_SCREENSHOT_DIR||path.join('/tmp','ash-radio-ui');fs.mkdirSync(out,{recursive:true});
 for(const theme of ['light','dark'])for(const width of [1440,390]){
  await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);await page.setViewportSize({width,height:1050});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`No horizontal overflow: ${theme} ${width}`);
  await page.screenshot({path:path.join(out,theme+'-'+width+'.png'),fullPage:true});
 }
 assert.deepEqual(errors,[]);console.log('PASS UI: 12 presets per page; create page; assign/play preset; Stop; directory; light/dark desktop/mobile; no browser errors.');
 await browser.close();server.close();
})().catch(e=>{console.error(e);server.close();process.exit(1)});
