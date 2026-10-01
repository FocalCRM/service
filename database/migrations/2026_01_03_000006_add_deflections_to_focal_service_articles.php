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
        $articlesTable = config('focal-service.tables.articles', 'focal_service_articles');

        Schema::table($articlesTable, function (Blueprint $table): void {
            $table->unsignedInteger('deflections_count')->default(0)->after('not_helpful_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $articlesTable = config('focal-service.tables.articles', 'focal_service_articles');

        Schema::table($articlesTable, function (Blueprint $table): void {
            $table->dropColumn('deflections_count');
        });
    }
};
