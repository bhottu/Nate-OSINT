<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('php2shell_scan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('php2shell_scan_id')->constrained()->cascadeOnDelete();
            $table->text('target_url');
            $table->text('normalized_url')->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('verdict', 32)->index(); // VULNERABLE | NOT VULNERABLE | INCONCLUSIVE | ERROR | TIMEOUT | UNREACHABLE
            $table->text('reason')->nullable();
            $table->text('evidence')->nullable(); // telah disanitasi (tanpa secret/kredensial)
            $table->string('sapi', 16)->nullable();
            $table->string('x_powered_by', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('php2shell_scan_targets');
    }
};
