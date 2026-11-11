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
        // 1. Drop unused tables (0 records, unused in application)
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('business_trip_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('shift_change_requests');

        // 2. Drop unused columns from users
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'direct_supervisor_id')) {
                try {
                    $table->dropForeign(['direct_supervisor_id']);
                } catch (\Throwable $e) {
                }
            }

            if (Schema::hasColumn('users', 'immediate_manager_id')) {
                try {
                    $table->dropForeign(['immediate_manager_id']);
                } catch (\Throwable $e) {
                }
            }

            $userColsToDrop = array_values(array_filter(
                ['direct_supervisor_id', 'immediate_manager_id', 'avatar_base64'],
                fn (string $col) => Schema::hasColumn('users', $col)
            ));

            if (! empty($userColsToDrop)) {
                $table->dropColumn($userColsToDrop);
            }
        });

        // 3. Drop unused columns from attendance_events
        Schema::table('attendance_events', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_events', 'selfie_base64')) {
                $table->dropColumn('selfie_base64');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create users columns
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'direct_supervisor_id')) {
                $table->foreignId('direct_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'immediate_manager_id')) {
                $table->foreignId('immediate_manager_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'avatar_base64')) {
                $table->longText('avatar_base64')->nullable()->after('avatar');
            }
        });

        // Re-create attendance_events selfie_base64
        Schema::table('attendance_events', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_events', 'selfie_base64')) {
                $table->text('selfie_base64')->nullable()->after('selfie_path');
            }
        });

        // Re-create tables
        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type');
                $table->date('start_date');
                $table->date('end_date');
                $table->text('reason');
                $table->string('status')->default('PENDING');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_trip_requests')) {
            Schema::create('business_trip_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('destination');
                $table->date('start_date');
                $table->date('end_date');
                $table->text('purpose');
                $table->string('status')->default('PENDING');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->time('start_time');
                $table->time('end_time');
                $table->text('reason');
                $table->string('status')->default('PENDING');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shift_change_requests')) {
            Schema::create('shift_change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('target_date');
                $table->foreignId('requested_shift_id')->constrained('shifts')->cascadeOnDelete();
                $table->text('reason');
                $table->string('status')->default('PENDING');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }
    }
};
