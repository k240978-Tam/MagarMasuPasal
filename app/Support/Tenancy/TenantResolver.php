<?php

namespace App\Support\Tenancy;

/**
 * Populates the bound TenantContext for the current request. The shared-database
 * default lives in AuthenticatedUserTenantResolver; a future large tenant needing
 * its own database can swap in a different implementation without touching any
 * module code, since every module depends on this interface, never a concrete class.
 */
interface TenantResolver
{
    public function resolve(): void;
}
