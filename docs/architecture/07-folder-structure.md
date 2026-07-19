# 7. Folder Structure

Laravel 12 root, using `nwidart/laravel-modules`. Business logic lives in `Modules/`; `app/`
holds only true cross-cutting kernel code (tenancy resolution, shared contracts, base classes)
that every module depends on but no single module owns.

```
magarmasupasal/
├── app/
│   ├── Console/
│   │   └── Commands/                     # e.g. tenant:seed-defaults, backup:run
│   ├── Http/
│   │   ├── Middleware/                   # ResolveTenant, EnsureBranchAccess, ...
│   │   └── Kernel.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── BroadcastServiceProvider.php  # Reverb/Echo channel registration
│   │   └── ModuleServiceProvider.php     # ties module service bindings together
│   ├── Support/
│   │   ├── Tenancy/
│   │   │   ├── TenantContext.php
│   │   │   ├── TenantResolver.php        # interface
│   │   │   └── BelongsToTenant.php       # Eloquent trait + global scope
│   │   ├── Money/                        # value object, DECIMAL(14,2) helpers
│   │   ├── Ulid/                         # public_id generation trait
│   │   └── Contracts/                    # cross-module interfaces (repositories, DTO bases)
│   └── Models/
│       └── User.php                      # kept minimal; module-specific concerns via traits
│
├── Modules/
│   ├── Tenancy/
│   ├── UserManagement/
│   ├── Settings/
│   ├── AuditLog/
│   ├── Notification/
│   ├── Backup/
│   ├── Units/
│   ├── Categories/
│   ├── Products/
│   ├── Suppliers/
│   ├── Purchases/
│   ├── Inventory/
│   ├── Customers/
│   ├── POS/
│   ├── CustomerDisplay/
│   ├── Sales/
│   ├── PaymentManager/
│   ├── CashRegister/
│   ├── Expenses/
│   ├── Accounting/
│   ├── Dashboard/
│   ├── Reports/
│   ├── Analytics/
│   └── Api/                               # thin route/versioning shell over module Http layers
│       # every module above follows the same internal shape, e.g. Modules/Products/:
│       ├── Config/
│       │   └── config.php
│       ├── Console/
│       ├── Database/
│       │   ├── Migrations/
│       │   ├── Seeders/
│       │   └── Factories/
│       ├── Entities/  (Models)
│       │   ├── Product.php
│       │   └── ProductAttributeValue.php
│       ├── DTOs/
│       │   ├── CreateProductDTO.php
│       │   └── ProductDTO.php
│       ├── Events/
│       │   └── ProductStockLow.php
│       ├── Listeners/
│       ├── Http/
│       │   ├── Controllers/
│       │   │   ├── Api/                   # API v1 controllers for this module
│       │   │   └── Web/                   # Blade-serving controllers
│       │   ├── Requests/
│       │   │   ├── StoreProductRequest.php
│       │   │   └── UpdateProductRequest.php
│       │   └── Resources/
│       │       └── ProductResource.php
│       ├── Policies/
│       │   └── ProductPolicy.php
│       ├── Providers/
│       │   ├── ProductsServiceProvider.php
│       │   ├── RouteServiceProvider.php
│       │   └── EventServiceProvider.php
│       ├── Repositories/
│       │   ├── ProductRepositoryInterface.php
│       │   └── EloquentProductRepository.php
│       ├── Services/
│       │   └── ProductService.php
│       ├── Routes/
│       │   ├── api.php
│       │   ├── web.php
│       │   └── channels.php
│       ├── Resources/
│       │   ├── views/                     # Blade views + components for this module
│       │   ├── js/                        # Alpine components scoped to this module
│       │   └── lang/
│       ├── Tests/
│       │   ├── Unit/
│       │   └── Feature/
│       └── module.json
│
├── resources/
│   ├── views/
│   │   └── layouts/
│   │       ├── app.blade.php              # authenticated back-office shell
│   │       ├── pos.blade.php              # fullscreen POS shell
│   │       └── display.blade.php          # fullscreen Customer Display shell
│   ├── css/
│   │   └── app.css                        # Tailwind entry (+ dark mode config)
│   └── js/
│       ├── app.js                         # Alpine + Echo bootstrap
│       └── echo.js                        # Reverb/Echo client config
│
├── routes/
│   ├── web.php                            # loads Modules/*/Routes/web.php
│   ├── api.php                            # loads Modules/*/Routes/api.php under v1 prefix
│   └── channels.php                       # loads Modules/*/Routes/channels.php
│
├── database/
│   ├── migrations/                        # only platform-wide tables (businesses, business_types)
│   ├── seeders/
│   │   └── BusinessTypeSeeder.php         # seeds meat_shop, grocery, pharmacy, ... definitions
│   └── factories/
│
├── config/
│   ├── modules.php
│   ├── reverb.php
│   ├── sanctum.php
│   ├── permission.php                     # Spatie
│   └── media-library.php                  # Spatie
│
├── tests/
│   ├── Feature/                           # cross-module integration tests
│   └── Unit/
│
├── docs/
│   └── architecture/                      # this design documentation
│
├── .github/workflows/                     # CI: lint, static analysis, tests, module boundary check
├── docker/                                # local dev + deployment container definitions
├── composer.json
├── package.json
├── tailwind.config.js
├── vite.config.js
└── phpunit.xml
```

## 7.1 Why this shape

- **`Modules/{Name}/` mirrors a full Laravel app skeleton** — a developer who knows Laravel
  can be productive in any module in minutes; there's no bespoke framework-within-a-framework
  to learn.
- **`app/Support/Tenancy` is the only place tenant-resolution logic lives.** Every module
  consumes it via the `BelongsToTenant` trait; no module reimplements tenant scoping.
- **`Modules/Api` is deliberately thin** — it exists only to own versioning/prefix concerns
  and cross-module API composition (e.g. a dashboard summary endpoint that reads from several
  modules' Services); actual resource endpoints live inside each owning module's
  `Http/Controllers/Api`.
- **A CI check enforces the module boundary rule**: static analysis (e.g. `deptrac` or a custom
  PHPStan rule) fails the build if `Modules/Sales` imports an Eloquent model from
  `Modules/Accounting` directly instead of going through an event or a public service
  interface — this is what makes "modules installable without rewriting existing code" an
  enforced property, not just a convention.
