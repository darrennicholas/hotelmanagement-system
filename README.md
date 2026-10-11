# Haven Hotel Management System

Aplikasi manajemen operasional hotel dengan alur reservasi, ketersediaan kamar, tamu walk-in, pembayaran, service tambahan, check-in/check-out, laporan, dan manajemen user.

## Stack

- Frontend: Next.js 16, React 19, TypeScript, SweetAlert2
- Backend: Laravel 13, PHP 8.3+, Laravel Sanctum
- Database: MySQL/MariaDB
- Testing backend: PHPUnit dan SQLite in-memory

## Prasyarat

Install terlebih dahulu:

- PHP 8.3 atau lebih baru dengan ekstensi `pdo_mysql`
- Composer
- Node.js LTS dan npm
- MySQL/MariaDB, misalnya melalui Laragon atau XAMPP
- `pdo_sqlite` dan `sqlite3` jika ingin menjalankan feature test

## Setup dari nol

### 1. Siapkan database

Buat database kosong, misalnya:

```sql
CREATE DATABASE hotel_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Jalankan backend

PowerShell:

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `backend/.env` dan sesuaikan konfigurasi berikut:

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

Jalankan migration, seeder, dan server:

```powershell
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API tersedia di `http://localhost:8000/api`.

### 3. Jalankan frontend

Buka terminal kedua:

```powershell
cd frontend
npm install
Copy-Item .env.example .env.local
npm run dev
```

Pastikan `frontend/.env.local` berisi:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

Buka `http://localhost:3000`.

### 4. Akun demo

Seeder membuat akun berikut. Password semua akun adalah `password`.

| Role | Email |
| --- | --- |
| Admin | `admin@hotel.test` |
| Receptionist | `receptionist@hotel.test` |
| Manager | `manager@hotel.test` |
| User | `darren@example.com` |

Ganti password atau nonaktifkan akun demo sebelum deployment production.

## Alur operasional hotel

1. User, Receptionist, Manager, atau Admin membuat reservasi.
2. Reservasi dapat dikaitkan ke user terdaftar atau tamu walk-in.
3. Backend memeriksa overlap tanggal dan kapasitas kamar.
4. Reservasi customer dimulai dari `Pending`; pembayaran deposit pertama atau konfirmasi staff mengubahnya menjadi `Confirmed`.
5. Pembayaran dapat dicatat bertahap. Check-in tidak wajib lunas, sedangkan checkout wajib memiliki saldo nol.
6. Check-in/check-out di luar jadwal membutuhkan alasan override staff dan seluruh aksi masuk audit log.
7. Reservasi dapat dibatalkan atau ditandai `No Show`; pembayaran yang sudah masuk diselesaikan melalui refund tercatat.
8. Setelah check-out, kamar berubah menjadi `Cleaning`. Setelah housekeeping selesai, staff mengubahnya menjadi `Available`.
9. Pajak dan service charge dapat diatur Admin dan disimpan sebagai snapshot pada setiap reservasi.
10. Dashboard dan laporan keuangan menghitung pembayaran dikurangi refund dan mengabaikan transaksi void.

## Hak akses

- **Admin:** semua akses, termasuk master kamar, tipe kamar, service, fasilitas, user, dan status kamar.
- **Receptionist:** booking tamu, walk-in guest, pembayaran, check-in/check-out, laporan, dan status housekeeping.
- **Manager:** operasional, pembayaran, laporan, dan status housekeeping.
- **User:** reservasi milik sendiri, service tambahan, dan pembayaran milik sendiri.

## Endpoint utama

Semua endpoint berikut selain login/register membutuhkan Bearer token:

```http
Accept: application/json
Authorization: Bearer <token>
```

- `POST /api/login`
- `POST /api/register`
- `GET /api/dashboard?date=YYYY-MM-DD`
- `GET|POST /api/reservations`
- `POST /api/reservations/{id}/payments`
- `GET /api/reservations/{id}/folio`
- `POST /api/payments/{id}/refund`
- `POST /api/payments/{id}/void`
- `POST /api/reservations/{id}/check-in`
- `POST /api/reservations/{id}/check-out`
- `POST /api/reservations/{id}/no-show`
- `GET|PATCH /api/settings`
- `GET /api/rooms/availability?check_in=...&check_out=...&guests=...`
- `PATCH /api/rooms/{id}/status`
- `GET /api/reports/occupancy?date=YYYY-MM-DD`
- `GET /api/reports/revenue?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/reports/reservations?from=YYYY-MM-DD&to=YYYY-MM-DD`

## Struktur proyek

```text
hotel-managementsystem/
├── backend/                  # Laravel REST API dan business logic
│   ├── app/Models/            # Eloquent models
│   ├── app/Services/          # Reservation dan hotel metrics service
│   ├── app/Http/Controllers/  # Controller API
│   ├── database/migrations/  # Schema hotel, membership, dan guest
│   ├── database/seeders/     # Data demo
│   ├── routes/api.php         # Endpoint API
│   └── tests/                 # Feature dan unit test
├── frontend/                 # Next.js dashboard
│   ├── src/app/               # Root app dan navigasi
│   ├── src/components/       # View UI hotel
│   ├── src/services/         # API client, alert, formatter
│   └── src/types/            # Kontrak data TypeScript
└── README.md
```

## Perintah quality check

Frontend:

```powershell
cd frontend
npx tsc --noEmit
npm run lint
npm run build
```

Backend:

```powershell
cd backend
php artisan route:list
php artisan migrate:status
composer test
vendor/bin/pint --dirty --format agent
```

Jika test gagal dengan pesan `could not find driver` untuk SQLite, aktifkan ekstensi PHP `pdo_sqlite` dan `sqlite3`, lalu jalankan ulang test.

## Reset data lokal

Perintah ini menghapus dan membuat ulang seluruh tabel database lokal, lalu memasukkan data demo:

```powershell
cd backend
php artisan migrate:fresh --seed
```

Jangan menjalankan perintah tersebut pada database production.

## Troubleshooting

### Frontend tidak dapat mengakses API

- Pastikan backend berjalan di port `8000`.
- Periksa `NEXT_PUBLIC_API_URL` di `frontend/.env.local`.
- Periksa `FRONTEND_URL` di `backend/.env`.
- Setelah mengubah `.env`, jalankan `php artisan config:clear` dan restart server.

### Database gagal terhubung

- Pastikan MySQL/MariaDB aktif.
- Pastikan database `hotel_management` sudah dibuat.
- Periksa `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.

### Migration belum berjalan

```powershell
cd backend
php artisan migrate:status
php artisan migrate
```

## Keamanan

Jangan commit `backend/.env`, `frontend/.env.local`, token, password database, atau credential lain. File `.env.example` hanya digunakan sebagai template konfigurasi.
