<?php

namespace App\Support\Nepali;

/**
 * Rupee amounts spelled out for tax invoices, using the South Asian
 * numbering system (lakh / crore) rather than the Western million / billion.
 */
final class AmountInWords
{
    private const UNITS = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    public static function rupees(float $amount): string
    {
        $rounded = round($amount, 2);
        $rupees = (int) floor($rounded);
        $paisa = (int) round(($rounded - $rupees) * 100);

        $words = 'Rupees '.self::convert($rupees);

        if ($paisa > 0) {
            $words .= ' and '.self::convert($paisa).' Paisa';
        }

        return $words.' Only';
    }

    private static function convert(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $remainder = $number % 10;

            return self::TENS[intdiv($number, 10)].($remainder ? ' '.self::UNITS[$remainder] : '');
        }

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $value => $label) {
            if ($number >= $value) {
                $remainder = $number % $value;

                return self::convert(intdiv($number, $value)).' '.$label
                    .($remainder ? ' '.self::convert($remainder) : '');
            }
        }

        return self::UNITS[$number];
    }
}
