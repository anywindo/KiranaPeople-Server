<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_events', 'notes')) {
                $table->text('notes')->nullable()->after('selfie_base64');
            }
            if (!Schema::hasColumn('attendance_events', 'accuracy')) {
                $table->decimal('accuracy', 8, 2)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('attendance_events', 'altitude')) {
                $table->decimal('altitude', 8, 2)->nullable()->after('accuracy');
            }
            if (!Schema::hasColumn('attendance_events', 'is_mocked')) {
                $table->boolean('is_mocked')->default(false)->after('altitude');
            }
            if (!Schema::hasColumn('attendance_events', 'device_timestamp')) {
                $table->timestamp('device_timestamp')->nullable()->after('is_mocked');
            }
            if (!Schema::hasColumn('attendance_events', 'device_telemetry')) {
                $table->json('device_telemetry')->nullable()->after('device_timestamp');
            }

            // Compound index for sequence lookups
            $table->index(['attendance_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex(['attendance_id', 'event_type']);

            $columns = [
                'notes',
                'accuracy',
                'altitude',
                'is_mocked',
                'device_timestamp',
                'device_telemetry'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('attendance_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

