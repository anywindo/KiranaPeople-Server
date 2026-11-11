<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_events', 'selfie_base64')) {
            Schema::table('attendance_events', function (Blueprint $table) {
                $table->text('selfie_base64')->nullable()->after('selfie_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('attendance_events', 'selfie_base64')) {
            Schema::table('attendance_events', function (Blueprint $table) {
                $table->dropColumn('selfie_base64');
            });
        }
    }
};
