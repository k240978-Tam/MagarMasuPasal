<?php

namespace Modules\Expenses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Expenses\Models\Expense;

class ExpenseRecorded
{
    use Dispatchable;

    public function __construct(public Expense $expense) {}
}
