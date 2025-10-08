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
        Schema::table('access_point_sessions', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('floor_id')->nullable()->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_point_sessions', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
            $table->dropForeign(['floor_id']);
            $table->dropColumn(['building_id', 'floor_id']);
        });
    }
};
