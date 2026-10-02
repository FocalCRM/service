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
        $table = config('odden-service.tables.routing_rules', 'odden_service_routing_rules');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('criteria')->nullable();
                $table->json('assigned_user_ids');
                $table->integer('last_assigned_index')->default(-1);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('odden-service.tables.routing_rules', 'odden_service_routing_rules');
        Schema::dropIfExists($table);
    }
};
