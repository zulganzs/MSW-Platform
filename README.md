# MSW Platform — Platform Pelaporan Sampah Berbasis Web & Mobile

Platform pelaporan masalah sampah kota (Municipal Solid Waste) yang menghubungkan warga, petugas triage, dan crew lapangan dalam satu alur: **lapor → triage → dispatch → resolve → notifikasi email**.

Dibangun dengan **Laravel 12 (API-only)** untuk backend dan **Expo React Native** untuk frontend yang jalan di **web, Android, dan iOS** dari satu codebase.

Selaras dengan **SDG 11 — Sustainable Cities and Communities**, Target 11.6: pengelolaan sampah kota.

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Arsitektur](#arsitektur)
- [Peran Pengguna](#peran-pengguna)
- [Alur Aplikasi](#alur-aplikasi)
- [Model Data](#model-data)
- [API Endpoints](#api-endpoints)
- [Cara Menjalankan](#cara-menjalankan)
- [Testing](#testing)
- [Struktur Proyek](#struktur-proyek)
- [Dokumentasi](#dokumentasi)

---

## Fitur Utama

| Fitur | Deskripsi |
|---|---|
| **Lapor Sampah** | Warga buat laporan dengan kategori, deskripsi, pin peta, dan foto. Bisa public, private, atau anonymous. |
| **Triage & Assignment** | Staff melihat laporan masuk, cek duplikat, dan tugaskan ke crew lapangan. |
| **Eksekusi Lapangan** | Crew terima tugas, mulai kerja (assigned → in_progress), upload bukti penyelesaian, tandai selesai (in_progress → completed). |
| **Notifikasi Email** | Saat laporan selesai, sistem otomatis kirim email konfirmasi ke pelapor (dilewati jika anonymous). |
| **Peta Publik** | Semua laporan public tampil di peta interaktif (Leaflet di web, react-native-maps di native). |
| **Role-Based Access** | Setiap aksi di-guard oleh role: citizen, staff, atau crew. |
| **Anonymous Masking** | Laporan anonymous: user_id tersimpan di DB, tapi identitas pelapor dihilangkan dari response API. |

---

## Arsitektur

```
┌──────────────┐     HTTP/JSON     ┌──────────────────┐
│   Expo App    │ ←──────────────→ │  Laravel 12 API   │
│  (Web/Android/│   Bearer Token   │  (Sanctum)        │
│   iOS)        │                  │                  │
│               │                  │  ┌────────────┐   │
│  Expo Router  │                  │  │ SQLite DB  │   │
│  Axios        │                  │  │ (5 tables) │   │
│  Leaflet/Maps │                  │  └────────────┘   │
└──────────────┘                  │  Mail (SMTP/log)  │
                                  └──────────────────┘
```

**Backend:** Laravel 12, PHP 8.2+, SQLite (dev) / PostgreSQL (prod-ready), Sanctum token auth, Eloquent ORM.

**Frontend:** Expo SDK 57, React Native 0.86, Expo Router (file-based routing), Axios, expo-secure-store, react-leaflet (web), react-native-maps (native).

---

## Peran Pengguna

| Role | Akses | Aksi |
|---|---|---|
| **Citizen** (Warga) | Public + own data | Register, login, buat laporan, upload foto, lacak laporan, lihat peta publik, hapus laporan (status: submitted) |
| **Staff** (Petugas Triage) | All reports | Login, lihat laporan masuk, tugaskan crew, kelola kategori, lihat daftar crew |
| **Crew** (Petugas Lapangan) | Assigned reports | Login, lihat tugas, mulai kerja, upload bukti penyelesaian, tandai selesai |

---

## Alur Aplikasi

```
1. INTAKE          Citizen → POST /api/reports + POST /api/attachments (type=submission)
                   Status: submitted

2. TRIAGE          Staff → GET /api/staff/reports?status=submitted
                   Staff → POST /api/staff/reports/{id}/assign
                   Status: submitted → assigned

3. EXECUTION       Crew → GET /api/crew/reports
                   Crew → PATCH /api/crew/reports/{id}/status (assigned → in_progress)
                   Crew → POST /api/attachments (type=closure)
                   Crew → PATCH /api/crew/reports/{id}/status (in_progress → completed)
                   Status: assigned → in_progress → completed

4. NOTIFICATION    ReportObserver fires on status=completed
                   → Mail::send(ReportCompletedMail) to citizen email
                   → Skipped if visibility=anonymous
```

### Visibility Matrix

| visibility | Siapa bisa lihat detail | Siapa bisa lihat identitas pelapor |
|---|---|---|
| `public` | Semua (termasuk belum login) | Semua |
| `private` | Pemilik + staff + crew | Pemilik + staff + crew |
| `anonymous` | Semua (termasuk belum login) | **Tidak ada** (bahkan staff/crew via API) |

### Status Transition Matrix

| Dari | Ke | Siapa bisa trigger |
|---|---|---|
| submitted | assigned | Staff only |
| assigned | in_progress | Crew (yang ditugaskan) |
| in_progress | completed | Crew (yang ditugaskan) |
| * | * (mundur) | **Ditolak** — 422 |

---

## Model Data

5 tabel utama dengan relasi:

```
users (1) ───────< (N) reports (N) >─────── (1) categories
  │                     │
  │                     │
  └───< (N) attachments (N) >─── (1) reports
                          │
                          │
reports (M) ──── (N) users [crew]       ← via report_crew pivot
                  │
                  └── staff_user_id (siapa yang assign)
                  └── assigned_at (kapan diassign)
                  └── unique(report_id, crew_user_id) ← anti double-assign
```

| Tabel | Relasi | FK |
|---|---|---|
| `users` → `reports` | One-to-Many | `reports.user_id` (nullable) |
| `categories` → `reports` | One-to-Many | `reports.category_id` |
| `reports` → `attachments` | One-to-Many | `attachments.report_id` |
| `users` → `attachments` | One-to-Many | `attachments.user_id` |
| `users` ↔ `reports` | Many-to-Many | `report_crew` (pivot) |

---

## API Endpoints

### Auth

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `POST` | `/api/register` | Public | Registrasi citizen baru |
| `POST` | `/api/login` | Public | Login, return bearer token |
| `POST` | `/api/logout` | Auth | Logout, revoke token |
| `GET` | `/api/user` | Auth | Info user yang login |

### Reports

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/reports` | Public | List laporan (paginated 15/page, filter: `status`, `category_id`) |
| `GET` | `/api/reports/{id}` | Public/Owner/Staff/Crew | Detail laporan (visibility matrix diterapkan) |
| `POST` | `/api/reports` | Citizen | Buat laporan baru |
| `DELETE` | `/api/reports/{id}` | Citizen (owner) | Hapus laporan (hanya status: submitted) |

### Categories

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/categories` | Public | List 10 kategori sampah |
| `POST` | `/api/categories` | Staff | Tambah kategori |
| `PUT` | `/api/categories/{id}` | Staff | Update kategori |
| `DELETE` | `/api/categories/{id}` | Staff | Hapus kategori |

### Staff

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/staff/dashboard` | Staff | Health check |
| `POST` | `/api/staff/reports/{id}/assign` | Staff | Tugaskan crew ke laporan |
| `GET` | `/api/staff/users` | Staff | List semua user (untuk picker crew) |

### Crew

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/crew/dashboard` | Crew | Health check |
| `GET` | `/api/crew/reports` | Crew | List laporan yang ditugaskan ke crew ini |
| `PATCH` | `/api/crew/reports/{id}/status` | Crew | Update status (assigned→in_progress→completed) |

### Attachments

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `POST` | `/api/attachments` | Auth | Upload file (type=submission: citizen, type=closure: crew) |

### Health

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/health` | Public | Health check |

---

## Cara Menjalankan

### Prasyarat

- **PHP** 8.2+
- **Composer** 2.x
- **Node.js** 18+
- **npm** atau **npx**
- **Expo CLI** (`npm install -g expo-cli` opsional)

### 1. Backend (Laravel API)

```bash
cd backend

# Install dependencies
composer install

# Copy .env
cp .env.example .env

# Konfigurasi .env untuk SQLite (default sudah SQLite)
# DB_CONNECTION=sqlite
# DB_DATABASE=/path/to/database.sqlite
# FRONTEND_URL=http://localhost:19006

# Generate app key
php artisan key:generate

# Jalankan migrasi + seeder
php artisan migrate:fresh --seed

# Start server
php artisan serve
# API berjalan di http://localhost:8000
```

Seeder akan membuat:
- 10 kategori sampah (Tumpukan Sampah Liar, Tempat Sampah Penuh, dll)
- 3 user dengan role berbeda (citizen, staff, crew)

### 2. Frontend (Expo App)

```bash
cd mobile

# Install dependencies
npm install

# Copy .env
cp .env.example .env
# EXPO_PUBLIC_API_URL=http://localhost:8000/api

# Start Expo
npx expo start

# Pilih platform:
# - Web:  npx expo start --web  → http://localhost:19006
# - Android: npx expo start --android (butuh emulator/device)
# - iOS: npx expo start --ios (butuh Xcode/Expo Go)
# - Scan QR code dengan Expo Go app di device fisik
```

### 3. Aplikasi Siap Dipakai

1. Buka `http://localhost:19006` di browser (web target)
2. Login dengan salah satu akun seeder (lihat [Test Credentials](#test-credentials))
3. Pilih peran (citizen/staff/crew) — navigasi otomatis ke dashboard sesuai role

---

## Test Credentials

| Role | Nama | Email | Password |
|---|---|---|---|
| Citizen | Budi Santoso | `citizen@test.com` | `Password123!` |
| Staff | Siti Rahayu | `staff@test.com` | `Password123!` |
| Crew | Joko Prasetyo | `crew@test.com` | `Password123!` |

> Credential di-generate oleh `UserSeeder`. Lihat `backend/database/seeders/UserSeeder.php`.

---

## Testing

### Backend (PHPUnit)

```bash
cd backend

# Jalankan semua test
php artisan test

# Filter test spesifik
php artisan test --filter=ReportStoreTest
php artisan test --filter=Auth
php artisan test --filter=AttachmentUploadTest

# Dengan coverage
php artisan test --coverage
```

Testing strategy:
- **TDD** (red-green-refactor) — test ditulis sebelum implementasi
- `RefreshDatabase` trait — DB di-reset per test
- `Storage::fake()` — file upload tidak menulis ke disk
- `Mail::fake()` — email tidak terkirim saat test
- SQLite `:memory:` untuk test DB (cepat, isolated)

### Frontend

```bash
cd mobile

# Type check
npx tsc --noEmit

# Start dev server (web target untuk QA)
npx expo start --web
```

---

## Struktur Proyek

```
StudyCaseEISD/
├── backend/                          # Laravel 12 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/           # AuthController, ReportController, dll
│   │   │   └── Middleware/
│   │   │       └── CheckRole.php      # Role-based access control
│   │   ├── Models/                    # User, Report, Category, Attachment, ReportCrew
│   │   ├── Observers/
│   │   │   └── ReportObserver.php     # Trigger email saat status=completed
│   │   └── Mail/
│   │       └── ReportCompletedMail.php
│   ├── database/
│   │   ├── migrations/               # 5 tabel + Sanctum
│   │   ├── factories/                # UserFactory, ReportFactory, dll
│   │   └── seeders/                   # CategorySeeder, UserSeeder
│   ├── routes/
│   │   └── api.php                    # Semua route (API-only, no web.php)
│   ├── tests/
│   │   ├── Feature/                   # Auth, Reports, Attachments, Assignment, Status
│   │   └── Unit/                      # Model relationship tests
│   └── phpunit.xml                    # SQLite :memory: config
│
├── mobile/                           # Expo React Native
│   ├── app/                          # Expo Router (file-based routing)
│   │   ├── (auth)/                   # Login, register screens
│   │   ├── (app)/                    # Protected screens
│   │   │   ├── (citizen)/            # Create report, list, map
│   │   │   ├── (staff)/              # Dashboard, assignment
│   │   │   └── (crew)/               # Dashboard, status update, closure
│   │   └── _layout.tsx              # Root navigator
│   ├── src/
│   │   ├── components/               # MapWidget (web/native conditional)
│   │   ├── hooks/                    # useAuth (token + role management)
│   │   └── services/                 # api.ts (Axios), auth.ts
│   └── app.json
│
├── docs/
│   ├── UML-DIAGRAMS.md              # Use Case, Class, Activity, Sequence diagrams
│   └── VIDEO-SCRIPT.md              # Skrip video presentasi
│
├── PROJECT-DESIGN.md                # UI design specification
├── a-web-based-platform-for-municipal-solid-waste-management.md  # Case study
└── README.md                        # This file
```

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [`docs/UML-DIAGRAMS.md`](docs/UML-DIAGRAMS.md) | Use Case, Class, Activity, dan Sequence diagram (Mermaid) |
| [`docs/VIDEO-SCRIPT.md`](docs/VIDEO-SCRIPT.md) | Skrip video presentasi (Bahasa Indonesia) |
| [`PROJECT-DESIGN.md`](PROJECT-DESIGN.md) | Spesifikasi desain UI untuk web dan mobile |
| [`a-web-based-platform-for-municipal-solid-waste-management.md`](a-web-based-platform-for-municipal-solid-waste-management.md) | Studi kasus + data dictionary + blueprint Laravel |

---

## Tech Stack

### Backend

| Teknologi | Versi | Fungsi |
|---|---|---|
| Laravel | 12.x | Framework PHP (API-only) |
| PHP | 8.2+ | Runtime |
| Sanctum | (bundled) | Token-based auth |
| Eloquent ORM | (bundled) | Database abstraction |
| SQLite | — | Database (dev/test) |
| PHPUnit | 11.x | Testing |

### Frontend

| Teknologi | Versi | Fungsi |
|---|---|---|
| Expo SDK | 57 | Cross-platform framework |
| React Native | 0.86 | Mobile framework |
| Expo Router | 57 | File-based routing |
| Axios | 1.x | HTTP client |
| expo-secure-store | 57 | Token storage (native) |
| react-leaflet | 5.x | Map (web) |
| react-native-maps | 1.x | Map (native) |
| expo-location | 57 | GPS access |
| expo-image-picker | 57 | Photo upload |

---

## Lisensi

MIT — bebas digunakan dan dimodifikasi.

---

**Dibuat untuk:** Studi kasus EISD — SDG 11 Target 11.6
**Tanggal:** September 2026
