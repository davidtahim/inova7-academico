<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil "Gestor de laboratórios" (role lab_manager): a área define quais laboratórios
 * a pessoa administra — ti, saude, comunicacao, engenharia, arquitetura (App\Support\LabAreas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lab_area', 20)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('lab_area'));
    }
};
