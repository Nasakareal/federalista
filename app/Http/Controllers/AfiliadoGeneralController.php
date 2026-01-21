<?php

namespace App\Http\Controllers;

use App\Models\AfiliadoGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AfiliadoGeneralController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim((string)$request->query('q'));
        $seccion   = $request->query('seccion');
        $cveMun    = $request->query('cve_mun');
        $municipio = $request->query('municipio');
        $estatus   = $request->query('estatus');
        $capId     = $request->query('capturista_id');

        $full = $this->fullNameField();
        $hasCveMun = Schema::hasColumn('afiliados_general', 'cve_mun');

        $afiliados = AfiliadoGeneral::query()
            ->leftJoin('secciones', function ($j) use ($hasCveMun) {
                $j->on('secciones.seccion', '=', 'afiliados_general.seccion');
                if ($hasCveMun) {
                    $j->on('secciones.cve_mun', '=', 'afiliados_general.cve_mun');
                } else {
                    $j->on('secciones.municipio', '=', 'afiliados_general.municipio');
                }
            })
            ->leftJoin('users', 'users.id', '=', 'afiliados_general.capturista_id')
            ->when($q !== '', function ($qb) use ($q, $full) {
                $qb->where(function ($w) use ($q, $full) {
                    if ($full === 'nombre_completo') {
                        $w->where('afiliados_general.nombre_completo', 'like', "%{$q}%");
                    } else {
                        $w->whereRaw(
                            "CONCAT_WS(' ',afiliados_general.nombre,afiliados_general.apellido_paterno,afiliados_general.apellido_materno) like ?",
                            ["%{$q}%"]
                        );
                    }
                    $w->orWhere('afiliados_general.telefono', 'like', "%{$q}%")
                      ->orWhere('afiliados_general.email', 'like', "%{$q}%")
                      ->orWhere('afiliados_general.clave_elector', 'like', "%{$q}%");
                });
            })
            ->when($seccion, fn($qb) => $qb->where('afiliados_general.seccion', $seccion))
            ->when($cveMun, fn($qb) => $qb->where('afiliados_general.cve_mun', $cveMun))
            ->when($municipio, fn($qb) => $qb->where('afiliados_general.municipio', $municipio))
            ->when($estatus, fn($qb) => $qb->where('afiliados_general.estatus', $estatus))
            ->when($capId, fn($qb) => $qb->where('afiliados_general.capturista_id', $capId))
            ->select([
                'afiliados_general.*',
                'secciones.municipio as s_municipio',
                'secciones.cve_mun as s_cve_mun',
                'secciones.lista_nominal as s_lista_nominal',
                'secciones.distrito_local as s_distrito_local',
                'secciones.distrito_federal as s_distrito_federal',
                'secciones.centroid_lat as s_centroid_lat',
                'secciones.centroid_lng as s_centroid_lng',
                'users.name as capturista_nombre',
            ])
            ->orderByDesc('afiliados_general.id')
            ->paginate(20)
            ->withQueryString();

        return view('afiliados_general.index', compact('afiliados', 'q', 'seccion', 'cveMun', 'municipio', 'estatus', 'capId'));
    }

    public function create()
    {
        $municipios = $this->cargarMunicipiosDesdeGeo();

        $secciones = collect();
        if ($municipios->count() > 0) {
            $cve = $municipios->first()->cve_mun;
            $secciones = DB::table('secciones')
                ->where('cve_mun', $cve)
                ->orderBy('seccion')
                ->pluck('seccion');
        }

        $rules    = $this->rulesStore();
        $required = $this->requiredMap($rules);
        $fullNameField = $this->fullNameField();

        return view('afiliados_general.create', compact('municipios', 'secciones', 'required', 'fullNameField'));
    }

    public function store(Request $request)
    {
        $full = $this->fullNameField();

        $raw  = $this->squish($request->input($full, ''));
        $name = Str::upper(Str::ascii($raw));
        $request->merge([$full => $name]);

        if ($request->filled('clave_elector')) {
            $request->merge([
                'clave_elector' => Str::upper(Str::ascii($this->squish($request->input('clave_elector')))),
            ]);
        }

        $rules = $this->rulesStore();
        $data  = $request->validate($rules);

        if (empty($data['fecha_convencimiento'])) {
            $data['fecha_convencimiento'] = now();
        }

        $data['capturista_id'] = Auth::id();

        $data = $this->storeIneFiles($request, $data, null);

        $afiliado = AfiliadoGeneral::create($data);

        return redirect()->route('afiliados-general.show', $afiliado->id)
            ->with('status', 'Afiliado creado correctamente.');
    }

    public function show(AfiliadoGeneral $afiliadoGeneral)
    {
        $afiliadoGeneral->load('capturista');

        $seccionInfo = DB::table('secciones')
            ->where('seccion', $afiliadoGeneral->seccion)
            ->when(
                $afiliadoGeneral->cve_mun,
                fn($q) => $q->where('cve_mun', $afiliadoGeneral->cve_mun),
                fn($q) => $q->where('municipio', $afiliadoGeneral->municipio)
            )
            ->select('seccion', 'municipio', 'cve_mun', 'distrito_local', 'distrito_federal', 'lista_nominal', 'centroid_lat', 'centroid_lng')
            ->first();

        return view('afiliados_general.show', compact('afiliadoGeneral', 'seccionInfo'));
    }

    public function edit(AfiliadoGeneral $afiliadoGeneral)
    {
        $municipios = $this->cargarMunicipiosDesdeGeo();

        $selCve = $afiliadoGeneral->cve_mun;
        if (!$selCve) {
            $hit = $municipios->firstWhere('municipio', $afiliadoGeneral->municipio);
            $selCve = $hit->cve_mun ?? null;
        }

        $secciones = DB::table('secciones')
            ->when(
                $selCve,
                fn($q) => $q->where('cve_mun', $selCve),
                fn($q) => $q->where('municipio', $afiliadoGeneral->municipio)
            )
            ->orderBy('seccion')
            ->pluck('seccion');

        $rules    = $this->rulesUpdate($afiliadoGeneral);
        $required = $this->requiredMap($rules);
        $fullNameField = $this->fullNameField();

        return view('afiliados_general.edit', compact('afiliadoGeneral', 'municipios', 'secciones', 'required', 'fullNameField'));
    }

    public function update(Request $request, AfiliadoGeneral $afiliadoGeneral)
    {
        $full = $this->fullNameField();

        $raw  = $this->squish($request->input($full, $afiliadoGeneral->{$full} ?? ''));
        $name = Str::upper(Str::ascii($raw));
        $request->merge([$full => $name]);

        if ($request->filled('clave_elector')) {
            $request->merge([
                'clave_elector' => Str::upper(Str::ascii($this->squish($request->input('clave_elector')))),
            ]);
        } else {
            $request->merge(['clave_elector' => null]);
        }

        $rules = $this->rulesUpdate($afiliadoGeneral);
        $data  = $request->validate($rules);

        if (empty($data['fecha_convencimiento'])) {
            $data['fecha_convencimiento'] = now();
        }

        $data = $this->storeIneFiles($request, $data, $afiliadoGeneral);

        $afiliadoGeneral->update($data);

        return redirect()
            ->route('afiliados-general.show', $afiliadoGeneral->id)
            ->with('status', 'Afiliado actualizado correctamente.');
    }

    public function destroy(AfiliadoGeneral $afiliadoGeneral)
    {
        try {
            $afiliadoGeneral->forceDelete();

            if (!empty($afiliadoGeneral->ine_frente)) {
                Storage::disk('public')->delete($afiliadoGeneral->ine_frente);
            }
            if (!empty($afiliadoGeneral->ine_reverso)) {
                Storage::disk('public')->delete($afiliadoGeneral->ine_reverso);
            }

            $dir = 'afiliados_general/ine/' . $afiliadoGeneral->id;
            if (Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->deleteDirectory($dir);
            }

            return redirect()->route('afiliados-general.index')
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
        return Schema::hasColumn('afiliados_general', 'nombre_completo') ? 'nombre_completo' : 'nombre';
    }

    private function rulesStore(): array
    {
        $full = $this->fullNameField();

        return [
            $full              => ['required', 'string', 'max:120', Rule::unique('afiliados_general', $full)],
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
            'perfil'           => ['required', 'string', 'max:120'],
            'estatus'          => ['required', Rule::in(['pendiente', 'validado', 'descartado'])],

            'fecha_convencimiento' => ['nullable', 'date'],

            'ine_frente'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'ine_reverso'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    private function rulesUpdate(AfiliadoGeneral $afiliadoGeneral): array
    {
        $full = $this->fullNameField();

        return [
            $full              => ['required', 'string', 'max:120', Rule::unique('afiliados_general', $full)->ignore($afiliadoGeneral->id, 'id')],
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
            'perfil'           => ['required', 'string', 'max:120'],
            'estatus'          => ['required', Rule::in(['pendiente', 'validado', 'descartado'])],

            'fecha_convencimiento' => ['nullable', 'date'],

            'ine_frente'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'ine_reverso'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    private function storeIneFiles(Request $request, array $data, ?AfiliadoGeneral $afiliadoGeneral): array
    {
        $hasFront = $request->hasFile('ine_frente');
        $hasBack  = $request->hasFile('ine_reverso');

        if (!$hasFront && !$hasBack) {
            return $data;
        }

        $folder = $afiliadoGeneral ? ('afiliados_general/ine/' . $afiliadoGeneral->id) : 'afiliados_general/ine/tmp';

        if ($hasFront) {
            if ($afiliadoGeneral && !empty($afiliadoGeneral->ine_frente)) {
                Storage::disk('public')->delete($afiliadoGeneral->ine_frente);
            }
            $data['ine_frente'] = $request->file('ine_frente')->store($folder, 'public');
        }

        if ($hasBack) {
            if ($afiliadoGeneral && !empty($afiliadoGeneral->ine_reverso)) {
                Storage::disk('public')->delete($afiliadoGeneral->ine_reverso);
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

    private function cargarMunicipiosDesdeGeo()
    {
        $posibles = [
            public_path('geo/michoacan.json'),
            public_path('geo/16_michoacan.json'),
            public_path('geo/16/municipios.json'),
            public_path('geo/16/michoacan.json'),
        ];

        foreach ($posibles as $ruta) {
            if (is_file($ruta)) {
                $raw = @file_get_contents($ruta);
                $json = json_decode($raw, true);
                if (isset($json['features']) && is_array($json['features'])) {
                    $items = collect($json['features'])->map(function ($f) {
                        $p = $f['properties'] ?? [];
                        $cve = $p['CVE_MUN'] ?? $p['CVE_MUNI'] ?? $p['CVE_MPIO'] ?? null;
                        if (!$cve && isset($p['CVEGEO'])) {
                            $cve = substr((string)$p['CVEGEO'], -3);
                        }
                        $nom = $p['NOMGEO'] ?? $p['NOM_MUN'] ?? $p['NOM_MPIO'] ?? $p['NOMMUN'] ?? null;

                        if ($cve && $nom) {
                            return (object)[
                                'cve_mun'   => str_pad($cve, 3, '0', STR_PAD_LEFT),
                                'municipio' => $nom,
                            ];
                        }
                        return null;
                    })->filter()->unique('cve_mun')->sortBy('municipio')->values();

                    if ($items->count() > 0) {
                        return $items;
                    }
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

    private function squish($value): string
    {
        if (method_exists(Str::class, 'squish')) {
            return Str::squish($value);
        }
        return preg_replace('/\s+/u', ' ', trim((string)$value));
    }
}
