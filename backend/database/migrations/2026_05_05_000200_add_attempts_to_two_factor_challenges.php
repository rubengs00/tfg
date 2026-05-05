<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_factor_challenges', function (Blueprint $table): void {
            $table->unsignedTinyInteger('attempts_count')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('two_factor_challenges', function (Blueprint $table): void {
            $table->dropColumn('attempts_count');
        });
    }
};
