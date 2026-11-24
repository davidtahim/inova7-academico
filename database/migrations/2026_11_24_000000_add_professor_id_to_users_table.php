<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'professor_id')) {
                $table->foreignId('professor_id')
                    ->nullable()
                    ->constrained('professors')
                    ->nullOnDelete()
                    ->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'professor_id')) {
                $table->dropForeignIdFor('professors', 'professor_id');
                $table->dropColumn('professor_id');
            }
        });
    }
};
