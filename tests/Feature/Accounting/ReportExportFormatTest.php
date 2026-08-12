<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Support\Export\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * Reports have to leave the system in three shapes — CSV, Excel, PDF — in
 * either language, and the figures must be the same in all of them.
 */
class ReportExportFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider reportFormats
     */
    public function test_reports_export_in_every_format(string $report, string $format, string $signature): void
    {
        $response = $this->actingAs($this->owner())
            ->get("/accounting/reports/{$report}/export?bs_month=2083-04&format={$format}");

        $response->assertOk();

        $body = $format === 'csv' ? $response->streamedContent() : $response->getContent();

        if ($signature === 'csv') {
            // A CSV has no magic bytes; the period stamp is what proves the
            // right report and period came back.
            $this->assertStringContainsString('Shrawan 2083', $body);

            return;
        }

        $this->assertStringStartsWith($signature, $body, "{$report} as {$format} did not produce a {$format} file");
    }

    /**
     * @return array<int, array{string, string, string}>
     */
    public static function reportFormats(): array
    {
        $cases = [];

        foreach (['profit-loss', 'trial-balance', 'balance-sheet', 'cash-book', 'ledger'] as $report) {
            $cases[] = [$report, 'csv', 'csv'];
            $cases[] = [$report, 'xlsx', 'PK'];   // xlsx is a zip
            $cases[] = [$report, 'pdf', '%PDF'];
        }

        return $cases;
    }

    public function test_the_monthly_pack_bundles_every_statement_into_one_pdf(): void
    {
        $response = $this->actingAs($this->owner())
            ->get('/accounting/reports/monthly-pack?bs_month=2083-04&format=pdf');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_unknown_format_falls_back_to_csv_rather_than_failing(): void
    {
        $response = $this->actingAs($this->owner())
            ->get('/accounting/reports/profit-loss/export?bs_month=2083-04&format=exe');

        $response->assertOk();
        $this->assertStringContainsString('Shrawan 2083', $response->streamedContent());
    }

    public function test_the_pdf_embeds_a_devanagari_font_when_the_interface_is_nepali(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['locale' => 'ne'])->save();

        $pdf = $this->actingAs($owner)
            ->get('/accounting/reports/profit-loss/export?bs_month=2083-04&format=pdf')
            ->getContent();

        // Without the embedded font dompdf silently falls back to Times and
        // every Devanagari glyph disappears from the printed report.
        $this->assertStringContainsString('NotoSansDevanagari', $pdf);
    }

    public function test_the_workbook_is_a_readable_archive_with_numeric_cells(): void
    {
        $bytes = (new XlsxWriter)
            ->addSheet('Test', ['Account', 'Amount'], [['Sales', '720.00'], ['जम्मा', '720.00']], [
                'numericColumns' => [1],
                'totalRows' => [1],
            ])
            ->contents();

        $path = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The workbook is not a readable zip archive');

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        // Money must arrive as a number Excel can sum, not as text.
        $this->assertStringContainsString('<v>720.00</v>', $sheet);
        // Devanagari must survive the XML round trip.
        $this->assertStringContainsString('जम्मा', $sheet);
    }

    private function owner(): User
    {
        $branch = BranchFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($branch->business_id);

        Permission::findOrCreate('reports.view', 'web');
        $role = Role::create(['name' => 'Export Viewer', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('reports.view');

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'default_branch_id' => $branch->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
