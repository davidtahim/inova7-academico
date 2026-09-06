<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_normatives', function (Blueprint $table) {
            $table->id(); $table->string('code'); $table->string('name'); $table->string('version'); $table->date('approved_at')->nullable(); $table->string('approved_by')->nullable(); $table->string('file_path')->nullable(); $table->boolean('is_current')->default(true); $table->timestamps(); $table->unique(['code', 'version']);
        });
        Schema::create('audit_items', function (Blueprint $table) {
            $table->id(); $table->string('axis'); $table->string('code')->unique(); $table->string('name'); $table->unsignedTinyInteger('weight')->default(1); $table->text('criteria')->nullable(); $table->string('eser_category')->nullable(); $table->timestamps();
        });
        Schema::create('audit_templates', function (Blueprint $table) {
            $table->id(); $table->foreignId('audit_item_id')->constrained()->cascadeOnDelete(); $table->string('code'); $table->string('name'); $table->string('version'); $table->string('shift')->nullable(); $table->string('approved_by')->nullable(); $table->date('approved_at')->nullable(); $table->string('file_path'); $table->string('mime_type')->default('text/html'); $table->json('markers')->nullable(); $table->boolean('is_current')->default(false); $table->timestamps(); $table->unique(['code', 'version', 'shift']);
        });
        Schema::create('audit_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('audit_template_id')->constrained()->restrictOnDelete(); $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete(); $table->foreignId('course_id')->constrained()->cascadeOnDelete(); $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('class_code')->nullable(); $table->unsignedTinyInteger('period')->nullable(); $table->string('shift')->nullable(); $table->unsignedInteger('revision')->default(1); $table->enum('status', ['draft', 'issued', 'published', 'conform', 'nonconform'])->default('draft'); $table->string('file_path')->nullable(); $table->json('data_snapshot'); $table->timestamp('issued_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_evidences', function (Blueprint $table) {
            $table->id(); $table->foreignId('audit_document_id')->constrained()->cascadeOnDelete(); $table->enum('channel', ['email', 'blog', 'notice_board', 'other']); $table->string('file_path'); $table->date('published_at'); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('notes')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_evidences'); Schema::dropIfExists('audit_documents'); Schema::dropIfExists('audit_templates'); Schema::dropIfExists('audit_items'); Schema::dropIfExists('audit_normatives');
    }
};
