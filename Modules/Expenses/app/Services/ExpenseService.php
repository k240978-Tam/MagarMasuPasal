<?php

namespace Modules\Expenses\Services;

use Illuminate\Support\Facades\DB;
use Modules\Expenses\Events\ExpenseRecorded;
use Modules\Expenses\Models\Expense;

class ExpenseService
{
    public function record(array $attributes): Expense
    {
        return DB::transaction(function () use ($attributes) {
            $expense = Expense::create($attributes);

            ExpenseRecorded::dispatch($expense);

            return $expense;
        });
    }
}
