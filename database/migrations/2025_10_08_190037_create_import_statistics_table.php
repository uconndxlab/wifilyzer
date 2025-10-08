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
        Schema::create('import_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->foreignId('floor_id')->constrained()->onDelete('cascade');
            $table->integer('total_records_processed');
            $table->integer('imported_records');
            $table->integer('short_duration_filtered');
            $table->integer('weak_signal_filtered');
            $table->timestamp('import_date');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_statistics');
    }
};
