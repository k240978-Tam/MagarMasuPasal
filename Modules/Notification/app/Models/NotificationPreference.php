<?php

namespace Modules\Notification\Models;

use App\Support\Tenancy\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'user_id',
        'channel',
        'type',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
