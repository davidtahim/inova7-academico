<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inova7:status', function () {
    $this->info('Inova7 Acadêmico: estrutura instalada.');
})->purpose('Verifica se a aplicação está carregando corretamente.');
