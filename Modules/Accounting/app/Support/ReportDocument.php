<?php

namespace Modules\Accounting\Support;

use App\Support\Export\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A report rendered once as headers/rows, then emitted as CSV, Excel or PDF.
 *
 * Keeping one shape for all three formats is what stops the figures drifting
 * apart between what the screen shows, what the accountant opens in Excel,
 * and what gets signed and filed.
 */
class ReportDocument
{
    /** @var array<int, array<string, mixed>> */
    protected array $sections = [];

    public function __construct(
        public readonly string $title,
        public readonly ReportPeriod $period,
        public readonly string $filename,
    ) {}

    public static function make(string $title, ReportPeriod $period, string $filename): self
    {
        return new self($title, $period, $filename);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $numericColumns  zero-based columns holding money
     * @param  array<int, int>  $totalRows  zero-based row indexes to emphasise
     * @param  array<string, string>  $summary
     * @param  array<int, float>  $columnWidths
     */
    public function addSection(
        string $title,
        array $headers,
        array $rows,
        array $numericColumns = [],
        array $totalRows = [],
        array $summary = [],
        array $columnWidths = [],
    ): self {
        $this->sections[] = compact('title', 'headers', 'rows', 'numericColumns', 'totalRows', 'summary', 'columnWidths');

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    public function toCsv(): StreamedResponse
    {
        $sections = $this->sections;
        $period = $this->period;
        $title = $this->title;

        return response()->streamDownload(function () use ($sections, $period, $title) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [$title]);
            fputcsv($handle, [__('reports.period'), $period->label]);
            fputcsv($handle, [__('reports.range_bs'), $period->fromBs().' — '.$period->toBs()]);
            fputcsv($handle, [__('reports.range_ad'), $period->from->toDateString().' — '.$period->to->toDateString()]);

            foreach ($sections as $section) {
                fputcsv($handle, []);

                if (count($sections) > 1) {
                    fputcsv($handle, [$section['title']]);
                }

                fputcsv($handle, $section['headers']);

                foreach ($section['rows'] as $row) {
                    fputcsv($handle, $row);
                }

                foreach ($section['summary'] as $label => $value) {
                    fputcsv($handle, ['', $label, $value]);
                }
            }

            fclose($handle);
        }, "{$this->filename}.csv", ['Content-Type' => 'text/csv']);
    }

    public function toXlsx(): Response
    {
        $writer = new XlsxWriter;

        foreach ($this->sections as $index => $section) {
            $rows = $section['rows'];
            $totalRows = $section['totalRows'];

            // Summary lines become emphasised rows at the foot of the sheet,
            // so the workbook carries the same totals as the PDF.
            foreach ($section['summary'] as $label => $value) {
                $row = array_fill(0, max(1, count($section['headers'])), '');
                $row[0] = $label;
                $row[count($section['headers']) - 1] = $value;
                $totalRows[] = count($rows);
                $rows[] = $row;
            }

            $writer->addSheet(
                $section['title'] !== '' ? $section['title'] : 'Sheet'.($index + 1),
                $section['headers'],
                $rows,
                [
                    'title' => $index === 0 ? $this->title : $section['title'],
                    'subtitles' => [
                        __('reports.period').': '.$this->period->label,
                        __('reports.range_bs').': '.$this->period->fromBs().' — '.$this->period->toBs(),
                        __('reports.range_ad').': '.$this->period->from->toDateString().' — '.$this->period->to->toDateString(),
                    ],
                    'numericColumns' => $section['numericColumns'],
                    'totalRows' => $totalRows,
                    'columnWidths' => $section['columnWidths'],
                ],
            );
        }

        return response($writer->contents(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename.'.xlsx"',
        ]);
    }

    public function toPdf(): Response
    {
        $user = Auth::user();
        $business = $user?->business;

        $pdf = Pdf::loadView('exports.report-pdf', [
            'title' => $this->title,
            'period' => $this->period,
            'sections' => $this->sections,
            'business' => $business,
            'sellerPan' => $business?->pan_vat_number,
            'branchLine' => $user?->defaultBranch?->name ?? __('nav.all_branches'),
        ])->setPaper('a4', 'portrait');

        // dompdf needs this on to resolve the @font-face file:// path that
        // embeds the Devanagari font.
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf->download("{$this->filename}.pdf");
    }

    /**
     * @return Response|StreamedResponse
     */
    public function render(string $format)
    {
        return match ($format) {
            'pdf' => $this->toPdf(),
            'xlsx', 'excel' => $this->toXlsx(),
            default => $this->toCsv(),
        };
    }
}
