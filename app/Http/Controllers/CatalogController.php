<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CatalogController extends Controller
{
    public function courses()
    {
        $courses = Course::with(['matrices' => function ($query) {
            $query->with(['subjects' => function ($subjectQuery) {
                $subjectQuery->orderBy('name');
            }]);
        }])
            ->orderBy('name')
            ->get();

        return view('catalog.courses', [
            'courses' => $courses,
        ]);
    }

    public function professors()
    {
        $professors = Professor::with(['subjects', 'availabilities'])
            ->orderBy('name')
            ->get();

        return view('catalog.professors', [
            'professors' => $professors,
        ]);
    }

    public function subjects(Request $request)
    {
        $query = $request->input('q');

        $subjects = Subject::with('professors')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('catalog.subjects', [
            'subjects' => $subjects,
            'query' => $query,
        ]);
    }

    public function students(Request $request)
    {
        $query = $request->input('q');

        $students = User::where('role', 'student')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");

                    if (Schema::hasColumn('users', 'registration_number')) {
                        $inner->orWhere('registration_number', 'like', "%{$query}%");
                    }
                });
            })
            ->orderBy('name')
            ->get();

        return view('catalog.students', [
            'students' => $students,
            'query' => $query,
        ]);
    }

    public function studentDetail(User $user)
    {
        abort_unless($user->role === 'student', 404);

        $records = $user->studentAcademicRecords()
            ->with(['academicTerm', 'course', 'subject'])
            ->orderByDesc('created_at')
            ->get();

        return view('catalog.student-detail', [
            'student' => $user,
            'records' => $records,
        ]);
    }
}
