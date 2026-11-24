<?php

namespace Database\Seeders;

use App\Models\Professor;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ProfessorSubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $reginaldo = Professor::where('name', 'like', '%Reginaldo%')->first();
        
        if (!$reginaldo) {
            $this->command->error('Professor Reginaldo não encontrado!');
            return;
        }

        $this->command->info("Carregando disciplinas para: {$reginaldo->name}");

        // Disciplinas do professor Reginaldo baseado nos dados fornecidos
        $disciplinesData = [
            'GMAT001' => 'Matemática Aplicada',
            'GMAT002' => 'Lógica Matemática',
            'GMAT003' => 'Estatística e Probabilidade',
            'GPROG001' => 'Linguagens de Programação',
            'GPROG002' => 'Lógica de Programação',
            'GPROG003' => 'Programação Orientada a Objeto',
            'GSIA001' => 'Inteligência Artificial',
            'GMAT004' => 'Cálculo',
        ];

        $subjectIds = [];
        $count = 0;
        
        foreach ($disciplinesData as $code => $name) {
            // Tenta encontrar por código exato
            $subject = Subject::where('code', $code)->first();
            
            // Se não encontrar, tenta encontrar por nome (parcial)
            if (!$subject) {
                $subject = Subject::where('name', 'like', "%{$name}%")->first();
            }
            
            if ($subject) {
                $subjectIds[] = $subject->id;
                $this->command->info("✓ {$subject->name} (Código: {$subject->code})");
                $count++;
            } else {
                $this->command->warn("⚠ Disciplina '{$name}' (Código: {$code}) não encontrada no sistema");
            }
        }

        if (!empty($subjectIds)) {
            // Sincroniza as disciplinas com o professor
            $reginaldo->subjects()->sync($subjectIds);
            $this->command->info("✓ {$count} disciplinas associadas ao professor Reginaldo!");
        } else {
            $this->command->error('Nenhuma disciplina foi associada. Verifique os códigos/nomes das disciplinas no sistema.');
        }
    }
}
