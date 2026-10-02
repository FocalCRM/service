<?php

declare(strict_types=1);

namespace Focal\Service;

use Focal\Core\Events\CompaniesMerged;
use Focal\Core\Events\ContactsMerged;
use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Service\Listeners\MoveMergedRecords;
use Focal\Service\Models\Ticket;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ServiceHubServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/focal-service.php',
            'focal-service'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'focal-service');
        if (config('focal-service.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (class_exists(Contact::class)) {
            Contact::resolveRelationUsing('tickets', function (Contact $contact) {
                return $contact->hasMany(Ticket::class, 'contact_id');
            });
        }

        if (class_exists(Company::class)) {
            Company::resolveRelationUsing('tickets', function (Company $company) {
                return $company->hasMany(Ticket::class, 'company_id');
            });
        }

        // Synchronous on purpose: these run inside Core's merge transaction.
        Event::listen(ContactsMerged::class, [MoveMergedRecords::class, 'handleContactsMerged']);
        Event::listen(CompaniesMerged::class, [MoveMergedRecords::class, 'handleCompaniesMerged']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\CheckSlaBreachesCommand::class,
                Console\Commands\RunServiceAutomationsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/focal-service.php' => config_path('focal-service.php'),
            ], 'focal-service-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'focal-service-migrations');
        }
    }
}
