@extends('layouts.app')

@section('title','Nuevo afiliado')

@section('content_header')
  <h1 class="text-center w-100">Crear afiliado</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="card card-outline card-primary">
    <div class="card-body">

      <style>
        label.required::after { content:" *"; color:#dc3545; margin-left:.25rem; }
        .form-control[readonly] { background-color:#f8f9fa; }
      </style>

      @php
        $req = fn($f) => !empty($required[$f] ?? false);
        $fullNameField = $fullNameField ?? 'nombre';

        $perfilOld = old('perfil', '');
        $perfilOpts = ['Coordinador' => 'Coordinador', 'Enlace' => 'Enlace', 'Apoyo' => 'Apoyo'];

        $estado = old('estado', $estado ?? request('estado', ''));

        $sexoOld  = old('sexo','');
        $sexoOpts = ['M'=>'Hombre','F'=>'Mujer','Otro'=>'Otro'];

        $estatusOld = old('estatus','pendiente');
        $labelMap = ['pendiente'=>'Pendiente','validado'=>'Sí','descartado'=>'No'];
        $badgeMap = ['pendiente'=>'secondary','validado'=>'success','descartado'=>'danger'];
        $snMap    = ['pendiente'=>'Pendiente','validado'=>'SI','descartado'=>'NO'];
      @endphp

      <form action="{{ route('afiliados.store') }}" method="POST" autocomplete="off" enctype="multipart/form-data">
        @csrf

        <input type="hidden" name="estado" id="txtEstado" value="{{ $estado }}">
        <input type="hidden" name="cvegeo" id="txtCveGeo" value="{{ old('cvegeo') }}">

        <div class="row g-3">

          <div class="col-md-4">
            <label class="form-label {{ $req('estado') ? 'required' : '' }}">Estado</label>
            <select name="estado_select" id="slEstado"
                    class="form-select @error('estado') is-invalid @enderror"
                    {{ $req('estado') ? 'required' : '' }}>
              <option value="">-- Selecciona --</option>
              @foreach(($estados ?? []) as $e)
                <option value="{{ $e }}" {{ $estado===$e ? 'selected' : '' }}>{{ $e }}</option>
              @endforeach
            </select>
            @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">CVEGEO</label>
            <input type="text" id="txtCveGeoView" value="{{ old('cvegeo') }}" readonly class="form-control" placeholder="16053">
          </div>

          <div class="col-md-6"></div>

          <div class="col-md-6">
            <label class="form-label {{ $req($fullNameField) ? 'required' : '' }}">Nombre completo</label>
            <input
              type="text"
              name="{{ $fullNameField }}"
              value="{{ old($fullNameField) }}"
              class="form-control @error($fullNameField) is-invalid @enderror"
              {{ $req($fullNameField) ? 'required' : '' }}
              placeholder="EJEMPLO: MARIO DANTE BAUTISTA REBOLLAR">
            @error($fullNameField)<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Edad</label>
            <input type="number" name="edad" value="{{ old('edad') }}" min="0" max="120"
                   class="form-control @error('edad') is-invalid @enderror">
            @error('edad')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Sexo</label>
            <select name="sexo" class="form-select @error('sexo') is-invalid @enderror">
              <option value="" {{ $sexoOld===''?'selected':'' }}>Seleccione…</option>
              @foreach($sexoOpts as $val => $label)
                <option value="{{ $val }}" {{ $sexoOld===$val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            @error('sexo')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('telefono') ? 'required' : '' }}">Teléfono</label>
            <input type="text" name="telefono" value="{{ old('telefono') }}"
                   class="form-control @error('telefono') is-invalid @enderror"
                   {{ $req('telefono') ? 'required' : '' }}>
            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('email') ? 'required' : '' }}">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   {{ $req('email') ? 'required' : '' }}>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('municipio') ? 'required' : '' }}">Municipio</label>
            <select name="municipio" id="slMunicipio"
                    class="form-select @error('municipio') is-invalid @enderror"
                    {{ $req('municipio') ? 'required' : '' }}>
              <option value="">-- Selecciona --</option>
              @foreach(($municipios ?? collect()) as $m)
                <option value="{{ $m->municipio }}"
                        data-cve="{{ str_pad($m->cve_mun,3,'0',STR_PAD_LEFT) }}"
                        {{ old('municipio')===$m->municipio?'selected':'' }}>
                  {{ $m->municipio }}
                </option>
              @endforeach
            </select>
            @error('municipio')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label {{ $req('cve_mun') ? 'required' : '' }}">CVE mun (3)</label>
            <input type="text" name="cve_mun" id="txtCveMun"
                   value="{{ old('cve_mun') }}" maxlength="3" readonly
                   class="form-control @error('cve_mun') is-invalid @enderror"
                   {{ $req('cve_mun') ? 'required' : '' }}
                   placeholder="053">
            @error('cve_mun')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label {{ $req('seccion') ? 'required' : '' }}">Sección</label>
            <input type="text" name="seccion" value="{{ old('seccion') }}" list="dlSecciones"
                   class="form-control @error('seccion') is-invalid @enderror"
                   {{ $req('seccion') ? 'required' : '' }}
                   placeholder="Ej. 1234">
            <datalist id="dlSecciones">
              @if(isset($secciones))
                @foreach($secciones as $sec)
                  <option value="{{ $sec }}">{{ $sec }}</option>
                @endforeach
              @endif
            </datalist>
            @error('seccion')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label {{ $req('distrito_local') ? 'required' : '' }}">Distrito local</label>
            <input type="number" name="distrito_local" value="{{ old('distrito_local') }}"
                   min="1" max="100" step="1" inputmode="numeric" pattern="[0-9]*"
                   class="form-control @error('distrito_local') is-invalid @enderror"
                   {{ $req('distrito_local') ? 'required' : '' }} readonly>
            @error('distrito_local')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-2">
            <label class="form-label {{ $req('distrito_federal') ? 'required' : '' }}">Distrito federal</label>
            <input type="number" name="distrito_federal" value="{{ old('distrito_federal') }}"
                   min="1" max="100" step="1" inputmode="numeric" pattern="[0-9]*"
                   class="form-control @error('distrito_federal') is-invalid @enderror"
                   {{ $req('distrito_federal') ? 'required' : '' }} readonly>
            @error('distrito_federal')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('localidad') ? 'required' : '' }}">Localidad</label>
            <input type="text" name="localidad" value="{{ old('localidad') }}"
                   class="form-control @error('localidad') is-invalid @enderror"
                   {{ $req('localidad') ? 'required' : '' }}>
            @error('localidad')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('colonia') ? 'required' : '' }}">Colonia</label>
            <input type="text" name="colonia" value="{{ old('colonia') }}"
                   class="form-control @error('colonia') is-invalid @enderror"
                   {{ $req('colonia') ? 'required' : '' }}>
            @error('colonia')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="form-label {{ $req('clave_elector') ? 'required' : '' }}">Clave de elector</label>
            <input type="text"
                   name="clave_elector"
                   value="{{ old('clave_elector') }}"
                   maxlength="18"
                   class="form-control @error('clave_elector') is-invalid @enderror"
                   {{ $req('clave_elector') ? 'required' : '' }}
                   placeholder="EJEMPLO: ABCD123456HDFRLL09">
            @error('clave_elector')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6">
            <label class="form-label {{ $req('ine_frente') ? 'required' : '' }}">INE (anverso)</label>
            <input type="file"
                   name="ine_frente"
                   class="form-control @error('ine_frente') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.webp,.pdf">
            @error('ine_frente')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6">
            <label class="form-label {{ $req('ine_reverso') ? 'required' : '' }}">INE (reverso)</label>
            <input type="file"
                   name="ine_reverso"
                   class="form-control @error('ine_reverso') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.webp,.pdf">
            @error('ine_reverso')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6">
            <label class="form-label {{ $req('perfil') ? 'required' : '' }}">Perfil</label>
            <select name="perfil"
                    class="form-select @error('perfil') is-invalid @enderror"
                    {{ $req('perfil') ? 'required' : '' }}>
              <option value="" {{ $perfilOld==='' ? 'selected' : '' }}>-- Selecciona --</option>
              @foreach($perfilOpts as $val => $label)
                <option value="{{ $val }}" {{ $perfilOld===$val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            @error('perfil')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6">
            <label class="form-label {{ $req('observaciones') ? 'required' : '' }}">Observaciones (localidad/municipio, etc.)</label>
            <input type="text"
                   name="observaciones"
                   value="{{ old('observaciones') }}"
                   class="form-control @error('observaciones') is-invalid @enderror"
                   {{ $req('observaciones') ? 'required' : '' }}
                   placeholder="Ej. Angangueo - Enlace">
            @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-3">
            <label class="form-label {{ $req('estatus') ? 'required' : '' }}">Afiliado</label>
            <select name="estatus" class="form-select @error('estatus') is-invalid @enderror"
                    {{ $req('estatus') ? 'required' : '' }}>
              <option value="pendiente"  {{ $estatusOld==='pendiente'?'selected':'' }}>{{ $labelMap['pendiente'] }}</option>
              <option value="validado"   {{ $estatusOld==='validado'?'selected':'' }}>{{ $labelMap['validado'] }}</option>
              <option value="descartado" {{ $estatusOld==='descartado'?'selected':'' }}>{{ $labelMap['descartado'] }}</option>
            </select>
            @error('estatus')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <small class="form-text mt-1 d-block">
              <span class="badge bg-{{ $badgeMap[$estatusOld] }}">{{ $snMap[$estatusOld] }}</span>
            </small>
          </div>

          <div class="col-md-4">
            <label class="form-label">Fecha de convencimiento</label>
            <input type="datetime-local" name="fecha_convencimiento"
                   value="{{ old('fecha_convencimiento') }}"
                   class="form-control @error('fecha_convencimiento') is-invalid @enderror">
            @error('fecha_convencimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

        </div>

        <div class="mt-4 d-flex gap-2">
          <a href="{{ route('afiliados.index') }}" class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const $estadoSel = document.querySelector('#slEstado');
  const $estadoTxt = document.querySelector('#txtEstado');

  const $sec = document.querySelector('input[name="seccion"], select[name="seccion"]');
  const $cve = document.querySelector('#txtCveMun');
  const $mun = document.querySelector('#slMunicipio');
  const $dl  = document.querySelector('input[name="distrito_local"]');
  const $df  = document.querySelector('input[name="distrito_federal"]');

  const $cvegeo = document.querySelector('#txtCveGeo');
  const $cvegeoView = document.querySelector('#txtCveGeoView');

  const mapCveEnt = {
    'AGUASCALIENTES':'01',
    'BAJA CALIFORNIA':'02',
    'BAJA CALIFORNIA SUR':'03',
    'CAMPECHE':'04',
    'COAHUILA DE ZARAGOZA':'05',
    'COLIMA':'06',
    'CHIAPAS':'07',
    'CHIHUAHUA':'08',
    'CIUDAD DE MEXICO':'09',
    'DURANGO':'10',
    'GUANAJUATO':'11',
    'GUERRERO':'12',
    'HIDALGO':'13',
    'JALISCO':'14',
    'MEXICO':'15',
    'ESTADO DE MEXICO':'15',
    'MICHOACAN':'16',
    'MICHOACAN DE OCAMPO':'16',
    'MORELOS':'17',
    'NAYARIT':'18',
    'NUEVO LEON':'19',
    'OAXACA':'20',
    'PUEBLA':'21',
    'QUERETARO':'22',
    'QUINTANA ROO':'23',
    'SAN LUIS POTOSI':'24',
    'SINALOA':'25',
    'SONORA':'26',
    'TABASCO':'27',
    'TAMAULIPAS':'28',
    'TLAXCALA':'29',
    'VERACRUZ':'30',
    'VERACRUZ DE IGNACIO DE LA LLAVE':'30',
    'YUCATAN':'31',
    'ZACATECAS':'32',
  };

  function normalize(s){
    return String(s||'')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[^A-Z0-9 ]/gi,'')
      .trim().toUpperCase();
  }
  const pad3 = v => (v==null ? '' : String(v).trim().padStart(3,'0'));
  const squish = v => String(v||'').trim().replace(/\s+/g,' ');

  function getCveEnt(){
    const est = $estadoTxt ? squish($estadoTxt.value) : '';
    const key = normalize(est);
    return mapCveEnt[key] || '16';
  }

  function syncCveFromMunicipio(){
    if(!$mun || !$cve) return;
    const opt = $mun.options[$mun.selectedIndex];
    const cve = (opt && opt.dataset && opt.dataset.cve) ? String(opt.dataset.cve).padStart(3,'0') : '';
    $cve.value = cve;
  }

  function syncCveGeo(){
    const ent = getCveEnt();
    const mun = $cve ? pad3($cve.value) : '';
    const val = (ent && mun) ? (String(ent) + String(mun)) : '';
    if ($cvegeo) $cvegeo.value = val;
    if ($cvegeoView) $cvegeoView.value = val;
  }

  if ($estadoSel && $estadoTxt) {
    $estadoSel.addEventListener('change', function(){
      const v = squish($estadoSel.value);
      $estadoTxt.value = v;
      const url = new URL(window.location.href);
      if (v) url.searchParams.set('estado', v); else url.searchParams.delete('estado');
      window.location.href = url.toString();
    });
  }

  [$cve,$dl,$df].forEach(el=>{
    if(!el) return;
    el.readOnly = true;
    ['keydown','paste','drop'].forEach(evt=>{
      el.addEventListener(evt, e=>{ if(el.readOnly) e.preventDefault(); });
    });
  });

  if ($mun) {
    $mun.addEventListener('change', () => {
      syncCveFromMunicipio();
      syncCveGeo();
      if ($sec && $sec.value) debouncedLookup();
    });
    syncCveFromMunicipio();
  }
  syncCveGeo();

  if (!$sec) return;

  const endpoint = "{{ route('secciones.lookup') }}";
  let t=null;

  async function fetchLookup(params){
    const url = endpoint + '?' + (new URLSearchParams(params)).toString();
    const r = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } });
    if (!r.ok) throw r;
    return r.json();
  }

  function fillFields(j, forceCanon){
    if ($dl) $dl.value = j.distrito_local ?? '';
    if ($df) $df.value = j.distrito_federal ?? '';
    if ($cve && (forceCanon || !squish($cve.value)) && j.cve_mun) $cve.value = pad3(j.cve_mun);

    if ($mun && (forceCanon || !squish($mun.value)) && j.municipio) {
      const val = j.municipio;
      const opt = Array.from($mun.options).find(o=>o.value===val);
      if (opt) { $mun.value = val; syncCveFromMunicipio(); }
    }

    syncCveGeo();

    [$dl,$df,$cve,$mun].forEach(el=>{
      if(!el) return;
      el.classList.remove('is-invalid');
      el.classList.add('is-valid');
      setTimeout(()=>el.classList.remove('is-valid'), 600);
    });
  }

  async function lookup(){
    const seccion = squish($sec.value);
    if (!seccion) return;

    const strict = { seccion };

    if ($cve && squish($cve.value)) strict.cve_mun = pad3($cve.value);
    else if ($mun && squish($mun.value)) strict.municipio = squish($mun.value);

    if ($cvegeo && squish($cvegeo.value)) strict.cvegeo = squish($cvegeo.value);

    try { const j = await fetchLookup(strict); fillFields(j,false); return; }
    catch(e){}

    try { const j = await fetchLookup({ seccion }); fillFields(j,true); }
    catch(e){
      if ($dl) $dl.value = '';
      if ($df) $df.value = '';
      [$dl,$df,$cve,$mun].forEach(el=>{
        if(!el) return;
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        setTimeout(()=>el.classList.remove('is-invalid'), 800);
      });
    }
  }

  function debouncedLookup(){ if (t) clearTimeout(t); t = setTimeout(lookup, 200); }

  $sec.addEventListener('input', debouncedLookup);
  ['change','blur'].forEach(ev => $sec.addEventListener(ev, lookup));

  if (squish($sec.value)) lookup();
});
</script>
@endpush
