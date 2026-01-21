<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // Limpia caché
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ===============================
        // 1) Permisos del sistema
        // ===============================
        $perms = [

            // Comunicados
            'comunicados.ver', 'comunicados.crear', 'comunicados.editar', 'comunicados.borrar',

            // Afiliados (estructura principal)
            'afiliados.ver', 'afiliados.crear', 'afiliados.editar', 'afiliados.borrar',

            // Afiliados General (estructura paralela)
            'afiliados_general.ver', 'afiliados_general.crear',
            'afiliados_general.editar', 'afiliados_general.borrar',

            // Secciones
            'secciones.ver', 'secciones.crear', 'secciones.editar', 'secciones.borrar',

            // Actividades
            'actividades.ver', 'actividades.crear', 'actividades.editar', 'actividades.borrar',

            // Mapa y reportes
            'mapa.ver', 'reportes.ver',

            // Settings
            'settings.ver', 'settings.editar',

            // Usuarios / roles / permisos
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.borrar',
            'roles.ver', 'roles.crear', 'roles.editar', 'roles.borrar',
            'permisos.ver', 'permisos.crear', 'permisos.editar', 'permisos.borrar',
        ];

        // ===============================
        // 2) Crear permisos si no existen
        // ===============================
        foreach ($perms as $p) {
            Permission::firstOrCreate([
                'name'       => $p,
                'guard_name' => $guard,
            ]);
        }

        // ===============================
        // 3) Roles
        // ===============================
        $roleSuper = Role::firstOrCreate(['name' => 'SuperAdmin',  'guard_name' => $guard]);
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin',       'guard_name' => $guard]);
        $roleCoord = Role::firstOrCreate(['name' => 'Coordinador', 'guard_name' => $guard]);
        $roleCapt  = Role::firstOrCreate(['name' => 'Capturista',  'guard_name' => $guard]);
        $roleView  = Role::firstOrCreate(['name' => 'Consulta',    'guard_name' => $guard]);

        // ===============================
        // 4) Asignación de permisos
        // ===============================

        // SuperAdmin → TODO
        $roleSuper->syncPermissions(
            Permission::where('guard_name', $guard)->get()
        );

        // Admin → todos los definidos (aditivo)
        $roleAdmin->givePermissionTo($perms);

        // Coordinador → opera ambas estructuras + reportes
        $roleCoord->givePermissionTo([
            // Afiliados
            'afiliados.ver','afiliados.crear','afiliados.editar','afiliados.borrar',

            // Afiliados General
            'afiliados_general.ver','afiliados_general.crear',
            'afiliados_general.editar','afiliados_general.borrar',

            // Otras
            'actividades.ver','actividades.crear','actividades.editar','actividades.borrar',
            'secciones.ver','mapa.ver','reportes.ver',
        ]);

        // Capturista → captura, no administra
        $roleCapt->givePermissionTo([
            'afiliados.ver','afiliados.crear',
            'afiliados_general.ver','afiliados_general.crear',
            'mapa.ver',
        ]);

        // Consulta → solo lectura
        $roleView->givePermissionTo([
            'afiliados.ver',
            'afiliados_general.ver',
            'secciones.ver',
            'actividades.ver',
            'mapa.ver',
            'reportes.ver',
        ]);

        // Limpia caché final
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
