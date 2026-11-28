<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de reservas de laboratórios de informática (módulo do NTI) integrado à grade.
 * Regulamento PED-RGU-18307: Art. 8º III (laboratório só para carga prática), Art. 16 I
 * (aula regular bloqueia o semestre) e Art. 16 II / Art. 4º (horários livres).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('computers')->nullable()->after('capacity');   // qtd_computadores
            $table->text('softwares')->nullable()->after('computers');                   // softwares homologados (um por linha ou vírgula)
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->text('required_software')->nullable()->after('extension_hours');     // softwares que a disciplina precisa
        });

        Schema::create('reservas_laboratorio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('laboratorio_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('class_offerings')->cascadeOnDelete();
            $table->foreignId('solicitante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_solicitacao', 20);          // Aula | Avulsa | Evento | Prova | Monitoria
            $table->string('origem', 10)->default('manual'); // grade (sincronizada) | manual
            $table->string('titulo')->nullable();
            $table->unsignedTinyInteger('dia_semana')->nullable(); // 1 = segunda … 6 = sábado
            $table->date('data')->nullable();                      // reservas pontuais
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->string('turno', 10)->nullable();
            $table->boolean('recorrente')->default(false);
            $table->string('frequencia', 20)->nullable();          // Semanal
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->unsignedSmallInteger('participantes')->nullable();
            $table->string('status', 12)->default('Pendente');      // Pendente | Aprovada | Recusada | Cancelada
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['laboratorio_id', 'dia_semana']);
            $table->index(['laboratorio_id', 'data']);
            $table->index(['turma_id', 'origem']);
        });

        // Aulas já alocadas em laboratório passam a ter a reserva semestral (semestres não concluídos).
        foreach (\App\Models\AcademicTerm::where('status', '!=', 'closed')->get() as $term) {
            app(\App\Services\GradeLaboratorioSyncService::class)->syncTerm($term);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_laboratorio');
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn('required_software'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['computers', 'softwares']));
    }
};
