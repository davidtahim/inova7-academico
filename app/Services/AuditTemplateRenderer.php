<?php

namespace App\Services;

use App\Models\AuditTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AuditTemplateRenderer
{
    /** @return array<int, string> */
    public function markers(string $html): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', $html, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    public function renderHtml(AuditTemplate $template, array $data): string
    {
        $html = Storage::disk('local')->get($template->file_path);
        $missing = [];
        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function ($match) use ($data, &$missing) {
            $value = Arr::get($data, $match[1]);
            if ($value === null) { $missing[] = $match[1]; return $match[0]; }
            return (string) $value;
        }, $html);

        if ($missing) throw new RuntimeException('Marcações sem dados: '.implode(', ', array_unique($missing)));
        return $rendered;
    }

    public function pdf(AuditTemplate $template, array $data)
    {
        return Pdf::loadHTML($this->renderHtml($template, $data))->setPaper('a4', 'landscape');
    }
}
