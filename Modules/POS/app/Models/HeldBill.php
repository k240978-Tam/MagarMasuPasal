<?php

namespace Modules\POS\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\BranchTerminal;

class HeldBill extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'branch_id',
        'terminal_id',
        'cashier_id',
        'cart_snapshot',
        'held_at',
    ];

    protected $casts = [
        'cart_snapshot' => 'array',
        'held_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal()
    {
        return $this->belongsTo(BranchTerminal::class, 'terminal_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
