<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manutenção do sistema (somente Administrador de TI).
 *
 * A hospedagem não oferece terminal: esta tela faz pelo navegador o que seria
 * "php artisan migrate --force" e a limpeza de caches depois de um deploy.
 */
class MaintenanceController extends Controller
{
    public function index()
    {
        return view('system.maintenance', [
            'checks' => $this->checks(),
            'migrations' => $this->migrations(),
            'log' => $this->logTail(),
        ]);
    }

    public function migrate(Request $request)
    {
        $before = $this->migrations();
        if ($before['error'] || $before['pending'] === []) {
            return back()->with('warning', $before['error'] ?? 'Nenhuma migration pendente.');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            Log::error('Manutenção: migrate falhou', ['user' => $request->user()->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'A atualização do banco falhou: ' . $e->getMessage())
                ->with('maintenance_output', $e->getMessage());
        }

        $after = $this->migrations();
        Log::info('Manutenção: migrations aplicadas', ['user' => $request->user()->id, 'applied' => array_values(array_diff($before['pending'], $after['pending']))]);

        return back()
            ->with($after['pending'] === [] ? 'success' : 'warning',
                (count($before['pending']) - count($after['pending'])) . ' migration(s) aplicada(s).'
                . ($after['pending'] ? ' Ainda pendentes: ' . count($after['pending']) . '.' : ' O banco está atualizado.'))
            ->with('maintenance_output', $output);
    }

    public function clearCaches(Request $request)
    {
        $done = [];
        foreach (['config:clear' => 'configuração', 'route:clear' => 'rotas', 'view:clear' => 'telas', 'cache:clear' => 'cache da aplicação'] as $command => $label) {
            try {
                Artisan::call($command);
                $done[] = $label;
            } catch (Throwable $e) {
                return back()->with('error', "Falha ao limpar {$label}: " . $e->getMessage());
            }
        }
        Log::info('Manutenção: caches limpos', ['user' => $request->user()->id]);

        return back()->with('success', 'Caches limpos: ' . implode(', ', $done) . '.');
    }

    public function storageLink(Request $request)
    {
        if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
            return back()->with('warning', 'O link public/storage já existe.');
        }
        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            return back()->with('warning', 'A hospedagem não permitiu criar o link (' . $e->getMessage() . '). Não tem problema: as fotos continuam sendo entregues pelo Laravel.');
        }

        return back()->with('success', 'Link public/storage criado: fotos de perfil e arquivos enviados passam a aparecer.');
    }

    // ------------------------------------------------------------------

    /** @return array<int, array{label: string, ok: bool, detail: string}> */
    private function checks(): array
    {
        $db = true;
        $dbDetail = config('database.default');
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $db = false;
            $dbDetail = 'sem conexão: ' . $e->getMessage();
        }

        $commit = $this->commit();

        return [
            ['label' => 'PHP', 'ok' => version_compare(PHP_VERSION, '8.2', '>='), 'detail' => PHP_VERSION],
            ['label' => 'Laravel', 'ok' => true, 'detail' => app()->version()],
            ['label' => 'Ambiente', 'ok' => ! (app()->environment('production') && config('app.debug')),
                'detail' => app()->environment() . (config('app.debug') ? ' · APP_DEBUG ligado (desligue em produção)' : ' · APP_DEBUG desligado')],
            ['label' => 'Banco de dados', 'ok' => $db, 'detail' => $dbDetail],
            ['label' => 'Pasta storage gravável', 'ok' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')), 'detail' => 'storage/framework e storage/logs'],
            // Sem o link, a rota storage.public entrega os arquivos: não é mais um problema.
            ['label' => 'Fotos e arquivos públicos', 'ok' => true, 'detail' => is_link(public_path('storage')) || is_dir(public_path('storage'))
                ? 'entregues pelo servidor web (link public/storage)'
                : 'entregues pelo Laravel (a hospedagem não permite o link public/storage; não é preciso fazer nada)'],
            ['label' => 'Versão do código', 'ok' => $commit !== null, 'detail' => $commit ?? 'não identificada (pasta .git ausente)'],
        ];
    }

    /** @return array{ran: int, pending: array<int, string>, error: ?string} */
    private function migrations(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

            return ['ran' => count($ran), 'pending' => array_values(array_diff(array_keys($files), $ran)), 'error' => null];
        } catch (Throwable $e) {
            return ['ran' => 0, 'pending' => [], 'error' => 'Não foi possível ler as migrations: ' . $e->getMessage()];
        }
    }

    /** Commit implantado, lido direto da pasta .git (sem depender do comando git). */
    private function commit(): ?string
    {
        $head = base_path('.git/HEAD');
        if (! is_readable($head)) {
            return null;
        }
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $path = base_path('.git/' . substr($ref, 5));
            $sha = is_readable($path) ? trim((string) file_get_contents($path)) : null;
            if (! $sha && is_readable(base_path('.git/packed-refs'))) {
                foreach (file(base_path('.git/packed-refs')) as $line) {
                    if (str_ends_with(trim($line), substr($ref, 5))) {
                        $sha = strtok($line, ' ');
                    }
                }
            }

            return $sha ? substr($sha, 0, 7) . ' (' . basename(substr($ref, 5)) . ')' : null;
        }

        return substr($ref, 0, 7);
    }

    /** Últimas linhas do log do Laravel, para investigar erros sem acesso ao servidor. */
    private function logTail(int $lines = 60): ?string
    {
        $file = storage_path('logs/laravel.log');
        if (! is_readable($file)) {
            return null;
        }
        $size = filesize($file);
        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - 64 * 1024));
        $content = stream_get_contents($handle);
        fclose($handle);

        return implode("\n", array_slice(explode("\n", trim($content)), -$lines));
    }
}
