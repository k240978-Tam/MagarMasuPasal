<?php

namespace Tests\Feature\CashRegister;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\CashRegister\Services\CashSessionService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_record_movements_and_close_computes_variance(): void
    {
        $branch = BranchFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($branch->business_id);
        $terminal = BranchTerminal::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $branch->business_id]);

        $service = app(CashSessionService::class);

        $session = $service->open($branch->business_id, $branch->id, $terminal->id, $user->id, 2000);
        $this->assertSame('open', $session->status);

        $service->recordMovement($session, 'paid_in', 500, 'Change fund top-up', $user->id);
        $service->recordMovement($session, 'paid_out', 150, 'Supplier tea money', $user->id);

        // opening 2000 + paid_in 500 - paid_out 150 (no cash sales in this test) = 2350
        $this->assertSame(2350.0, $service->expectedCash($session));

        $closed = $service->close($session, 2340, $user->id);

        $this->assertSame('closed', $closed->status);
        $this->assertSame('2350.00', $closed->closing_cash_expected);
        $this->assertSame('2340.00', $closed->closing_cash_counted);
        $this->assertSame('-10.00', $closed->variance);
    }

    public function test_a_terminal_cannot_have_two_open_sessions(): void
    {
        $branch = BranchFactory::new()->create();
        $terminal = BranchTerminal::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $branch->business_id]);

        $service = app(CashSessionService::class);
        $service->open($branch->business_id, $branch->id, $terminal->id, $user->id, 1000);

        $this->expectException(\LogicException::class);
        $service->open($branch->business_id, $branch->id, $terminal->id, $user->id, 1000);
    }
}
