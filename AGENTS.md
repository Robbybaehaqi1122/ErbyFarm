# AGENTS.md

## What this repo is

- `erby-farm` is a renamed **Sneat Vuetify Vue 3 + Laravel 11** admin template. It is **not** a finished farm app: no farm domain model, IoT integration, or production data yet.
- Laravel serves one Blade shell (`resources/views/application.blade.php`) that boots the Vue SPA. The SPA catch-all (`{any?}`, route name `spa`) is registered **last** by `App\Providers\ModularServiceProvider` and excludes `api/*`. `routes/web.php` contains only comments — do not add a catch-all there or it will shadow module routes.

## Commands

- `npm install` — installs JS deps and runs `postinstall` → `npm run build:icons` (generates `resources/js/plugins/iconify/icons.css`).
- `composer install` — installs PHP deps.
- `php artisan serve` + `npm run dev` — run Laravel and Vite separately. Or `composer dev` (server, queue, Pail logs, Vite together).
- `npm run build` — production Vite build (~50s). **Must pass before considering a change complete.**
- `npm run lint` — ESLint **with `--fix`**; it rewrites files across the repo.
- `./vendor/bin/pint` — PHP formatter (default Laravel preset; no `pint.json`).
- `php artisan test` — PHPUnit. Focus: `php artisan test --filter=TestName`.
- No frontend tests and no CI workflows exist.

## Backend architecture (modular)

- Feature modules live in `app/Modules/{Module}/`, namespace `App\Modules\{Module}\...`. PSR-4 mapping `App\Modules\` → `app/Modules/` is in `composer.json` (in addition to `App\`).
- `App\Providers\ModularServiceProvider` (registered in `bootstrap/providers.php`) auto-discovers modules via `config/modules.php` and registers:
  - `Providers/*ServiceProvider.php` on `register()`
  - `Migrations/` via `loadMigrationsFrom`
  - `Views/` as Blade namespace `<module>::`
  - `Lang/` as translation namespace `<module>::`
  - `Routes/web.php` with `web` middleware
  - `Routes/api.php` with `api` middleware + `/api` prefix
- Module route files own their own prefix and route names. **Do not** add `/api` inside a module API route file.
- Module discovery is configured in `config/modules.php` (`path`, `auto_discover`, `enabled` allow-list, `disabled` deny-list). Empty `enabled` = all discovered modules enabled.
- Standard module skeleton/conventions: `app/Modules/README.md`. Cross-module shared code: `app/Support/` (never domain logic).

## Current state: `app/Modules/User` (Auth/Role)

Fully implemented: model, factory, `AuthController`/`UserController`, `LoginRequest`/`RegisterRequest`/`UpdateUserRoleRequest`, `UserResource`, `UserPolicy`, `UserServiceProvider`, `Role` enum, migrations (`users`, `permission tables`, `personal_access_tokens`), seeders (`RoleSeeder`, `UserSeeder`).
- Auth: Laravel Sanctum (session cookie, same-origin SPA) + spatie/laravel-permission.
- Roles: `admin`, `operator`, `viewer`.
- API routes: `/api/auth/{login,register,logout,me}` and `/api/users` (admin-only index, role update, destroy).
- First registered account becomes `admin`; later registrations are `viewer`.
- `config/auth.php` model points to `App\Modules\User\Models\User`.

## Current state: `app/Modules/Farm` (Kandang)

- Migration `farms`: `name`, `capacity`, `status` (active/maintenance/inactive), `notes`.
- Model `Farm`, `FarmController` (CRUD: index/store/show/update/destroy with Gate authorization).
- `FarmPolicy` + `FarmServiceProvider` (registers `Gate::policy(Farm::class, FarmPolicy::class)`).
- API routes under `/api/farms` (`auth:sanctum`), auto-registered by `ModularServiceProvider`.
- Vue page `resources/js/pages/farm/index.vue`: VDataTable with status badge chips, add/edit modal via VDialog, delete confirmation dialog.
- Frontend route `/farms` (meta roles: admin, operator) in `resources/js/plugins/router/routes.js`.
- Sidebar menu "Kandang" in `resources/js/layouts/components/NavItems.vue` (visible to admin/operator).

## Current state: `app/Modules/Livestock` (Populasi Ayam)

- Migration `livestocks`: `farm_id` (FK → `farms`, cascade on delete), `initial_population`, `current_population`, `entry_date`, `age_weeks`, `status` (active/culled/empty).
- Model `Livestock` (`belongsTo` Farm), `LivestockController` (CRUD with Gate authorization, eager-loads `farm`, search by farm name/farm/status filters).
- `age_weeks` and `current_population` are auto-filled on create when omitted (`current_population` defaults to the initial population; age is derived from `entry_date`).
- `Farm` model has `livestocks()` `hasMany` relation.
- `LivestockPolicy` + `LivestockServiceProvider` (registers the policy). API routes under `/api/livestocks` (`auth:sanctum`).
- Vue page `resources/js/pages/livestock/index.vue`: summary stat cards, filters (farm/status/search), VDataTable with survival-rate bars, add/edit modal, delete confirmation.
- Frontend route `/livestocks` (meta roles: admin, operator) in `resources/js/plugins/router/routes.js`.
- Sidebar menu "Populasi Ayam" under "Kandang" in `resources/js/layouts/components/NavItems.vue`.
- Feature coverage: `tests/Feature/LivestockTest.php`.

## Current state: `app/Modules/Production` (Produksi Harian)

- Migration `daily_productions`: `livestock_id` (FK → `livestocks`, cascade on delete), `date`, `egg_count`, `egg_weight_kg`, `feed_consumed_kg`, `water_consumed_liter`, `mortality`.
- Unique constraint on `(livestock_id, date)` — one production record per flock per day.
- Model `DailyProduction` (`belongsTo` Livestock); `Livestock` has a `dailyProductions()` `hasMany` relation.
- `DailyProductionController` (CRUD with Gate authorization, eager-loads `livestock.farm`, filters by date range/livestock/farm search).
- **Population sync:** recording `mortality` reduces `livestocks.current_population` in a transaction. Editing adjusts by the difference (or moves the mortality between flocks), and deleting restores the population. Mortality above the live population is rejected with a 422.
- `DailyProductionPolicy` + `DailyProductionServiceProvider`. API routes under `/api/daily-productions` (`auth:sanctum`).
- Vue page `resources/js/pages/production/index.vue`: period/livestock filters, summary cards (eggs, weight, feed/water, mortality), VDataTable recap, add/edit modal with projected live population, delete confirmation.
- Frontend route `/productions` (meta roles: admin, operator); sidebar menu "Produksi Harian" under "Populasi Ayam".
- Feature coverage: `tests/Feature/ProductionTest.php`.

## Current state: `app/Modules/Report` (Laporan & Analitik)

- Read-only aggregation module — no migration or model of its own; reads from `daily_productions`, `livestocks`, and `farms`.
- `ReportController@index` serves `GET /api/reports` with filters `from`, `to`, `farm_id`, and `group_by` (`day` | `month`; default `day`). `from` defaults to the start of the current month and `to` to today.
- Response shape: `meta` (`from`, `to`, `group_by`, `farm_id`), `summary`, `by_farm[]`, and `periods[]`.
- Metrics per aggregate: `total_eggs`, `egg_weight_kg`, `total_feed_kg`, `average_feed_kg`, `total_water_liter`, `average_water_liter`, `total_mortality`, `average_mortality`, `average_egg_weight_gram`, `hen_day`, `recorded_days`.
- **Hen Day:** `total_eggs / sum(daily live population) × 100`. Population is reconstructed as `livestocks.current_population + mortality recorded on/after that date`, because `current_population` already reflects all mortality. Feed/water averages divide by distinct recorded dates.
- Access is checked in the controller with `hasRole(['admin', 'operator', 'viewer'])` — viewers may read reports. Invalid date ranges return a 422.
- Vue page `resources/js/pages/report/index.vue`: date/farm/grouping filters, four summary cards (eggs, Hen Day, feed/water, mortality), per-farm recap table, and daily/monthly trend table with Hen-Day progress bars and no-data states.
- Frontend route `/reports` (meta roles: admin, operator, viewer); sidebar menu "Laporan & Analitik" under "Produksi Harian".
- Feature coverage: `tests/Feature/ReportTest.php`.

## Current state: `app/Modules/Finance` (Keuangan & Biaya)

- Migration `expenses`: `farm_id` (nullable FK → `farms`, `nullOnDelete`), `category`, `amount` (decimal 15,2), `date`, `description` (nullable text). Indexed on `date`, `category`, and `(farm_id, date)`.
- `farm_id = null` means a general expense for the whole farm business, not tied to one kandang.
- Categories (`Expense::categories()`): `pakan`, `obat_vaksin`, `operasional`, `gaji`, `lain_lain`.
- Model `Expense` (`belongsTo` Farm, date stored as `Y-m-d`); `Farm` has an `expenses()` `hasMany` relation.
- `FinanceController` (full CRUD with Gate authorization) serves `/api/expenses` (`auth:sanctum`). Index filters: `from`, `to`, `farm_id` (use `general` for expenses without a farm), `category`, `search` (description or farm name), `per_page`.
- Index response is the paginator payload plus `summary`: `total_amount`, `total_records`, `average_amount`, and `by_category[]` (`category`, `total_amount`, `total_records`). Summary respects the same filters as the table.
- `ExpensePolicy`: admin/operator can create and update, only admin can delete, admin/operator/viewer can view.
- Vue page `resources/js/pages/finance/index.vue`: date/farm/category/search filters, four summary cards (total cost, transaction count, average, top category), server-paginated expense table, category breakdown card, add/edit modal, delete confirmation.
- Frontend route `/finance` (meta roles: admin, operator, viewer); sidebar menu "Keuangan & Biaya" under "Laporan & Analitik".
- Feature coverage: `tests/Feature/FinanceTest.php`.

## Current state: `app/Modules/Inventory` (Manajemen Stok)

- Migration `inventories`: `item_name`, `category`, `stock_quantity` (decimal 12,2), `unit`, `minimum_stock` (decimal 12,2). Indexed on `category` and `item_name`.
- Categories (`Inventory::categories()`): `pakan`, `obat`, `vitamin`, `perlengkapan`.
- Model `Inventory` with decimal casts and a `stockStatus()` helper returning `out` (stock ≤ 0), `low` (stock ≤ minimum), or `safe`.
- `InventoryController` (full CRUD with Gate authorization) serves `/api/inventories` (`auth:sanctum`). Index filters: `search` (item name), `category`, `stock_status` (`low` / `out` / `safe`), `per_page`.
- Index response is the paginator payload plus a **global** `summary` (independent of table filters): `total_items`, `low_stock_items`, `out_of_stock_items`, `category_count`, and `by_category[]` (`category`, `item_count`, `low_stock_count`, `out_of_stock_count`).
- `InventoryPolicy`: admin/operator can create and update, only admin can delete, admin/operator/viewer can view.
- Vue page `resources/js/pages/inventory/index.vue`: low/out-of-stock warning banner, four summary cards (total items, low stock, out of stock, categories), search/category/status filters, server-paginated stock table with category icons and status chips, add/edit modal, delete confirmation.
- Frontend route `/inventory` (meta roles: admin, operator, viewer); sidebar menu "Manajemen Stok" under "Keuangan & Biaya".
- Feature coverage: `tests/Feature/InventoryTest.php`.

## Current state: `app/Modules/Health` (Kesehatan & Vaksinasi)

- Migration `health_records`: `livestock_id` (FK → `livestocks`, cascade on delete), `treatment_type`, `medicine_name` (nullable), `dosage` (nullable), `date`, `notes` (nullable text). Indexed on `date`, `treatment_type`, and `(livestock_id, date)`.
- Treatment types (`HealthRecord::treatmentTypes()`): `vaksinasi`, `pengobatan`, `vitamin`, `culling`.
- Model `HealthRecord` (`belongsTo` Livestock), date stored as `Y-m-d`; `Livestock` has a `healthRecords()` `hasMany` relation.
- `HealthController` (full CRUD with Gate authorization) serves `/api/health-records` (`auth:sanctum`). Index filters: `livestock_id`, `treatment_type`, `from`, `to`, `search` (medicine name / notes / farm name), `per_page`.
- Index response is the paginator payload plus `summary`: `total_records`, `livestock_count`, `vaksinasi_count`, `pengobatan_count`, `vitamin_count`, `culling_count`. Summary respects the same filters as the table.
- `HealthRecordPolicy`: admin/operator can create and update, only admin can delete, admin/operator/viewer can view.
- Vue page `resources/js/pages/health/index.vue`: four summary cards (total records, vaksinasi, pengobatan, culling), date/treatment-type filters, server-paginated history table with treatment chips and farm info, add/edit modal, delete confirmation.
- Frontend route `/health-records` (meta roles: admin, operator, viewer); sidebar menu "Kesehatan & Vaksin" under "Manajemen Stok".
- Feature coverage: `tests/Feature/HealthTest.php`.

## Frontend architecture

- Entry: `resources/js/main.js` → `App.vue`. Plugins auto-registered from `resources/js/plugins/*.{js,ts}` via `@core/utils/plugins`.
- Routing is manual in `resources/js/plugins/router/routes.js` with an auth guard in `resources/js/plugins/router/index.js` (redirects unauthenticated → login, guests → dashboard, role-restricts admin pages). Adding an admin page needs: component in `resources/js/pages/`, route entry, link in `resources/js/layouts/components/NavItems.vue`.
- Layouts: `layouts/default.vue` (vertical-nav shell), `layouts/blank.vue` (login/register/404).
- HTTP client: `resources/js/services/http.js` (fetch wrapper with XSRF cookie handling for Sanctum). Auth state: `resources/js/stores/auth.js` (Pinia store: login/register/logout/me, role helpers).
- `resources/js/pages/users.vue` — admin user list with role switching.

## Frontend conventions (differ from defaults)

- Auto-imported globals: Vue, Vue Router, `@vueuse/core`, `@vueuse/math`, Pinia APIs. Do not manually import `ref`, `computed`, `useRouter`, etc.
- Auto-registered components: Vuetify components + files in `resources/js/components/` and `resources/js/@core/components/`. `IconBtn` aliases `VBtn`; `VueApexCharts` auto-imported.
- Do not import from `vuetify/components` or `vue3-apexcharts` directly; ESLint forbids it.
- Path aliases: `@` → `resources/js`, `@core`, `@layouts`, `@images` → `resources/images`, `@styles` → `resources/styles`. Never `@/assets/images` or `@/assets/styles`.
- ESLint style: no semicolons, single quotes, 2-space indent, trailing commas, blank lines around returns/comments. Use logical properties (`ps-*`, `pe-*`, `ms-*`, `me-*`); `pl-*`/`pr-*`/`ml-*`/`mr-*` rejected.
- Icons: Boxicons (`bx-*`, `bxl-*`, `bxs-*`) from generated `resources/js/plugins/iconify/icons.css`. `tabler-*` rejected.
- Generated files — do not hand-edit: `auto-imports.d.ts`, `components.d.ts`, `.eslintrc-auto-import.json`, `resources/js/plugins/iconify/icons.css`.

## Backend / data

- Local `.env` uses **MySQL** database `erby_farm`. `.env.example` is still the Laravel SQLite default and `database/database.sqlite` does not exist.
- `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` for isolated tests (overrides `.env` MySQL). No `testing` database needed locally.
- Sail in `docker-compose.yml` (PHP 8.4, MySQL, Redis, Selenium, Mailpit); local PHP is 8.2. `composer dev` is the non-Docker shortcut.
- Use **npm**: `package-lock.json` and `node_modules/.package-lock.json` are current; `pnpm-lock.yaml` is stale. `.vscode`/`.npmrc` mention pnpm and npm prints harmless warnings for pnpm-only keys.
- Repository is **not a git repo**. Back up or `git init` + commit before large refactors so changes can be rolled back.
