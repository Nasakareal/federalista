<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('cvegeo', 5)->nullable()->after('cve_mun')->index();
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn('cvegeo');
        });
    }
};
