<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_correlation_scans', function (Blueprint $table) {
            $table->id();
            $table->string('seed_platform', 40);
            $table->string('seed_username', 160);
            $table->text('seed_url')->nullable();
            $table->string('status', 24)->index();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('confidence', 32)->nullable();
            $table->json('results')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_correlation_scans');
    }
};
