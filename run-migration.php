<?php
/**
 * Script para executar migrations manualmente
 * Execute via: php run-migration.php
 */

$basePath = dirname(__FILE__);
chdir($basePath);

// Bootstrap Laravel
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

// Executar migrate
$status = $kernel->call('migrate', ['--step' => true]);

exit($status);
