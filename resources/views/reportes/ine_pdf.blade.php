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
    td { border:1px solid #999; vertical-align: top; padding:8px; }
    .outer td { padding:0; border:0; }
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
    .rowgap { height:10px; }

    .pagebreak { page-break-after: always; }
  </style>
</head>
<body>

  <div class="title">REPORTE INE</div>
  <div class="meta">
    Generado: {{ $fecha }}<br>
    Filtros: {{ json_encode($filters, JSON_UNESCAPED_UNICODE) }}
  </div>

  <table class="card">
    <tbody>
      @foreach($rows as $i => $r)
        <tr>
          <td class="col">
            <div class="block">
              <div class="lbl">Nombre</div>
              <div class="val normal">{{ $r->nombre_completo ?? '' }}</div>
            </div>

            <div class="block">
              <div class="lbl">Teléfono</div>
              <div class="val normal">{{ $r->telefono ?? '' }}</div>
            </div>

            <div class="block">
              <div class="lbl">INE (Frente)</div>
              <div class="imgwrap">
                @if(!empty($r->ine_frente_path) && file_exists($r->ine_frente_path))
                  <img class="img" src="{{ $r->ine_frente_path }}">
                @else
                  <span class="small">Sin INE frente</span>
                @endif
              </div>
            </div>
          </td>

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
              <div class="lbl">INE (Reverso)</div>
              <div class="imgwrap">
                @if(!empty($r->ine_reverso_path) && file_exists($r->ine_reverso_path))
                  <img class="img" src="{{ $r->ine_reverso_path }}">
                @else
                  <span class="small">Sin INE reverso</span>
                @endif
              </div>
            </div>
          </td>
        </tr>

        @if(($i + 1) % 2 === 0)
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
