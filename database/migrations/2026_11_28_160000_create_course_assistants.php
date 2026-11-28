<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assistentes do curso: o curso tem um coordenador (sai no CCG-FOR-02) e quantos assistentes precisar.
 *
 * Correção de dados: em SI, ADS e Bacharelado em IA, se a coordenação foi passada para Ianelli,
 * ela vira assistente e David Tahim (david.brito@uni7.edu.br) volta a ser o coordenador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_assistants')) {
            Schema::create('course_assistants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['course_id', 'user_id']);
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
            $current = $course->coordinator_user_id ? DB::table('users')->find($course->coordinator_user_id) : null;
            if (! $current || $current->id === $david->id || stripos((string) $current->name, 'ianelli') === false) {
                continue;
            }
            DB::table('course_assistants')->updateOrInsert(
                ['course_id' => $course->id, 'user_id' => $current->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
            DB::table('courses')->where('id', $course->id)->update(['coordinator_user_id' => $david->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_assistants');
    }
};
