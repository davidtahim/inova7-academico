<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AuditItem;
use App\Models\AuditTemplate;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AuditTemplateRenderer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email'=>'coordenacao@inova7.local'], ['name'=>'Larissa Torres','password'=>Hash::make('alterar-senha'),'role'=>'coordenacao']);
        $term = AcademicTerm::updateOrCreate(['code'=>'2026.2'], ['starts_at'=>'2026-08-01','ends_at'=>'2027-01-31','status'=>'planning']);
        $si = Course::updateOrCreate(['code'=>'SI'], ['name'=>'Sistemas de Informação','degree'=>'Bacharelado','active'=>true]);
        $ads = Course::updateOrCreate(['code'=>'ADS'], ['name'=>'Análise e Desenvolvimento de Sistemas','degree'=>'Tecnólogo','active'=>true]);
        $matrixSi = CurriculumMatrix::updateOrCreate(['course_id'=>$si->id,'code'=>'GRA-MAT-0228-F'], ['name'=>'Matriz 15 - Sistemas de Informação','version'=>'15','status'=>'Atual','effective_from'=>'2025-01-01']);
        CurriculumMatrix::updateOrCreate(['course_id'=>$si->id,'code'=>'GRA-MAT-0228-E'], ['name'=>'Matriz 14 - Sistemas de Informação','version'=>'14','status'=>'Ativa']);
        $matrixAds = CurriculumMatrix::updateOrCreate(['course_id'=>$ads->id,'code'=>'GRA-MAT-2140-H'], ['name'=>'Matriz 56 - Análise e Desenvolvimento de Sistemas','version'=>'56','status'=>'Atual']);

        $professors = collect([
            ['registration'=>'UNI727776','name'=>'Larissa Torres Ferreira','qualification'=>'Mestre'],
            ['registration'=>'UNI727711','name'=>'Edson Brito Junior','qualification'=>'Mestre'],
            ['registration'=>'FIL019906','name'=>'Ronaldo Cysne de Medeiros Vasconcelos','qualification'=>'Especialista'],
            ['registration'=>'UNI727720','name'=>'David Tahim Alves Brito','qualification'=>'Mestre'],
            ['registration'=>'UNI73001','name'=>'Francisco Antonio de Araujo e Souza','qualification'=>'Doutor'],
        ])->mapWithKeys(fn($data)=>[$data['registration']=>Professor::updateOrCreate(['registration'=>$data['registration']],$data)]);

        $siOfferings = [
            ['GSER98979','ATIVIDADES PRÁTICAS INTERDISCIPLINARES DE EXTENSÃO I','EXTENSÃO',2,'sexta', '07:30','09:10','Sala 42','UNI727776',2],
            ['GSER133620','BANCO DE DADOS','HÍBRIDA',2,'segunda','09:10','10:50','LI31','UNI727711',2],
            ['GSER133404','CODING','PRESENCIAL/PRÁTICA',2,'quinta','07:30','10:00','LI41','FIL019906',3],
            ['GSER012800','ENGENHARIA DE SOFTWARE','PRESENCIAL',2,'terça','08:20','10:00','LI31','UNI727776',2],
            ['GSER134913','INTELIGÊNCIA ARTIFICIAL APLICADA','NAVEGA',2,'quarta','09:10','11:40',null,null,0],
            ['GSER133619','ORGANIZAÇÃO E ARQUITETURA DE COMPUTADORES','DOL',2,'sábado','07:30','08:20',null,null,0],
            ['GSER133616','PROGRAMAÇÃO - WEB CODING','PRESENCIAL/PRÁTICA',2,'sexta','09:10','10:50','LI42','FIL019906',2],
        ];
        foreach ($siOfferings as $row) $this->offering($term,$si,$matrixSi,'CSE0280102NMA','MANHÃ',$row,$professors);

        $adsOfferings = [
            ['GSER133620','BANCO DE DADOS','HÍBRIDA',2,'terça','09:10','10:50','LI31','UNI727711',2],
            ['GSER133728','CODING','PRESENCIAL',2,'segunda','07:30','11:40','LI41','FIL019906',5],
            ['GSER133733','ENGENHARIA DE REQUISITOS, TESTE E QUALIDADE DE SOFTWARE','NOTÁVEL MESTRE',2,'terça','09:00','12:00',null,null,0],
        ];
        foreach ($adsOfferings as $row) $this->offering($term,$ads,$matrixAds,'CSE0400102NMA','MANHÃ',$row,$professors);

        DB::table('audit_normatives')->updateOrInsert(['code'=>'QUA-INT-08','version'=>'25'], ['name'=>'Instrução de Trabalho para Realização da Auditoria de Curso','approved_at'=>'2025-02-03','approved_by'=>'Francislene Hasmann','is_current'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $item = AuditItem::updateOrCreate(['code'=>'1.12.1'], ['axis'=>'Eixo 1','name'=>'Horários','weight'=>1,'criteria'=>'Apresentar CCG-FOR-01, CCG-FOR-26 e comprovantes de divulgação dos horários dos dois últimos semestres, no modelo padrão e devidamente preenchidos.','eser_category'=>'Horários']);
        $renderer = app(AuditTemplateRenderer::class);
        foreach ([['MANHÃ','CCG-FOR-01-manha-v08.html'],['NOITE','CCG-FOR-01-noite-v08.html']] as [$shift,$file]) {
            $path = "audit-templates/{$file}"; $html = file_get_contents(resource_path("audit-templates/{$file}")); Storage::disk('local')->put($path,$html);
            AuditTemplate::updateOrCreate(['code'=>'CCG-FOR-01','version'=>'08','shift'=>$shift], ['audit_item_id'=>$item->id,'name'=>'Horário de Aula','approved_by'=>'Superintendente Acadêmica','approved_at'=>'2025-12-22','file_path'=>$path,'mime_type'=>'text/html','markers'=>$renderer->markers($html),'is_current'=>true]);
        }
    }

    private function offering($term,$course,$matrix,string $classCode,string $shift,array $row,$professors): void
    {
        [$code,$name,$modality,$period,$day,$starts,$ends,$room,$registration,$weekly] = $row;
        $subject = Subject::firstOrCreate(['code'=>$code,'name'=>$name], ['total_hours'=>max(40,$weekly*20),'presential_hours'=>$weekly*20]);
        $offering = ClassOffering::updateOrCreate(['academic_term_id'=>$term->id,'class_code'=>$classCode,'subject_id'=>$subject->id], ['course_id'=>$course->id,'curriculum_matrix_id'=>$matrix->id,'period'=>$period,'shift'=>$shift,'modality'=>$modality,'occurs'=>true,'weekly_hours'=>$weekly,'totvs_hours'=>$weekly,'status'=>$registration?'planned':'confirmed']);
        $professor = $registration ? $professors->get($registration) : null;
        if ($professor) TeachingAssignment::updateOrCreate(['class_offering_id'=>$offering->id,'professor_id'=>$professor->id], ['weekly_hours'=>$weekly,'status'=>'planned']);
        $weekday = ['segunda'=>1,'terça'=>2,'quarta'=>3,'quinta'=>4,'sexta'=>5,'sábado'=>6][$day];
        ScheduleSlot::updateOrCreate(['class_offering_id'=>$offering->id,'weekday'=>$weekday,'starts_at'=>$starts.':00'], ['professor_id'=>$professor?->id,'ends_at'=>$ends.':00','room'=>$room,'block'=>'4º ANDAR']);
    }
}
