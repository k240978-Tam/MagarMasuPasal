<?php

namespace Modules\Accounting\Support;

use App\Support\Nepali\NepaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Resolves a report's date range from the query string, so every financial
 * report accepts the same three ways of asking for a period:
 *
 *   ?bs_month=2083-04      a Nepali month (Shrawan 2083)
 *   ?fiscal_year=2083/84   a whole Nepali fiscal year
 *   ?from=&to=             an explicit AD range
 *
 * Nepali month is the default because that is how a Nepali shop actually
 * closes its books and files VAT.
 */
final class ReportPeriod
{
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $label,
        public readonly ?string $bsMonth,
        public readonly ?string $fiscalYear,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $bsMonth = $request->query('bs_month');
        $fiscalYear = $request->query('fiscal_year');

        if (is_string($bsMonth) && preg_match('/^(\d{4})-(\d{2})$/', $bsMonth, $m)) {
            try {
                [$from, $to] = NepaliDate::monthRange((int) $m[1], (int) $m[2]);

                return new self(
                    $from,
                    $to,
                    NepaliDate::monthLabel((int) $m[1], (int) $m[2]),
                    $bsMonth,
                    null,
                );
            } catch (InvalidArgumentException) {
                // Fall through to the default period rather than 500 on a
                // hand-edited or out-of-range month.
            }
        }

        if (is_string($fiscalYear) && preg_match('/^(\d{4})\/\d{2}$/', $fiscalYear, $m)) {
            try {
                $start = NepaliDate::toAd((int) $m[1], NepaliDate::FISCAL_START_MONTH, 1);

                return new self(
                    $start,
                    NepaliDate::fiscalYearEnd($start),
                    'Fiscal Year '.$fiscalYear,
                    null,
                    $fiscalYear,
                );
            } catch (InvalidArgumentException) {
                // Same: prefer a sensible default over an error page.
            }
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->startOfDay();

            return new self(
                $start,
                $end,
                $start->toDateString().' to '.$end->toDateString(),
                null,
                null,
            );
        }

        return self::currentNepaliMonth();
    }

    /**
     * Reports that read a position rather than a period (trial balance,
     * balance sheet) use the end of the resolved range.
     */
    public function asOf(): CarbonImmutable
    {
        return $this->to;
    }

    public function fromBs(): string
    {
        return NepaliDate::format($this->from);
    }

    public function toBs(): string
    {
        return NepaliDate::format($this->to);
    }

    /**
     * Query parameters that reproduce this period, for export links.
     *
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        if ($this->bsMonth !== null) {
            return ['bs_month' => $this->bsMonth];
        }

        if ($this->fiscalYear !== null) {
            return ['fiscal_year' => $this->fiscalYear];
        }

        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    /**
     * Filename-safe slug for exports, e.g. "shrawan-2083".
     */
    public function slug(): string
    {
        return trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $this->label)), '-');
    }

    private static function currentNepaliMonth(): self
    {
        $bs = NepaliDate::fromAd(now());
        [$from, $to] = NepaliDate::monthRange($bs['year'], $bs['month']);

        return new self(
            $from,
            $to,
            NepaliDate::monthLabel($bs['year'], $bs['month']),
            sprintf('%04d-%02d', $bs['year'], $bs['month']),
            null,
        );
    }
}
