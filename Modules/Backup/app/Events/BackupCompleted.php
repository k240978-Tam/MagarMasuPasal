<?php

namespace Modules\Backup\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Backup\Models\BackupRun;

/**
 * Fired once a backup run finishes, success or failure. Notification hangs
 * off this the same way it hangs off SaleCompleted — Backup never imports
 * Notification directly.
 */
class BackupCompleted
{
    use Dispatchable;

    public function __construct(public BackupRun $run) {}
}
