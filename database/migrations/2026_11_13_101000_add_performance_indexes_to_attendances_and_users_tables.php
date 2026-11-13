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
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('date', 'attendances_date_index');
            $table->index('status', 'attendances_status_index');
            $table->index('shift_id', 'attendances_shift_id_index');
            $table->index(['date', 'status'], 'attendances_date_status_index');
            $table->index(['date', 'shift_id'], 'attendances_date_shift_id_index');
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->index('timestamp', 'attendance_events_timestamp_index');
            $table->index(['event_type', 'timestamp'], 'attendance_events_event_type_timestamp_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('location_id', 'users_location_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_date_index');
            $table->dropIndex('attendances_status_index');
            $table->dropIndex('attendances_shift_id_index');
            $table->dropIndex('attendances_date_status_index');
            $table->dropIndex('attendances_date_shift_id_index');
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('attendance_events_timestamp_index');
            $table->dropIndex('attendance_events_event_type_timestamp_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_location_id_index');
        });
    }
};
