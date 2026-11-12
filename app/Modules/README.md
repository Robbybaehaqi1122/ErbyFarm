# Modules

Setiap direktori di sini adalah satu feature module. `App\Providers\ModularServiceProvider`
menemukan module secara otomatis dan mendaftarkan:

- `Migrations/` ke migrator Laravel
- `Views/` sebagai Blade namespace `<module>::`
- `Lang/` sebagai translation namespace `<module>::`
- `Routes/web.php` dengan middleware `web`
- `Routes/api.php` dengan middleware `api` dan prefix `/api`
- `Providers/*ServiceProvider.php` ke service container

## Struktur standar

```text
app/Modules/{Module}/
├─ Controllers/
├─ Models/
├─ Requests/
├─ Actions/
├─ Services/
├─ Routes/
│  ├─ web.php
│  └─ api.php
├─ Views/                 # Blade, bukan Vue
├─ Migrations/
├─ Database/
│  ├─ Factories/
│  └─ Seeders/
└─ Providers/             # opsional, untuk binding/event/policy
```

## Konvensi

- Namespace: `App\Modules\{Module}\...`
- Nama module: PascalCase, singular (`User`, `Farm`, `Livestock`).
- File route module menentukan prefix dan nama route-nya sendiri.
- Route API cukup menulis path tanpa `/api`; provider menambahkan prefix tersebut.
- Vue component tidak disimpan di sini. Letakkan di `resources/js/Pages/{Module}`.
- Antar-modul berkomunikasi lewat service atau event, bukan langsung memanipulasi model modul lain.
- Jangan taruh domain logic di `app/Support`; itu hanya untuk kode lintas modul.
