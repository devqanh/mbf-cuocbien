<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheet_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0)->after('payload');
            $table->foreignId('updated_by')->nullable()->after('version')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sheet_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('version');
        });
    }
};
