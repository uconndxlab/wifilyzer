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
        Schema::create('access_point_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('client_username_hash')->nullable();
            $table->string('client_affiliation')->nullable();
            $table->string('client_ip_address')->nullable();
            $table->string('client_mac_address')->nullable();
            $table->timestamp('association_time')->nullable();
            $table->string('ap_name')->nullable();
            $table->string('ssid')->nullable();
            $table->integer('session_duration')->nullable();
            $table->integer('avg_session_throughput')->nullable();
            $table->string('endpoint_type')->nullable();
            $table->timestamp('disassociation_time')->nullable();
            $table->bigInteger('bytes_sent')->nullable();
            $table->bigInteger('bytes_received')->nullable();
            $table->integer('rssi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_point_sessions');
    }
};
