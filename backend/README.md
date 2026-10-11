# Haven Hotel System — Backend

Backend Haven Hotel System adalah REST API berbasis Laravel 13. Backend mengelola autentikasi, role, reservasi, kamar, pembayaran, layanan tambahan, fasilitas, laporan, dan user.

## Prasyarat

- PHP >= 8.3
- Composer
- MySQL/MariaDB untuk development lokal
- Ekstensi PHP yang dibutuhkan Laravel, termasuk `pdo_mysql`
- `pdo_sqlite` dan `sqlite3` untuk menjalankan feature test

## Setup lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Buat database MySQL bernama `hotel_management`, lalu sesuaikan `.env`:

```env
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_management
DB_USERNAME=root
DB_PASSWORD=
FRONTEND_URL=http://localhost:3000
```

Jalankan migration, data demo, dan server:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Base URL API: `http://localhost:8000/api`.

## Akun hasil seeder

Semua password akun demo adalah `password`:

- `admin@hotel.test` — Admin
- `receptionist@hotel.test` — Receptionist
- `manager@hotel.test` — Manager
- `darren@example.com` — User

Seeder juga membuat role, tipe kamar, kamar, fasilitas, dan layanan tambahan contoh.

## Struktur folder

```text
backend/
├── app/
│   ├── Http/Controllers/    # Controller REST API
│   ├── Models/              # Model Eloquent
│   └── Services/            # Business logic reservasi
├── bootstrap/               # Bootstrap Laravel
├── config/                  # Konfigurasi aplikasi dan service
├── database/
│   ├── factories/            # Factory untuk test
│   ├── migrations/           # Schema database
│   └── seeders/              # Data awal/demo
├── public/                  # Entry point publik
├── resources/               # View/resource Laravel
├── routes/
│   ├── api.php              # Endpoint REST API
│   ├── web.php              # Route web root
│   └── console.php          # Console routes
├── storage/                 # Log, cache, dan file runtime
├── tests/                   # Unit dan feature tests
├── .env.example             # Template environment yang boleh di-commit
├── .env                     # Environment lokal, jangan di-commit
├── .gitignore
├── artisan
├── composer.json
└── phpunit.xml
```

## Endpoint utama

Route publik:

- `POST /api/login`
- `POST /api/register`

Route terautentikasi:

- `GET /api/user`
- `POST /api/logout`
- `GET /api/dashboard`
- `GET /api/reservations`
- `POST /api/reservations`
- `PUT /api/reservations/{reservation}`
- `POST /api/reservations/{reservation}/confirm`
- `POST /api/reservations/{reservation}/check-in`
- `POST /api/reservations/{reservation}/check-out`
- `POST /api/reservations/{reservation}/no-show`
- `POST /api/reservations/{reservation}/cancel`
- `GET /api/rooms`, `/api/room-types`, `/api/facilities`, `/api/services`
- `GET /api/payments`
- `POST /api/reservations/{reservation}/payments`
- `GET /api/reservations/{reservation}/folio`
- `POST /api/payments/{payment}/refund`
- `POST /api/payments/{payment}/void`
- `GET /api/settings` dan `PATCH /api/settings` untuk Admin

Endpoint admin untuk mengelola rooms, room types, facilities, users, dan services juga tersedia pada `routes/api.php`.

Request terautentikasi harus menyertakan:

```http
Accept: application/json
Authorization: Bearer <token>
```

## Testing

Test menggunakan SQLite in-memory sesuai konfigurasi `phpunit.xml`, sehingga tidak memerlukan database MySQL:

```bash
composer test
composer test -- --testsuite=Feature
```

Perintah berguna lainnya:

```bash
php artisan route:list
php artisan migrate:status
php artisan migrate:fresh --seed
php artisan config:clear
php artisan cache:clear
```

## Environment dan keamanan

`backend/.env.example` adalah template yang boleh di-commit. `backend/.env` dan environment file lokal lainnya di-ignore oleh Git. Setelah menyalin template, buat `APP_KEY` dengan `php artisan key:generate` dan jangan membagikan nilai secret tersebut.

`FRONTEND_URL` harus sama dengan origin frontend agar CORS mengizinkan request dari browser. Jika mengubah `.env`, jalankan `php artisan config:clear` atau restart server.
