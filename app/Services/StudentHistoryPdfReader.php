<?php

namespace App\Services;

use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

/**
 * Lê o histórico escolar (PDF da UNI7) e devolve as linhas no formato do
 * StudentHistoryImporter — sem passar pelo CSV.
 *
 * Só extrai matrícula, nome, currículo, turno e a situação de cada disciplina:
 * CPF, RG e data de nascimento são ignorados e o arquivo não é guardado.
 */
class StudentHistoryPdfReader
{
    private const CODE = '(UNI7[A-Z]\d{3}|GSER\d{5,7})';

    /** Texto do PDF com as colunas separadas por dois espaços (como o "pdftotext -layout"). */
    public function text(string $path): string
    {
        if (str_ends_with(Str::lower($path), '.txt')) {
            return (string) file_get_contents($path);
        }

        $pdf = (new Parser())->parseFile($path);
        $lines = [];
        foreach ($pdf->getPages() as $page) {
            $rows = [];
            foreach ($page->getDataTm() as [$tm, $chunk]) {
                if (trim($chunk) === '') {
                    continue;
                }
                $rows[(string) round((float) $tm[5] / 3)][] = ['x' => (float) $tm[4], 'text' => $chunk];
            }
            // De cima para baixo (y do PDF cresce para cima); cada linha da esquerda para a direita.
            krsort($rows, SORT_NUMERIC);
            foreach ($rows as $chunks) {
                usort($chunks, fn ($a, $b) => $a['x'] <=> $b['x']);
                $line = '';
                $end = null;
                foreach ($chunks as $chunk) {
                    // Pedaço colado no anterior (mesma célula) leva um espaço; outra coluna leva dois.
                    $line .= $end === null ? '' : ($chunk['x'] - $end < 6 ? ' ' : '  ');
                    $line .= trim($chunk['text']);
                    $end = $chunk['x'] + mb_strlen(trim($chunk['text'])) * 4.2;
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Um PDF pode trazer vários históricos (exportação em lote): separa por aluno.
     *
     * @return array<int, array{rows: array<int, array<string, string>>, student: ?array<string, string>}>
     */
    public function parseAll(string $text): array
    {
        $parts = preg_split('/^(?=.*MATR[IÍ]CULA:?\s+\d+\s+NOME)/mu', str_replace("\r\n", "\n", $text), -1, PREG_SPLIT_NO_EMPTY);

        return collect($parts)->map(fn ($part) => $this->parse($part))->filter(fn ($r) => $r['student'])->values()->all();
    }

    /**
     * @return array{rows: array<int, array<string, string>>, student: ?array{matricula: string, nome: string, curriculo: string, turno: string}}
     */
    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\t"], ["\n", '  '], $text);

        if (! preg_match('/MATR[IÍ]CULA:?\s+(\d+)\s+NOME:?\s+(.+?)(?:\s{2,}|$)/mu', $text, $m)) {
            return ['rows' => [], 'student' => null];
        }
        $student = ['matricula' => $m[1], 'nome' => trim($m[2]), 'curriculo' => '', 'turno' => ''];
        if (preg_match('/C[OÓ]DIGO\s+CURR[IÍ]CULO.*\n\s*(\S+)\s+.*?\s(MANH[AÃ]|TARDE|NOITE)\s*$/mu', $text, $c)) {
            $student['curriculo'] = $c[1];
            $student['turno'] = $c[2];
        }

        $rows = [];
        $section = null;
        foreach (explode("\n", $text) as $line) {
            $u = Str::upper(Str::ascii($line));
            if (str_contains($u, 'DISCIPLINAS CURSADAS / DISPENSADAS')) { $section = 'cursada'; continue; }
            if (str_contains($u, 'CURSANDO / REPROVADAS')) { $section = 'cursando'; continue; }
            if (preg_match('/^\s*FALTA CURSAR\s*$/', $u)) { $section = 'falta'; continue; }
            if (preg_match('/ELETIVAS CURSADAS|OPTATIVAS CURSADAS|ATIVIDADE COMPLEMENTAR|RESUMO GERAL/', $u)) { $section = null; continue; }
            if (! $section || ! preg_match('/^\s*([A-Z]{0,3}\d{1,5}(?:\.\d)?)\s+' . self::CODE . '\s+(.+?)(?:\s{2,}(.*))?$/u', $line, $mm)) {
                continue;
            }
            [, $period, $code, $name] = $mm;
            $rest = trim($mm[4] ?? '');
            $restU = Str::upper(Str::ascii($rest));

            $status = match ($section) {
                'cursada' => str_contains($restU, 'DISPENS') || str_contains($restU, 'APROVEIT') ? 'dispensada' : 'aprovada',
                'cursando' => str_contains($restU, 'MATRICULAD') ? 'matriculada'
                    : (preg_match('/TRANC|CANCEL|ABANDON/', $restU) ? 'trancada' : 'reprovada'),
                default => 'falta_cursar',
            };

            $rows[] = $student + [
                'periodo_letivo' => $section !== 'falta' ? $period : '',
                'periodo_matriz' => $section === 'falta' ? $period : '',
                'codigo' => $code,
                'disciplina' => trim($name),
                'carga_horaria' => preg_match('/\b(\d{2,3})\b/', $rest, $ch) ? $ch[1] : '',
                'situacao' => $status,
                'resultado' => $section !== 'falta' ? $rest : '',
            ];
        }

        return ['rows' => $rows, 'student' => $student];
    }
}
