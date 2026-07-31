<?php

namespace Modules\POS\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<BranchTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(BranchTerminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
