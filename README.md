# Haven Hotel System

Haven Hotel System adalah aplikasi manajemen operasional hotel. Aplikasi ini menyediakan dashboard untuk reservasi, kamar, check-in/check-out, pembayaran, layanan tambahan, fasilitas, laporan, dan manajemen pengguna.

## Teknologi

- **Frontend:** Next.js 16, React 19, TypeScript, SweetAlert2
- **Backend:** Laravel 13, PHP 8.3+, Laravel Sanctum
- **Database:** MySQL untuk development, SQLite in-memory untuk testing
- **Authentication:** Bearer token melalui Laravel Sanctum

## Prasyarat

Pastikan perangkat sudah memiliki:

- PHP >= 8.3 dan ekstensi PHP yang dibutuhkan Laravel
- Composer
- Node.js dan npm
- MySQL/MariaDB (misalnya melalui Laragon atau XAMPP)
- Ekstensi PHP `pdo_sqlite` dan `sqlite3` jika ingin menjalankan feature test
- Git, jika project diambil dari repository

## Setup cepat

Jalankan setiap service pada terminal terpisah.

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Di Windows PowerShell, gunakan perintah berikut untuk menyalin environment file:

```powershell
Copy-Item .env.example .env
```

Buat database kosong bernama `hotel_management`, lalu pastikan nilai `DB_*` di `backend/.env` sesuai dengan MySQL lokal. Setelah itu jalankan migration dan seeder:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API tersedia di `http://localhost:8000/api`.

### 2. Frontend

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

Di Windows PowerShell:

```powershell
Copy-Item .env.example .env.local
```

Buka `http://localhost:3000` di browser. Pastikan `NEXT_PUBLIC_API_URL` di `frontend/.env.local` mengarah ke backend, biasanya:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

## Akun demo

Seeder menyediakan akun berikut dengan password `password`:

| Role | Email |
| --- | --- |
| Admin | `admin@hotel.test` |
| Receptionist | `receptionist@hotel.test` |
| Manager | `manager@hotel.test` |
| User | `darren@example.com` |

Untuk penggunaan nyata, segera ganti atau nonaktifkan akun demo dan jangan memakai password tersebut di production.

## Cara menggunakan aplikasi

1. Jalankan backend dan frontend.
2. Login melalui halaman utama.
3. Gunakan menu **Reservations** untuk membuat dan mengelola booking.
4. Gunakan **Rooms** untuk melihat status kamar dan tipe kamar.
5. Gunakan **Check-in / out** untuk proses kedatangan dan keberangkatan.
6. Gunakan **Payments** untuk memproses pembayaran reservasi atau layanan.
7. Gunakan **Services** untuk memilih layanan tambahan pada reservasi, mengatur jumlah orang, lalu meneruskan pembayaran.
8. Gunakan **Facilities**, **Reports**, dan **Users** sesuai role akun.

Hak akses utama:

- **Admin:** akses penuh termasuk pengelolaan katalog dan pengguna.
- **Receptionist:** operasional reservasi, kamar, check-in/out, pembayaran, dan laporan.
- **Manager:** dashboard, operasional, pembayaran, dan laporan.
- **User:** membuat reservasi, melihat layanan/fasilitas, dan melakukan pembayaran miliknya.

## Environment variables

Template environment disimpan dan boleh di-commit:

- `backend/.env.example`
- `frontend/.env.example`

File yang berisi konfigurasi lokal atau secret tidak boleh di-commit:

- `backend/.env`
- `frontend/.env.local`
- environment file lain seperti `.env.production`

`APP_KEY` pada backend dikosongkan di template dan harus dibuat dengan `php artisan key:generate`. `FRONTEND_URL` pada backend harus sama dengan URL frontend agar request API tidak ditolak oleh CORS.

## Struktur folder

```text
hotel-managementsystem/
├── README.md
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/    # Controller API dan authorization flow
│   │   ├── Models/              # Model Eloquent
│   │   └── Services/            # Business logic aplikasi
│   ├── bootstrap/               # Bootstrap Laravel
│   ├── config/                  # Konfigurasi aplikasi, database, CORS, Sanctum
│   ├── database/
│   │   ├── migrations/          # Struktur database
│   │   ├── seeders/              # Data awal/demo
│   │   └── database.sqlite       # Database lokal bila memakai SQLite
│   ├── public/                  # Entry point dan asset publik
│   ├── resources/               # Resource Laravel
│   ├── routes/                 # Route web, API, dan console
│   ├── tests/                  # Unit dan feature tests
│   ├── .env.example
│   ├── .gitignore
│   ├── artisan
│   ├── composer.json
│   └── phpunit.xml
└── frontend/
    ├── public/                 # Asset statis
    ├── src/
    │   ├── app/                # App Router, layout, global/page styles
    │   ├── components/         # Komponen UI dan view dashboard
    │   ├── config/             # Konfigurasi menu dan dashboard
    │   ├── lib/                # Re-export/helper library
    │   ├── services/           # API client, formatter, alert
    │   └── types/              # TypeScript types
    ├── .env.example
    ├── .gitignore
    ├── next.config.ts
    ├── package.json
    └── tsconfig.json
```

## Perintah development dan quality check

Frontend:

```bash
npm run dev       # development server
npm run lint      # ESLint
npm run build     # production build
npm run start     # menjalankan hasil build
```

Backend:

```bash
php artisan test                 # seluruh test
php artisan test --testsuite=Feature
php artisan route:list            # daftar endpoint
php artisan migrate:fresh --seed  # reset database lokal
```

## Endpoint utama

- `POST /api/login` dan `POST /api/register`
- `GET /api/dashboard`
- `GET|POST /api/reservations`
- `GET /api/rooms`, `/api/room-types`, `/api/facilities`, `/api/services`
- `GET /api/payments`
- `POST /api/reservations/{reservation}/payments`
- `POST /api/reservations/{reservation}/check-in`
- `POST /api/reservations/{reservation}/check-out`

Endpoint yang membutuhkan login harus menerima header:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Troubleshooting

**Frontend tidak bisa memanggil API**

- Pastikan backend berjalan di port 8000.
- Periksa `NEXT_PUBLIC_API_URL` di `frontend/.env.local`.
- Pastikan `FRONTEND_URL=http://localhost:3000` di `backend/.env`.
- Jalankan `php artisan config:clear` setelah mengubah `.env`.

**Database connection error**

- Pastikan MySQL/MariaDB aktif.
- Pastikan database `hotel_management` sudah dibuat.
- Periksa `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.

**Login gagal setelah perubahan konfigurasi**

```bash
php artisan config:clear
php artisan cache:clear
php artisan migrate --seed
```

## Catatan keamanan

Jangan commit `.env`, token, password database, atau credential layanan eksternal. File `.env.example` hanya berisi nilai contoh dan memang sengaja tidak di-ignore oleh Git agar dapat menjadi template setup bagi developer lain.
