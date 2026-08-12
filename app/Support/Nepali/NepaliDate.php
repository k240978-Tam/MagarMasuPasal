<?php

namespace App\Support\Nepali;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Bikram Sambat (BS) ↔ Gregorian (AD) conversion, plus Nepali fiscal-year
 * helpers. IRD requires tax invoices to carry the BS date and to number
 * invoices per fiscal year, which starts on Shrawan 1.
 *
 * Two tables back the conversion, deliberately:
 *
 *  - YEAR_START_AD anchors Baisakh 1 of each BS year to its AD date. These
 *    are published new-year dates and are the source of truth for year
 *    boundaries, so a mistake in a month split can never accumulate across
 *    years.
 *  - MONTH_DAYS holds the day count of each BS month within a year.
 *
 * NepaliDateTest asserts the two agree (each year's months sum to the gap
 * between consecutive anchors). Nepal publishes the official calendar year
 * by year, so month splits for far-future years are provisional: extend and
 * correct both tables from the official calendar rather than extrapolating,
 * and note that conversion throws outside the covered range instead of
 * silently returning a wrong legal date.
 */
final class NepaliDate
{
    /** @var array<int, string> AD date (Y-m-d) of Baisakh 1 for each BS year. */
    private const YEAR_START_AD = [
        2070 => '2013-04-14',
        2071 => '2014-04-14',
        2072 => '2015-04-14',
        2073 => '2016-04-13',
        2074 => '2017-04-14',
        2075 => '2018-04-14',
        2076 => '2019-04-14',
        2077 => '2020-04-13',
        2078 => '2021-04-14',
        2079 => '2022-04-14',
        2080 => '2023-04-14',
        2081 => '2024-04-13',
        2082 => '2025-04-14',
        2083 => '2026-04-14',
        2084 => '2027-04-14',
        2085 => '2028-04-13',
        2086 => '2029-04-14',
        2087 => '2030-04-14',
        2088 => '2031-04-14',
        2089 => '2032-04-13',
        2090 => '2033-04-14',
    ];

    /** @var array<int, array<int, int>> Days in each BS month, Baisakh → Chaitra. */
    private const MONTH_DAYS = [
        2070 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2072 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2073 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2074 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2075 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2078 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2079 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 29, 31],
        2080 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2081 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2082 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2083 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        2084 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        2085 => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        2086 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2087 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        2088 => [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        2089 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    ];

    /** @var array<int, string> */
    public const MONTH_NAMES = [
        1 => 'Baisakh',
        2 => 'Jestha',
        3 => 'Ashadh',
        4 => 'Shrawan',
        5 => 'Bhadra',
        6 => 'Ashwin',
        7 => 'Kartik',
        8 => 'Mangsir',
        9 => 'Poush',
        10 => 'Magh',
        11 => 'Falgun',
        12 => 'Chaitra',
    ];

    /** The BS month that starts the Nepali fiscal year (Shrawan). */
    public const FISCAL_START_MONTH = 4;

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function fromAd(CarbonInterface|string $date): array
    {
        $ad = CarbonImmutable::parse($date)->startOfDay();

        foreach (self::MONTH_DAYS as $bsYear => $months) {
            $yearStart = CarbonImmutable::parse(self::YEAR_START_AD[$bsYear])->startOfDay();
            $offset = $yearStart->diffInDays($ad, false);

            if ($offset < 0) {
                continue;
            }

            foreach ($months as $index => $daysInMonth) {
                if ($offset < $daysInMonth) {
                    return [
                        'year' => $bsYear,
                        'month' => $index + 1,
                        'day' => (int) $offset + 1,
                    ];
                }

                $offset -= $daysInMonth;
            }
        }

        throw new InvalidArgumentException(
            "Date [{$ad->toDateString()}] falls outside the Bikram Sambat calendar data. ".
            'Extend NepaliDate::YEAR_START_AD and MONTH_DAYS from the official Nepali calendar.'
        );
    }

    public static function toAd(int $year, int $month, int $day): CarbonImmutable
    {
        if (! isset(self::MONTH_DAYS[$year])) {
            throw new InvalidArgumentException("Bikram Sambat year [{$year}] is outside the calendar data.");
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Bikram Sambat month [{$month}] is out of range.");
        }

        $months = self::MONTH_DAYS[$year];

        if ($day < 1 || $day > $months[$month - 1]) {
            throw new InvalidArgumentException("Bikram Sambat day [{$day}] is out of range for {$year}-{$month}.");
        }

        $offset = $day - 1;

        for ($i = 0; $i < $month - 1; $i++) {
            $offset += $months[$i];
        }

        return CarbonImmutable::parse(self::YEAR_START_AD[$year])->startOfDay()->addDays($offset);
    }

    /**
     * Numeric BS date, e.g. "2083-04-27" — the form IRD's CBMS expects.
     */
    public static function format(CarbonInterface|string $date): string
    {
        $bs = self::fromAd($date);

        return sprintf('%04d-%02d-%02d', $bs['year'], $bs['month'], $bs['day']);
    }

    /**
     * Human-readable BS date, e.g. "27 Shrawan 2083".
     */
    public static function formatLong(CarbonInterface|string $date): string
    {
        $bs = self::fromAd($date);

        return sprintf('%d %s %d', $bs['day'], self::MONTH_NAMES[$bs['month']], $bs['year']);
    }

    /**
     * First and last AD dates of a BS month — the range behind "Shrawan 2083"
     * as a report period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function monthRange(int $year, int $month): array
    {
        if (! isset(self::MONTH_DAYS[$year])) {
            throw new InvalidArgumentException("Bikram Sambat year [{$year}] is outside the calendar data.");
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Bikram Sambat month [{$month}] is out of range.");
        }

        $start = self::toAd($year, $month, 1);
        $end = $start->addDays(self::MONTH_DAYS[$year][$month - 1] - 1);

        return [$start, $end];
    }

    public static function monthLabel(int $year, int $month): string
    {
        return self::MONTH_NAMES[$month].' '.$year;
    }

    /**
     * BS months ending at the given date, newest first, for a month picker.
     * Months outside the calendar data are skipped rather than guessed.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function recentMonths(int $count = 24, CarbonInterface|string|null $endingAt = null): array
    {
        $current = self::fromAd($endingAt ?? now());
        $year = $current['year'];
        $month = $current['month'];
        $months = [];

        for ($i = 0; $i < $count; $i++) {
            if (isset(self::MONTH_DAYS[$year])) {
                $months[] = [
                    'value' => sprintf('%04d-%02d', $year, $month),
                    'label' => self::monthLabel($year, $month),
                ];
            }

            $month--;

            if ($month < 1) {
                $month = 12;
                $year--;
            }
        }

        return $months;
    }

    /**
     * Nepali fiscal year label for an AD date, e.g. "2083/84". The year runs
     * Shrawan 1 → Ashadh end, so dates before Shrawan belong to the fiscal
     * year that opened in the previous BS year.
     */
    public static function fiscalYear(CarbonInterface|string $date): string
    {
        $bs = self::fromAd($date);
        $startYear = $bs['month'] >= self::FISCAL_START_MONTH ? $bs['year'] : $bs['year'] - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }

    /**
     * First AD date of the fiscal year containing the given date.
     */
    public static function fiscalYearStart(CarbonInterface|string $date): CarbonImmutable
    {
        $bs = self::fromAd($date);
        $startYear = $bs['month'] >= self::FISCAL_START_MONTH ? $bs['year'] : $bs['year'] - 1;

        return self::toAd($startYear, self::FISCAL_START_MONTH, 1);
    }

    /**
     * Last AD date of the fiscal year containing the given date.
     */
    public static function fiscalYearEnd(CarbonInterface|string $date): CarbonImmutable
    {
        return self::fiscalYearStart($date)->addYear()->subDay();
    }
}
