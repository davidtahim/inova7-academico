<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versões (cenários) da grade do semestre (RF07): retratos completos de professor,
 * sala e horários de cada disciplina/turma, para restaurar, exportar e importar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20)->default('manual'); // manual | auto | import
            $table->longText('payload');
            $table->unsignedInteger('offerings_count')->default(0);
            $table->unsignedInteger('classes_count')->default(0);
            $table->integer('score')->nullable();
            $table->integer('hard')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['academic_term_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_scenarios');
    }
};
