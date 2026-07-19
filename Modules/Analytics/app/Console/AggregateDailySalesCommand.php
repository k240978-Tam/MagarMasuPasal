<?php

namespace Modules\Analytics\Console;

use Illuminate\Console\Command;
use Modules\Analytics\Services\AggregationService;

class AggregateDailySalesCommand extends Command
{
    protected $signature = 'analytics:aggregate {date? : Y-m-d, defaults to yesterday}';

    protected $description = 'Roll up completed sales for one day into the analytics aggregate tables';

    public function handle(AggregationService $service): int
    {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();

        $service->aggregateDate($date);

        $this->info("Aggregated sales for {$date}.");

        return self::SUCCESS;
    }
}
