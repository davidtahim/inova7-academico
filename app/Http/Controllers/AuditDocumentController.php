<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditDocument;
use App\Models\AuditTemplate;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Services\AuditTemplateRenderer;
use App\Services\CcgFor01DataBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuditDocumentController extends Controller
{
    public function create()
    {
        return view('audit.documents.create', ['templates' => AuditTemplate::where('is_current', true)->get(), 'terms' => AcademicTerm::latest('id')->get(), 'courses' => Course::orderBy('name')->get()]);
    }

    public function store(Request $request, CcgFor01DataBuilder $builder, AuditTemplateRenderer $renderer)
    {
        $data = $request->validate(['template_id'=>'required|exists:audit_templates,id','term_id'=>'required|exists:academic_terms,id','course_id'=>'required|exists:courses,id','class_code'=>'required|string','period'=>'required|integer|min:1|max:20','shift'=>'required|in:MANHÃ,TARDE,NOITE','block'=>'nullable|string|max:80']);
        $template = AuditTemplate::findOrFail($data['template_id']); $term = AcademicTerm::findOrFail($data['term_id']); $course = Course::findOrFail($data['course_id']);
        $offerings = ClassOffering::with(['subject','slots.professor','assignments.professor'])->where('academic_term_id',$term->id)->where('course_id',$course->id)->where('class_code',$data['class_code'])->get();
        abort_if($offerings->isEmpty(), 422, 'Nenhuma oferta encontrada para esta turma.');
        $slots = $data['shift'] === 'MANHÃ' ? [['key'=>'0730','start'=>'07:30'],['key'=>'0820','start'=>'08:20'],['key'=>'0910','start'=>'09:10'],['key'=>'1000','start'=>'10:00'],['key'=>'1050','start'=>'10:50']] : [['key'=>'1830','start'=>'18:30'],['key'=>'1920','start'=>'19:20'],['key'=>'2010','start'=>'20:10'],['key'=>'2100','start'=>'21:00']];
        $payload = $builder->build($offerings, ['codigo_documento'=>$template->code,'versao'=>$template->version,'aprovado_por'=>$template->approved_by,'data_modelo'=>optional($template->approved_at)->format('d/m/Y'),'curso'=>$course->name,'semestre_letivo'=>$term->code,'periodo'=>$data['period'].'º','turma'=>$data['class_code'],'turno'=>$data['shift'],'bloco'=>$data['block'] ?? ''], $slots);
        $revision = AuditDocument::where('academic_term_id',$term->id)->where('course_id',$course->id)->where('class_code',$data['class_code'])->max('revision') + 1;
        $document = AuditDocument::create(['audit_template_id'=>$template->id,'academic_term_id'=>$term->id,'course_id'=>$course->id,'class_code'=>$data['class_code'],'period'=>$data['period'],'shift'=>$data['shift'],'revision'=>$revision,'status'=>'issued','data_snapshot'=>$payload,'issued_at'=>now()]);
        $path = "audit-documents/{$term->code}/{$template->code}-{$data['class_code']}-R{$revision}.pdf";
        Storage::disk('local')->put($path, $renderer->pdf($template,$payload)->output()); $document->update(['file_path'=>$path]);
        return $renderer->pdf($template,$payload)->download(basename($path));
    }
}
