<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapaController extends Controller
{
    private function normalize($s): string
    {
        $s = (string) ($s ?? '');
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
        $s = preg_replace('/[\p{Mn}]+/u', '', $s);
        $s = preg_replace('/[^A-Z0-9 ]/iu', '', $s);
        return strtoupper(trim($s));
    }

    private function listEstadosGeojson(): array
    {
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

                $key = $this->normalize($base);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

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

        usort($states, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $states;
    }

    private function mapCveEnt(): array
    {
        // Nota: agregué variantes por si el nombre del archivo o del geojson trae "DE OCAMPO", etc.
        return [
            'AGUASCALIENTES' => '01',

            'BAJA CALIFORNIA' => '02',
            'BAJA CALIFORNIA SUR' => '03',
            'CAMPECHE' => '04',
            'COAHUILA DE ZARAGOZA' => '05',
            'COLIMA' => '06',
            'CHIAPAS' => '07',
            'CHIHUAHUA' => '08',
            'CIUDAD DE MEXICO' => '09',
            'DURANGO' => '10',
            'GUANAJUATO' => '11',
            'GUERRERO' => '12',
            'HIDALGO' => '13',
            'JALISCO' => '14',

            'MEXICO' => '15',
            'ESTADO DE MEXICO' => '15',

            'MICHOACAN' => '16',
            'MICHOACAN DE OCAMPO' => '16',

            'MORELOS' => '17',
            'NAYARIT' => '18',
            'NUEVO LEON' => '19',
            'OAXACA' => '20',
            'PUEBLA' => '21',
            'QUERETARO' => '22',
            'QUINTANA ROO' => '23',
            'SAN LUIS POTOSI' => '24',
            'SINALOA' => '25',
            'SONORA' => '26',
            'TABASCO' => '27',
            'TAMAULIPAS' => '28',
            'TLAXCALA' => '29',
            'VERACRUZ' => '30',
            'YUCATAN' => '31',
            'ZACATECAS' => '32',
        ];
    }

    private function getCveEntFromEstado(?string $estado, array $states): string
    {
        $map = $this->mapCveEnt();

        // Si viene estado por query (?estado=...), úsalo
        if (!empty($estado)) {
            $norm = $this->normalize($estado);
            if (isset($map[$norm])) return $map[$norm];
        }

        // Si no viene, intenta con el primer estado listado
        if (!empty($states)) {
            $norm = $states[0]['norm'] ?? $this->normalize($states[0]['name'] ?? '');
            if (isset($map[$norm])) return $map[$norm];
        }

        // fallback: Michoacán (solo por si algo sale mal)
        return '16';
    }

    public function index(Request $request)
    {
        $estatus = $request->query('estatus', 'validado');
        $allowed = ['validado', 'pendiente', 'descartado', 'todos'];
        if (!in_array($estatus, $allowed, true)) $estatus = 'validado';

        $estado = $request->query('estado');
        $states = $this->listEstadosGeojson();

        // Si no viene estado y sí hay estados, ponemos por defecto el primero
        if (empty($estado) && count($states) > 0) {
            $estado = $states[0]['name'];
        }

        // CVE_ENT del estado seleccionado
        $cveEnt = $this->getCveEntFromEstado($estado, $states);

        /**
         * IMPORTANTE:
         * Aquí ya NO usamos cve_mun para construir CVEGEO.
         * Usamos el CVEGEO REAL que guardaste en BD.
         * Y filtramos por estado seleccionado con LEFT(cvegeo,2).
         */
        $rows = DB::table('afiliados')
            ->selectRaw("
                cvegeo,
                COUNT(*) as total,
                SUM(CASE WHEN estatus='validado'   THEN 1 ELSE 0 END) as afiliados,
                SUM(CASE WHEN estatus='descartado' THEN 1 ELSE 0 END) as no_afiliados,
                SUM(CASE WHEN estatus='pendiente'  THEN 1 ELSE 0 END) as pendientes
            ")
            ->whereNull('deleted_at')
            ->whereNotNull('cvegeo')
            ->whereRaw("LEFT(cvegeo,2) = ?", [$cveEnt])
            ->groupBy('cvegeo')
            ->get();

        $conteo = [];
        $statsCVE = [];

        foreach ($rows as $r) {
            $cvegeo = (string) $r->cvegeo;

            $afiliados    = (int) $r->afiliados;
            $no_afiliados = (int) $r->no_afiliados;
            $pendientes   = (int) $r->pendientes;
            $total        = (int) $r->total;

            $conteo[$cvegeo] = $total;

            $statsCVE[$cvegeo] = [
                'total'        => $total,
                'afiliados'    => $afiliados,
                'no_afiliados' => $no_afiliados,
                'pendientes'   => $pendientes,
                'convencidos'  => $afiliados + $no_afiliados,
            ];
        }

        // Ya NO es necesario empatar por nombre (eso mete errores y no es oficial)
        $conteoPorNombre = [];
        $statsNombre = [];

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
        $allowed = ['validado', 'pendiente', 'descartado', 'todos'];
        if (!in_array($estatus, $allowed, true)) $estatus = 'validado';

        $estado = $request->query('estado');
        $states = $this->listEstadosGeojson();
        $cveEnt = $this->getCveEntFromEstado($estado, $states);

        $rows = DB::table('afiliados')
            ->select('id', 'nombre', 'apellido_paterno', 'apellido_materno', 'municipio', 'lat', 'lng', 'cvegeo')
            ->whereNull('deleted_at')
            ->whereNotNull('lat')->whereNotNull('lng')
            ->whereNotNull('cvegeo')
            ->whereRaw("LEFT(cvegeo,2) = ?", [$cveEnt])
            ->when($estatus !== 'todos', fn($q) => $q->where('estatus', $estatus))
            ->limit(2000)
            ->get();

        return response()->json($rows);
    }
}
