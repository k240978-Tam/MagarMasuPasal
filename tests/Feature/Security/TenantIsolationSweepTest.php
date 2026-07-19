<?php

namespace Tests\Feature\Security;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Backup\Models\BackupRun;
use Modules\Tenancy\Models\Business;
use Modules\Tenancy\Models\BusinessType;
use Modules\Units\Models\Unit;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The formal tenant-isolation review the roadmap calls for: rather than
 * hand-picking a few modules to spot-check, this reflects over every
 * Eloquent model in the codebase and asserts that any model whose table
 * actually has a business_id column uses BelongsToTenant — the one thing
 * standing between a forgotten `use` statement and a real cross-tenant
 * data leak. A model that skips the trait fails this test the moment its
 * table gains a business_id column, before it ever ships.
 */
class TenantIsolationSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Models that legitimately do NOT scope by business_id, with the reason
     * why — reviewed and accepted, not just skipped.
     */
    protected const NOT_TENANT_SCOPED = [
        Business::class => 'is the tenant root itself',
        BusinessType::class => 'a shared platform-wide lookup table, not tenant data',
        BackupRun::class => 'backups cover the whole shared database, not one tenant',
        Unit::class => 'null business_id is a shared platform default, not "no tenant" — enforced by SharedOrTenantScope instead',
    ];

    public function test_every_model_with_a_business_id_column_uses_belongs_to_tenant(): void
    {
        $violations = [];
        $checked = [];

        foreach ($this->discoverModelClasses() as $class) {
            if (! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (array_key_exists($class, self::NOT_TENANT_SCOPED)) {
                continue;
            }

            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_id')) {
                continue;
            }

            $checked[] = $class;

            if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $violations[] = "{$class} (table `{$table}`)";
            }
        }

        $this->assertNotEmpty($checked, 'The model sweep found nothing to check — the discovery glob is broken.');
        $this->assertEmpty($violations, "These models have a business_id column but do not use BelongsToTenant:\n".implode("\n", $violations));
    }

    /**
     * @return array<int, class-string>
     */
    protected function discoverModelClasses(): array
    {
        $classes = [];

        $paths = array_filter(array_merge(
            [app_path('Models')],
            glob(base_path('Modules/*/app/Models')) ?: [],
        ), 'is_dir');

        foreach ((new Finder)->files()->in($paths)->name('*.php') as $file) {
            $relative = Str::after($file->getPathname(), base_path().'/');

            if (Str::startsWith($relative, 'app/Models/')) {
                $class = 'App\\Models\\'.Str::before(Str::after($relative, 'app/Models/'), '.php');
                $class = str_replace('/', '\\', $class);
            } else {
                // Modules/{Module}/app/Models/{Path}.php -> Modules\{Module}\Models\{Path}
                $module = Str::before(Str::after($relative, 'Modules/'), '/');
                $subPath = Str::before(Str::after($relative, "Modules/{$module}/app/Models/"), '.php');
                $class = "Modules\\{$module}\\Models\\".str_replace('/', '\\', $subPath);
            }

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
