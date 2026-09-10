<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_dns_records', function (Blueprint $table) {
            $table->string('hostname')->nullable();
            $table->string('type')->nullable();
            $table->string('value')->nullable();
            $table->timestamp('observed_at');
            $table->integer('ttl');
            $table->enum('source', ['DNS', 'TXT', 'CNAME', 'IP']);
            
            // Unique constraint
            $table->index(['hostname', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_dns_records');
    }
};
