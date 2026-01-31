<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Afiliado extends Model
{
    use SoftDeletes;

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'capturista_id',

        // Datos personales
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'edad',
        'sexo',
        'telefono',
        'email',

        // Ubicación (INEGI)
        'municipio',
        'cve_mun',
        'cvegeo',          // 👈 NUEVO (CVE_ENT + CVE_MUN)
        'localidad',
        'colonia',
        'calle',
        'numero_ext',
        'numero_int',
        'cp',

        // Geolocalización
        'lat',
        'lng',

        // Datos electorales
        'seccion',
        'distrito_federal',
        'distrito_local',

        // Perfil político
        'perfil',
        'clave_elector',
        'observaciones',

        // Estatus
        'estatus',
        'fecha_convencimiento',

        // Archivos
        'ine_frente',
        'ine_reverso',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'edad'                  => 'integer',
        'lat'                   => 'float',
        'lng'                   => 'float',
        'fecha_convencimiento'  => 'datetime',
    ];

    /* ============================================================
     |  RELACIONES
     |============================================================ */

    public function capturista()
    {
        return $this->belongsTo(User::class, 'capturista_id');
    }

    /**
     * Relación con secciones (amarrada por CVEGEO + sección)
     * Esto evita colisiones entre estados
     */
    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion', 'seccion')
            ->whereColumn('afiliados.cvegeo', 'secciones.cvegeo');
    }

    /* ============================================================
     |  SCOPES (Filtros reutilizables)
     |============================================================ */

    public function scopePorSecciones($q, $secciones)
    {
        $vals = is_array($secciones)
            ? $secciones
            : explode(',', (string) $secciones);

        return $q->whereIn('seccion', array_filter(array_map('trim', $vals)));
    }

    public function scopePorMunicipios($q, $municipios)
    {
        $vals = is_array($municipios)
            ? $municipios
            : explode(',', (string) $municipios);

        return $q->whereIn('municipio', array_filter(array_map('trim', $vals)));
    }

    public function scopePorCapturista($q, $userId)
    {
        return $q->where('capturista_id', $userId);
    }

    public function scopePorEstatus($q, $estatus)
    {
        if ($estatus === 'todos' || empty($estatus)) {
            return $q;
        }

        return $q->where('estatus', $estatus);
    }

    /**
     * Scope clave para mapas: filtrar por estado usando CVEGEO
     */
    public function scopePorEstado($q, string $cveEnt)
    {
        return $q->whereRaw('LEFT(cvegeo,2) = ?', [$cveEnt]);
    }

    /* ============================================================
     |  HELPERS
     |============================================================ */

    /**
     * Regresa CVE_ENT (2 dígitos) desde CVEGEO
     */
    public function getCveEntAttribute(): ?string
    {
        return $this->cvegeo ? substr($this->cvegeo, 0, 2) : null;
    }

    /**
     * Regresa CVE_MUN (3 dígitos) desde CVEGEO
     */
    public function getCveMunFromGeoAttribute(): ?string
    {
        return $this->cvegeo ? substr($this->cvegeo, 2, 3) : null;
    }
}
