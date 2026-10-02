<?php

declare(strict_types=1);

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
        $table = config('odden-service.tables.sla_policies', 'odden_service_sla_policies');

        if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'only_business_hours')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('only_business_hours')->default(false)->after('is_active');
                $table->string('business_hours_start')->default('09:00')->after('only_business_hours');
                $table->string('business_hours_end')->default('17:00')->after('business_hours_start');
                $table->json('business_days')->nullable()->after('business_hours_end');
                $table->json('holidays')->nullable()->after('business_days');
                $table->string('timezone')->default('UTC')->after('holidays');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('odden-service.tables.sla_policies', 'odden_service_sla_policies');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'only_business_hours')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn([
                    'only_business_hours',
                    'business_hours_start',
                    'business_hours_end',
                    'business_days',
                    'holidays',
                    'timezone',
                ]);
            });
        }
    }
};
