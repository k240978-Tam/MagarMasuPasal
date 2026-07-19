<?php

namespace App\Support\Tenancy;

use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Default tenant resolution: the business/branch come from the authenticated
 * principal (web session cookie or Sanctum token) — never from a client-supplied
 * field, which would let one tenant spoof another.
 */
class AuthenticatedUserTenantResolver implements TenantResolver
{
    public function __construct(
        protected TenantContext $context,
        protected AuthFactory $auth,
    ) {}

    public function resolve(): void
    {
        $user = $this->auth->guard()->user();

        if (! $user) {
            return;
        }

        if ($businessId = $user->business_id) {
            $this->context->setBusinessId($businessId);
        }

        if ($branchId = $user->default_branch_id) {
            $this->context->setBranchId($branchId);
        }
    }
}
