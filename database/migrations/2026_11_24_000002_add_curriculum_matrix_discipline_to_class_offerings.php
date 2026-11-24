<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            // Adicionar referência à tabela de vínculo matriz-disciplina
            // Para rastrear qual configuração de grade originou esta oferta
            $table->foreignId('curriculum_matrix_discipline_id')
                ->nullable()
                ->after('curriculum_matrix_id')
                ->constrained('curriculum_matrix_discipline')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['curriculum_matrix_discipline_id']);
            $table->dropColumn('curriculum_matrix_discipline_id');
        });
    }
};
