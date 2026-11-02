<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset Spatie cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure Employee role exists for web guard
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep role for data integrity
    }
};
