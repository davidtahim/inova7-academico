<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

/** Salas da lista de apoio da planilha de oferta (aba Professores/Página2, coluna "Salas"). */
class RoomsSeeder extends Seeder
{
    public function run(): void
    {
        $handle = fopen(__DIR__ . '/data/planilha-2027-1/salas.csv', 'r');
        fgetcsv($handle, 0, ';'); // cabeçalho
        $count = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $code = trim((string) ($row[0] ?? ''));
            if ($code === '' || strcasecmp($code, 'Sem Sala') === 0) {
                continue;
            }
            Room::firstOrCreate(['code' => $code], ['name' => $code, 'type' => Room::guessType($code), 'active' => true]);
            $count++;
        }
        fclose($handle);

        $this->command?->info("Salas: {$count}");
    }
}
