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
        $ticketsTable = config('focal-service.tables.tickets', 'focal_service_tickets');

        Schema::table($ticketsTable, function (Blueprint $table) use ($ticketsTable): void {
            $table->foreignId('merged_into_ticket_id')
                ->nullable()
                ->after('owner_id')
                ->constrained($ticketsTable)
                ->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ticketsTable = config('focal-service.tables.tickets', 'focal_service_tickets');

        Schema::table($ticketsTable, function (Blueprint $table): void {
            $table->dropForeign(['merged_into_ticket_id']);
            $table->dropColumn(['merged_into_ticket_id', 'merged_at']);
        });
    }
};
