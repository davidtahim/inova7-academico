<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Sigla curta para a grade compacta (ex.: BD2, SO, LP).
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('abbreviation', 12)->nullable()->after('name');
        });

        // Carga da disciplina na matriz (Planilha de Oferta Ubíqua): CH total e modalidade.
        // weekly_hours continua sendo as aulas semanais na grade (= H/A Classis).
        Schema::table('curriculum_matrix_discipline', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_hours')->nullable()->after('weekly_hours');
            $table->string('modality', 30)->nullable()->after('total_hours');
        });

        // Aulas a distribuir na grade (RN01). weekly_hours da oferta segue como hora-aula paga
        // ao professor (1 no Estudo Dirigido).
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->unsignedTinyInteger('classes_per_week')->nullable()->after('weekly_hours');
        });

        // Limites definidos pela coordenação (RF02).
        Schema::table('professors', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_classes_per_day')->nullable();
            $table->unsignedTinyInteger('max_days_per_week')->nullable();
        });

        // Publicação da grade do semestre (RF07): alunos e professores só veem depois de publicada.
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->timestamp('schedule_published_at')->nullable();
            $table->foreignId('schedule_published_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_published_by');
            $table->dropColumn('schedule_published_at');
        });
        Schema::table('professors', fn (Blueprint $t) => $t->dropColumn(['max_classes_per_day', 'max_days_per_week']));
        Schema::table('class_offerings', fn (Blueprint $t) => $t->dropColumn('classes_per_week'));
        Schema::table('curriculum_matrix_discipline', fn (Blueprint $t) => $t->dropColumn(['total_hours', 'modality']));
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('abbreviation'));
    }
};
