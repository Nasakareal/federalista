@extends('layouts.app')

@section('title','Mapa de Afiliados - México')

@section('content')
<div class="container-fluid p-0 page-map-wrap">
  <div class="page-map-toolbar">
    <div class="toolbar-inner">
      <div style="font-weight:700">México</div>

      {{-- Si quieres permitir cambiar archivo nacional, descomenta este bloque
      <label for="mexicoSelect" class="toolbar-label">GeoJSON:</label>
      <select id="mexicoSelect" class="form-select form-select-sm toolbar-select">
        @foreach(($estadosGeo ?? []) as $e)
          <option value="{{ $e['file'] }}" @selected(($estadoFile ?? '') === $e['file'])>{{ $e['name'] }}</option>
        @endforeach
      </select>
      --}}
    </div>
  </div>

  <div class="page-map-body">
    <div id="map"></div>
  </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
  html, body { height: 100%; margin: 0; padding: 0; }
  :root { --vh: 1vh; }

  .page-map-wrap{
    height: calc(var(--vh) * 100);
    display: flex;
    flex-direction: column;
  }

  .page-map-toolbar{
    flex: 0 0 auto;
    padding: 10px 12px;
  }

  .toolbar-inner{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
  }

  .toolbar-label{ font-weight:700; margin:0; }
  .toolbar-select{ max-width: 360px; }

  .page-map-body{
    flex: 1 1 auto;
    min-height: 0;
  }

  #map{ width:100%; height:100%; }

  @media (max-width: 576px){
    .toolbar-select{ max-width: 100% !important; }

    .leaflet-control-layers{
      max-height: 40vh;
      overflow: auto;
    }

    .info-legend{
      font-size: 11px;
      padding: 6px 8px;
      max-height: 28vh;
      overflow: auto;
    }

    .leaflet-bottom.leaflet-right{
      margin-bottom: 32px;
    }

    .mun-label-text{ font-size: 11px; }
  }

  .leaflet-interactive { cursor: pointer; }

  .info-legend {
    background:#fff;
    padding:8px 10px;
    border-radius:10px;
    box-shadow:0 1px 5px rgba(0,0,0,.25);
    font:14px/1.2 system-ui, sans-serif;
  }
  .info-legend i {
    width:14px;
    height:14px;
    display:inline-block;
    margin-right:6px;
    vertical-align:middle;
  }

  .leaflet-div-icon.mun-label { background: transparent; border: none; }
  .mun-label-text{
    font: 14px/1.1 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    font-weight: 700; color: #111;
    text-shadow: 0 0 3px #fff, 0 0 6px #fff, 0 1px 0 #fff;
    white-space: nowrap; pointer-events: none;
    transform: translate(-50%, -50%) scale(1);
    transform-origin: 50% 50%;
  }

  .legend-wrap{ position: relative; }
  .legend-toggle{
    width: 36px; height: 36px;
    border: 1px solid rgba(0,0,0,.2);
    background:#fff;
    border-radius:10px;
    box-shadow: 0 1px 5px rgba(0,0,0,.25);
    cursor: pointer;
    display:flex; align-items:center; justify-content:center;
    font-size: 18px; line-height: 1;
    user-select:none;
  }
  .legend-panel{ margin-top: 6px; }
  .legend-collapsed .legend-panel{ display:none; }

  @media (min-width: 577px){
    .legend-toggle{ display:none; }
    .legend-panel{ display:block !important; }
  }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

<script>
  function setRealVh(){
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
  }
  setRealVh();
  window.addEventListener('resize', setRealVh);
  window.addEventListener('orientationchange', () => setTimeout(setRealVh, 250));

  const conteoPorCVE    = @json($conteo) || {};          // aquí CVE_ENT (01..32) si tu controller lo manda así
  const conteoPorNombre = @json($conteoPorNombre) || {}; // fallback por nombre normalizado
  const statsCVE        = @json($statsCVE) || {};
  const statsNombre     = @json($statsNombre) || {};

  function normalize(s){
    return (s || '').toString().normalize('NFD')
      .replace(/[\u0300-\u036f]/g,'')
      .replace(/[^A-Z0-9 ]/gi,'')
      .trim().toUpperCase();
  }

  const breaks = [0,5,20,50,100,250,500,1000];
  function getColor(v){
    return v >= breaks[7] ? '#5B0013' :
           v >= breaks[6] ? '#7A001A' :
           v >= breaks[5] ? '#990021' :
           v >= breaks[4] ? '#B80027' :
           v >= breaks[3] ? '#D61A3C' :
           v >= breaks[2] ? '#E34B6A' :
           v >= breaks[1] ? '#F08AA7' : '#F8CBD7';
  }
  function styleFeature(total){
    return { color:'#111', weight:1, fillColor:getColor(total), fillOpacity:0.75, interactive:true };
  }

  const map = L.map('map', {
    zoomControl: true,
    doubleClickZoom: false,
    scrollWheelZoom: true,
    dragging: true,
    tap: true
  });

  setTimeout(() => map.invalidateSize(true), 0);
  window.addEventListener('resize', () => setTimeout(() => map.invalidateSize(true), 50));
  window.addEventListener('orientationchange', () => setTimeout(() => map.invalidateSize(true), 250));

  map.createPane('estadosPane');   map.getPane('estadosPane').style.zIndex  = 650;
  map.createPane('overlaysPane');  map.getPane('overlaysPane').style.zIndex = 700;
  map.createPane('labelsPane');    map.getPane('labelsPane').style.zIndex   = 800;
  map.getPane('labelsPane').style.pointerEvents = 'none';

  const layersControl = L.control.layers(null, {}, {
    collapsed: window.innerWidth < 576
  }).addTo(map);

  const labelsGroup = L.layerGroup().addTo(map);
  layersControl.addOverlay(labelsGroup, 'Nombres de estados');

  const legend = L.control({position:'bottomright'});
  legend.onAdd = function(){
    const div = L.DomUtil.create('div','info-legend legend-wrap legend-collapsed');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'legend-toggle';
    btn.title = 'Mostrar/ocultar leyenda';
    btn.innerHTML = '≡';

    const panel = document.createElement('div');
    panel.className = 'legend-panel';
    panel.innerHTML = '<strong>Total de registros</strong><br>';

    for (let i=0;i<breaks.length;i++){
      const from = breaks[i], to = breaks[i+1];
      const label = to ? (from + '-' + (to-1)) : (from + '+');
      const sampleVal = to ? (to-1) : (from+1);
      panel.innerHTML += '<div><i style="background:' + getColor(sampleVal) + '"></i>' + label + '</div>';
    }
    panel.innerHTML += '<div style="margin-top:6px"><small>* El color usa el total (todos los estatus)</small></div>';

    btn.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      div.classList.toggle('legend-collapsed');
    });

    L.DomEvent.disableClickPropagation(div);
    L.DomEvent.disableScrollPropagation(div);

    div.appendChild(btn);
    div.appendChild(panel);
    return div;
  };
  legend.addTo(map);

  function pickStats(p){
    // intentamos por CVE_ENT primero
    const cveEnt = (p.CVE_ENT ?? p.cve_ent ?? p.CVEENT ?? '').toString().padStart(2,'0');
    if (cveEnt && statsCVE && statsCVE[cveEnt]) return statsCVE[cveEnt];

    // fallback por nombre
    const nom = normalize(p.NOMGEO || p.nombre || p.name || p.ESTADO || p.estado || '');
    if (nom && statsNombre && statsNombre[nom]) return statsNombre[nom];

    return { total:0, afiliados:0, no_afiliados:0, pendientes:0, convencidos:0 };
  }

  const MIN_LABEL_SCALE = 0.2;
  const stateLabels = [];

  function fitLabel(item){
    const el = item.label.getElement();
    if (!el) return;
    const textEl = item.textEl || el.querySelector('.mun-label-text');
    if (!textEl) return;

    const b  = item.layer.getBounds();
    const nw = map.latLngToLayerPoint(b.getNorthWest());
    const se = map.latLngToLayerPoint(b.getSouthEast());
    const polyW = Math.abs(se.x - nw.x), polyH = Math.abs(se.y - nw.y);

    const maxW = polyW * 0.80, maxH = polyH * 0.50;

    textEl.style.transform = 'translate(-50%, -50%) scale(1)';
    const rect = textEl.getBoundingClientRect();
    const w0 = rect.width || 1, h0 = rect.height || 1;

    let scale = Math.min(maxW / w0, maxH / h0, 1);
    if (!isFinite(scale)) scale = 1;
    scale = Math.max(scale, MIN_LABEL_SCALE);

    textEl.style.transform = 'translate(-50%, -50%) scale(' + scale.toFixed(3) + ')';
  }

  function fitAll(){ stateLabels.forEach(fitLabel); }
  map.on('zoomend viewreset', fitAll);

  let capaEstados = null;
  const OVERLAY_NAME = 'Estados (total)';

  const GEO_BASE_URL = "{{ asset('geo/federal') }}";
  const MEXICO_FILE  = "mexico.json"; // tu archivo fijo

  function clearEstados(){
    if (capaEstados) {
      try { map.removeLayer(capaEstados); } catch(e){}
      try { layersControl.removeLayer(capaEstados); } catch(e){}
      capaEstados = null;
    }
    labelsGroup.clearLayers();
    stateLabels.length = 0;
  }

  function loadMexico(){
    clearEstados();

    const url = GEO_BASE_URL + '/' + encodeURIComponent(MEXICO_FILE);

    fetch(url)
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' -> ' + url);
        return r.json();
      })
      .then(function(geo){
        capaEstados = L.geoJSON(geo, {
          pane: 'estadosPane',
          style: f => styleFeature(pickStats((f.properties||{})).total),
          onEachFeature: function(feature, layer){
            const p  = feature.properties || {};
            const st = pickStats(p);

            const cveEnt = (p.CVE_ENT ?? p.cve_ent ?? p.CVEENT ?? '').toString().padStart(2,'0');
            const nombre = p.NOMGEO || p.nombre || p.name || p.ESTADO || p.estado || 'Desconocido';

            layer.on('click', function(){
              const html = `
                <div style="min-width:240px">
                  <h5 style="margin:0 0 6px 0">${nombre}</h5>
                  <div><strong>Afiliados (sí):</strong> ${st.afiliados}</div>
                  <div><strong>No afiliados (no):</strong> ${st.no_afiliados}</div>
                  <div><strong>Convencidos (sí + no):</strong> ${st.convencidos}</div>
                  <div style="margin-top:6px"><small>Total (todos): ${st.total}${st.pendientes ? (' — Pendientes: ' + st.pendientes) : ''}</small></div>
                  ${cveEnt ? `<div><small>CVE_ENT: ${cveEnt}</small></div>` : ``}
                </div>`;
              this.bindPopup(html, { closeButton:true }).openPopup();
              this.setStyle({ weight:3, fillOpacity:1.0 }); this.bringToFront();
            });

            layer.on('mouseover', function(){ this.setStyle({ weight:2, fillOpacity:0.9 }); this.bringToFront(); });
            layer.on('mouseout',  function(){ this.setStyle(styleFeature(st.total)); });

            let latlng;
            try {
              const com = turf.centerOfMass(feature);
              const c   = com?.geometry?.coordinates;
              latlng = (c && c.length>=2) ? [c[1], c[0]] : layer.getBounds().getCenter();
            } catch(_) {
              latlng = layer.getBounds().getCenter();
            }

            const label = L.marker(latlng, {
              pane: 'labelsPane',
              interactive: false, keyboard: false, bubblingMouseEvents: false,
              icon: L.divIcon({ className: 'mun-label', html: `<span class="mun-label-text">${nombre}</span>` })
            }).addTo(labelsGroup);

            const item = { layer, label };
            item.label.on('add', () => {
              item.textEl = item.label.getElement().querySelector('.mun-label-text');
              fitLabel(item);
            });
            stateLabels.push(item);
          }
        }).addTo(map);

        layersControl.addOverlay(capaEstados, OVERLAY_NAME);

        const bounds = capaEstados.getBounds();
        map.fitBounds(bounds);
        map.setMaxBounds(bounds.pad(0.05));

        setTimeout(() => {
          map.invalidateSize(true);
          fitAll();
        }, 50);
      })
      .catch(err => console.error('Error cargando GeoJSON México:', err));
  }

  // Cargar de una vez
  loadMexico();

  // Overlays extra (igual que tu index)
  function pickProp(props, keys){
    for (const k of keys) if (props && props[k] !== undefined && props[k] !== null && props[k] !== '') return props[k];
    return null;
  }
  function baseOverlayStyle(feature){
    const t = feature && feature.geometry && feature.geometry.type || '';
    if (/LineString/i.test(t)) return { weight: 2, color:'#333' };
    if (/Polygon/i.test(t))    return { weight: 1, color:'#333', fill:false };
    return {};
  }

  @foreach ($layers as $l)
    fetch("{{ $l['url'] }}")
      .then(r => r.json())
      .then(geo => {
        const group = L.layerGroup();

        L.geoJSON(geo, {
          pane: 'overlaysPane',
          style: baseOverlayStyle,
          onEachFeature: function(feature, layer){
            layer.on('click', function(e){
              const props = feature.properties || {};
              const html = `<div style="min-width:220px"><pre style="margin:0">${JSON.stringify(props, null, 2)}</pre></div>`;
              L.popup({ closeButton:true }).setLatLng(e.latlng).setContent(html).openOn(map);
              this.setStyle({ weight:3, color:'#000' }); this.bringToFront();
            });
            layer.on('mouseover', function(){ this.setStyle({ weight:2 }); this.bringToFront(); });
            layer.on('mouseout',  function(){ this.setStyle(baseOverlayStyle(feature)); });
          }
        }).addTo(group);

        layersControl.addOverlay(group, "{{ $l['name'] }}");
        map.on('overlayadd', e => {
          if (e.layer === group) setTimeout(() => { map.invalidateSize(true); }, 0);
        });

        setTimeout(() => map.invalidateSize(true), 50);
      })
      .catch(err => console.error('Error capa {{ $l['name'] }}:', err));
  @endforeach
</script>
@endpush
