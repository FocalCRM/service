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
        $ticketsTable = config('odden-service.tables.tickets', 'odden_service_tickets');

        Schema::table($ticketsTable, function (Blueprint $table): void {
            $table->string('portal_token', 64)->nullable()->unique()->after('ticket_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ticketsTable = config('odden-service.tables.tickets', 'odden_service_tickets');

        Schema::table($ticketsTable, function (Blueprint $table): void {
            $table->dropColumn('portal_token');
        });
    }
};
