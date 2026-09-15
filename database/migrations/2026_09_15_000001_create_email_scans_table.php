<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_scans', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254);
            $table->string('normalized_email', 254)->index();
            $table->string('local_part', 64);
            $table->string('domain', 253)->index();
            $table->string('status', 20)->default('PENDING')->index();
            $table->unsignedTinyInteger('total_sources_checked')->default(0);
            $table->unsignedTinyInteger('total_potential_matches')->default(0);
            $table->unsignedTinyInteger('total_unverified')->default(0);
            $table->unsignedTinyInteger('total_unable_to_verify')->default(0);
            $table->string('provider', 60)->nullable();
            $table->string('disposable', 10)->default('UNKNOWN');
            $table->json('technical_report')->nullable();
            $table->json('breach_report')->nullable();
            $table->decimal('scan_time_seconds', 8, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_scans');
    }
};
