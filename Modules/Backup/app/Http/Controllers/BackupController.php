<?php

namespace Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Backup\Models\BackupRun;
use Modules\Backup\Services\BackupService;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('backup::index', [
            'runs' => BackupRun::with('triggeredBy')->latest('started_at')->paginate(15),
        ]);
    }

    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        $run = $backups->run('manual', $request->user()->id);

        return back()->with(
            $run->status === 'success' ? 'status' : 'error',
            $run->status === 'success' ? "Backup complete ({$run->sizeHuman()})." : "Backup failed: {$run->failure_reason}",
        );
    }
}
