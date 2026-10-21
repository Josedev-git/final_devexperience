'use strict';
(() => {
  const base = document.querySelector('meta[name="base-path"]').content;
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const endpoint = (page) => `${base}/index.php?page=${encodeURIComponent(page)}`;
  async function api(page, values = null, signal) {
    const options = {headers: {Accept: 'application/json'}, signal};
    if (values) {
      options.method = 'POST'; options.headers['X-CSRF-Token'] = token;
      options.body = new URLSearchParams(values);
    }
    const response = await fetch(endpoint(page), options);
    const data = await response.json().catch(() => ({error: 'Resposta inválida do servidor.'}));
    if (!response.ok) throw new Error(data.error || 'Não foi possível concluir.');
    return data;
  }
  const chat = document.getElementById('chat-form');
  if (chat) {
    const panel = document.getElementById('helper-panel');
    const log = document.getElementById('chat-messages');
    const input = document.getElementById('question');
    const feedback = document.getElementById('chat-answer');
    const suggestions = document.getElementById('chat-suggestions');
    const clear = document.getElementById('chat-clear');
    const send = chat.querySelector('[type=submit]');
    const mode = document.getElementById('helper-mode');
    let busy = false, loaded = false, loading = null, greeted = log.firstElementChild.cloneNode(true);
    const scroll = () => { log.scrollTop = log.scrollHeight; };
    function actions(container, items) {
      for (const item of items || []) {
        const target = new URL(item.url, location.origin);
        if (target.origin !== location.origin || !target.pathname.endsWith('/index.php')) continue;
        const a = document.createElement('a'); a.href = target.href; a.textContent = item.label; container.append(a);
      }
    }
    function append(message) {
      const article = document.createElement('article'); article.className = `chat-message ${message.role === 'user' ? 'user' : 'assistant'}`;
      const label = document.createElement('span'); label.className = 'message-label'; label.textContent = message.role === 'user' ? 'VOCÊ' : (message.source === 'local' ? 'SENN LOCAL' : 'SENN HELPER');
      const text = document.createElement('p'); text.textContent = message.text; article.append(label, text);
      if (message.actions?.length) { const links = document.createElement('div'); links.className = 'message-actions'; actions(links, message.actions); article.append(links); }
      log.append(article); while (log.children.length > 17) log.firstElementChild.remove(); scroll(); return article;
    }
    function setSuggestions(items) {
      suggestions.replaceChildren();
      for (const question of items.slice(0, 3)) { const button = document.createElement('button'); button.type = 'button'; button.dataset.question = question; button.textContent = question; button.disabled = busy; suggestions.append(button); }
    }
    function setBusy(value) { busy = value; send.disabled = value; clear.disabled = value; suggestions.querySelectorAll('button').forEach(button => { button.disabled = value; }); log.setAttribute('aria-busy', value ? 'true' : 'false'); }
    function loadHistory() {
      if (loaded) return Promise.resolve();
      if (loading) return loading;
      setBusy(true);
      loading = api('chat-history').then(result => {
        if (result.messages?.length) { log.replaceChildren(); result.messages.forEach(append); }
        mode.textContent = result.mode === 'gemini' ? 'Gemini opcional · fallback local' : 'SENN Local · sem chave de API';
        loaded = true;
      }).catch(() => { feedback.textContent = 'Não foi possível recuperar a conversa. Você pode tentar novamente.'; }).finally(() => { loading = null; setBusy(false); });
      return loading;
    }
    document.addEventListener('click', event => { const button = event.target.closest('[data-helper-question]'); if (!button) return; panel.open = true; loadHistory().then(() => { input.value = button.dataset.helperQuestion; input.dispatchEvent(new Event('input')); if (!busy) chat.requestSubmit(); }); });
    panel.addEventListener('toggle', () => { if (panel.open) { loadHistory().then(scroll); input.focus({preventScroll: true}); } });
    input.addEventListener('input', () => { document.getElementById('chat-count').textContent = `${input.value.length}/500`; });
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); if (!busy) chat.requestSubmit(); } });
    suggestions.addEventListener('click', event => { const button = event.target.closest('[data-question]'); if (!button || busy) return; input.value = button.dataset.question; input.dispatchEvent(new Event('input')); chat.requestSubmit(); });
    clear.addEventListener('click', async () => {
      if (busy) return; setBusy(true);
      try { await api('chat-clear', {request: 'clear'}); log.replaceChildren(greeted.cloneNode(true)); feedback.textContent = 'Conversa limpa.'; input.value = ''; input.dispatchEvent(new Event('input')); setSuggestions(['Como está o desempenho hoje?', 'Quais entregas estão atrasadas?', 'Como planejar uma rota?']); }
      catch (error) { feedback.textContent = error.message; }
      finally { setBusy(false); input.focus(); }
    });
    chat.addEventListener('submit', async event => {
      event.preventDefault(); if (busy) return;
      const question = input.value.trim(); if (!question) return;
      await loadHistory(); if (busy) return;
      setBusy(true); feedback.textContent = '';
      append({role: 'user', text: question}); const pending = append({role: 'assistant', text: 'Consultando os dados da operação…', source: 'local'}); pending.classList.add('pending');
      try {
        const result = await api('chat', {question}); pending.remove(); append({role: 'assistant', ...result});
        mode.textContent = result.source === 'local' ? 'SENN Local · consulta no servidor' : result.source;
        setSuggestions(result.suggestions || ['Como está o desempenho hoje?', 'Quais entregas estão atrasadas?']);
        input.value = ''; input.dispatchEvent(new Event('input'));
      } catch (error) { pending.remove(); feedback.textContent = error.message; }
      finally { setBusy(false); input.focus({preventScroll: true}); scroll(); }
    });
  }
  const analysis = document.getElementById('analysis-button');
  analysis?.addEventListener('click', async () => {
    const output = document.getElementById('analysis-answer'); analysis.disabled = true;
    output.textContent = 'Analisando indicadores…';
    try { const result = await api('analysis', {request: 'analysis', ...(document.querySelector('[name=from]') ? {from:document.querySelector('[name=from]').value,to:document.querySelector('[name=to]').value} : {})}); output.textContent = `${result.text}\n\nFonte: ${result.source}`; }
    catch (error) { output.textContent = error.message; }
    finally { analysis.disabled = false; }
  });
  document.getElementById('print-report')?.addEventListener('click', () => window.print());
  document.querySelectorAll('.failure-form').forEach(form => form.addEventListener('submit', event => {
    if (!confirm('Confirmar falha desta entrega? A ocorrência será registrada.')) event.preventDefault();
  }));
  let watcher = null, lastSent = 0, sending = false;
  const gpsStatus = document.getElementById('gps-status');
  function stopGps() { if (watcher !== null) navigator.geolocation.clearWatch(watcher); watcher = null; if (gpsStatus) gpsStatus.textContent = 'Coleta de localização interrompida.'; }
  document.getElementById('gps-stop')?.addEventListener('click', stopGps);
  window.addEventListener('pagehide', stopGps);
  document.querySelectorAll('.gps-start').forEach(button => button.addEventListener('click', () => {
    if (!navigator.geolocation) { gpsStatus.textContent = 'Geolocalização indisponível neste navegador.'; return; }
    stopGps(); lastSent = 0; gpsStatus.textContent = 'Aguardando permissão e sinal GPS…';
    watcher = navigator.geolocation.watchPosition(async position => {
      if (sending || Date.now() - lastSent < 10000) return;
      if (position.coords.accuracy > 250) { gpsStatus.textContent = 'GPS impreciso (>250 m). Aguardando sinal melhor.'; return; }
      sending = true; lastSent = Date.now();
      try {
        await api('position', {route_id: button.dataset.route, lat: position.coords.latitude, lng: position.coords.longitude});
        gpsStatus.textContent = `Localização enviada às ${new Date().toLocaleTimeString('pt-BR')}. Precisão ±${Math.round(position.coords.accuracy)} m.`;
      } catch (error) { gpsStatus.textContent = error.message; }
      finally { sending = false; }
    }, error => { gpsStatus.textContent = `GPS indisponível: ${error.message}. Verifique HTTPS e permissão.`; }, {enableHighAccuracy: true, maximumAge: 5000, timeout: 15000});
  }));
  document.querySelectorAll('.pod-form').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault(); const button = form.querySelector('button');
    if (!navigator.geolocation) { gpsStatus.textContent = 'GPS é necessário para o comprovante.'; return; }
    button.disabled = true;
    navigator.geolocation.getCurrentPosition(position => {
      if (position.coords.accuracy > 250) { gpsStatus.textContent = 'Precisão GPS insuficiente para o POD. Tente novamente em local aberto.'; button.disabled = false; return; }
      form.elements.lat.value = position.coords.latitude; form.elements.lng.value = position.coords.longitude;
      form.submit();
    }, error => { gpsStatus.textContent = `POD não enviado: ${error.message}`; button.disabled = false; }, {enableHighAccuracy: true, maximumAge: 0, timeout: 20000});
  }));
  const mapElement = document.getElementById('map');
  if (mapElement) {
    let map, layer, initialized = false, tilesFailed = false;
    const status = document.getElementById('tracking-status');
    if (window.L && document.querySelector('meta[name="maps-enabled"]').content === '1') {
      map = L.map(mapElement).setView([-23.0264, -45.5553], 12);
      const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
      tiles.on('tileerror', () => { tilesFailed = true; status.textContent = 'Mapa base indisponível. Consulte coordenadas e status na tabela.'; });
      layer = L.layerGroup().addTo(map);
    } else { mapElement.textContent = 'Mapa indisponível ou desativado. A operação continua pela tabela abaixo.'; }
    const filters = document.getElementById('tracking-filters');
    async function refresh() {
      if (document.hidden) { setTimeout(refresh, 10000); return; }
      try {
        const params = new URLSearchParams(new FormData(filters)); params.delete('page');
        const response = await fetch(`${endpoint('tracking-data')}&${params}`, {headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(8000)});
        const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Falha no rastreamento.');
        const tbody = document.getElementById('tracking-body'); tbody.replaceChildren();
        if (layer) layer.clearLayers();
        const bounds = [], paths = new Map();
        data.deliveries.forEach(d => {
          const tr = document.createElement('tr');
          [d.route_id, d.plate + (d.algorithm?.startsWith('demo-') ? ' · SIMULAÇÃO' : ''), d.reference, d.region, d.status, `${d.lat}, ${d.lng}`].forEach(value => { const td = document.createElement('td'); td.textContent = value; tr.append(td); }); tbody.append(tr);
          if (layer) {
            const point = [Number(d.lat), Number(d.lng)]; bounds.push(point);
            const popup = document.createElement('span'); popup.textContent = `${d.reference}: ${d.status}`;
            L.circleMarker(point, {radius: 6, color: d.status === 'entregue' ? '#0c705a' : '#80611c'}).bindPopup(popup).addTo(layer);
            if (!paths.has(d.route_id)) paths.set(d.route_id, []); paths.get(d.route_id).push(point);
          }
        });
        if (layer) paths.forEach(points => L.polyline(points, {color: '#0c705a', dashArray: '5 8', weight: 2}).addTo(layer));
        const list = document.getElementById('position-list'); list.replaceChildren();
        data.positions.forEach(p => {
          const line = document.createElement('p'); line.textContent = `${p.algorithm?.startsWith('demo-') ? 'SIMULAÇÃO · ' : ''}Rota ${p.route_id} · GPS ${p.lat}, ${p.lng} · ${p.recorded_at}${p.stale ? ' · POSIÇÃO ANTIGA' : ''}`; list.append(line);
          if (layer) {
            const point = [Number(p.lat), Number(p.lng)]; bounds.push(point);
            const popup = document.createElement('span'); popup.textContent = line.textContent;
            L.circleMarker(point, {radius: 10, color: p.stale ? '#777' : '#172b43', fillOpacity: 1}).bindPopup(popup).addTo(layer);
          }
        });
        if (map && !initialized && bounds.length) { map.fitBounds(bounds, {padding: [25, 25], maxZoom: 14}); initialized = true; }
        status.textContent = `${tilesFailed ? 'Mapa base indisponível. ' : ''}Dados atualizados às ${new Date().toLocaleTimeString('pt-BR')}. ${data.positions.length} veículo(s) com posição. Limite: ${data.limit} paradas.`;
      } catch (error) { status.textContent = `Sem atualização: ${error.message} Últimos dados preservados. Isso não é uma posição ao vivo.`; }
      setTimeout(refresh, 10000); // não sobrepõe requisições
    }
    refresh();
  }
})();
