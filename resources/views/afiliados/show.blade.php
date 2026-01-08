@extends('layouts.app')

@section('title','Afiliado')

@section('content_header')
  <h1 class="text-center w-100">Afiliado</h1>
@endsection

@section('content')
@php
  $badge = match($afiliado->estatus){
    'validado'   => 'badge bg-success',
    'pendiente'  => 'badge bg-warning text-dark',
    'descartado' => 'badge bg-danger',
    default      => 'badge bg-secondary'
  };
  $mun     = $seccionInfo->municipio   ?? $afiliado->municipio;
  $cveMun  = $seccionInfo->cve_mun     ?? $afiliado->cve_mun;
  $dLoc    = $seccionInfo->distrito_local   ?? $afiliado->distrito_local;
  $dFed    = $seccionInfo->distrito_federal ?? $afiliado->distrito_federal;

  // ✅ INE (si ya vienen guardadas rutas tipo "afiliados/ine/xxx.jpg")
  $ineFrente  = $afiliado->ine_frente  ?? null;
  $ineReverso = $afiliado->ine_reverso ?? null;

  $ineUrl = function($path){
    if(!$path) return null;
    // Si guardas en storage/app/public => asset('storage/...')
    return asset('storage/'.$path);
  };

  $isImage = function($path){
    if(!$path) return false;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','webp','gif']);
  };
@endphp

<div class="container-xl">
  <div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="card-title m-0">
          <strong>{{ $afiliado->nombre }} {{ $afiliado->apellido_paterno }} {{ $afiliado->apellido_materno }}</strong>
        </h3>
        <div class="small text-muted">
          ID: {{ $afiliado->id }} · Capturista: {{ $afiliado->capturista->name ?? '—' }}
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="{{ $badge }}">{{ ucfirst($afiliado->estatus) }}</span>
        @can('afiliados.editar')
          <a href="{{ route('afiliados.edit',$afiliado->id) }}" class="btn btn-success btn-sm">
            <i class="fa fa-pen"></i> Editar
          </a>
        @endcan
        @can('afiliados.borrar')
          <form action="{{ route('afiliados.destroy',$afiliado->id) }}" method="POST" id="formDel-{{ $afiliado->id }}">
            @csrf @method('DELETE')
            <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminar('{{ $afiliado->id }}', this)">
              <i class="fa fa-trash"></i> Eliminar
            </button>
          </form>
        @endcan
        <a href="{{ route('afiliados.index') }}" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Volver
        </a>
      </div>
    </div>

    <div class="card-body">
      <div class="row g-4">

        {{-- Columna 1: datos personales y contacto --}}
        <div class="col-lg-6">
          <div class="mb-3">
            <h5 class="mb-2">Datos personales</h5>
            <dl class="row mb-0">
              <dt class="col-sm-4">Nombre</dt>
              <dd class="col-sm-8">{{ $afiliado->nombre }} {{ $afiliado->apellido_paterno }} {{ $afiliado->apellido_materno }}</dd>

              <dt class="col-sm-4">Edad / Sexo</dt>
              <dd class="col-sm-8">
                {{ $afiliado->edad ? $afiliado->edad.' años' : '—' }}
                @if($afiliado->sexo) · {{ $afiliado->sexo }} @endif
              </dd>

              <dt class="col-sm-4">Perfil</dt>
              <dd class="col-sm-8">{{ $afiliado->perfil ?: '—' }}</dd>

              <dt class="col-sm-4">Observaciones</dt>
              <dd class="col-sm-8">{{ $afiliado->observaciones ?: '—' }}</dd>
            </dl>
          </div>

          <div>
            <h5 class="mb-2">Contacto</h5>
            <dl class="row mb-0">
              <dt class="col-sm-4">Teléfono</dt>
              <dd class="col-sm-8">{{ $afiliado->telefono ?: '—' }}</dd>

              <dt class="col-sm-4">Email</dt>
              <dd class="col-sm-8">{{ $afiliado->email ?: '—' }}</dd>
            </dl>
          </div>
        </div>

        {{-- Columna 2: ubicación y estructura --}}
        <div class="col-lg-6">
          <div class="mb-3">
            <h5 class="mb-2">Ubicación</h5>
            <dl class="row mb-0">
              <dt class="col-sm-4">Municipio</dt>
              <dd class="col-sm-8">
                {{ $mun ?: '—' }}
                @if($cveMun)
                  <span class="text-muted"> ({{ str_pad($cveMun,3,'0',STR_PAD_LEFT) }})</span>
                @endif
              </dd>

              <dt class="col-sm-4">Domicilio</dt>
              <dd class="col-sm-8">
                @php
                  $dom = collect([
                    $afiliado->calle,
                    $afiliado->numero_ext ? "No. ".$afiliado->numero_ext : null,
                    $afiliado->numero_int ? "Int. ".$afiliado->numero_int : null,
                    $afiliado->colonia,
                    $afiliado->localidad,
                    $afiliado->cp ? "CP ".$afiliado->cp : null,
                  ])->filter()->implode(', ');
                @endphp
                {{ $dom ?: '—' }}
              </dd>

              <dt class="col-sm-4">Coordenadas</dt>
              <dd class="col-sm-8">
                @if($afiliado->lat && $afiliado->lng)
                  {{ $afiliado->lat }}, {{ $afiliado->lng }}
                  <a class="small ms-1" target="_blank"
                     href="https://maps.google.com/?q={{ $afiliado->lat }},{{ $afiliado->lng }}">
                     (ver mapa)
                  </a>
                @else
                  —
                @endif
              </dd>
            </dl>
          </div>

          <div>
            <h5 class="mb-2">Estructura electoral</h5>
            <dl class="row mb-0">
              <dt class="col-sm-4">Sección</dt>
              <dd class="col-sm-8">{{ $afiliado->seccion ?: '—' }}</dd>

              <dt class="col-sm-4">Distrito local</dt>
              <dd class="col-sm-8">{{ $dLoc ?: '—' }}</dd>

              <dt class="col-sm-4">Distrito federal</dt>
              <dd class="col-sm-8">{{ $dFed ?: '—' }}</dd>

              @if($seccionInfo?->lista_nominal)
                <dt class="col-sm-4">Lista nominal</dt>
                <dd class="col-sm-8">{{ number_format($seccionInfo->lista_nominal) }}</dd>
              @endif
            </dl>
          </div>
        </div>

        {{-- ✅ NUEVO BLOQUE: INE (frente / reverso) --}}
        <div class="col-12">
          <hr class="my-2">
          <h5 class="mb-3">INE</h5>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>Frente (anverso)</strong>
                  @if($ineFrente)
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="{{ $ineUrl($ineFrente) }}">
                      <i class="fa fa-eye"></i> Ver / Descargar
                    </a>
                  @endif
                </div>

                @if($ineFrente)
                  @if($isImage($ineFrente))
                    <img src="{{ $ineUrl($ineFrente) }}" class="img-fluid rounded" alt="INE frente">
                  @else
                    <div class="text-muted small">Archivo cargado: {{ basename($ineFrente) }}</div>
                  @endif
                @else
                  <div class="text-muted">— Sin archivo —</div>
                @endif
              </div>
            </div>

            <div class="col-md-6">
              <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>Reverso</strong>
                  @if($ineReverso)
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="{{ $ineUrl($ineReverso) }}">
                      <i class="fa fa-eye"></i> Ver / Descargar
                    </a>
                  @endif
                </div>

                @if($ineReverso)
                  @if($isImage($ineReverso))
                    <img src="{{ $ineUrl($ineReverso) }}" class="img-fluid rounded" alt="INE reverso">
                  @else
                    <div class="text-muted small">Archivo cargado: {{ basename($ineReverso) }}</div>
                  @endif
                @else
                  <div class="text-muted">— Sin archivo —</div>
                @endif
              </div>
            </div>
          </div>

          @can('afiliados.editar')
            <div class="mt-3">
              <a href="{{ route('afiliados.edit',$afiliado->id) }}" class="btn btn-primary btn-sm">
                <i class="fa fa-upload"></i> Subir / Cambiar INE
              </a>
            </div>
          @endcan
        </div>

      </div>

      <hr class="my-4">

      <div class="row g-3">
        <div class="col-md-4">
          <div class="small text-muted">Creado</div>
          <div>{{ optional($afiliado->created_at)->format('Y-m-d H:i') }}</div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">Actualizado</div>
          <div>{{ optional($afiliado->updated_at)->format('Y-m-d H:i') }}</div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">Fecha de convencimiento</div>
          <div>
            {{ optional($afiliado->fecha_convencimiento)->format('Y-m-d H:i') ?? '—' }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminar(id, btn){
  const form = document.getElementById('formDel-'+id);
  btn.disabled = true;
  if(typeof Swal === 'undefined'){
    if(confirm('¿Eliminar afiliado?')) form.submit(); else btn.disabled=false;
    return;
  }
  Swal.fire({
    title:'Eliminar afiliado', text:'¿Deseas eliminarlo?', icon:'warning',
    showDenyButton:true, confirmButtonText:'Eliminar', denyButtonText:'Cancelar',
    confirmButtonColor:'#e3342f'
  }).then(r=>{ if(r.isConfirmed) form.submit(); else btn.disabled=false; });
}
</script>
@endsection
