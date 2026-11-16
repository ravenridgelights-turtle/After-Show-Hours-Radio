(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('ash-radio');
    if (!root) return;
    const $ = id => document.getElementById('ash-' + id);
    const api = root.dataset.api;
    let snapshot, configHash = '', presetsHash = '', listHash = '', editingPresets = false;
    let loading = false, timer, bankAction, directory = [], directorySource = '', directoryPage = 1, directoryIdentity = '';
    let stationBusy = false, lastSleepUntil = null, tuneBusy = false;
    const node = (tag, text, className) => {
      const el = document.createElement(tag);
      if (text !== undefined) el.textContent = text;
      if (className) el.className = className;
      return el;
    };
    const button = (text, fn, className, title) => {
      const b = node('button', text, className); b.type = 'button';
      if (title) b.title = title;
      b.addEventListener('click', fn); return b;
    };
    const option = (value, text) => { const o = node('option', text); o.value = value; return o; };
    function notice(message, error = false) {
      $('notice').textContent = message; $('notice').hidden = !message;
      $('notice').classList.toggle('ash-error', error);
    }
    async function request(path, data) {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), path === 'control' ? 20000 : 18000);
      try {
        const r = await fetch(api + path, {
          method: data === undefined ? 'GET' : 'POST', cache: 'no-store', credentials: 'same-origin',
          headers: data === undefined ? {} : { 'Content-Type': 'application/json' },
          body: data === undefined ? undefined : JSON.stringify(data), signal: controller.signal
        });
        if (!r.ok) throw new Error(`FPP returned HTTP ${r.status}.`);
        const result = await r.json();
        if (!result.status) throw new Error(result.error || 'Radio request failed.');
        return result.data;
      } catch (e) {
        if (e.name === 'AbortError') throw new Error('The request timed out. Refresh the radio status before trying again.');
        throw e;
      } finally { clearTimeout(timeout); }
    }
    async function refresh() {
      if (loading || document.hidden) return;
      loading = true;
      try { snapshot = await request('details'); render(); }
      catch (e) { $('service').textContent = 'Connection lost'; notice(e.message, true); }
      finally {
        loading = false; clearTimeout(timer);
        timer = setTimeout(refresh, snapshot?.state?.wanted ? 5000 : 15000);
      }
    }
    async function mutation(path, data, message) {
      const result = await request(path, data); if (message) notice(message);
      // Mutations should show their new config even if a status poll is already finishing.
      const fresh = await request('details'); snapshot = fresh; render(); return result;
    }
    async function control(action, extra = {}) {
      const isSeek = action.startsWith('seek');
      const tuning = !['stop','volume','mute','sleep'].includes(action);
      if (tuning && tuneBusy) return;
      if (tuning) tuneBusy = true;
      if (isSeek) notice('Seeking… the current station keeps playing while up to five saved stations are checked.');
      try { await mutation('control', { action, ...extra }, action === 'stop' ? 'Radio stopped.' : isSeek ? 'Station found.' : ''); }
      catch (e) { notice(e.message, true); await refresh(); }
      finally { if (tuning) tuneBusy = false; }
    }
    function currentStation() { return snapshot?.config.stations.find(s => s.uid === snapshot.state.uid); }
    function render() {
      if (!snapshot) return;
      const { config, state, audio, error } = snapshot;
      const playing = audio.player.state === 'play';
      const station = currentStation();
      root.classList.toggle('ash-playing', playing);
      $('service').textContent = error ? 'Audio service unavailable' : playing ? 'On air' : 'Ready';
      $('live').textContent = playing ? 'NOW PLAYING' : state.wanted ? 'CONNECTING / RETRYING' : 'RADIO OFF';
      $('station-name').textContent = station?.name || 'Choose your soundtrack.';
      $('track').textContent = error || audio.player.error || (playing ? (audio.song.Title || audio.song.Name || 'Live internet radio') : state.wanted ? 'Connecting to the stream. Recovery checks run about once a minute.' : 'Pick a preset or play a saved station.');
      $('codec').textContent = playing && audio.player.bitrate ? `${audio.player.bitrate} kbps` : '';
      $('mute').textContent = state.muted ? 'Unmute' : 'Mute';
      $('mute').setAttribute('aria-pressed', String(!!state.muted));
      if (document.activeElement !== $('volume')) {
        $('volume').value = state.volume; $('volume-label').textContent = `${state.volume}%`;
      }
      if (lastSleepUntil !== state.sleepUntil) {
        lastSleepUntil = state.sleepUntil;
        $('sleep-note').textContent = state.sleepUntil ? `Sleep timer: stops around ${new Date(state.sleepUntil * 1000).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'})}.` : '';
        if (!state.sleepUntil) $('sleep').value = '0';
      }
      const hash = JSON.stringify(config);
      if (hash !== configHash) {
        configHash = hash;
        $('bank').replaceChildren(...Object.entries(config.banks).map(([id, b]) => option(id, b.name)));
        $('bank').value = config.activeBank;
        $('count').textContent = config.stations.length;
        // Do not overwrite a settings form while someone is editing it.
        if (!$('settings-form').contains(document.activeElement)) {
          $('protect').checked = config.settings.protectShow; $('recover').checked = config.settings.autoRecover;
          $('default-volume').value = config.settings.defaultVolume;
        }
      }
      renderPresets(); renderStations();
    }
    function renderPresets() {
      if (!snapshot) return;
      const {config, state} = snapshot;
      const bank = config.banks[config.activeBank];
      const hash = JSON.stringify([bank, config.stations, state.uid, state.wanted, editingPresets]);
      if (hash === presetsHash) return; presetsHash = hash;
      const buttons = [];
      for (let i = 1; i <= 12; i++) {
        const uid = bank.presets[i]; const station = config.stations.find(s => s.uid === uid);
        const b = button('', () => {
          if (editingPresets || !station) openPreset(i);
          else control('preset', { slot: i, bank: config.activeBank });
        }, 'ash-preset' + (!station ? ' ash-empty' : '') + (station && state.wanted && uid === state.uid ? ' ash-current' : ''));
        b.setAttribute('aria-label', `${bank.name} preset ${i}: ${station?.name || 'Empty, assign station'}`);
        b.append(node('span', String(i).padStart(2, '0'), 'ash-preset-number'), node('span', station?.name || '+ Assign station', 'ash-preset-name'));
        if (station && state.wanted && uid === state.uid) b.setAttribute('aria-current', 'true');
        buttons.push(b);
      }
      $('preset-grid').replaceChildren(...buttons);
      $('preset-edit').setAttribute('aria-pressed', String(editingPresets));
      $('preset-edit').textContent = editingPresets ? 'Done editing' : 'Edit presets';
      $('preset-hint').textContent = editingPresets ? 'Choose any preset to assign a station or clear its button.' : 'Tap a preset to tune in. Use Edit presets to assign or clear buttons.';
    }
    function renderStations() {
      if (!snapshot) return;
      const { config, state } = snapshot;
      const query = $('search').value.toLowerCase(); const onlyFavorites = $('only-favorites').checked;
      const hash = JSON.stringify([config.stations, state.uid, state.wanted, query, onlyFavorites]);
      if (hash === listHash) return; listHash = hash;
      const rows = [];
      config.stations.forEach((s, index) => {
        if (onlyFavorites && !s.favorite) return;
        if (query && !`${s.name} ${s.category} ${s.url}`.toLowerCase().includes(query)) return;
        const row = node('div', undefined, 'ash-saved-row' + (state.wanted && s.uid === state.uid ? ' ash-current' : ''));
        const play = button('▶ Play', () => control('play', {uid:s.uid}), 'ash-primary');
        const text = node('div', undefined, 'ash-station-copy');
        text.append(node('strong', s.name), node('span', s.url, 'ash-url'), node('div', [s.category, s.volume === null ? 'Current volume' : `${s.volume}% volume`].filter(Boolean).join(' · '), 'ash-meta'));
        text.querySelector('.ash-url').title = s.url;
        const actions = node('div', undefined, 'ash-row-actions');
        const enabled = node('label', undefined, 'ash-enabled'); const checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.checked = s.enabled;
        checkbox.setAttribute('aria-label', 'Include ' + s.name + ' in station tuning');
        checkbox.addEventListener('change', async () => {
          try { await mutation('station', {uid:s.uid, enabled:checkbox.checked}); } catch (e) { checkbox.checked = !checkbox.checked; notice(e.message,true); }
        }); enabled.append(checkbox, document.createTextNode('Enabled'));
        const favorite = button(s.favorite ? '★' : '☆', async () => {
          try { await mutation('station', {uid:s.uid, favorite:!s.favorite}); } catch (e) { notice(e.message,true); }
        }, '', s.favorite ? 'Remove favorite' : 'Mark favorite');
        favorite.setAttribute('aria-label', (s.favorite ? 'Unfavorite ' : 'Favorite ') + s.name);
        const up = button('↑', () => reorder(index,-1), '', 'Move station up');
        const down = button('↓', () => reorder(index,1), '', 'Move station down');
        up.disabled = index === 0; down.disabled = index === config.stations.length - 1;
        actions.append(enabled, favorite, button('Edit', () => openStation(s)), up, down, button('Remove', async () => {
          if (await confirmDialog('Remove station?', `${s.name} will be removed from saved stations and all preset pages.`)) {
            try { await mutation('station', {action:'delete',uid:s.uid},'Station removed.'); } catch (e) { notice(e.message,true); }
          }
        }));
        row.append(play,text,actions);rows.push(row);
      });
      $('saved-list').replaceChildren(...rows);
      if (!rows.length) $('saved-list').append(node('p', config.stations.length ? 'No stations match this filter.' : 'Your radio is ready. Find stations or add your own stream to get started.', 'ash-hint'));
    }
    async function reorder(index, delta) {
      const ids = snapshot.config.stations.map(s => s.uid); [ids[index],ids[index+delta]] = [ids[index+delta],ids[index]];
      try { await mutation('order',{uids:ids}); } catch (e) { notice(e.message,true); }
    }
    function openStation(station) {
      $('station-error').textContent = ''; $('station-dialog-title').textContent = station ? 'Edit station' : 'Add station';
      $('edit-uid').value = station?.uid || ''; $('edit-name').value = station?.name || ''; $('edit-url').value = station?.url || '';
      $('edit-category').value = station?.category || ''; $('edit-volume').value = station?.volume ?? '';
      $('edit-enabled').checked = station?.enabled ?? true; $('edit-favorite').checked = station?.favorite ?? false;
      $('station-dialog').showModal();
    }
    function openPreset(slot) {
      if (!snapshot) return;
      const c = snapshot.config;const bank = c.banks[c.activeBank];
      $('slot').value = slot; $('preset-error').textContent = '';
      $('preset-dialog-title').textContent = `${bank.name} · Preset ${slot}`;
      $('preset-station').replaceChildren(option('', 'Empty — clear this preset'), ...c.stations.map(s => option(s.uid,s.name)));
      $('preset-station').value = bank.presets[slot] || ''; $('preset-dialog').showModal();
    }
    function openBank(action) {
      if (!snapshot) return; bankAction = action;
      $('bank-error').textContent = ''; $('bank-dialog-title').textContent = action === 'create' ? 'New preset page' : 'Rename preset page';
      $('bank-name').value = action === 'create' ? '' : snapshot.config.banks[snapshot.config.activeBank].name; $('bank-dialog').showModal();
    }
    function confirmDialog(title, message) {
      const d = $('confirm-dialog'); $('confirm-title').textContent = title; $('confirm-message').textContent = message; d.returnValue = '';
      return new Promise(resolve => { d.addEventListener('close', () => resolve(d.returnValue === 'ok'), {once:true}); d.showModal(); });
    }
    async function loadDirectory(force = false, more = false) {
      if (stationBusy) return; stationBusy = true;
      $('directory-load').disabled = $('directory-refresh').disabled = true;
      $('directory-status').textContent = 'Loading one directory page…';
      try {
        const source = $('source').value;
        const query = ['radiobrowser-search','internet-radio-genre'].includes(source) ? $('directory-query').value.trim() : '';
        const searchBy = $('directory-by').value; const identity = JSON.stringify([source,query,searchBy]);
        const append = more && identity === directoryIdentity; const page = append ? directoryPage + 1 : 1;
        const data = await request('directory', {source, refresh:force, page,query,searchBy});
        const currentQuery = ['radiobrowser-search','internet-radio-genre'].includes($('source').value) ? $('directory-query').value.trim() : '';
        if ($('source').value!==source || currentQuery!==query || $('directory-by').value!==searchBy) return;
        const all = append ? directory.concat(data.stations) : data.stations;
        directory = [...new Map(all.map(station => [station.url,station])).values()];
        directorySource = source; directoryPage = data.page; directoryIdentity = identity;
        $('directory-status').textContent = [data.warning, data.bundled ? 'Showing bundled starter links; live availability has not been checked.' : data.fetchedAt ? `${data.cached ? 'Cached' : 'Updated'} ${new Date(data.fetchedAt*1000).toLocaleString()}.` : 'No cached results for this source.', `${directory.length} links loaded.`].filter(Boolean).join(' ');
        $('directory-more').hidden = !data.hasMore; $('directory-search').hidden = false; renderDirectory();
      } catch (e) { $('directory-status').textContent = e.message; }
      finally { stationBusy = false; $('directory-load').disabled = $('directory-refresh').disabled = false; }
    }
    function renderDirectory() {
      const query = $('directory-search').value.toLowerCase(); const cards = [];
      directory.forEach((s, index) => {
        if (query && !`${s.name} ${s.url} ${s.country || ''} ${s.codec || ''}`.toLowerCase().includes(query)) return;
        const card = node('div', undefined, 'ash-directory-card');const url = node('span', s.url, 'ash-url');url.title=s.url;
        card.append(node('strong', s.name), url);
        const meta = [s.country,s.codec,s.bitrate ? s.bitrate+' kbps' : ''].filter(Boolean).join(' · ');
        if (meta) card.append(node('span',meta,'ash-meta'));
        const actions = node('div', undefined, 'ash-toolbar');
        if (s.detail) actions.append(button('Find stream links', async event => {
          const b=event.currentTarget; b.disabled=true;
          try { const r=await request('resolve',{source:directorySource,url:s.url});directory.splice(index,1,...r.stations);renderDirectory(); }
          catch(e) { notice(e.message,true);b.disabled=false; }
        }));
        else {
          const save = async play => {
            const existing=snapshot.config.stations.find(old=>old.url===s.url);
            try {
              const station = existing || await mutation('station',{name:s.name,url:s.url,category:s.category,source:directorySource,enabled:true,volume:null},'Station saved.');
              if (play) await control('play',{uid:station.uid});else notice(existing ? 'This station is already saved.' : 'Station saved.');
            } catch(e) {notice(e.message,true);}
          };
          actions.append(button('Save station',()=>save(false)),button('▶ Play',()=>save(true),'ash-primary'));
        }
        card.append(actions);cards.push(card);
      });
      $('directory-list').replaceChildren(...cards);
      if (!cards.length) $('directory-list').append(node('p','No station links to display. Choose another source or add a stream manually.','ash-hint'));
    }
    root.querySelectorAll('[data-control]').forEach(b => b.addEventListener('click', () => control(b.dataset.control)));
    root.querySelectorAll('[data-close-dialog]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
    root.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => {
      root.querySelectorAll('[data-tab]').forEach(t => { t.classList.toggle('ash-selected',t===b);t.setAttribute('aria-pressed',String(t===b));$('tab-'+t.dataset.tab).hidden=t!==b; });
    }));
    $('volume').addEventListener('input', e => { $('volume-label').textContent = e.target.value+'%'; });
    $('volume').addEventListener('change', e => control('volume',{value:Number(e.target.value)}));
    $('sleep').addEventListener('change',e=>control('sleep',{minutes:Number(e.target.value)}));
    $('search').addEventListener('input',renderStations);$('only-favorites').addEventListener('change',renderStations);
    $('add').addEventListener('click',()=>openStation());
    $('preset-edit').addEventListener('click',()=>{editingPresets=!editingPresets;renderPresets();});
    $('bank-add').addEventListener('click',()=>openBank('create'));$('bank-rename').addEventListener('click',()=>openBank('rename'));
    $('bank').addEventListener('change',async e=>{try{await mutation('bank',{action:'select',bank:e.target.value});}catch(err){notice(err.message,true);}});
    $('bank-delete').addEventListener('click',async()=>{
      if (!snapshot) return; const id=snapshot.config.activeBank;
      if(await confirmDialog('Remove preset page?', `Remove ${snapshot.config.banks[id].name} and its 12 preset assignments? Saved stations are kept.`))try{await mutation('bank',{action:'delete',bank:id},'Preset page removed.');}catch(e){notice(e.message,true);}
    });
    $('station-form').addEventListener('submit',async e=>{
      e.preventDefault(); const b=e.submitter;b.disabled=true;
      try{await mutation('station',{uid:$('edit-uid').value,name:$('edit-name').value,url:$('edit-url').value,category:$('edit-category').value,volume:$('edit-volume').value,enabled:$('edit-enabled').checked,favorite:$('edit-favorite').checked},'Station saved.');$('station-dialog').close();}
      catch(err){$('station-error').textContent=err.message;}finally{b.disabled=false;}
    });
    $('preset-form').addEventListener('submit',async e=>{
      e.preventDefault();const b=e.submitter;b.disabled=true;
      try{await mutation('preset',{bank:snapshot.config.activeBank,slot:Number($('slot').value),uid:$('preset-station').value},'Preset saved.');$('preset-dialog').close();}
      catch(err){$('preset-error').textContent=err.message;}finally{b.disabled=false;}
    });
    $('bank-form').addEventListener('submit',async e=>{
      e.preventDefault();const b=e.submitter;b.disabled=true;
      try{await mutation('bank',{action:bankAction,bank:snapshot.config.activeBank,name:$('bank-name').value},'Preset page saved.');$('bank-dialog').close();}
      catch(err){$('bank-error').textContent=err.message;}finally{b.disabled=false;}
    });
    $('settings-form').addEventListener('submit',async e=>{
      e.preventDefault();try{await mutation('settings',{protectShow:$('protect').checked,autoRecover:$('recover').checked,defaultVolume:Number($('default-volume').value)},'Preferences saved.');}catch(err){notice(err.message,true);}
    });
    function updateSourceControls() {
      const source=$('source').value; const custom=['radiobrowser-search','internet-radio-genre'].includes(source);
      $('directory-query-controls').hidden=!custom; $('directory-by-label').hidden=source!=='radiobrowser-search';
      $('directory-query-label').textContent=source==='internet-radio-genre' ? 'Genre' : 'Search';
      $('directory-query').placeholder=source==='internet-radio-genre' ? 'jazz, oldies, country, classic rock…' : 'Enter a station name or genre/tag';
      $('directory-more').hidden=true;
    }
    $('source').addEventListener('change',()=>{updateSourceControls();$('directory-list').replaceChildren();$('directory-search').hidden=true;$('directory-status').textContent='Choose Load stations to browse this source.';});
    $('directory-query-go').addEventListener('click',()=>loadDirectory());
    $('directory-query').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();loadDirectory();}});
    updateSourceControls();
    $('directory-load').addEventListener('click',()=>loadDirectory());$('directory-refresh').addEventListener('click',()=>loadDirectory(true));$('directory-more').addEventListener('click',()=>loadDirectory(false,true));
    $('directory-search').addEventListener('input',renderDirectory);
    $('export').addEventListener('click',()=>{
      if(!snapshot)return;const url=URL.createObjectURL(new Blob([JSON.stringify(snapshot.config,null,2)],{type:'application/json'}));
      const a=node('a');a.href=url;a.download='After-Show-Hours-Radio-backup.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);notice('Radio backup exported.');
    });
    $('import').addEventListener('change',async e=>{
      const f=e.target.files[0];if(!f)return;
      try{if(f.size>1048576)throw new Error('Backup must be smaller than 1 MB.');const data=JSON.parse(await f.text());const r=await mutation('import',data);notice(`Imported ${r.added} stations and merged preset pages.`);}catch(err){notice(err.message,true);}finally{e.target.value='';}
    });
    $('clear-cache').addEventListener('click',async()=>{try{await request('clearCache',{});notice('Directory cache cleared. Request cooldowns still apply.');}catch(e){notice(e.message,true);}});
    document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(!document.hidden)refresh();});
    window.addEventListener('pagehide',()=>clearTimeout(timer));
    refresh();
  });
})();
