<?php

declare(strict_types=1);

namespace Odden\Service\Console\Commands;

use Odden\Service\Actions\CheckSlaBreachesAction;
use Illuminate\Console\Command;

class CheckSlaBreachesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect open customer support tickets and flag first-response or resolution SLA breaches';

    /**
     * Execute the console command.
     */
    public function handle(CheckSlaBreachesAction $action): int
    {
        $this->info('Evaluating support ticket SLA policies...');

        $stats = $action->execute();

        $this->table(
            ['Metric', 'Breaches Flagged'],
            [
                ['First Response SLA Breaches', $stats['response_breaches']],
                ['Resolution SLA Breaches', $stats['resolution_breaches']],
            ]
        );

        $this->info('SLA breach evaluation completed successfully.');

        return self::SUCCESS;
    }
}
