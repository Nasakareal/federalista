<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('afiliados_general', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('capturista_id');

            $table->string('nombre', 120);
            $table->string('apellido_paterno', 120)->nullable();
            $table->string('apellido_materno', 120)->nullable();
            $table->unsignedTinyInteger('edad')->nullable();
            $table->enum('sexo', ['M', 'F', 'Otro'])->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();

            $table->string('municipio', 120);
            $table->string('cve_mun', 3)->nullable();
            $table->string('localidad', 150)->nullable();
            $table->string('colonia', 150)->nullable();
            $table->string('calle', 150)->nullable();
            $table->string('numero_ext', 20)->nullable();
            $table->string('numero_int', 20)->nullable();
            $table->string('cp', 10)->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->string('seccion', 6)->nullable();
            $table->unsignedSmallInteger('distrito_federal')->nullable();
            $table->unsignedSmallInteger('distrito_local')->nullable();

            $table->text('perfil')->nullable();
            $table->string('clave_elector', 18)->nullable();
            $table->text('observaciones')->nullable();

            $table->enum('estatus', ['pendiente', 'validado', 'descartado'])
                  ->default('pendiente');

            $table->timestamp('fecha_convencimiento')->nullable();

            $table->string('ine_frente', 255)->nullable();
            $table->string('ine_reverso', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices (idénticos a la original)
            $table->index('capturista_id');
            $table->index('municipio');
            $table->index('lat');
            $table->index('seccion');
            $table->index('distrito_federal');
        });
    }

    public function down()
    {
        Schema::dropIfExists('afiliados_general');
    }
};
