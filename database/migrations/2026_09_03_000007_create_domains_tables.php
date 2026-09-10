<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->string('domain')->unique()->primary();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            // Security scores
            $table->integer('security_score')->default(0)->nullable(true);
            $table->text('security_summary')->nullable();
            $table->enum('security_status', ['GOOD', 'WARNING', 'HIGH', 'CRITICAL']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
