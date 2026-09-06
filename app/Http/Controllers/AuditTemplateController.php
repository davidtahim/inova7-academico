<?php

namespace App\Http\Controllers;

use App\Models\AuditItem;
use App\Models\AuditTemplate;
use App\Services\AuditTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuditTemplateController extends Controller
{
    public function create() { return view('audit.templates.create', ['items' => AuditItem::orderBy('code')->get()]); }

    public function store(Request $request, AuditTemplateRenderer $renderer)
    {
        $data = $request->validate([
            'audit_item_id' => ['required','exists:audit_items,id'], 'code' => ['required','string','max:50'], 'name' => ['required','string','max:180'],
            'version' => ['required','string','max:30'], 'shift' => ['nullable','in:MANHÃ,TARDE,NOITE'], 'approved_by' => ['nullable','string','max:180'],
            'approved_at' => ['nullable','date'], 'template' => ['required','file','mimes:html,htm','max:2048'], 'is_current' => ['nullable','boolean'],
        ]);
        $html = file_get_contents($request->file('template')->getRealPath());
        $markers = $renderer->markers($html);
        abort_if(!$markers, 422, 'O template não possui marcações {{campo}}.');
        $path = $request->file('template')->store('audit-templates');

        $template = DB::transaction(function () use ($data, $path, $markers) {
            if (!empty($data['is_current'])) AuditTemplate::where('code', $data['code'])->where('shift', $data['shift'] ?? null)->update(['is_current' => false]);
            return AuditTemplate::create([...$data, 'file_path' => $path, 'mime_type' => 'text/html', 'markers' => $markers, 'is_current' => (bool)($data['is_current'] ?? false)]);
        });
        return redirect()->route('audit.index')->with('success', "Template {$template->code} carregado com sucesso.");
    }
}
