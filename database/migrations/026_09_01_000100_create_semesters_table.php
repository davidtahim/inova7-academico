// database/migrations/2026_09_01_000100_create_semesters_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('semesters', function (Blueprint $table) {
$table->id();
$table->string('code')->unique(); // Ex: '2026.2' [1, 3]
$table->date('start_date'); // Data inicial [3]
$table->date('end_date'); // Data final [3]
$table->enum('status', ['planning', 'open', 'closed'])->default('planning'); // Planejamento, Aberto ou Encerrado [3]
$table->boolean('is_current')->default(false); // Indica se é o semestre vigente [3]
$table->timestamps();
});
}

public function down(): void
{
Schema::dropIfExists('semesters');
}
};