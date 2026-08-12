<?php

namespace App\Support\Export;

use RuntimeException;
use ZipArchive;

/**
 * Writes real .xlsx workbooks — bold headers, currency formatting, frozen
 * header rows, sized columns, and several sheets in one file.
 *
 * Hand-rolled against the Office Open XML spec rather than pulling in a
 * spreadsheet library: the reports here are plain tables, PHP already ships
 * ZipArchive, and an .xlsx is just a zip of XML parts. Strings are written
 * inline (no shared string table), which keeps the writer small and handles
 * Devanagari without extra work.
 */
class XlsxWriter
{
    /** @var array<int, array<string, mixed>> */
    private array $sheets = [];

    // Style indexes, matching the order declared in cellXfs below.
    private const STYLE_DEFAULT = 0;

    private const STYLE_BOLD = 1;

    private const STYLE_HEADER = 2;

    private const STYLE_NUMBER = 3;

    private const STYLE_TOTAL_NUMBER = 4;

    private const STYLE_TITLE = 5;

    private const STYLE_TOTAL_TEXT = 6;

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array{title?: string, subtitles?: array<int, string>, numericColumns?: array<int, int>, totalRows?: array<int, int>, columnWidths?: array<int, float>}  $options
     */
    public function addSheet(string $name, array $headers, array $rows, array $options = []): self
    {
        $this->sheets[] = [
            // Excel rejects these characters in a sheet name and caps it at 31.
            'name' => mb_substr(str_replace(['\\', '/', '*', '?', ':', '[', ']'], '-', $name), 0, 31),
            'headers' => $headers,
            'rows' => $rows,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * Build the workbook and return the raw bytes.
     */
    public function contents(): string
    {
        if ($this->sheets === []) {
            throw new RuntimeException('A workbook needs at least one sheet.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the workbook.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open the workbook archive for writing.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheetXml($sheet));
        }

        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read back the generated workbook.');
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function sheetXml(array $sheet): string
    {
        /** @var array<int, string> $headers */
        $headers = $sheet['headers'];
        /** @var array<int, array<int, mixed>> $rows */
        $rows = $sheet['rows'];
        /** @var array<string, mixed> $options */
        $options = $sheet['options'];

        $numeric = array_flip($options['numericColumns'] ?? []);
        $totalRows = array_flip($options['totalRows'] ?? []);

        $xml = [];
        $rowNumber = 1;

        // Title and subtitle block, so a printed sheet says what it is.
        if (! empty($options['title'])) {
            $xml[] = $this->rowXml($rowNumber++, [$options['title']], fn () => self::STYLE_TITLE);
        }

        foreach ($options['subtitles'] ?? [] as $subtitle) {
            $xml[] = $this->rowXml($rowNumber++, [$subtitle], fn () => self::STYLE_DEFAULT);
        }

        if ($xml !== []) {
            $rowNumber++; // blank spacer row
        }

        $headerRowNumber = $rowNumber;
        $xml[] = $this->rowXml($rowNumber++, $headers, fn () => self::STYLE_HEADER);

        foreach ($rows as $index => $row) {
            $isTotal = isset($totalRows[$index]);

            $xml[] = $this->rowXml($rowNumber++, $row, function (int $column, mixed $value) use ($numeric, $isTotal) {
                $isNumeric = isset($numeric[$column]) && is_numeric($value);

                return match (true) {
                    $isTotal && $isNumeric => self::STYLE_TOTAL_NUMBER,
                    $isTotal => self::STYLE_TOTAL_TEXT,
                    $isNumeric => self::STYLE_NUMBER,
                    default => self::STYLE_DEFAULT,
                };
            });
        }

        $columns = '';

        foreach ($options['columnWidths'] ?? [] as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        // Freeze everything above the first data row so headers stay visible.
        $freezeAt = 'A'.($headerRowNumber + 1);
        $panes = '<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="'.$headerRowNumber.'" topLeftCell="'.$freezeAt.'" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$panes
            .($columns !== '' ? '<cols>'.$columns.'</cols>' : '')
            .'<sheetData>'.implode('', $xml).'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  callable(int, mixed): int  $styleFor
     */
    private function rowXml(int $rowNumber, array $values, callable $styleFor): string
    {
        $cells = '';
        $column = 0;

        foreach ($values as $value) {
            $reference = $this->columnLetter($column).$rowNumber;
            $style = $styleFor($column, $value);

            if ($value === null || $value === '') {
                $cells .= '<c r="'.$reference.'" s="'.$style.'"/>';
            } elseif (is_numeric($value) && ! is_string($value)) {
                $cells .= '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
            } elseif (is_numeric($value) && $this->looksLikeNumber((string) $value)) {
                $cells .= '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
            } else {
                $cells .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            }

            $column++;
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    /**
     * Values like "00123" or "2083/84" must stay text; only plain decimals
     * become real numbers so Excel can total them.
     */
    private function looksLikeNumber(string $value): bool
    {
        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1
            && ! (str_starts_with($value, '0') && strlen($value) > 1 && ! str_starts_with($value, '0.'));
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + ($i % 26)).$letter;
        }

        return $letter;
    }

    private function contentTypesXml(): string
    {
        $sheets = '';

        foreach (array_keys($this->sheets) as $index) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'" '
                .'sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $relationships = '';

        foreach (array_keys($this->sheets) as $index) {
            $relationships .= '<Relationship Id="rId'.($index + 1).'" '
                .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                .'Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $stylesId = count($this->sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'<Relationship Id="rId'.$stylesId.'" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            // Excel requires fill 0 = none and fill 1 = gray125 before any custom fill.
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEFEFEF"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top style="thin"><color rgb="FF000000"/></top><bottom/><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
