<?php
/**
 * Script para executar SQL de fix diretamente
 * Execute via: php apply-fix.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    // Incluir autoload
    require_once 'vendor/autoload.php';
    require_once 'bootstrap/app.php';
    
    $app = require_once 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class);
    
    // Verificar se coluna já existe
    if (!Schema::hasColumn('users', 'professor_id')) {
        echo "[INFO] Adicionando coluna professor_id à tabela users...\n";
        
        Schema::table('users', function ($table) {
            $table->foreignId('professor_id')
                ->nullable()
                ->constrained('professors')
                ->nullOnDelete()
                ->after('is_active');
        });
        
        echo "[✓] Coluna professor_id adicionada com sucesso!\n";
    } else {
        echo "[!] Coluna professor_id já existe na tabela users.\n";
    }
    
    // Verificar se constraint foi criada
    echo "[✓] Fix aplicado com sucesso!\n";
    echo "[✓] Agora tente criar um usuário novamente.\n";
    
} catch (\Exception $e) {
    echo "[✗] ERRO: " . $e->getMessage() . "\n";
    echo "[!] Arquivo: " . $e->getFile() . "\n";
    echo "[!] Linha: " . $e->getLine() . "\n";
    exit(1);
}
