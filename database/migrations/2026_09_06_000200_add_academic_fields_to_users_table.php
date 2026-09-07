use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->unique()->after('id'); // RA / Matrícula
            $table->enum('role', ['admin', 'coordinator', 'teacher', 'student', 'staff'])->default('student')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'role', 'is_active']);
        });
    }
};