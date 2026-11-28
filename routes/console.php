<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inova7:status', function () {
    $this->info('Inova7 Acadêmico: estrutura instalada.');
})->purpose('Verifica se a aplicação está carregando corretamente.');

// Importa o histórico escolar normalizado (CSV extraído dos PDFs) dos alunos de matrizes antigas.
Artisan::command('alunos:importar-historico {arquivo : CSV normalizado (matricula;nome;curriculo;turno;...;situacao)} {--fonte= : Identificação da origem, ex.: "Históricos 24/10/2025"}', function (\App\Services\StudentHistoryImporter $importer) {
    $path = $this->argument('arquivo');
    if (! is_file($path)) {
        $this->error("Arquivo não encontrado: {$path}");

        return 1;
    }
    $report = $importer->importCsv($path, $this->option('fonte') ?: basename($path));
    $this->info("Alunos: {$report['students']} | situações por disciplina: {$report['statuses']}");
    if ($report['unknown_curricula'] !== []) {
        $this->warn('Currículos sem matriz correspondente: ' . implode(', ', $report['unknown_curricula']));
    }

    return 0;
})->purpose('Importa históricos de alunos para a enturmação');
