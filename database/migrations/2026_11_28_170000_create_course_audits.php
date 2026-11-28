<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de curso (QUA-INT-08) por curso e semestre: nota prevista de cada indicador e
 * situação de cada documento exigido. Os indicadores vêm de database/data/auditoria/*.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('catalog', 40)->default('qua-int-08-v25');
            $table->string('auditor')->nullable();
            $table->date('audit_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'academic_term_id']);
        });

        Schema::create('course_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_audit_id')->constrained()->cascadeOnDelete();
            $table->string('indicator', 10);
            $table->unsignedTinyInteger('score')->nullable(); // 1–5; SIM = 5, NÃO = 1
            $table->boolean('not_applicable')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_audit_id', 'indicator']);
        });

        Schema::create('course_audit_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_audit_id')->constrained()->cascadeOnDelete();
            $table->string('indicator', 10);
            $table->string('doc_key', 16);
            $table->string('name', 500);
            $table->string('status', 12)->default('pending'); // pending | delivered | na
            $table->string('link', 500)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_audit_id', 'doc_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_audit_documents');
        Schema::dropIfExists('course_audit_entries');
        Schema::dropIfExists('course_audits');
    }
};
