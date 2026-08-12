<?php

namespace Tests\Unit;

use App\Support\Nepali\NepaliDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NepaliDateTest extends TestCase
{
    /**
     * The month table and the year-anchor table are independent sources; if
     * they ever disagree the converter silently produces wrong legal dates on
     * tax invoices, so assert they line up for every covered year.
     */
    public function test_month_lengths_agree_with_published_new_year_anchors(): void
    {
        $reflection = new ReflectionClass(NepaliDate::class);
        $anchors = $reflection->getConstant('YEAR_START_AD');
        $monthDays = $reflection->getConstant('MONTH_DAYS');

        foreach ($monthDays as $bsYear => $months) {
            $this->assertCount(12, $months, "BS {$bsYear} must have 12 months");

            if (! isset($anchors[$bsYear + 1])) {
                continue;
            }

            $expected = (int) CarbonImmutable::parse($anchors[$bsYear])
                ->diffInDays(CarbonImmutable::parse($anchors[$bsYear + 1]));

            $this->assertSame(
                $expected,
                array_sum($months),
                "BS {$bsYear} month lengths must sum to the gap between new-year anchors"
            );
        }
    }

    /**
     * @dataProvider newYearDates
     */
    public function test_converts_known_nepali_new_year_dates(string $ad, int $bsYear): void
    {
        $this->assertSame(
            ['year' => $bsYear, 'month' => 1, 'day' => 1],
            NepaliDate::fromAd($ad)
        );
    }

    /**
     * @return array<int, array{string, int}>
     */
    public static function newYearDates(): array
    {
        return [
            ['2013-04-14', 2070],
            ['2016-04-13', 2073],
            ['2024-04-13', 2081],
            ['2025-04-14', 2082],
            ['2026-04-14', 2083],
        ];
    }

    /**
     * @dataProvider roundTripDates
     */
    public function test_round_trips_ad_to_bs_and_back(string $ad): void
    {
        $bs = NepaliDate::fromAd($ad);

        $this->assertSame($ad, NepaliDate::toAd($bs['year'], $bs['month'], $bs['day'])->toDateString());
    }

    /**
     * @return array<int, array{string}>
     */
    public static function roundTripDates(): array
    {
        return [
            ['2024-04-13'],
            ['2025-01-01'],
            ['2026-07-17'],
            ['2026-08-12'],
            ['2027-03-31'],
        ];
    }

    public function test_formats_bs_dates_for_invoices(): void
    {
        $this->assertSame('2083-04-27', NepaliDate::format('2026-08-12'));
        $this->assertSame('27 Shrawan 2083', NepaliDate::formatLong('2026-08-12'));
    }

    public function test_derives_the_fiscal_year_which_starts_on_shrawan_1(): void
    {
        // Ashadh end, 2083 — the last day of fiscal year 2082/83.
        $this->assertSame('2082/83', NepaliDate::fiscalYear('2026-07-16'));
        // Shrawan 1, 2083 — the first day of fiscal year 2083/84.
        $this->assertSame('2083/84', NepaliDate::fiscalYear('2026-07-17'));
        $this->assertSame('2083/84', NepaliDate::fiscalYear('2026-08-12'));
    }

    public function test_reports_fiscal_year_boundaries_as_ad_dates(): void
    {
        $this->assertSame('2026-07-17', NepaliDate::fiscalYearStart('2026-08-12')->toDateString());
        $this->assertSame('2027-07-16', NepaliDate::fiscalYearEnd('2026-08-12')->toDateString());
    }

    public function test_refuses_dates_outside_the_calendar_data_rather_than_guessing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NepaliDate::fromAd('2099-01-01');
    }
}
