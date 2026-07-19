# Retail ERP Platform — Architecture & Planning

**Project:** Cloud-based, multi-tenant Retail ERP for Nepal
**First tenant:** Magar Masu Pasal Tatha Anya Tarkari (meat shop), Halchowk, Kathmandu
**Status:** Design phase — no implementation has started. This folder contains the eight
deliverables requested before any code is written.

| # | Deliverable | File |
|---|---|---|
| 1 | System architecture | [01-system-architecture.md](01-system-architecture.md) |
| 2 | Module breakdown | [02-module-breakdown.md](02-module-breakdown.md) |
| 3 | Database schema | [03-database-schema.md](03-database-schema.md) |
| 4 | User flow diagrams | [04-user-flows.md](04-user-flows.md) |
| 5 | Screen wireframes | [05-screen-wireframes.md](05-screen-wireframes.md) |
| 6 | API design | [06-api-design.md](06-api-design.md) |
| 7 | Folder structure | [07-folder-structure.md](07-folder-structure.md) |
| 8 | Development roadmap | [08-roadmap.md](08-roadmap.md) |

## Ground rules that shaped every decision below

1. **Nothing is meat-shop-specific.** Business type is a configuration (unit sets, attribute
   sets, receipt template, tax rules), never an `if ($businessType === 'meat')` in code.
2. **Multi-tenant from day one**, even though we launch with a single tenant. Retrofitting
   tenancy after tables and queries already assume "one business" is far more expensive than
   building it in now.
3. **Modular monolith, not microservices.** At "thousands of SMB tenants," microservices add
   operational cost (service mesh, distributed tracing, network latency between modules that
   are always used together) without a matching benefit. A well-bounded modular monolith gets
   us independent modules, testability, and a future extraction path if one module ever needs
   to scale independently — without paying the distributed-systems tax on day one.
4. **Every module is a Laravel package-like unit** (own migrations, routes, models, service
   provider) so a future module (e.g. "Loyalty Points", "E-commerce Sync") can be dropped in
   without touching existing modules.

## Key decisions and trade-offs (recommended defaults — flag if you want to override)

| Decision | Recommendation | Why | Alternative considered |
|---|---|---|---|
| Module system | `nwidart/laravel-modules` | Enforced boundaries, `module:make` scaffolding, per-module autoloading, enable/disable — directly matches "installable modules without rewriting existing code." | Hand-rolled `app/Domain/*` folders — lighter but relies purely on discipline, no tooling. |
| Multi-tenancy | Single database, shared schema, `business_id` column + global scope (custom `TenantContext`, not a heavyweight package) | Cheapest to operate at SMB SaaS scale (one MySQL instance serves thousands of small tenants); simplest backup/restore story. | Database-per-tenant (`stancl/tenancy`) — better isolation, but ops cost (migrations × N databases, connection pooling) is unjustifiable for a shop doing a few hundred sales/day. We keep a `TenantResolver` interface abstract enough to move a large enterprise tenant to its own database later without an application rewrite. |
| CSS framework | **TailwindCSS + AlpineJS only.** Drop Bootstrap 5. | The brief lists both Bootstrap 5 and Tailwind, but running both in one app causes specificity fights and doubles the CSS payload for no benefit. Tailwind + Alpine is the standard modern Laravel stack (matches Blade component + utility-first workflow, smallest bundle, easiest dark-mode via `dark:` variants) and is what Square/Shopify-style UIs are realistically built with today. | Bootstrap 5 — faster to prototype with pre-built components, but fights Tailwind utilities and produces a heavier, more generic look, working against the "Square/Shopify POS quality" bar. |
| Product extensibility | Core `products` table with universal columns + a typed `product_attribute_definitions`/`product_attribute_values` table (structured EAV) + one `custom_attributes` JSON column for free-form per-business-type data | Avoids both (a) hardcoding meat-specific columns and (b) a full EAV model for *everything*, which kills query performance and reporting. Structured attributes (e.g. "Size", "Color", "Expiry Required") are configurable per business type via a small number of joined rows; free-form JSON covers the long tail. | Pure EAV — maximum flexibility, poor reporting/indexing performance. Single JSON-only column — fast to build, but unqueryable/unindexable for things like "filter by size." |
| Primary keys | `BIGINT UNSIGNED AUTO_INCREMENT` internal PK on every table, plus a `ULID` `public_id` column on tenant-facing/externally-referenced entities (sales, customers, products, invoices) | Auto-increment keeps joins/indexes fast and small; ULID gives sortable, non-guessable, safely-exposable IDs for receipts, QR codes, and the API — without paying UUID's index-fragmentation cost as the primary key. | All-UUID PKs — simpler mentally, but larger indexes and worse insert locality at scale. |
| Auth (web + API) | Laravel Sanctum | First-party, works for the Blade SPA-ish frontend (cookie auth) and future mobile apps (token auth) from one package, no OAuth ceremony needed for a first-party client. | Passport — full OAuth2 server, overkill until we have true third-party API consumers. |
| Real-time transport | Laravel Reverb (self-hosted, first-party) + Laravel Echo | Explicitly requested; self-hosted avoids per-connection billing (Pusher) which matters at thousands of tenants running all-day POS terminals. | Pusher-hosted — zero ops, but recurring cost scales with exactly the metric (concurrent connections × tenants) we're trying to grow. |
| Offline resilience | PWA with a local outbox (IndexedDB via a thin JS layer) that queues sales made during a connectivity drop and syncs on reconnect | Nepal retail connectivity is not always reliable; a POS that hard-fails without internet is not production-viable for this market. | Fully online-only — simpler, but unacceptable failure mode for a till that must never stop selling. |

## Open items for explicit sign-off before implementation begins

- [ ] Confirm dropping Bootstrap 5 in favor of Tailwind-only (see table above).
- [ ] Confirm shared-database multi-tenancy (vs. database-per-tenant).
- [ ] Confirm `nwidart/laravel-modules` as the module framework.
- [ ] Confirm phase order in the roadmap (docs/architecture/08-roadmap.md) — in particular
      whether Accounting should move earlier than Phase 4 for the first tenant's launch.

Once these are confirmed, implementation starts at Phase 0 of the roadmap.
