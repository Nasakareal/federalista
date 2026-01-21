<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte INE</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
    .title { text-align:center; font-size:16px; font-weight:bold; margin: 0 0 8px 0; }
    .meta { font-size:10px; margin-bottom:10px; color:#333; }

    table { width:100%; border-collapse: collapse; }
    .card { width:100%; border-collapse: collapse; }
    .card td { border:1px solid #999; padding:8px; vertical-align: top; }

    .col { width:50%; }
    .lbl { font-size:9px; color:#555; margin-bottom:2px; }
    .val { font-size:11px; font-weight:600; }
    .val.normal { font-weight:400; }
    .block { margin-bottom:8px; }
    .imgwrap { margin-top:6px; text-align:center; }
    .img { max-width:260px; max-height:170px; width:auto; height:auto; }
    .small { font-size:10px; color:#666; }

    .pagebreak { page-break-after: always; }
  </style>
</head>
<body>

@php
  // 3 tarjetas por hoja
  $perPage = 3;

  // DomPDF: imágenes en base64 para que SIEMPRE salgan
  $imgData = function($path){
    if(!$path || !is_file($path)) return null;

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext){
      'jpg','jpeg' => 'image/jpeg',
      'png'        => 'image/png',
      'webp'       => 'image/webp',
      default      => null,
    };
    if(!$mime) return null;

    $bin = @file_get_contents($path);
    if($bin === false) return null;

    return 'data:'.$mime.';base64,'.base64_encode($bin);
  };
@endphp

<div class="title">REPORTE INE</div>
<div class="meta">
  Generado: {{ $fecha }}<br>
  Filtros: {{ json_encode($filters, JSON_UNESCAPED_UNICODE) }}
</div>

<table class="card">
  <tbody>
    @foreach($rows as $i => $r)
      @php
        $cargo   = trim((string)($r->perfil ?? ''));
        $nombre  = trim((string)($r->nombre_completo ?? ''));
        $seccion = trim((string)($r->seccion ?? ''));

        $front = $imgData($r->ine_frente_path ?? null);
        $back  = $imgData($r->ine_reverso_path ?? null);
      @endphp

      <tr>
        {{-- COLUMNA IZQUIERDA --}}
        <td class="col">
          <div class="block">
            <div class="lbl">Cargo</div>
            <div class="val normal">{{ $cargo }}</div>
          </div>

          <div class="block">
            <div class="lbl">Nombre</div>
            <div class="val normal">{{ $nombre }}</div>
          </div>

          <div class="block">
            <div class="lbl">Teléfono</div>
            <div class="val normal">{{ $r->telefono ?? '' }}</div>
          </div>

          

          <div class="block">
            <div class="imgwrap">
              @if($front)
                <img class="img" src="{{ $front }}">
              @else
                <span class="small">Sin INE frente</span>
              @endif
            </div>
          </div>
        </td>

        {{-- COLUMNA DERECHA --}}
        <td class="col">
          <div class="block">
            <div class="lbl">Clave de elector</div>
            <div class="val normal">{{ $r->clave_elector ?? '' }}</div>
          </div>

          <div class="block">
            <div class="lbl">Email</div>
            <div class="val normal">{{ $r->email ?? '' }}</div>
          </div>

          <div class="block">
            <div class="lbl">Sección</div>
            <div class="val normal">{{ $seccion }}</div>
          </div>

          <div class="block">
            <div class="imgwrap">
              @if($back)
                <img class="img" src="{{ $back }}">
              @else
                <span class="small">Sin INE reverso</span>
              @endif
            </div>
          </div>
        </td>
      </tr>

      {{-- pagebreak cada 3 registros --}}
      @if(($i + 1) % $perPage === 0 && ($i + 1) < count($rows))
        </tbody>
      </table>
      <div class="pagebreak"></div>
      <table class="card">
        <tbody>
      @endif
    @endforeach
  </tbody>
</table>

</body>
</html>
