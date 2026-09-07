// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'registration_number' => 'nullable|string|max:20|unique:users',
            'role' => 'required|in:admin,coordinator,teacher,student,staff',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'registration_number' => $validated['registration_number'],
            'role' => $validated['role'],
            'password' => $validated['password'], // Hash gerado automaticamente pelo Model cast 'hashed'
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Novo usuário cadastrado com sucesso!');
    }
}
