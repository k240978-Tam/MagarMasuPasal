<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The analytics/notification/backup commands are scheduled via each
 * module's configureSchedules() (an nwidart/laravel-modules hook), not
 * bootstrap/app.php — easy to miss on a quick grep. This guards two things
 * found during the Phase 8 launch review: that every expected command is
 * actually registered, and that each one is anchored to Asia/Kathmandu
 * rather than the server's own UTC clock (an un-anchored dailyAt() would
 * run backups/aggregation in the middle of the shop's business hours).
 */
class ScheduleTest extends TestCase
{
    public function test_all_expected_commands_are_scheduled_against_kathmandu_time(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach ([
            'backup:run',
            'backup:verify',
            'analytics:aggregate',
            'notifications:daily-summary',
            'notifications:low-stock',
            'notifications:dues',
        ] as $command) {
            $event = $events->first(fn ($e) => str_contains($e->command ?? '', $command));

            $this->assertNotNull($event, "Expected the scheduler to run [{$command}].");
            $this->assertSame('Asia/Kathmandu', $event->timezone, "[{$command}] must run on Nepal time, not the server's own clock.");
        }
    }
}
