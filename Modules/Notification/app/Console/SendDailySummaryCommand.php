<?php

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Notification\Notifications\DailySummaryDigest;
use Modules\Notification\Services\NotificationDispatchService;
use Modules\Reports\Services\ReportService;
use Modules\Tenancy\Models\Business;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'notifications:daily-summary {date? : Y-m-d, defaults to yesterday}';

    protected $description = 'Send each business\'s management yesterday\'s sales summary.';

    public function handle(ReportService $reports, NotificationDispatchService $dispatch): int
    {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();

        Business::where('status', 'active')->each(function (Business $business) use ($date, $reports, $dispatch) {
            $sales = $reports->salesReport($business->id, null, $date, $date);
            $topProducts = $reports->topSellingProducts($business->id, null, $date, $date, 1);

            $dispatch->notifyManagement($business->id, new DailySummaryDigest(
                date: $date,
                totalSales: (float) $sales->sum('total_amount'),
                transactionCount: $sales->count(),
                topProduct: $topProducts->first()->name ?? null,
            ));
        });

        $this->info("Daily summaries sent for {$date}.");

        return self::SUCCESS;
    }
}
