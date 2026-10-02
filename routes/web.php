<?php

declare(strict_types=1);

use Focal\Core\Http\Middleware\RequireApiToken;
use Focal\Core\Support\CsrfExemption;
use Focal\Core\Support\RouteGroup;
use Focal\Service\Http\Controllers\ChatWidgetController;
use Focal\Service\Http\Controllers\HelpCenterController;
use Focal\Service\Http\Controllers\InboundEmailWebhookController;
use Focal\Service\Http\Controllers\KnowledgeDeflectionController;
use Focal\Service\Http\Controllers\SupportPortalController;
use Illuminate\Support\Facades\Route;

Route::group(RouteGroup::attributes('focal-service.routes.web'), function (): void {
    // Knowledge Base / Help Center
    Route::get('/help', [HelpCenterController::class, 'index'])->name('focal.help.index');
    Route::get('/help/{slug}', [HelpCenterController::class, 'show'])->name('focal.help.show');
    Route::post('/help/{slug}/vote', [HelpCenterController::class, 'vote'])
        ->middleware('throttle:focal-public')
        ->name('focal.help.vote');

    // Customer Support Ticket Portal
    Route::get('/support', [SupportPortalController::class, 'create'])->name('focal.support.create');
    Route::post('/support', [SupportPortalController::class, 'store'])
        ->middleware('throttle:focal-public')
        ->name('focal.support.store');
    Route::get('/support/tickets/{token}', [SupportPortalController::class, 'show'])->name('focal.support.show');
    Route::post('/support/tickets/{token}/reply', [SupportPortalController::class, 'reply'])
        ->middleware('throttle:focal-public')
        ->name('focal.support.reply');
    Route::get('/support/rate/{token}', [SupportPortalController::class, 'rate'])->name('focal.support.rate');
    Route::post('/support/rate/{token}', [SupportPortalController::class, 'submitRating'])
        ->middleware('throttle:focal-public')
        ->name('focal.support.submitRating');
});

Route::group(RouteGroup::attributes('focal-service.routes.api'), function (): void {
    // Inbound Email-to-Ticket Webhook: server-to-server, requires the service API token.
    Route::post('/inbound-email', InboundEmailWebhookController::class)
        ->withoutMiddleware(CsrfExemption::middleware())
        ->middleware([RequireApiToken::class.':focal-service.api.token', 'throttle:focal-api'])
        ->name('focal.service.inbound-email');

    // AI Knowledge Deflection & Smart Suggestions
    Route::get('/knowledge/suggest', [KnowledgeDeflectionController::class, 'suggest'])
        ->name('focal.service.knowledge.suggest');
    Route::post('/knowledge/deflect', [KnowledgeDeflectionController::class, 'deflect'])
        ->withoutMiddleware(CsrfExemption::middleware())
        ->middleware('throttle:focal-public')
        ->name('focal.service.knowledge.deflect');

    // Embeddable Web Chat & Support Messenger
    Route::post('/chat/start', [ChatWidgetController::class, 'start'])
        ->withoutMiddleware(CsrfExemption::middleware())
        ->middleware('throttle:focal-public')
        ->name('focal.service.chat.start');
    Route::post('/chat/{token}/message', [ChatWidgetController::class, 'message'])
        ->withoutMiddleware(CsrfExemption::middleware())
        ->middleware('throttle:focal-public')
        ->name('focal.service.chat.message');
    Route::get('/chat/{token}/messages', [ChatWidgetController::class, 'messages'])
        ->name('focal.service.chat.messages');
});
