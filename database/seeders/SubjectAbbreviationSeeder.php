<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/** Preenche a sigla das disciplinas que ainda não têm (a coordenação pode editar depois). */
class SubjectAbbreviationSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;
        Subject::whereNull('abbreviation')->each(function (Subject $subject) use (&$count) {
            $subject->update(['abbreviation' => Subject::suggestAbbreviation($subject->name)]);
            $count++;
        });
        $this->command?->info("Siglas sugeridas: {$count}");
    }
}
