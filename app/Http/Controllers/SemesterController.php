// app/Http/Controllers/SemesterController.php
namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
public function create()
{
return view('semesters.create');
}

public function store(Request $request)
{
$validated = $request->validate([
'code' => 'required|string|unique:semesters,code|max:10',
'start_date' => 'required|date',
'end_date' => 'required|date|after:start_date',
'status' => 'required|in:planning,open,closed',
'is_current' => 'boolean',
'clone_previous' => 'boolean'
]);

DB::transaction(function () use ($validated, $request) {
$isCurrent = $request->has('is_current');

// Se for definido como vigente, remove a vigência de todos os outros
if ($isCurrent) {
Semester::where('is_current', true)->update(['is_current' => false]);
}

$semester = Semester::create([
'code' => $validated['code'],
'start_date' => $validated['start_date'],
'end_date' => $validated['end_date'],
'status' => $validated['status'],
'is_current' => $isCurrent,
]);

// Se solicitado, executa a cópia do planejamento do semestre anterior [1]
if ($request->has('clone_previous')) {
$this->clonePreviousPlanning($semester);
}
});

return redirect()->route('dashboard')
->with('success', 'Semestre letivo cadastrado com sucesso!');
}

private function clonePreviousPlanning(Semester $newSemester)
{
// Encontra o semestre anterior cronologicamente
$previousSemester = Semester::where('id', '!=', $newSemester->id)
->orderBy('code', 'desc')
->first();

if ($previousSemester) {
// Lógica de cópia de ofertas e turmas do período anterior [1]
// Exemplo: Duplicar tabelas de 'offers' e 'classes' associando ao $newSemester->code
}
}
}