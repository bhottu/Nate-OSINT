<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add an unguessable public identifier for result URLs so scan
     * IDs are never exposed as sequential numbers (/email-intelligence/12).
     */
    public function up(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->string('public_id', 32)->nullable()->unique()->after('id');
        });

        $ids = DB::table('email_scans')->whereNull('public_id')->pluck('id');

        foreach ($ids as $id) {
            do {
                $token = Str::random(12);
            } while (DB::table('email_scans')->where('public_id', $token)->exists());

            DB::table('email_scans')->where('id', $id)->update(['public_id' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('email_scans', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
