<?php

declare(strict_types=1);

use Odden\Core\Support\UserModel;
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
        $slaTable = config('odden-service.tables.sla_policies', 'odden_service_sla_policies');
        $ticketsTable = config('odden-service.tables.tickets', 'odden_service_tickets');
        $messagesTable = config('odden-service.tables.messages', 'odden_service_ticket_messages');
        $articlesTable = config('odden-service.tables.articles', 'odden_service_articles');
        $cannedResponsesTable = config('odden-service.tables.canned_responses', 'odden_service_canned_responses');

        $contactsTable = config('odden-core.tables.contacts', 'odden_contacts');
        $companiesTable = config('odden-core.tables.companies', 'odden_companies');

        // 1. SLA Policies Table
        Schema::create($slaTable, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Response & resolution target limits in minutes
            $table->unsignedInteger('urgent_first_response_minutes')->default(60);
            $table->unsignedInteger('urgent_resolution_minutes')->default(240);
            $table->unsignedInteger('high_first_response_minutes')->default(120);
            $table->unsignedInteger('high_resolution_minutes')->default(480);
            $table->unsignedInteger('medium_first_response_minutes')->default(240);
            $table->unsignedInteger('medium_resolution_minutes')->default(1440);
            $table->unsignedInteger('low_first_response_minutes')->default(480);
            $table->unsignedInteger('low_resolution_minutes')->default(2880);

            $table->timestamps();
        });

        // 2. Support Tickets Table
        Schema::create($ticketsTable, function (Blueprint $table) use ($slaTable, $contactsTable, $companiesTable): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('source', 30)->default('web_portal')->index();

            $table->foreignId('contact_id')->nullable()->constrained($contactsTable)->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained($companiesTable)->nullOnDelete();
            $table->foreignIdFor(UserModel::className(), 'owner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained($slaTable)->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable()->index();

            // SLA Tracking
            $table->dateTime('first_response_due_at')->nullable()->index();
            $table->dateTime('first_responded_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_sla_response_breached')->default(false);
            $table->boolean('is_sla_resolution_breached')->default(false);

            // CSAT Feedback
            $table->unsignedTinyInteger('csat_rating')->nullable();
            $table->text('csat_comment')->nullable();

            $table->json('properties')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Ticket Messages / Conversation Threads Table
        Schema::create($messagesTable, function (Blueprint $table) use ($ticketsTable, $contactsTable): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained($ticketsTable)->cascadeOnDelete();
            $table->string('sender_type', 20)->default('agent');
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained($contactsTable)->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // 4. Knowledge Base Articles Table
        Schema::create($articlesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('General');
            $table->text('body');
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // 5. Canned Responses / Macros Table
        Schema::create($cannedResponsesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('shortcut')->unique();
            $table->string('category')->default('General');
            $table->text('content');
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_shared')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('odden-service.tables.canned_responses', 'odden_service_canned_responses'));
        Schema::dropIfExists(config('odden-service.tables.articles', 'odden_service_articles'));
        Schema::dropIfExists(config('odden-service.tables.messages', 'odden_service_ticket_messages'));
        Schema::dropIfExists(config('odden-service.tables.tickets', 'odden_service_tickets'));
        Schema::dropIfExists(config('odden-service.tables.sla_policies', 'odden_service_sla_policies'));
    }
};
