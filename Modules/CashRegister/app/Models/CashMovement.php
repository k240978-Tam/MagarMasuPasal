<?php

namespace Modules\CashRegister\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'cash_session_id',
        'type',
        'amount',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
