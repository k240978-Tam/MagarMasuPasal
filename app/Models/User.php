<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\AuditLog\Traits\Auditable;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\Business;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasApiTokens, HasFactory, HasPublicId, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'locale',
        'phone',
        'password',
        'business_id',
        'default_branch_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Kept out of the audit trail entirely — see Auditable::redactAuditFields().
     *
     * @var list<string>
     */
    protected array $auditExcept = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch')->withPivot('is_default')->withTimestamps();
    }
}
