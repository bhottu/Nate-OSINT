<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('username_scan_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('username_scans')->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('username', 64);
            $table->text('profile_url');
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_scan_results');
    }
};
