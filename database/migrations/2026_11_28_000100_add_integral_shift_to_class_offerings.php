<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            // INTEGRAL = Manhã + Tarde (07:30 - 17:30), igual ao vínculo matriz ↔ disciplina.
            $table->enum('shift', ['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_offerings', function (Blueprint $table) {
            $table->enum('shift', ['MANHÃ', 'TARDE', 'NOITE'])->change();
        });
    }
};
