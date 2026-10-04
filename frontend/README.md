# Haven Hotel System — Frontend

Frontend dashboard Haven Hotel System dibuat dengan Next.js, React, dan TypeScript. Frontend berkomunikasi dengan Laravel API pada folder `../backend` menggunakan Bearer token.

## Prasyarat

- Node.js versi LTS
- npm
- Backend Laravel sudah berjalan di `http://localhost:8000`

## Setup

```bash
npm install
cp .env.example .env.local
npm run dev
```

Untuk Windows PowerShell:

```powershell
Copy-Item .env.example .env.local
```

Buka `http://localhost:3000`. Isi `frontend/.env.local`:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

Semua perubahan pada environment variable `NEXT_PUBLIC_*` memerlukan restart development server.

## Scripts

```bash
npm run dev      # menjalankan development server
npm run lint     # menjalankan ESLint
npm run build    # membuat production build
npm run start    # menjalankan production build
```

## Struktur folder

```text
frontend/
├── public/                 # Asset statis
├── src/
│   ├── app/
│   │   ├── layout.tsx      # Root layout dan metadata
│   │   ├── page.tsx        # Root dashboard dan navigasi halaman
│   │   ├── globals.css     # Global styles
│   │   └── page.module.css # Styles komponen dashboard
│   ├── components/
│   │   ├── ui.tsx          # Panel, status, empty state, dan UI umum
│   │   ├── Login.tsx       # Halaman login/register
│   │   └── views/          # View Overview, Reservations, Services, dll.
│   ├── config/             # Konfigurasi menu dashboard
│   ├── lib/                # Re-export helper
│   ├── services/
│   │   ├── api.ts          # HTTP client dan bearer token
│   │   ├── alerts.ts       # SweetAlert2 helpers
│   │   └── formatters.ts   # Formatter tanggal dan mata uang
│   └── types/              # TypeScript types
├── .env.example            # Template environment yang boleh di-commit
├── .env.local              # Environment lokal, jangan di-commit
├── .gitignore
├── next.config.ts
├── package.json
└── tsconfig.json
```

## Alur login dan API

Saat login berhasil, token disimpan di `localStorage` dengan key `hotel_token`. Client API mengirimkannya sebagai:

```http
Authorization: Bearer <token>
```

Jika API tidak bisa diakses, periksa hal berikut:

1. Laravel berjalan di port 8000.
2. `NEXT_PUBLIC_API_URL` benar.
3. `FRONTEND_URL` pada backend mengarah ke `http://localhost:3000`.
4. Development server sudah direstart setelah `.env.local` diubah.

## Catatan environment

`.gitignore` mengabaikan file `.env*`, tetapi memberikan pengecualian untuk `.env.example`. Dengan begitu, template konfigurasi tetap tersedia di repository sementara secret lokal tetap aman.
