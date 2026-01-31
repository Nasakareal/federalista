@extends('layouts.app')

@section('title','Mapa de Afiliados')

@section('content')
<div class="container-fluid p-0">
  <div id="map"></div>

  {{-- Conserva estatus si lo usas --}}
  <input type="hidden" id="estatusValue" value="{{ $estatus ?? 'validado' }}">
  {{-- Guardamos el estado seleccionado (para inicializar el control flotante) --}}
  <input type="hidden" id="estadoSelected" value="{{ $estado ?? '' }}">
</div>
@endsection

@push('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
  html, body { margin:0; padding:0; height:100%; overflow:hidden; }

  /* Mapa full pantalla (móvil friendly) */
  #map{
    width: 100%;
    height: 100vh;
    height: 100svh; /* iOS Safari */
    height: 100dvh; /* viewport dinámico */
  }

  .leaflet-interactive { cursor: pointer; }

  .info-legend {
    background:#fff;
    padding:8px 10px;
    border-radius:6px;
    box-shadow:0 1px 5px rgba(0,0,0,.3);
    font:14px/1.2 system-ui, sans-serif;
  }
  .info-legend i {
    width:14px; height:14px; display:inline-block;
    margin-right:6px; vertical-align:middle;
  }

  /* Labels */
  .leaflet-div-icon.mun-label { background: transparent; border: none; }
  .mun-label-text{
    font: 14px/1.1 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    font-weight: 700; color: #111;
    text-shadow: 0 0 3px #fff, 0 0 6px #fff, 0 1px 0 #fff;
    white-space: nowrap; pointer-events: none;
    transform: translate(-50%, -50%) scale(1);
    transform-origin: 50% 50%;
  }

  .map-label { pointer-events: none; }
  .map-label-text{
    display:inline-block;
    font: 600 12px/1 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    color:#fff;
    text-shadow:0 1px 2px rgba(0,0,0,.7);
    background: rgba(0,0,0,.25);
    padding:2px 6px;
    border-radius:4px;
    white-space:nowrap;
    transform: translate(-50%, -50%) scale(1);
    transform-origin: 50% 50%;
  }

  /* Control flotante del selector */
  .estado-control{
    background:#fff;
    border-radius:10px;
    box-shadow: 0 6px 22px rgba(0,0,0,.18);
    padding:10px;
    min-width: 220px;
    max-width: min(92vw, 360px);
  }
  .estado-control .title{
    font: 700 13px/1.1 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    margin:0 0 6px 0;
  }
  .estado-control select{
    width:100%;
    font: 600 13px/1.2 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    padding:8px 10px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,.15);
    outline:none;
    background:#fff;
  }

  /* Layers control: que no tape todo en móvil */
  .leaflet-control-layers{
    border-radius:10px;
    box-shadow: 0 6px 22px rgba(0,0,0,.18);
  }

  /* iPhone notch safe area */
  .leaflet-top.leaflet-left { padding-top: env(safe-area-inset-top); }
  .leaflet-top.leaflet-right{ padding-top: env(safe-area-inset-top); }

  @media (max-width: 768px){
    .info-legend{ font-size:12px; }
    .leaflet-control-layers-expanded{
      max-height: 40vh;
      overflow:auto;
    }
  }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

<script>
  const conteoPorCVE    = @json($conteo) || {};
  const conteoPorNombre = @json($conteoPorNombre) || {};
  const statsCVE        = @json($statsCVE) || {};
  const statsNombre     = @json($statsNombre) || {};

  // Lista de geojson de estados que te mandó el controller
  const estadosLayers = @json($states) || [];

  function normalize(s){
    return (s || '').toString()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
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

  const isMobile = window.matchMedia('(max-width: 768px)').matches;

  const map = L.map('map', {
    zoomControl:true,
    doubleClickZoom:false,
    scrollWheelZoom:true,
    dragging:true,
    tap:true
  });

  map.createPane('municipiosPane');  map.getPane('municipiosPane').style.zIndex = 650;
  map.createPane('overlaysPane');    map.getPane('overlaysPane').style.zIndex   = 700;
  map.createPane('labelsPane');      map.getPane('labelsPane').style.zIndex     = 800;
  map.getPane('labelsPane').style.pointerEvents = 'none';

  // Control de capas: colapsado en móvil
  const layersControl = L.control.layers(null, {}, { collapsed: isMobile }).addTo(map);

  const labelsGroup = L.layerGroup().addTo(map);
  layersControl.addOverlay(labelsGroup, 'Nombres de municipios');

  (function(){
    const s = document.createElement('style');
    s.innerHTML = '#map{position:relative}.leaflet-overlay-pane svg path,.leaflet-interactive{pointer-events:auto !important}.municipio{cursor:pointer}';
    document.head.appendChild(s);
  })();

  const legend = L.control({position:'bottomright'});
  legend.onAdd = function(){
    const div = L.DomUtil.create('div','info-legend');
    div.innerHTML = '<strong>Total de registros</strong><br>';
    for (let i=0;i<breaks.length;i++){
      const from = breaks[i], to = breaks[i+1];
      const label = to ? (from + '-' + (to-1)) : (from + '+');
      const sampleVal = to ? (to-1) : (from+1);
      div.innerHTML += '<div><i style="background:' + getColor(sampleVal) + '"></i>' + label + '</div>';
    }
    div.innerHTML += '<div style="margin-top:6px"><small>* El color usa el total (todos los estatus)</small></div>';
    return div;
  };
  legend.addTo(map);

  function pickStats(p){
    const cve = (p.CVEGEO || (String(p.CVE_ENT||'') + String(p.CVE_MUN||''))).toString();
    if (statsCVE && statsCVE[cve]) return statsCVE[cve];
    const nomN = normalize(p.NOMGEO || '');
    if (statsNombre && statsNombre[nomN]) return statsNombre[nomN];
    return { total:0, afiliados:0, no_afiliados:0, pendientes:0, convencidos:0 };
  }

  const MIN_LABEL_SCALE = 0.2;
  const munLabels = [];

  function fitMunicipio(item){
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
  function fitMunicipios(){ munLabels.forEach(fitMunicipio); }
  map.on('zoomend viewreset', fitMunicipios);

  // ===========
  // Selector de estado como CONTROL dentro del mapa (para que no tape)
  // ===========
  const EstadoControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function(){
      const div = L.DomUtil.create('div','estado-control');

      // Evita que al tocar el control se mueva el mapa
      L.DomEvent.disableClickPropagation(div);
      L.DomEvent.disableScrollPropagation(div);

      const selectedFromServer = document.getElementById('estadoSelected').value || '';

      const opts = [
        `<option value="">Todos (sin filtro)</option>`,
        ...estadosLayers.map(l => {
          const name = (l && l.name) ? String(l.name) : '';
          const sel = (selectedFromServer === name) ? 'selected' : '';
          return `<option value="${name.replace(/"/g,'&quot;')}" ${sel}>${name}</option>`;
        })
      ].join('');

      div.innerHTML = `
        <div class="title">Estado</div>
        <select id="estadoSelect">${opts}</select>
      `;

      return div;
    }
  });
  map.addControl(new EstadoControl());

  // ===========
  // MUNICIPIOS (por estado)
  // ===========
  let capaMunicipios = null;

  function getEstadoURLSeleccionado(){
    const sel = document.getElementById('estadoSelect');
    const estadoName = sel ? (sel.value || '') : '';
    if (!estadoName) return null;

    const normSel = normalize(estadoName);

    // tus items vienen como {id,name,url,norm}
    const found = estadosLayers.find(x => normalize(x.name) === normSel || (x.norm && x.norm === normSel));
    if (found && found.url) return found.url;

    const found2 = estadosLayers.find(x => (x.name || '') === estadoName);
    return found2 ? found2.url : null;
  }

  function limpiarMunicipios(){
    labelsGroup.clearLayers();
    munLabels.length = 0;
    if (capaMunicipios) {
      map.removeLayer(capaMunicipios);
      try { layersControl.removeLayer(capaMunicipios); } catch(e){}
      capaMunicipios = null;
    }
  }

  function cargarMunicipiosDeEstado(urlGeoJson){
    if (!urlGeoJson) {
      limpiarMunicipios();
      return;
    }

    limpiarMunicipios();

    fetch(urlGeoJson)
      .then(r => r.json())
      .then(function(geo){
        capaMunicipios = L.geoJSON(geo, {
          pane: 'municipiosPane',
          style: f => styleFeature(pickStats((f && f.properties) ? f.properties : {}).total),
          onEachFeature: function(feature, layer){
            const p  = feature.properties || {};
            const st = pickStats(p);
            const cve    = (p.CVEGEO || (String(p.CVE_ENT||'') + String(p.CVE_MUN||''))).toString();
            const nombre = p.NOMGEO || 'Desconocido';

            layer.options.className = 'municipio';

            layer.on('click', function(){
              const html = `
                <div style="min-width:240px">
                  <h5 style="margin:0 0 6px 0">${nombre}</h5>
                  <div><strong>Afiliados (sí):</strong> ${st.afiliados}</div>
                  <div><strong>No afiliados (no):</strong> ${st.no_afiliados}</div>
                  <div><strong>Convencidos (sí + no):</strong> ${st.convencidos}</div>
                  <div style="margin-top:6px"><small>Total (todos): ${st.total}${st.pendientes ? (' — Pendientes: ' + st.pendientes) : ''}</small></div>
                  <div><small>CVEGEO: ${cve}</small></div>
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
              fitMunicipio(item);
            });
            munLabels.push(item);
          }
        }).addTo(map);

        layersControl.addOverlay(capaMunicipios, 'Municipios (total)');

        const bounds = capaMunicipios.getBounds();
        map.fitBounds(bounds);

        // En móvil, NO lo amarres tan duro, si no se siente “encerrado”
        if (!isMobile) {
          map.setMaxBounds(bounds.pad(0.05));
        }

        setTimeout(fitMunicipios, 0);
      })
      .catch(err => console.error('Error cargando GeoJSON de estado:', err));
  }

  // ===========
  // CAMBIO DE ESTADO: recarga con querystring
  // ===========
  function wireEstadoChange(){
    const sel = document.getElementById('estadoSelect');
    if (!sel) return;

    sel.addEventListener('change', function(){
      const estado = this.value || '';
      const estatus = document.getElementById('estatusValue').value || 'validado';

      const url = new URL(window.location.href);
      if (estado) url.searchParams.set('estado', estado);
      else url.searchParams.delete('estado');

      if (estatus) url.searchParams.set('estatus', estatus);
      window.location.href = url.toString();
    });
  }

  // ===========
  // CARGA INICIAL
  // ===========
  (function init(){
    // centra el mapa en algo aunque no haya estado seleccionado
    map.setView([19.4326, -99.1332], 5);

    // Espera un tick a que el control se pinte (y exista el select)
    setTimeout(() => {
      wireEstadoChange();
      const url = getEstadoURLSeleccionado();
      cargarMunicipiosDeEstado(url);
    }, 0);
  })();
</script>
@endpush
