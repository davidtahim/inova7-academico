<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('curriculum_matrix_discipline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_matrix_id')->constrained('curriculum_matrices')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedTinyInteger('period')->comment('Período na matriz (1-8)');
            $table->enum('shift', ['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])->default('MANHÃ')->comment('Turno padrão');
            $table->unsignedSmallInteger('weekly_hours')->comment('Carga horária semanal');
            $table->enum('offering_type', ['OBRIGATORIA', 'OPTATIVA', 'ELETIVA', 'INTEGRADORA'])->default('OBRIGATORIA')->comment('Tipo de oferta');
            $table->boolean('allows_shared_class')->default(false)->comment('Permite aula compartilhada/docência compartilhada');
            $table->string('required_room_type')->nullable()->comment('Ex: SALA_COMUM, LABORATORIO_INFORMATICA, LABORATORIO_ENGENHARIA');
            $table->unsignedTinyInteger('max_consecutive_classes')->default(2)->comment('Máximo de aulas consecutivas (restrição flexível)');
            $table->timestamps();
            
            // Índice único composto: cada disciplina pode ter apenas 1 vínculo por período na matriz
            $table->unique(['curriculum_matrix_id', 'subject_id', 'period'], 'curriculum_matrix_discipline_unique');
            
            // Índices para queries comuns
            $table->index(['curriculum_matrix_id', 'period']);
            $table->index(['shift']);
            $table->index(['offering_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_matrix_discipline');
    }
};
