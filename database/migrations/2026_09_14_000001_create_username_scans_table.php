<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('username_scans', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64);
            $table->string('normalized_username', 64)->index();
            $table->unsignedTinyInteger('total_checked')->default(0);
            $table->unsignedTinyInteger('total_found')->default(0);
            $table->string('status', 20)->default('PENDING')->index();
            $table->decimal('scan_time_seconds', 8, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_scans');
    }
};
