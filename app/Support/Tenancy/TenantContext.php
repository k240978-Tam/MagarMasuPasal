<?php

namespace App\Support\Tenancy;

/**
 * Request-scoped holder for the resolved tenant (business) and, optionally,
 * the active branch. Bound as a singleton — populated once per request by a
 * TenantResolver and read everywhere else (global scope, policies, services).
 */
class TenantContext
{
    protected ?int $businessId = null;

    protected ?int $branchId = null;

    public function setBusinessId(?int $businessId): void
    {
        $this->businessId = $businessId;
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function hasBusiness(): bool
    {
        return $this->businessId !== null;
    }

    public function setBranchId(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function hasBranch(): bool
    {
        return $this->branchId !== null;
    }
}
