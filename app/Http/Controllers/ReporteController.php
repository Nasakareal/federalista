<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteController extends Controller
{
    public function index()
    {
        return view('reportes.index');
    }

    public function afiliados()
    {
        return view('reportes.afiliados');
    }

    public function afiliadosData(Request $request)
    {
        $q = $this->buildAfiliadosBaseQuery($request);

        $rows = $q->leftJoin('users as u', 'u.id', '=', 'afiliados.capturista_id')
            ->select([
                'afiliados.id',
                DB::raw("COALESCE(u.name, CONCAT('ID ', afiliados.capturista_id)) as capturista"),
                'afiliados.nombre',
                'afiliados.apellido_paterno',
                'afiliados.apellido_materno',
                'afiliados.sexo',
                'afiliados.telefono',
                'afiliados.email',
                'afiliados.municipio',
                'afiliados.cve_mun',
                'afiliados.localidad',
                'afiliados.colonia',
                'afiliados.calle',
                'afiliados.numero_ext',
                'afiliados.numero_int',
                'afiliados.cp',
                'afiliados.seccion',
                'afiliados.distrito_federal',
                'afiliados.distrito_local',
                'afiliados.clave_elector',
                'afiliados.estatus',
                'afiliados.fecha_convencimiento',
                'afiliados.created_at',
            ])
            ->orderByDesc('afiliados.created_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function afiliadosExportXlsx(Request $request)
    {
        $q = $this->buildAfiliadosBaseQuery($request);

        $allowed = [
            'id','capturista',
            'nombre','sexo','telefono','email',
            'municipio','cve_mun','localidad','colonia','calle','numero_ext','numero_int','cp',
            'seccion','distrito_federal','distrito_local',
            'clave_elector',
            'estatus','fecha_convencimiento','created_at',
        ];

        $cols = $request->input('columns', []);
        if (is_string($cols)) {
            $cols = array_filter(array_map('trim', explode(',', $cols)));
        } else {
            $cols = array_filter(array_map('trim', (array)$cols));
        }

        if (empty($cols)) {
            $cols = [
                'capturista','nombre','sexo','telefono','email',
                'municipio','cve_mun','seccion','distrito_federal','distrito_local',
                'clave_elector',
                'estatus','fecha_convencimiento','created_at'
            ];
        }

        $cols = array_values(array_intersect($cols, $allowed));

        $rows = $q->leftJoin('users as u', 'u.id', '=', 'afiliados.capturista_id')
            ->select([
                'afiliados.id',
                DB::raw("COALESCE(u.name, CONCAT('ID ', afiliados.capturista_id)) as capturista"),
                'afiliados.nombre','afiliados.apellido_paterno','afiliados.apellido_materno',
                'afiliados.sexo','afiliados.telefono','afiliados.email',
                'afiliados.municipio','afiliados.cve_mun',
                'afiliados.localidad','afiliados.colonia','afiliados.calle','afiliados.numero_ext','afiliados.numero_int','afiliados.cp',
                'afiliados.seccion','afiliados.distrito_federal','afiliados.distrito_local',
                'afiliados.clave_elector',
                'afiliados.estatus','afiliados.fecha_convencimiento','afiliados.created_at',
            ])
            ->orderBy('afiliados.municipio')
            ->orderBy('afiliados.seccion')
            ->get();

        $labels = [
            'id'=>'ID',
            'capturista'=>'Capturista',
            'nombre'=>'Nombre',
            'sexo'=>'Sexo',
            'telefono'=>'Teléfono',
            'email'=>'Email',
            'municipio'=>'Municipio',
            'cve_mun'=>'CVE_MUN',
            'localidad'=>'Localidad',
            'colonia'=>'Colonia',
            'calle'=>'Calle',
            'numero_ext'=>'No. Ext',
            'numero_int'=>'No. Int',
            'cp'=>'CP',
            'seccion'=>'Sección',
            'distrito_federal'=>'Distrito Federal',
            'distrito_local'=>'Distrito Local',
            'clave_elector'=>'Clave Elector',
            'estatus'=>'Estatus',
            'fecha_convencimiento'=>'Fecha Convencimiento',
            'created_at'=>'Creado',
        ];
        $headers = array_map(fn($k)=>$labels[$k], $cols);

        $out = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $k) {
                if ($k === 'nombre') {
                    $line[] = trim(implode(' ', array_filter([$r->nombre,$r->apellido_paterno,$r->apellido_materno])));
                } else {
                    $line[] = $r->{$k} ?? null;
                }
            }
            $out[] = $line;
        }

        $filename = 'reporte_afiliados_'.now()->format('Ymd_His').'.xlsx';
        return $this->exportTabular($filename, $headers, $out);
    }

    public function secciones()
    {
        return view('reportes.secciones');
    }

    public function seccionesData(Request $request)
    {
        $base = $this->buildAfiliadosBaseQuery($request);

        $rows = $base
            ->select([
                'afiliados.seccion',
                'afiliados.municipio',
                'afiliados.cve_mun',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'validado'   THEN 1 ELSE 0 END) as validado"),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'descartado' THEN 1 ELSE 0 END) as descartado"),
            ])
            ->groupBy('afiliados.seccion','afiliados.municipio','afiliados.cve_mun')
            ->orderBy('afiliados.municipio')
            ->orderBy('afiliados.seccion')
            ->get()
            ->map(function ($r) {
                $otros = (int)$r->total - (int)$r->validado - (int)$r->descartado;
                $r->otros = $otros;
                $r->porcentaje_validado = $r->total ? round(($r->validado / $r->total) * 100, 2) : 0;
                return $r;
            });

        return response()->json(['data' => $rows]);
    }

    public function seccionesExportXlsx(Request $request)
    {
        $base = $this->buildAfiliadosBaseQuery($request);

        $rows = $base
            ->select([
                'afiliados.municipio',
                'afiliados.cve_mun',
                'afiliados.seccion',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'validado'   THEN 1 ELSE 0 END) as validado"),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'descartado' THEN 1 ELSE 0 END) as descartado"),
            ])
            ->groupBy('afiliados.seccion','afiliados.municipio','afiliados.cve_mun')
            ->orderBy('afiliados.municipio')
            ->orderBy('afiliados.seccion')
            ->get()
            ->map(function ($r) {
                $otros = (int)$r->total - (int)$r->validado - (int)$r->descartado;
                $pctValidado = $r->total ? round(($r->validado / $r->total) * 100, 2) : 0;
                return [
                    'municipio'     => $r->municipio,
                    'cve_mun'       => $r->cve_mun,
                    'seccion'       => $r->seccion,
                    'total'         => (int)$r->total,
                    'validado'      => (int)$r->validado,
                    'descartado'    => (int)$r->descartado,
                    'otros'         => $otros,
                    'pct_validado'  => $pctValidado,
                ];
            })->values()->all();

        $headers  = ['Municipio','CVE_MUN','Sección','Total','Validados','Descartados','Otros','% Validados'];
        $filename = 'reporte_secciones_'.Carbon::now()->format('Ymd_His').'.xlsx';

        return $this->exportTabular($filename, $headers, $rows);
    }

    public function capturistas()
    {
        return view('reportes.capturistas');
    }

    public function capturistasData(Request $request)
    {
        $base = $this->buildAfiliadosBaseQuery($request);

        $rows = $base
            ->leftJoin('users as u', 'u.id', '=', 'afiliados.capturista_id')
            ->select([
                DB::raw("COALESCE(u.name, CONCAT('ID ', afiliados.capturista_id)) as capturista"),
                'afiliados.capturista_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'validado'   THEN 1 ELSE 0 END) as validado"),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'descartado' THEN 1 ELSE 0 END) as descartado"),
                DB::raw('MAX(afiliados.created_at) as ultima_captura'),
            ])
            ->groupBy('afiliados.capturista_id','u.name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) {
                $otros = (int)$r->total - (int)$r->validado - (int)$r->descartado;
                $r->otros = $otros;
                $r->porcentaje_validado = $r->total ? round(($r->validado / $r->total) * 100, 2) : 0;
                return $r;
            });

        return response()->json(['data' => $rows]);
    }

    public function capturistasExportXlsx(Request $request)
    {
        $base = $this->buildAfiliadosBaseQuery($request);

        $rows = $base
            ->leftJoin('users as u', 'u.id', '=', 'afiliados.capturista_id')
            ->select([
                DB::raw("COALESCE(u.name, CONCAT('ID ', afiliados.capturista_id)) as capturista"),
                'afiliados.capturista_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'validado'   THEN 1 ELSE 0 END) as validado"),
                DB::raw("SUM(CASE WHEN afiliados.estatus = 'descartado' THEN 1 ELSE 0 END) as descartado"),
                DB::raw('MAX(afiliados.created_at) as ultima_captura'),
            ])
            ->groupBy('afiliados.capturista_id','u.name')
            ->orderBy('capturista')
            ->get()
            ->map(function ($r) {
                $otros = (int)$r->total - (int)$r->validado - (int)$r->descartado;
                $pct   = $r->total ? round(($r->validado / $r->total) * 100, 2) : 0;
                return [
                    'capturista'     => $r->capturista,
                    'capturista_id'  => $r->capturista_id,
                    'total'          => (int)$r->total,
                    'validado'       => (int)$r->validado,
                    'descartado'     => (int)$r->descartado,
                    'otros'          => $otros,
                    'ultima_captura' => $r->ultima_captura,
                    'pct_validado'   => $pct,
                ];
            })->values()->all();

        $headers  = ['Capturista','ID','Total','Validados','Descartados','Otros','Última captura','% Validados'];
        $filename = 'reporte_capturistas_'.Carbon::now()->format('Ymd_His').'.xlsx';

        return $this->exportTabular($filename, $headers, $rows);
    }

    public function ine()
    {
        return view('reportes.ine');
    }

    public function ineData(Request $request)
    {
        $q = $this->buildAfiliadosBaseQuery($request);

        $rows = $q->select([
                'afiliados.id',
                'afiliados.perfil',
                'afiliados.nombre',
                'afiliados.apellido_paterno',
                'afiliados.apellido_materno',
                'afiliados.clave_elector',
                'afiliados.telefono',
                'afiliados.email',
                'afiliados.ine_frente',
                'afiliados.ine_reverso',
                'afiliados.created_at',
            ])
            ->orderByDesc('afiliados.created_at')
            ->get()
            ->map(function ($r) {
                $r->nombre_completo = trim(implode(' ', array_filter([$r->nombre, $r->apellido_paterno, $r->apellido_materno])));
                $r->ine_frente_url  = $r->ine_frente ? asset('storage/' . $r->ine_frente) : null;
                $r->ine_reverso_url = $r->ine_reverso ? asset('storage/' . $r->ine_reverso) : null;
                return $r;
            });

        return response()->json(['data' => $rows]);
    }

    public function ineExportPdf(Request $request)
    {
        $page = max(1, (int)$request->input('page', 1));
        $per  = min(80, max(10, (int)$request->input('per', 40)));
        $off  = ($page - 1) * $per;

        $q = $this->buildAfiliadosBaseQuery($request);

        $rows = $q
            ->where(function($w){
                $w->whereNotNull('afiliados.ine_frente')
                  ->orWhereNotNull('afiliados.ine_reverso');
            })
            ->orderByDesc('afiliados.created_at')
            ->offset($off)
            ->limit($per)
            ->get([
                'afiliados.id',
                'afiliados.perfil',
                'afiliados.nombre',
                'afiliados.apellido_paterno',
                'afiliados.apellido_materno',
                'afiliados.clave_elector',
                'afiliados.telefono',
                'afiliados.email',
                'afiliados.ine_frente',
                'afiliados.ine_reverso',
                'afiliados.created_at',
            ])
            ->map(function ($r) {
                $r->nombre_completo = trim(implode(' ', array_filter([$r->nombre, $r->apellido_paterno, $r->apellido_materno])));
                $r->ine_frente_path  = $r->ine_frente ? public_path('storage/' . $r->ine_frente) : null;
                $r->ine_reverso_path = $r->ine_reverso ? public_path('storage/' . $r->ine_reverso) : null;
                return $r;
            });

        $filename = 'reporte_ine_p'.$page.'_'.now()->format('Ymd_His').'.pdf';

        $pdf = \PDF::loadView('reportes.ine_pdf', [
            'rows'    => $rows,
            'filters' => $request->all(),
            'fecha'   => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        return $pdf->download($filename);
    }

    public function facets(Request $request)
    {
        return response()->json([
            'secciones'           => $this->facetList('afiliados.seccion',          $request),
            'municipios'          => $this->facetList('afiliados.municipio',        $request),
            'cve_mun'             => $this->facetList('afiliados.cve_mun',          $request),
            'distritos_locales'   => $this->facetList('afiliados.distrito_local',   $request),
            'distritos_federales' => $this->facetList('afiliados.distrito_federal', $request),
            'estatus'             => $this->facetList('afiliados.estatus',          $request),
            'capturistas'         => $this->facetList('afiliados.capturista_id',    $request, true),
            'sexo'                => $this->facetList('afiliados.sexo',             $request),
        ]);
    }

    public function facetSecciones(Request $request)           { return response()->json($this->facetList('afiliados.seccion',          $request)); }
    public function facetMunicipios(Request $request)          { return response()->json($this->facetList('afiliados.municipio',        $request)); }
    public function facetCveMun(Request $request)              { return response()->json($this->facetList('afiliados.cve_mun',          $request)); }
    public function facetDistritosLocales(Request $request)    { return response()->json($this->facetList('afiliados.distrito_local',   $request)); }
    public function facetDistritosFederales(Request $request)  { return response()->json($this->facetList('afiliados.distrito_federal', $request)); }
    public function facetEstatus(Request $request)             { return response()->json($this->facetList('afiliados.estatus',          $request)); }
    public function facetCapturistas(Request $request)         { return response()->json($this->facetList('afiliados.capturista_id',    $request, true)); }
    public function facetSexo(Request $request)                { return response()->json($this->facetList('afiliados.sexo',             $request)); }

    private function buildAfiliadosBaseQuery(Request $request, array $ignoreCols = [])
    {
        $q = DB::table('afiliados')->whereNull('afiliados.deleted_at');

        $this->applyIncludeExclude($q, 'afiliados.seccion',          $request, 'secciones',           'excluir_secciones',           $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.municipio',        $request, 'municipios',          'excluir_municipios',          $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.cve_mun',          $request, 'cve_mun',             'excluir_cve_mun',             $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.distrito_local',   $request, 'distritos_locales',   'excluir_distritos_locales',   $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.distrito_federal', $request, 'distritos_federales', 'excluir_distritos_federales', $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.estatus',          $request, 'estatus',             'excluir_estatus',             $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.capturista_id',    $request, 'capturistas',         'excluir_capturistas',         $ignoreCols);
        $this->applyIncludeExclude($q, 'afiliados.sexo',             $request, 'sexo',                'excluir_sexo',                $ignoreCols);

        $this->applySingularIfPresent($q, 'afiliados.seccion',          $request, 'seccion',          $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.municipio',        $request, 'municipio',        $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.cve_mun',          $request, 'cve_mun',          $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.distrito_local',   $request, 'distrito_local',   $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.distrito_federal', $request, 'distrito_federal', $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.estatus',          $request, 'estatus_single',   $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.capturista_id',    $request, 'capturista_id',    $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.sexo',             $request, 'sexo_single',      $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.sexo',             $request, 'sexo',             $ignoreCols);

        $this->applyIncludeExclude($q, 'afiliados.clave_elector', $request, 'claves_elector', 'excluir_claves_elector', $ignoreCols);
        $this->applySingularIfPresent($q, 'afiliados.clave_elector', $request, 'clave_elector', $ignoreCols);

        $fcDesde = $request->input('fecha_convencimiento_desde');
        $fcHasta = $request->input('fecha_convencimiento_hasta');
        if ($fcDesde) $q->where('afiliados.fecha_convencimiento', '>=', Carbon::parse($fcDesde)->startOfDay());
        if ($fcHasta) $q->where('afiliados.fecha_convencimiento', '<=', Carbon::parse($fcHasta)->endOfDay());

        $cDesde = $request->input('created_desde');
        $cHasta = $request->input('created_hasta');
        if ($cDesde) $q->where('afiliados.created_at', '>=', Carbon::parse($cDesde)->startOfDay());
        if ($cHasta) $q->where('afiliados.created_at', '<=', Carbon::parse($cHasta)->endOfDay());

        return $q;
    }

    private function applyIncludeExclude($q, string $column, Request $request, string $incKey, string $excKey, array $ignoreCols): void
    {
        if (in_array($column, $ignoreCols, true)) return;

        $inc = $this->normalizeList($request->input($incKey));
        if (!empty($inc)) $q->whereIn($column, $inc);

        $exc = $this->normalizeList($request->input($excKey));
        if (!empty($exc)) $q->whereNotIn($column, $exc);
    }

    private function applySingularIfPresent($q, string $column, Request $request, string $key, array $ignoreCols): void
    {
        if (in_array($column, $ignoreCols, true)) return;

        $v = $request->input($key, null);
        if ($v !== null && $v !== '') {
            $q->where($column, $v);
        }
    }

    private function normalizeList($input): array
    {
        if (is_null($input)) return [];
        if (is_array($input)) $arr = $input;
        else $arr = preg_split('/[,\s;]+/', (string)$input, -1, PREG_SPLIT_NO_EMPTY);
        $arr = array_map(fn($v)=>trim((string)$v), $arr);
        return array_values(array_filter($arr, fn($v)=>$v!==''));
    }

    private function facetList(string $column, Request $request, bool $isCapturista = false): array
    {
        $base = $this->buildAfiliadosBaseQuery($request, [$column]);

        $q = $base->select([$column.' as value', DB::raw('COUNT(*) as total')])
            ->groupBy($column)
            ->orderBy($column);

        if ($isCapturista) {
            $q = $q->leftJoin('users as u','u.id','=','afiliados.capturista_id')
                ->addSelect(DB::raw("COALESCE(u.name, CONCAT('ID ', afiliados.capturista_id)) as label"));
        }

        $items = $q->get()->map(function($r) use ($request, $column, $isCapturista){
            [$incKey, $excKey] = match ($column) {
                'afiliados.seccion'          => ['secciones','excluir_secciones'],
                'afiliados.municipio'        => ['municipios','excluir_municipios'],
                'afiliados.cve_mun'          => ['cve_mun','excluir_cve_mun'],
                'afiliados.distrito_local'   => ['distritos_locales','excluir_distritos_locales'],
                'afiliados.distrito_federal' => ['distritos_federales','excluir_distritos_federales'],
                'afiliados.estatus'          => ['estatus','excluir_estatus'],
                'afiliados.capturista_id'    => ['capturistas','excluir_capturistas'],
                'afiliados.sexo'             => ['sexo','excluir_sexo'],
                'afiliados.clave_elector'    => ['claves_elector','excluir_claves_elector'],
                default                      => [null,null],
            };

            $inc = $this->normalizeList($incKey ? $request->input($incKey) : null);
            $exc = $this->normalizeList($excKey ? $request->input($excKey) : null);

            return [
                'value'     => $r->value,
                'label'     => $isCapturista ? ($r->label ?? ('ID '.$r->value)) : (string)$r->value,
                'total'     => (int)$r->total,
                'selected'  => in_array((string)$r->value, $inc, true),
                'excluded'  => in_array((string)$r->value, $exc, true),
            ];
        })->values()->all();

        return $items;
    }

    private function exportTabular(string $filename, array $headers, array $rows)
    {
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($headers as $i => $h) {
                $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
            }

            $r = 2;
            foreach ($rows as $row) {
                $values = is_array($row) ? array_values($row) : array_values((array)$row);
                foreach ($values as $c => $val) {
                    $sheet->setCellValueByColumnAndRow($c + 1, $r, $val);
                }
                $r++;
            }

            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $binary = ob_get_clean();

            return response($binary, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        $csvName = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $fh = fopen('php://temp', 'w+');

        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : array_values((array)$row);
            fputcsv($fh, $values);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csvName.'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
