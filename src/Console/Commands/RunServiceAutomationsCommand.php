<?php

declare(strict_types=1);

namespace Focal\Service\Console\Commands;

use Focal\Service\Actions\RunServiceAutomationsAction;
use Illuminate\Console\Command;

class RunServiceAutomationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service:run-automations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute automated ticket lifecycle maintenance (inactivity closure, resolved archiving)';

    /**
     * Execute the console command.
     */
    public function handle(RunServiceAutomationsAction $action): int
    {
        $this->info('Running support service lifecycle automations...');

        $results = $action->execute();

        $this->table(
            ['Automation Action', 'Tickets Processed'],
            [
                ['Inactivity Auto-Closed (>7d waiting)', $results['inactivity_closed']],
                ['Resolved Auto-Archived (>48h resolved)', $results['resolved_closed']],
            ]
        );

        $this->info('Service automations completed successfully.');

        return self::SUCCESS;
    }
}
