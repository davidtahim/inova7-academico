<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordenador do curso vinculado a um usuário (coordenação ou Administrador de TI).
 * Vincula Sistemas de Informação, ADS e Bacharelado em IA a David Tahim Alves Brito,
 * se o usuário david.brito@uni7.edu.br existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'coordinator_user_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->foreignId('coordinator_user_id')->nullable()->after('coordinator_name')->constrained('users')->nullOnDelete();
            });
        }

        $david = DB::table('users')->whereRaw('LOWER(email) = ?', ['david.brito@uni7.edu.br'])->first();
        if (! $david) {
            return;
        }

        $courses = DB::table('courses')->where(function ($q) {
            $q->where('name', 'like', 'Sistemas de Informa%')
                ->orWhere('name', 'like', 'An%lise e Desenvolvimento de Sistemas%')
                ->orWhere('name', 'like', '%Intelig%ncia Artificial%');
        })->get();

        foreach ($courses as $course) {
            DB::table('courses')->where('id', $course->id)->update([
                'coordinator_user_id' => $david->id,
                // Como sai no CCG-FOR-02 ("SISTEMAS DE INFORMAÇÃO – PROF. DAVID TAHIM"), se ainda vazio.
                'coordinator_name' => $course->coordinator_name ?: 'Prof. David Tahim',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'coordinator_user_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('coordinator_user_id');
            });
        }
    }
};
