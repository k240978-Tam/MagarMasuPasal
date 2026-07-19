<?php

namespace Modules\CustomerDisplay\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Tenancy\Models\BranchTerminal;

class DisplayScreenController extends Controller
{
    public function __invoke(BranchTerminal $terminal): View
    {
        $terminal->load('branch.business');

        return view('customerdisplay::screen', [
            'terminal' => $terminal,
            'business' => $terminal->branch->business,
        ]);
    }
}
