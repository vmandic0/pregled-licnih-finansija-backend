<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'transactions:generate-recurring';

    protected $description = 'Generate due transactions from active recurring transaction templates';

    public function handle(RecurringTransactionService $service): int
    {
        $created = $service->generateDue();

        $this->info("Generated {$created} recurring transaction(s).");

        return self::SUCCESS;
    }
}
