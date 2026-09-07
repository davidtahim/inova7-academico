<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['professor_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_subject');
    }
};
