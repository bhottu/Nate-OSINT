<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('php2shell_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 16); // single | multiple
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->string('status', 20)->index(); // QUEUED, RUNNING, COMPLETED, PARTIAL, FAILED, CANCELLED
            $table->string('scanner_version', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('php2shell_scans');
    }
};
