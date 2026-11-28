<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Matriz e turno do aluno (ex.: SI 2019.1, noite).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('curriculum_matrix_id')->nullable()->after('professor_id')->constrained('curriculum_matrices')->nullOnDelete();
            $table->string('shift')->nullable()->after('curriculum_matrix_id');
        });

        // Situação do aluno em cada disciplina da matriz, vinda do histórico escolar.
        Schema::create('student_subject_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['aprovada', 'dispensada', 'matriculada', 'reprovada', 'trancada', 'falta_cursar']);
            $table->string('term_code')->nullable()->comment('Período letivo do histórico, ex.: 20252, EAD2021');
            $table->unsignedTinyInteger('matrix_period')->nullable();
            $table->decimal('grade', 4, 2)->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'subject_id']);
        });

        // Enturmação: aluno × turma/disciplina ofertada no semestre.
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_offering_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['planned', 'confirmed'])->default('planned');
            $table->timestamps();
            $table->unique(['user_id', 'class_offering_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('student_subject_statuses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curriculum_matrix_id');
            $table->dropColumn('shift');
        });
    }
};
