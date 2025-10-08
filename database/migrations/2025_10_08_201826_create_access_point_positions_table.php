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
        Schema::create('access_point_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->onDelete('cascade');
            $table->string('ap_name');
            $table->decimal('x_percentage', 5, 2); // 0-100 with 2 decimal places
            $table->decimal('y_percentage', 5, 2); // 0-100 with 2 decimal places
            $table->timestamps();
            
            // Ensure one position per AP per floor
            $table->unique(['floor_id', 'ap_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_point_positions');
    }
};
