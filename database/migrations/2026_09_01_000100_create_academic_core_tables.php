<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->date('starts_at')->nullable(); $table->date('ends_at')->nullable(); $table->string('status')->default('planning'); $table->timestamps();
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->string('degree')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('curriculum_matrices', function (Blueprint $table) {
            $table->id(); $table->foreignId('course_id')->constrained()->cascadeOnDelete(); $table->string('code'); $table->string('name'); $table->string('version')->nullable(); $table->enum('status', ['Atual', 'Ativa', 'Inativa'])->default('Ativa'); $table->date('effective_from')->nullable(); $table->timestamps(); $table->unique(['course_id', 'code']);
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id(); $table->string('code')->nullable()->index(); $table->string('name'); $table->text('syllabus')->nullable(); $table->unsignedSmallInteger('total_hours')->default(0); $table->unsignedSmallInteger('presential_hours')->default(0); $table->unsignedSmallInteger('online_hours')->default(0); $table->unsignedSmallInteger('practice_hours')->default(0); $table->unsignedSmallInteger('extension_hours')->default(0); $table->timestamps();
        });
        Schema::create('curriculum_subject', function (Blueprint $table) {
            $table->id(); $table->foreignId('curriculum_matrix_id')->constrained()->cascadeOnDelete(); $table->foreignId('subject_id')->constrained()->cascadeOnDelete(); $table->unsignedTinyInteger('period'); $table->unsignedTinyInteger('sequence')->default(1); $table->unique(['curriculum_matrix_id', 'subject_id']);
        });
        Schema::create('professors', function (Blueprint $table) {
            $table->id(); $table->string('registration')->nullable()->unique(); $table->string('name'); $table->string('email')->nullable(); $table->string('qualification')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('professor_availabilities', function (Blueprint $table) {
            $table->id(); $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete(); $table->foreignId('professor_id')->constrained()->cascadeOnDelete(); $table->unsignedTinyInteger('weekday'); $table->time('starts_at'); $table->time('ends_at'); $table->enum('preference', ['available', 'preferred', 'unavailable'])->default('available'); $table->text('notes')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('professor_availabilities'); Schema::dropIfExists('professors'); Schema::dropIfExists('curriculum_subject'); Schema::dropIfExists('subjects'); Schema::dropIfExists('curriculum_matrices'); Schema::dropIfExists('courses'); Schema::dropIfExists('academic_terms');
    }
};
