# AGENTS.md

`erby-farm` is a renamed **Sneat Vuetify Vue 3 + Laravel 11** admin template shaped into a farm management app. Laravel boots one Vue SPA via a single Blade shell; feature modules live in `app/Modules/`.

## Commands (exact)

- `composer install` — PHP deps.
- `npm install` — JS deps; `postinstall` runs `build:icons`, generating gitignored `resources/js/plugins/iconify/icons.css`.
- `composer dev` — `concurrently` runs `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail --timeout=0`, `npm run dev`. Or run `php artisan serve` + `npm run dev` separately.
- `php artisan test` — PHPUnit (sqlite `:memory:`, overrides `.env` MySQL; `BCRYPT_ROUNDS=4`). Focus: `php artisan test --filter=TestName`.
- `npm run build` — production Vite build (~50s). **Must pass before considering a change complete.**
- `npm run lint` — ESLint **with `--fix`**; it rewrites files across the repo. Run after editing Vue/JS.
- `./vendor/bin/pint` — PHP formatter, default Laravel preset; no `pint.json`.

No CI workflows, no frontend tests, no pre-commit config. Git repo; `.gitignore` ignores `node_modules`, `vendor`, `public/build`, `resources/js/plugins/iconify/icons.css`, `.env*`.

## Backend architecture (modular)

- Modules: `app/Modules/{Module}/`, namespace `App\Modules\{Module}\...` (PSR-4 in `composer.json`).
- `App\Providers\ModularServiceProvider` (registered in `bootstrap/providers.php`) auto-discovers modules via `config/modules.php` and registers each module's `Providers/*ServiceProvider.php`, `Migrations/`, `Views/` (Blade namespace `<module>::`), `Lang/`, `Routes/web.php` (`web` middleware), and `Routes/api.php` (`api` middleware + `/api` prefix). **Put module migrations directly in `app/Modules/{Module}/Migrations/` — `Database/Migrations/` is NOT loaded** (factories live in `Database/Factories/`).
- **Route rules:** Module API route files define only their path — **do not** prefix with `/api` (the provider adds it). `routes/web.php` has only comments; do not add routes there.
- The SPA catch-all `Route::view('{any?}', 'application')->name('spa')` is registered **last**, excludes `api/*` (`^(?!api(?:/|$)).*$`), and cannot shadow module routes.
- `config/modules.php`: `path`, `auto_discover`, `enabled` (allow-list), `disabled` (deny-list). Empty `enabled` = all discovered modules enabled.
- Cross-module shared code: `app/Support/`.

## Modules present (8)

| Module | Owns / API | Notable behavior |
|--------|-----------|------------------|
| User | users, roles, `/api/auth/*`, `/api/users` | Sanctum session cookie + spatie/laravel-permission. Roles: admin, operator, viewer, developer. **First registered account becomes developer (superadmin); later ones viewer.** `config/auth.php` model = `App\Modules\User\Models\User`. Roles are auto-created in the DB via `SpatieRole::findOrCreate()` in `AuthController::register` and `UserController::{store,update,updateRole}` before assigning, so a new role enum case won't 500 even if the DB row is missing. **Developer has the same menu/route access as admin** (`isAdmin` and every `hasRole`/`meta.roles` array include `developer` in `NavItems.vue`, `auth.js`, `routes.js`). User accounts have extended profile fields (organization, phone, address, state, zip_code, country, language, timezone, currency, avatar_url, is_active) editable via **Edit Account** in the Aksi dropdown di halaman Pengguna. Role and active status are edited inside that same dialog (tombol Aktifkan/Nonaktifkan), so there is no separate Edit Role or Nonaktifkan menu item. `UpdateUserRequest` makes `name`/`email` `sometimes` so the status toggle can send only `is_active`. Aksi CRUD halaman Pengguna menampilkan notifikasi sukses via `VSnackbar`. Self-service `PUT /api/auth/profile` (`UpdateProfileRequest`) lets any user edit their own account fields; role cannot be changed there (admin-only via `/api/users`). Account Settings Account tab and navbar avatar use the logged-in user's data (no more johnDoe dummy); uploaded avatar data URLs are written to `public/uploads/avatars`. Login rejects inactive accounts. **Auth quirk:** `logout` clears the session cookie via `Cookie::forget()` (session-based Sanctum `TransientToken` has no `delete()` — calling it 500s). `me` returns `user: null` when unauthenticated. Router guard calls `fetchUser()` on every nav; if logout doesn't clear the cookie, a refresh sends the user back to dashboard. |
| Farm | `farms`, `/api/farms` | `name`, `capacity`, `status` (active/maintenance/inactive), `notes`. Gate policy. |
| Livestock | `livestocks`, `/api/livestocks` | `farm_id` FK (cascade). `current_population`/`age_weeks` auto-filled on create when omitted. `Farm::livestocks()` hasMany. |
| Production | `daily_productions`, `/api/daily-productions` | Unique on `(livestock_id, date)`. **Mortality sync:** recording/editing/deleting `mortality` adjusts `livestocks.current_population` in a transaction; mortality above live population → 422. |
| Report | read-only, `/api/reports` | Aggregates `daily_productions`/`livestocks`/`farms`. Filters `from`, `to`, `farm_id`, `group_by` (day\|month). **Hen Day** = total_eggs / sum(daily live population) × 100. Viewer may read. Invalid date range → 422. |
| Finance | `expenses`, `/api/expenses` | `farm_id` nullable (`nullOnDelete`); `null` = general business expense (index filter `farm_id=general`). Categories: pakan, obat_vaksin, operasional, gaji, lain_lain. Only admin deletes. |
| Inventory | `inventories`, `/api/inventories` | Categories: pakan, obat, vitamin, perlengkapan. `stockStatus()` → out/low/safe. **Index summary is global** (ignores table filters). Only admin deletes. |
| Health | `health_records`, `/api/health-records` | `livestock_id` FK. Types: vaksinasi, pengobatan, vitamin, culling. Only admin deletes. |

Tests: `tests/Feature/` (Report, Production, Livestock, Inventory, Health, Finance, Auth, Example). No `tests/Unit` tests.

## Frontend

- Entry: `resources/js/main.js` → `App.vue`. Plugins auto-registered from `resources/js/plugins/*.{js,ts}`.
- Routes: `resources/js/plugins/router/routes.js`; auth guard in `plugins/router/index.js` (unauth → login, guest → dashboard, role-restricts admin pages). To add a page: component in `resources/js/pages/`, route entry, link in `resources/js/layouts/components/NavItems.vue`.
- Layouts: `layouts/default.vue` (vertical nav), `layouts/blank.vue` (login/register/404).
- HTTP: `resources/js/services/http.js` (fetch + XSRF for Sanctum; exports `get`, `post`, `put`, `patch`, `delete` — use `http.put` for the user module's `Route::put('users/{user}')`). Auth: `resources/js/stores/auth.js` (Pinia).

### Frontend conventions (differ from defaults)

- Auto-imported globals: Vue, Vue Router, `@vueuse/core`, `@vueuse/math`, Pinia. Do not manually `import ref/computed/useRouter`.
- Auto-registered components: Vuetify + `resources/js/components/` and `resources/js/@core/components/`. `VueApexCharts` auto-imported. **Do not** import from `vuetify/components` or `vue3-apexcharts` directly (ESLint forbids).
- VMenu: use `activator="parent"` with `<VMenu>` nested inside its activator element (see `MoreBtn.vue`, `UserProfile.vue`). The Vuetify 2 `#activator="{ on, attrs }"` slot with `v-on="on"` throws `v-on with no argument expects an object value` and shows no dropdown.
- Aliases: `@` → `resources/js`, `@core`, `@layouts`, `@images` → `resources/images`, `@styles` → `resources/styles`. Never `@/assets/images` or `@/assets/styles`.
- ESLint: no semicolons, single quotes, 2-space indent, trailing commas, logical props (`ps-*`/`pe-*`/`ms-*`/`me-*`); `pl-*`/`pr-*`/`ml-*`/`mr-*` rejected.
- Icons: Boxicons (`bx-*`, `bxl-*`, `bxs-*`) plus a few `mdi`/`fa`, bundled into gitignored `resources/js/plugins/iconify/icons.css` by `npm run build:icons`. **Do not hand-edit**; `tabler-*` rejected by ESLint.
- Generated — do not hand-edit: `auto-imports.d.ts`, `components.d.ts`, `.eslintrc-auto-import.json`, `resources/js/plugins/iconify/icons.css`.

## Backend / data

- `.env`: MySQL, database `erby_farm` (user `farm`). `.env.example` is still the Laravel SQLite default and `database/database.sqlite` does not exist.
- `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` for tests; `BCRYPT_ROUNDS=4`.
- Sail in `docker-compose.yml` (PHP 8.4, MySQL, Redis, Selenium, Mailpit). Local PHP is 8.2 (composer requires `^8.2`; `.nvmrc` = `lts/*`). `composer dev` is the non-Docker shortcut.
- Use **npm**: `package-lock.json` current; `pnpm-lock.yaml` is stale. `.npmrc` sets `auto-install-peers` and `shamefully-hoist`.
