<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('professors', function (Blueprint $table) {
            if (! Schema::hasColumn('professors', 'chapa')) {
                $table->string('chapa')->nullable()->after('qualification');
            }

            if (! Schema::hasColumn('professors', 'lattes')) {
                $table->string('lattes')->nullable()->after('chapa');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professors', function (Blueprint $table) {
            if (Schema::hasColumn('professors', 'lattes')) {
                $table->dropColumn('lattes');
            }

            if (Schema::hasColumn('professors', 'chapa')) {
                $table->dropColumn('chapa');
            }
        });
    }
};
