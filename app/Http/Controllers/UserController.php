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

public function profile()
{
$user = auth()->user();

return view('users.profile', [
'user' => $user,
'roles' => User::roleOptions(),
]);
}

public function editProfile()
{
$user = auth()->user();

return view('users.profile-edit', [
'user' => $user,
'roles' => User::roleOptions(),
]);
}

public function updateProfile(Request $request)
{
$user = auth()->user();

$validated = $request->validate([
'name' => 'required|string|max:255',
'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
'registration_number' => ['nullable', 'string', 'max:20', 'unique:users,registration_number,' . $user->id],
'role' => 'required|in:admin,coordinator,teacher,student,staff',
'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
]);

$user->fill([
'name' => $validated['name'],
'email' => $validated['email'],
'registration_number' => $validated['registration_number'] ?? null,
'role' => $validated['role'],
]);

if (! empty($validated['password'])) {
$user->password = $validated['password'];
}

$user->save();

return redirect()->route('profile')->with('success', 'Perfil atualizado com sucesso!');
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
'password' => $validated['password'],
'is_active' => $request->has('is_active'),
]);

return redirect()->route('dashboard')->with('success', 'Novo usuário cadastrado com sucesso!');
}
}