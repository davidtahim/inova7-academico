<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use Illuminate\Database\Seeder;

class ProfessorAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $term = AcademicTerm::where('code', '2027_1')->first();
        
        if (!$term) {
            $this->command->warn('Semestre 2027_1 não encontrado. Criando...');
            $term = AcademicTerm::create([
                'code' => '2027_1',
                'status' => 'active',
                'starts_at' => '2027-02-01',
                'ends_at' => '2027-07-31',
            ]);
        }

        $reginaldo = Professor::where('name', 'like', '%Reginaldo%')->first();
        
        if (!$reginaldo) {
            $this->command->error('Professor Reginaldo não encontrado!');
            return;
        }

        $this->command->info("Carregando disponibilidade para: {$reginaldo->name}");

        $availabilities = [
            // Manhã - Terça a Sexta
            ['weekday' => 2, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '08:20', 'ends_at' => '09:10', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '08:20', 'ends_at' => '09:10', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '08:20', 'ends_at' => '09:10', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '08:20', 'ends_at' => '09:10', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '09:10', 'ends_at' => '10:00', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '09:10', 'ends_at' => '10:00', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '09:10', 'ends_at' => '10:00', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '09:10', 'ends_at' => '10:00', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '10:10', 'ends_at' => '11:00', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '10:10', 'ends_at' => '11:00', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '10:10', 'ends_at' => '11:00', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '10:10', 'ends_at' => '11:00', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '11:00', 'ends_at' => '11:50', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '11:00', 'ends_at' => '11:50', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '11:00', 'ends_at' => '11:50', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '11:00', 'ends_at' => '11:50', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '11:50', 'ends_at' => '12:40', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '11:50', 'ends_at' => '12:40', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '11:50', 'ends_at' => '12:40', 'preference' => 'preferred'],
            ['weekday' => 5, 'starts_at' => '11:50', 'ends_at' => '12:40', 'preference' => 'preferred'],

            // Noite - Terça a Quinta
            ['weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '19:20', 'ends_at' => '20:10', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '19:20', 'ends_at' => '20:10', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '19:20', 'ends_at' => '20:10', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '20:20', 'ends_at' => '21:10', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '20:20', 'ends_at' => '21:10', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '20:20', 'ends_at' => '21:10', 'preference' => 'preferred'],

            ['weekday' => 2, 'starts_at' => '21:10', 'ends_at' => '22:00', 'preference' => 'preferred'],
            ['weekday' => 3, 'starts_at' => '21:10', 'ends_at' => '22:00', 'preference' => 'preferred'],
            ['weekday' => 4, 'starts_at' => '21:10', 'ends_at' => '22:00', 'preference' => 'preferred'],
        ];

        $count = 0;
        foreach ($availabilities as $data) {
            $exists = ProfessorAvailability::where('professor_id', $reginaldo->id)
                ->where('academic_term_id', $term->id)
                ->where('weekday', $data['weekday'])
                ->where('starts_at', $data['starts_at'])
                ->where('ends_at', $data['ends_at'])
                ->exists();

            if (!$exists) {
                ProfessorAvailability::create([
                    'professor_id' => $reginaldo->id,
                    'academic_term_id' => $term->id,
                    'weekday' => $data['weekday'],
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'preference' => $data['preference'],
                ]);
                $count++;
            }
        }

        $this->command->info("✓ {$count} horários cadastrados com sucesso!");
    }
}
