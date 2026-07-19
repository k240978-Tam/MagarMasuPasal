<?php

namespace Modules\CashRegister\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\CashRegister\Models\CashSession;

class CashSessionClosed
{
    use Dispatchable;

    public function __construct(public CashSession $session) {}
}
