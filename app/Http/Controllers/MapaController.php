<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapaController extends Controller
{
    private function normalize($s): string
    {
        $s = (string)($s ?? '');
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
        $s = preg_replace('/[\p{Mn}]+/u', '', $s);
        $s = preg_replace('/[^A-Z0-9 ]/iu', '', $s);
        return strtoupper(trim($s));
    }

    private function listEstadosGeojson(): array
    {
        // Busca en estas rutas (ajusta si tus estados están en otra):
        $candidates = [
            public_path('maps'),
            public_path('geo'),
            public_path('geo/estados'),
        ];

        $states = [];
        $seen = [];

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) continue;

            $files = array_merge(
                glob($dir . '/*.geojson') ?: [],
                glob($dir . '/*.json') ?: []
            );

            foreach ($files as $path) {
                $file = basename($path);
                $base = pathinfo($file, PATHINFO_FILENAME);

                // Evita duplicados por nombre
                $key = $this->normalize($base);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                // Construye URL pública relativa
                $rel = str_replace(public_path(), '', $path);
                $rel = str_replace('\\', '/', $rel);
                $rel = ltrim($rel, '/');

                $states[] = [
                    'id'   => strtolower(str_replace(' ', '_', $base)),
                    'name' => $base,
                    'url'  => asset($rel),
                    'norm' => $key,
                ];
            }
        }

        // Orden alfabético por name
        usort($states, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $states;
    }

    public function index(Request $request)
    {
        $estatus = $request->query('estatus', 'validado');
        $allowed = ['validado','pendiente','descartado','todos'];
        if (!in_array($estatus, $allowed, true)) $estatus = 'validado';

        $estado = $request->query('estado');
        $estadoNorm = $estado ? $this->normalize($estado) : null;

        $states = $this->listEstadosGeojson();

        // Si no viene estado y sí hay estados, ponemos por defecto el primero
        if (!$estadoNorm && count($states) > 0) {
            $estado = $states[0]['name'];
            $estadoNorm = $states[0]['norm'];
        }

        // Mapa nombre estado -> CVE_ENT (para CVEGEO; si tu BD NO tiene cve_ent no pasa nada)
        $mapCveEnt = [
            'AGUASCALIENTES' => '01','BAJA CALIFORNIA' => '02','BAJA CALIFORNIA SUR' => '03','CAMPECHE' => '04',
            'COAHUILA DE ZARAGOZA' => '05','COLIMA' => '06','CHIAPAS' => '07','CHIHUAHUA' => '08',
            'CIUDAD DE MEXICO' => '09','DURANGO' => '10','GUANAJUATO' => '11','GUERRERO' => '12',
            'HIDALGO' => '13','JALISCO' => '14','MEXICO' => '15','MICHOACAN' => '16','MORELOS' => '17',
            'NAYARIT' => '18','NUEVO LEON' => '19','OAXACA' => '20','PUEBLA' => '21','QUERETARO' => '22',
            'QUINTANA ROO' => '23','SAN LUIS POTOSI' => '24','SINALOA' => '25','SONORA' => '26','TABASCO' => '27',
            'TAMAULIPAS' => '28','TLAXCALA' => '29','VERACRUZ' => '30','YUCATAN' => '31','ZACATECAS' => '32',
        ];

        $cveEnt = ($estadoNorm && isset($mapCveEnt[$estadoNorm])) ? $mapCveEnt[$estadoNorm] : '16';

        // Conteos por municipio (SIN usar columna estado, porque no existe)
        $rows = DB::table('afiliados')
            ->selectRaw("
                LPAD(cve_mun,3,'0') as cve_mun,
                municipio,
                COUNT(*) as total,
                SUM(CASE WHEN estatus='validado'   THEN 1 ELSE 0 END) as afiliados,
                SUM(CASE WHEN estatus='descartado' THEN 1 ELSE 0 END) as no_afiliados,
                SUM(CASE WHEN estatus='pendiente'  THEN 1 ELSE 0 END) as pendientes
            ")
            ->whereNull('deleted_at')
            ->groupBy('cve_mun','municipio')
            ->get();

        $conteo = [];
        $conteoPorNombre = [];
        $statsCVE = [];
        $statsNombre = [];

        foreach ($rows as $r) {
            $cvegeo = $cveEnt . $r->cve_mun;

            $afiliados    = (int)$r->afiliados;
            $no_afiliados = (int)$r->no_afiliados;
            $pendientes   = (int)$r->pendientes;
            $total        = (int)$r->total;

            $conteo[$cvegeo] = $total;

            $norm = $this->normalize($r->municipio);
            $conteoPorNombre[$norm] = $total;

            $stats = [
                'total'        => $total,
                'afiliados'    => $afiliados,
                'no_afiliados' => $no_afiliados,
                'pendientes'   => $pendientes,
                'convencidos'  => $afiliados + $no_afiliados,
            ];

            $statsCVE[$cvegeo]  = $stats;
            $statsNombre[$norm] = $stats;
        }

        return view('mapa.index', [
            'conteo'          => $conteo,
            'conteoPorNombre' => $conteoPorNombre,
            'estatus'         => $estatus,
            'estado'          => $estado,
            'states'          => $states,
            'statsCVE'        => $statsCVE,
            'statsNombre'     => $statsNombre,
        ]);
    }

    public function data(Request $request)
    {
        $estatus = $request->query('estatus', 'validado');
        $allowed = ['validado','pendiente','descartado','todos'];
        if (!in_array($estatus, $allowed, true)) $estatus = 'validado';

        $rows = DB::table('afiliados')
            ->select('id','nombre','apellido_paterno','apellido_materno','municipio','lat','lng')
            ->whereNull('deleted_at')
            ->when($estatus !== 'todos', fn($q)=>$q->where('estatus', $estatus))
            ->whereNotNull('lat')->whereNotNull('lng')
            ->limit(2000)
            ->get();

        return response()->json($rows);
    }
}
