<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('clave_elector', 18)
                  ->nullable()
                  ->after('perfil');
        });
    }

    public function down()
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn('clave_elector');
        });
    }
};
