<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Salas físicas usadas na grade (Li30, Sala 40…). O tipo é comparado com o
        // required_room_type do vínculo matriz ↔ disciplina.
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('SALA_COMUM');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('class_offerings', function (Blueprint $table) {
            // "Estudo Dirigido" da planilha de oferta: turmas pequenas com carga docente reduzida.
            $table->boolean('directed_study')->default(false)->after('student_count');
        });
    }

    public function down(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->dropColumn('directed_study');
        });
        Schema::dropIfExists('rooms');
    }
};
