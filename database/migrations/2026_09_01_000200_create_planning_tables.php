<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_offerings', function (Blueprint $table) {
            $table->id(); $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete(); $table->foreignId('course_id')->constrained()->cascadeOnDelete(); $table->foreignId('curriculum_matrix_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('subject_id')->constrained()->cascadeOnDelete(); $table->string('class_code')->index(); $table->unsignedTinyInteger('period'); $table->enum('shift', ['MANHÃ', 'TARDE', 'NOITE']); $table->string('modality')->default('PRESENCIAL'); $table->unsignedSmallInteger('student_count')->nullable(); $table->boolean('occurs')->default(true); $table->decimal('weekly_hours', 5, 2)->default(0); $table->decimal('totvs_hours', 6, 2)->default(0); $table->string('status')->default('pending'); $table->timestamps(); $table->unique(['academic_term_id', 'class_code', 'subject_id']);
        });
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id(); $table->foreignId('class_offering_id')->constrained()->cascadeOnDelete(); $table->foreignId('professor_id')->constrained()->cascadeOnDelete(); $table->decimal('weekly_hours', 5, 2)->default(0); $table->enum('status', ['planned', 'pending_confirmation', 'confirmed'])->default('planned'); $table->timestamps(); $table->unique(['class_offering_id', 'professor_id']);
        });
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id(); $table->foreignId('class_offering_id')->constrained()->cascadeOnDelete(); $table->foreignId('professor_id')->nullable()->constrained()->nullOnDelete(); $table->unsignedTinyInteger('weekday'); $table->time('starts_at'); $table->time('ends_at'); $table->string('room')->nullable(); $table->string('block')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('schedule_slots'); Schema::dropIfExists('teaching_assignments'); Schema::dropIfExists('class_offerings');
    }
};
