<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_scans', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->nullable();
            $table->string('company_name')->nullable();
            $table->text('target_url');
            $table->string('normalized_domain');
            $table->string('status', 20)->index();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_scans');
    }
};
