<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('ine_frente', 255)->nullable()->after('fecha_convencimiento');
            $table->string('ine_reverso', 255)->nullable()->after('ine_frente');
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn(['ine_frente', 'ine_reverso']);
        });
    }
};
