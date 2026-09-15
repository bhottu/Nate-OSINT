<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_scan_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_scan_id')->constrained('email_scans')->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('identifier', 255)->nullable();
            $table->text('profile_url')->nullable();
            $table->string('status', 30)->index();
            $table->string('confidence', 20)->nullable();
            $table->text('evidence')->nullable();
            $table->text('source_url')->nullable();
            $table->unsignedInteger('response_time')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_scan_results');
    }
};
