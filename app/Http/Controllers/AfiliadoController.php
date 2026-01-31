<?php

namespace App\Http\Controllers;

use App\Models\Afiliado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AfiliadoController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim((string)$request->query('q'));
        $seccion   = $request->query('seccion');
        $cveMun    = $request->query('cve_mun');
        $municipio = $request->query('municipio');
        $estatus   = $request->query('estatus');
        $capId     = $request->query('capturista_id');
        $perfil    = $request->query('perfil');
        $estado    = $request->query('estado');

        $full = $this->fullNameField();

        $hasCveMun = Schema::hasColumn('afiliados', 'cve_mun');
        $hasCveGeo = Schema::hasColumn('afiliados', 'cvegeo');
        $hasSeccionesCvegeo = Schema::hasColumn('secciones', 'cvegeo');

        $cveEnt = null;
        if ($hasCveGeo && !empty($estado)) {
            $cveEnt = $this->getCveEntFromEstado($estado);
        }

        $afiliados = Afiliado::query()
            ->leftJoin('secciones', function ($j) use ($hasCveGeo, $hasSeccionesCvegeo, $hasCveMun) {
                $j->on('secciones.seccion', '=', 'afiliados.seccion');

                if ($hasCveGeo && $hasSeccionesCvegeo) {
                    $j->on('secciones.cvegeo', '=', 'afiliados.cvegeo');
                    return;
                }

                if ($hasCveMun) {
                    $j->on('secciones.cve_mun', '=', 'afiliados.cve_mun');
                } else {
                    $j->on('secciones.municipio', '=', 'afiliados.municipio');
                }
            })
            ->leftJoin('users', 'users.id', '=', 'afiliados.capturista_id')
            ->when($q !== '', function ($qb) use ($q, $full) {
                $qb->where(function ($w) use ($q, $full) {
                    if ($full === 'nombre_completo') {
                        $w->where('afiliados.nombre_completo', 'like', "%{$q}%");
                    } else {
                        $w->whereRaw(
                            "CONCAT_WS(' ',afiliados.nombre,afiliados.apellido_paterno,afiliados.apellido_materno) like ?",
                            ["%{$q}%"]
                        );
                    }

                    $w->orWhere('afiliados.telefono', 'like', "%{$q}%")
                      ->orWhere('afiliados.email', 'like', "%{$q}%")
                      ->orWhere('afiliados.clave_elector', 'like', "%{$q}%")
                      ->orWhere('afiliados.perfil', 'like', "%{$q}%")
                      ->orWhere('afiliados.observaciones', 'like', "%{$q}%");
                });
            })
            ->when($seccion, fn($qb) => $qb->where('afiliados.seccion', $seccion))
            ->when($cveMun, fn($qb) => $qb->where('afiliados.cve_mun', $cveMun))
            ->when($municipio, fn($qb) => $qb->where('afiliados.municipio', $municipio))
            ->when($estatus, fn($qb) => $qb->where('afiliados.estatus', $estatus))
            ->when($capId, fn($qb) => $qb->where('afiliados.capturista_id', $capId))
            ->when($perfil, fn($qb) => $qb->where('afiliados.perfil', $perfil))
            ->when($cveEnt, function ($qb) use ($cveEnt, $hasCveGeo) {
                if ($hasCveGeo) {
                    $qb->whereRaw("LEFT(afiliados.cvegeo,2) = ?", [$cveEnt]);
                }
            })
            ->select([
                'afiliados.*',
                'secciones.municipio as s_municipio',
                'secciones.cve_mun as s_cve_mun',
                'secciones.lista_nominal as s_lista_nominal',
                'secciones.distrito_local as s_distrito_local',
                'secciones.distrito_federal as s_distrito_federal',
                'secciones.centroid_lat as s_centroid_lat',
                'secciones.centroid_lng as s_centroid_lng',
                'users.name as capturista_nombre',
            ])
            ->orderByDesc('afiliados.id')
            ->paginate(20)
            ->withQueryString();

        $estados = $this->listEstadosDisponibles();

        return view('afiliados.index', compact(
            'afiliados', 'q', 'seccion', 'cveMun', 'municipio', 'estatus', 'capId', 'perfil', 'estado', 'estados'
        ));
    }

    public function create(Request $request)
    {
        $estado = (string)$request->query('estado', '');

        $estados = $this->listEstadosDisponibles();
        if (empty($estado) && count($estados) > 0) {
            $estado = $estados[0];
        }

        $municipios = $this->cargarMunicipiosDesdeGeo($estado);

        $secciones = collect();
        if ($municipios->count() > 0) {
            $cve = (string)$municipios->first()->cve_mun;
            $secciones = DB::table('secciones')
                ->where('cve_mun', $cve)
                ->orderBy('seccion')
                ->pluck('seccion');
        }

        $rules = $this->rulesStore();
        $required = $this->requiredMap($rules);
        $fullNameField = $this->fullNameField();

        return view('afiliados.create', compact(
            'estados', 'estado', 'municipios', 'secciones', 'required', 'fullNameField'
        ));
    }

    public function store(Request $request)
    {
        $full = $this->fullNameField();

        $raw = $this->squish($request->input($full, ''));
        $name = Str::upper(Str::ascii($raw));
        $request->merge([$full => $name]);

        if ($request->filled('clave_elector')) {
            $request->merge([
                'clave_elector' => Str::upper(Str::ascii($this->squish($request->input('clave_elector')))),
            ]);
        }

        $rules = $this->rulesStore();
        $data = $request->validate($rules);

        if (array_key_exists('observaciones', $data) && $data['observaciones'] !== null) {
            $data['observaciones'] = $this->squish($data['observaciones']);
        }

        if (empty($data['fecha_convencimiento'])) {
            $data['fecha_convencimiento'] = now();
        }

        $data['capturista_id'] = Auth::id();

        if (Schema::hasColumn('afiliados', 'cvegeo')) {
            $estado = (string)($data['estado'] ?? $request->input('estado') ?? $request->query('estado') ?? '');
            $cveEnt = $this->getCveEntFromEstado($estado);
            $data['cvegeo'] = $cveEnt . str_pad((string)($data['cve_mun'] ?? ''), 3, '0', STR_PAD_LEFT);
        }

        $data = $this->storeIneFiles($request, $data, null);

        $afiliado = Afiliado::create($data);

        return redirect()->route('afiliados.show', $afiliado->id)
            ->with('status', 'Afiliado creado correctamente.');
    }

    public function show(Afiliado $afiliado)
    {
        $afiliado->load('capturista');

        $hasSeccionesCvegeo = Schema::hasColumn('secciones', 'cvegeo');
        $hasAfiliadosCvegeo = Schema::hasColumn('afiliados', 'cvegeo');

        $seccionInfo = DB::table('secciones')
            ->where('seccion', $afiliado->seccion)
            ->when(
                $hasSeccionesCvegeo && $hasAfiliadosCvegeo && !empty($afiliado->cvegeo),
                fn($q) => $q->where('cvegeo', $afiliado->cvegeo),
                function ($q) use ($afiliado) {
                    $q->when(
                        !empty($afiliado->cve_mun),
                        fn($qq) => $qq->where('cve_mun', $afiliado->cve_mun),
                        fn($qq) => $qq->where('municipio', $afiliado->municipio)
                    );
                }
            )
            ->select('seccion', 'municipio', 'cve_mun', 'distrito_local', 'distrito_federal', 'lista_nominal', 'centroid_lat', 'centroid_lng', 'cvegeo')
            ->first();

        return view('afiliados.show', compact('afiliado', 'seccionInfo'));
    }

    public function edit(Request $request, Afiliado $afiliado)
    {
        $estados = $this->listEstadosDisponibles();

        $estado = (string)$request->query('estado', '');
        if (empty($estado) && Schema::hasColumn('afiliados', 'cvegeo') && !empty($afiliado->cvegeo)) {
            $estado = $this->getEstadoNameFromCveEnt(substr((string)$afiliado->cvegeo, 0, 2));
        }
        if (empty($estado) && count($estados) > 0) {
            $estado = $estados[0];
        }

        $municipios = $this->cargarMunicipiosDesdeGeo($estado);

        $selCve = $afiliado->cve_mun;
        if (!$selCve) {
            $hit = $municipios->firstWhere('municipio', $afiliado->municipio);
            $selCve = $hit->cve_mun ?? null;
        }

        $hasSeccionesCvegeo = Schema::hasColumn('secciones', 'cvegeo');
        $hasAfiliadosCvegeo = Schema::hasColumn('afiliados', 'cvegeo');

        $secciones = DB::table('secciones')
            ->when(
                $selCve,
                fn($q) => $q->where('cve_mun', $selCve),
                fn($q) => $q->where('municipio', $afiliado->municipio)
            )
            ->when(
                $hasSeccionesCvegeo && $hasAfiliadosCvegeo && !empty($afiliado->cvegeo),
                fn($q) => $q->where('cvegeo', $afiliado->cvegeo)
            )
            ->orderBy('seccion')
            ->pluck('seccion');

        $rules = $this->rulesUpdate($afiliado);
        $required = $this->requiredMap($rules);
        $fullNameField = $this->fullNameField();

        return view('afiliados.edit', compact(
            'afiliado', 'estados', 'estado', 'municipios', 'secciones', 'required', 'fullNameField'
        ));
    }

    public function update(Request $request, Afiliado $afiliado)
    {
        $full = $this->fullNameField();

        $raw = $this->squish($request->input($full, $afiliado->{$full} ?? ''));
        $name = Str::upper(Str::ascii($raw));
        $request->merge([$full => $name]);

        if ($request->filled('clave_elector')) {
            $request->merge([
                'clave_elector' => Str::upper(Str::ascii($this->squish($request->input('clave_elector')))),
            ]);
        } else {
            $request->merge(['clave_elector' => null]);
        }

        $rules = $this->rulesUpdate($afiliado);
        $data = $request->validate($rules);

        if (array_key_exists('observaciones', $data) && $data['observaciones'] !== null) {
            $data['observaciones'] = $this->squish($data['observaciones']);
        }

        if (empty($data['fecha_convencimiento'])) {
            $data['fecha_convencimiento'] = now();
        }

        if (Schema::hasColumn('afiliados', 'cvegeo')) {
            $estado = (string)($data['estado'] ?? $request->input('estado') ?? $request->query('estado') ?? '');
            $cveEnt = null;

            if (!empty($estado)) {
                $cveEnt = $this->getCveEntFromEstado($estado);
            } elseif (!empty($afiliado->cvegeo)) {
                $cveEnt = substr((string)$afiliado->cvegeo, 0, 2);
            } else {
                $cveEnt = '16';
            }

            $data['cvegeo'] = $cveEnt . str_pad((string)($data['cve_mun'] ?? ''), 3, '0', STR_PAD_LEFT);
        }

        $data = $this->storeIneFiles($request, $data, $afiliado);

        $afiliado->update($data);

        return redirect()
            ->route('afiliados.show', $afiliado->id)
            ->with('status', 'Afiliado actualizado correctamente.');
    }

    public function destroy(Afiliado $afiliado)
    {
        try {
            $afiliado->forceDelete();

            if (!empty($afiliado->ine_frente)) {
                Storage::disk('public')->delete($afiliado->ine_frente);
            }
            if (!empty($afiliado->ine_reverso)) {
                Storage::disk('public')->delete($afiliado->ine_reverso);
            }

            $dir = 'afiliados/ine/' . $afiliado->id;
            if (Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->deleteDirectory($dir);
            }

            return redirect()->route('afiliados.index')
                ->with('status', 'Afiliado eliminado definitivamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->with('error', 'No se puede borrar: hay registros relacionados (FK).');
            }
            throw $e;
        }
    }

    private function fullNameField(): string
    {
        return Schema::hasColumn('afiliados', 'nombre_completo') ? 'nombre_completo' : 'nombre';
    }

    private function rulesStore(): array
    {
        $full = $this->fullNameField();

        return [
            $full              => ['required', 'string', 'max:120', Rule::unique('afiliados', $full)],
            'clave_elector'    => ['nullable', 'string', 'max:18'],

            'edad'             => ['nullable', 'integer', 'min:0', 'max:120'],
            'sexo'             => ['nullable', Rule::in(['M', 'F', 'Otro'])],
            'email'            => ['nullable', 'email', 'max:150'],
            'distrito_federal' => ['nullable', 'integer'],
            'distrito_local'   => ['nullable', 'integer'],
            'localidad'        => ['nullable', 'string', 'max:150'],
            'colonia'          => ['nullable', 'string', 'max:150'],
            'telefono'         => ['nullable', 'string', 'max:30'],

            'municipio'        => ['required', 'string', 'max:120'],
            'cve_mun'          => ['required', 'string', 'size:3'],
            'seccion'          => ['required', 'string', 'max:6'],
            'perfil'           => ['required', Rule::in(['Coordinador', 'Enlace', 'Apoyo'])],
            'observaciones'    => ['nullable', 'string', 'max:255'],
            'estatus'          => ['required', Rule::in(['pendiente', 'validado', 'descartado'])],

            'fecha_convencimiento' => ['nullable', 'date'],

            'ine_frente'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'ine_reverso'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],

            'estado'           => ['required', 'string', 'max:120'],
        ];
    }

    private function rulesUpdate(Afiliado $afiliado): array
    {
        $full = $this->fullNameField();

        return [
            $full              => ['required', 'string', 'max:120', Rule::unique('afiliados', $full)->ignore($afiliado->id, 'id')],
            'clave_elector'    => ['nullable', 'string', 'max:18'],

            'edad'             => ['nullable', 'integer', 'min:0', 'max:120'],
            'sexo'             => ['nullable', Rule::in(['M', 'F', 'Otro'])],
            'email'            => ['nullable', 'email', 'max:150'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'distrito_federal' => ['nullable', 'integer'],
            'distrito_local'   => ['nullable', 'integer'],
            'localidad'        => ['nullable', 'string', 'max:150'],
            'colonia'          => ['nullable', 'string', 'max:150'],

            'municipio'        => ['required', 'string', 'max:120'],
            'cve_mun'          => ['required', 'string', 'size:3'],
            'seccion'          => ['required', 'string', 'max:6'],
            'perfil'           => ['required', Rule::in(['Coordinador', 'Enlace', 'Apoyo'])],
            'observaciones'    => ['nullable', 'string', 'max:255'],
            'estatus'          => ['required', Rule::in(['pendiente', 'validado', 'descartado'])],

            'fecha_convencimiento' => ['nullable', 'date'],

            'ine_frente'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'ine_reverso'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],

            'estado'           => ['required', 'string', 'max:120'],
        ];
    }

    private function storeIneFiles(Request $request, array $data, ?Afiliado $afiliado): array
    {
        $hasFront = $request->hasFile('ine_frente');
        $hasBack  = $request->hasFile('ine_reverso');

        if (!$hasFront && !$hasBack) {
            return $data;
        }

        $folder = $afiliado ? ('afiliados/ine/' . $afiliado->id) : 'afiliados/ine/tmp';

        if ($hasFront) {
            if ($afiliado && !empty($afiliado->ine_frente)) {
                Storage::disk('public')->delete($afiliado->ine_frente);
            }
            $data['ine_frente'] = $request->file('ine_frente')->store($folder, 'public');
        }

        if ($hasBack) {
            if ($afiliado && !empty($afiliado->ine_reverso)) {
                Storage::disk('public')->delete($afiliado->ine_reverso);
            }
            $data['ine_reverso'] = $request->file('ine_reverso')->store($folder, 'public');
        }

        return $data;
    }

    private function requiredMap(array $rules): array
    {
        $map = [];
        foreach ($rules as $field => $ruleList) {
            $arr = is_array($ruleList) ? $ruleList : explode('|', (string)$ruleList);
            $hasRequired = false;
            foreach ($arr as $r) {
                if (is_string($r) && str_starts_with($r, 'required')) {
                    $hasRequired = true;
                    break;
                }
            }
            $map[$field] = $hasRequired;
        }
        return $map;
    }

    private function cargarMunicipiosDesdeGeo(string $estado)
    {
        $path = $this->findEstadoGeoFile($estado);

        if ($path && is_file($path)) {
            $raw = @file_get_contents($path);
            $json = json_decode($raw, true);

            if (isset($json['features']) && is_array($json['features'])) {
                $items = collect($json['features'])->map(function ($f) {
                    $p = $f['properties'] ?? [];

                    $cve = $p['CVE_MUN'] ?? $p['CVE_MUNI'] ?? $p['CVE_MPIO'] ?? null;
                    if (!$cve && isset($p['CVEGEO'])) {
                        $cve = substr((string)$p['CVEGEO'], -3);
                    }
                    $nom = $p['NOMGEO'] ?? $p['NOM_MUN'] ?? $p['NOM_MPIO'] ?? $p['NOMMUN'] ?? null;

                    if ($cve !== null && $nom) {
                        return (object)[
                            'cve_mun'   => str_pad((string)$cve, 3, '0', STR_PAD_LEFT),
                            'municipio' => (string)$nom,
                        ];
                    }

                    return null;
                })->filter()->unique('cve_mun')->sortBy('municipio')->values();

                if ($items->count() > 0) {
                    return $items;
                }
            }
        }

        return DB::table('secciones')
            ->select('cve_mun', 'municipio')
            ->distinct()
            ->orderBy('municipio')
            ->get()
            ->map(function ($r) {
                $r->cve_mun = str_pad((string)$r->cve_mun, 3, '0', STR_PAD_LEFT);
                return $r;
            });
    }

    private function listEstadosDisponibles(): array
    {
        $dir = public_path('geo');
        if (!is_dir($dir)) return [];

        $files = array_merge(
            glob($dir . '/*.json') ?: [],
            glob($dir . '/*.geojson') ?: []
        );

        $out = [];
        foreach ($files as $p) {
            $base = pathinfo($p, PATHINFO_FILENAME);
            if ($base === '' || $this->normalize($base) === 'FEDERAL') continue;
            $out[] = $base;
        }

        $out = array_values(array_unique($out));
        usort($out, fn($a, $b) => strcmp($a, $b));
        return $out;
    }

    private function findEstadoGeoFile(string $estado): ?string
    {
        $dir = public_path('geo');
        if (!is_dir($dir)) return null;

        $estado = trim((string)$estado);
        if ($estado === '') $estado = 'Michoacan';

        $target = $this->normalize($estado);

        $files = array_merge(
            glob($dir . '/*.json') ?: [],
            glob($dir . '/*.geojson') ?: []
        );

        foreach ($files as $p) {
            $base = pathinfo($p, PATHINFO_FILENAME);
            if ($this->normalize($base) === $target) {
                return $p;
            }
        }

        $fallback = 'Michoacan';
        $target2 = $this->normalize($fallback);
        foreach ($files as $p) {
            $base = pathinfo($p, PATHINFO_FILENAME);
            if ($this->normalize($base) === $target2) {
                return $p;
            }
        }

        return null;
    }

    private function getCveEntFromEstado(?string $estado): string
    {
        if (empty($estado)) return '16';

        $norm = $this->normalize($estado);

        $map = [
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
            'VERACRUZ DE IGNACIO DE LA LLAVE' => '30',
            'YUCATAN' => '31',
            'ZACATECAS' => '32',
        ];

        return $map[$norm] ?? '16';
    }

    private function getEstadoNameFromCveEnt(string $cveEnt): string
    {
        $map = [
            '01' => 'Aguascalientes',
            '02' => 'Baja California',
            '03' => 'Baja California Sur',
            '04' => 'Campeche',
            '05' => 'Coahuila de Zaragoza',
            '06' => 'Colima',
            '07' => 'Chiapas',
            '08' => 'Chihuahua',
            '09' => 'Ciudad de México',
            '10' => 'Durango',
            '11' => 'Guanajuato',
            '12' => 'Guerrero',
            '13' => 'Hidalgo',
            '14' => 'Jalisco',
            '15' => 'México',
            '16' => 'Michoacan',
            '17' => 'Morelos',
            '18' => 'Nayarit',
            '19' => 'Nuevo León',
            '20' => 'Oaxaca',
            '21' => 'Puebla',
            '22' => 'Querétaro',
            '23' => 'Quintana Roo',
            '24' => 'San Luis Potosí',
            '25' => 'Sinaloa',
            '26' => 'Sonora',
            '27' => 'Tabasco',
            '28' => 'Tamaulipas',
            '29' => 'Tlaxcala',
            '30' => 'Veracruz de Ignacio de la Llave',
            '31' => 'Yucatán',
            '32' => 'Zacatecas',
        ];

        return $map[$cveEnt] ?? 'Michoacan';
    }

    private function normalize($s): string
    {
        $s = (string)($s ?? '');
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
        $s = preg_replace('/[\p{Mn}]+/u', '', $s);
        $s = preg_replace('/[^A-Z0-9 ]/iu', '', $s);
        return strtoupper(trim($s));
    }

    private function squish($value): string
    {
        if (method_exists(Str::class, 'squish')) {
            return Str::squish($value);
        }
        return preg_replace('/\s+/u', ' ', trim((string)$value));
    }
}
