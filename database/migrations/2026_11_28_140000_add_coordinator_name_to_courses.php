<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nome do coordenador do curso, impresso no CCG-FOR-02 ("SISTEMAS DE INFORMAÇÃO – PROF. ..."). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'coordinator_name')) {
            Schema::table('courses', fn (Blueprint $table) => $table->string('coordinator_name')->nullable()->after('degree'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'coordinator_name')) {
            Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('coordinator_name'));
        }
    }
};
