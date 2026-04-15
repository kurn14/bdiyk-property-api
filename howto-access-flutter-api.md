# Panduan Akses Flutter API (Mobile App)

Dokumentasi lengkap untuk developer Flutter dalam mengakses API pemesanan properti BDI Yogyakarta.

---

## Daftar Isi

1. [Autentikasi](#1-autentikasi)
2. [CRUD Booking](#2-crud-booking)
3. [Monitoring Booking](#3-monitoring-booking)
4. [Kalender Booking](#4-kalender-booking)
5. [Kalender Detail (Per Tanggal)](#5-kalender-detail-per-tanggal)
6. [Kode Error](#6-kode-error)

---

## 1. Autentikasi

Semua endpoint Flutter API dilindungi oleh **Laravel Sanctum**. Anda harus login terlebih dahulu untuk mendapatkan token.

### Login

```
POST /login
```

**Request Body:**
```json
{
    "email": "admin@example.com",
    "password": "password"
}
```

**Response:**
```json
{
    "token": "1|abc123xyz..."
}
```

### Menggunakan Token

Sertakan token pada setiap request berikutnya:

```
Authorization: Bearer 1|abc123xyz...
```

### Logout

```
POST /logout
Authorization: Bearer {token}
```

---

## 2. CRUD Booking

### 2.1. List Semua Booking (Paginated)

```
GET /bookings
Authorization: Bearer {token}
```

**Parameter Query:**

| Parameter        | Tipe    | Wajib | Keterangan |
|:-----------------|:--------|:------|:-----------|
| `page`           | integer | ❌    | Halaman yang ingin ditampilkan (default: 1) |
| `per_page`       | integer | ❌    | Jumlah item per halaman (default: 10) |
| `search`         | string  | ❌    | Cari berdasarkan kode booking (`booking_code`) |
| `status`         | string  | ❌    | Filter status (lihat [Referensi Status](#referensi-status-booking) di bawah) |
| `property_type_id` | integer | ❌  | Filter berdasarkan tipe properti (1-5) |

### Referensi Status Booking

| Status      | Kode Warna | Keterangan |
|:------------|:-----------|:-----------|
| `booked`    | 🟡 Kuning  | Sedang dibooking, **belum dibayar**. Ruangan dikunci sementara sampai `payment_time_limit` habis. |
| `scheduled` | 🔵 Biru    | Sudah dipesan dan **sudah dibayar**, tetapi belum check-in. Ruangan terkunci permanen. |
| `in_use`    | 🟢 Hijau   | Sedang **digunakan** (sudah check-in, belum check-out). |
| `finished`  | ⚪ Abu-abu  | Sudah **check-out** / selesai digunakan. |
| `cancelled` | 🔴 Merah   | **Dibatalkan** (manual atau otomatis karena `payment_time_limit` terlewati). |

> **Tips Flutter UI:** Gunakan warna di atas sebagai acuan untuk `Container` atau `Chip` status di setiap card booking agar pengguna langsung memahami kondisi booking secara visual.

**Contoh Request:**
```bash
# Halaman pertama, 10 item
curl -X GET "http://localhost:8001/bookings?page=1&per_page=10" \
     -H "Authorization: Bearer {token}"

# Cari booking berdasarkan kode
curl -X GET "http://localhost:8001/bookings?search=CL1A" \
     -H "Authorization: Bearer {token}"

# Filter hanya yang scheduled, 5 item per halaman
curl -X GET "http://localhost:8001/bookings?status=scheduled&per_page=5" \
     -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "booking_code": "CL1A2B",
            "property_id": 1,
            "status": "scheduled",
            "contact_name": "Budi Santoso",
            "contact_email": "budi@instansi.go.id",
            "contact_phone": "08123456789",
            "institution": "Kementerian Perindustrian",
            "payment_time_limit": null,
            "property": {
                "id": 1,
                "name": "Ruang Kelas Borobudur",
                "type": { "id": 1, "name": "Ruang Kelas" }
            },
            "schedules": [
                { "id": 1, "start_time": "2026-04-14 08:00:00", "end_time": "2026-04-14 12:00:00" }
            ],
            "user": { "id": 1, "name": "Admin" }
        }
    ],
    "first_page_url": "http://localhost:8001/bookings?page=1",
    "from": 1,
    "last_page": 3,
    "last_page_url": "http://localhost:8001/bookings?page=3",
    "next_page_url": "http://localhost:8001/bookings?page=2",
    "path": "http://localhost:8001/bookings",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 25
}
```

> **Tips Flutter Pagination:**
> - Gunakan `current_page` dan `last_page` untuk mengetahui posisi halaman.
> - Gunakan `next_page_url` (jika bukan `null`) untuk *infinite scroll* / *load more*.
> - `total` menunjukkan jumlah keseluruhan data yang cocok dengan filter.
> - `from` dan `to` menunjukkan range item yang sedang ditampilkan.

### 2.2. Detail Booking

```
GET /bookings/{id}
Authorization: Bearer {token}
```

### 2.3. Buat Booking Baru

```
POST /bookings
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "property_id": 1,
    "contact_name": "Andi Saputra",
    "contact_email": "andi@mail.com",
    "contact_phone": "08987654321",
    "institution": "PT Maju Jaya",
    "schedules": [
        {
            "start_time": "2026-04-20 08:00:00",
            "end_time": "2026-04-20 12:00:00"
        }
    ]
}
```

**Response (201):**
```json
{
    "id": 5,
    "booking_code": "CL5X9Z",
    "property_id": 1,
    "status": "booked",
    "payment_time_limit": "2026-04-15 16:00:00",
    "schedules": [
        { "start_time": "2026-04-20 08:00:00", "end_time": "2026-04-20 12:00:00" }
    ]
}
```

### 2.4. Update Booking

```
PUT /bookings/{id}
Authorization: Bearer {token}
```

**Request Body (partial update):**
```json
{
    "status": "scheduled",
    "contact_name": "Nama Baru"
}
```

### 2.5. Hapus Booking

```
DELETE /bookings/{id}
Authorization: Bearer {token}
```

---

## 3. Monitoring Booking

Endpoint untuk menampilkan daftar booking berdasarkan periode waktu (**harian**, **mingguan**, atau **bulanan**). Cocok untuk halaman monitoring/dashboard.

### Endpoint

```
GET /bookings/monitoring
Authorization: Bearer {token}
```

### Parameter Query

| Parameter | Tipe   | Wajib | Keterangan |
|:----------|:-------|:------|:-----------|
| `period`  | string | ✅    | Filter periode: `daily`, `weekly`, atau `monthly` |
| `date`    | string | ❌    | Tanggal acuan, format `YYYY-MM-DD`. Default: hari ini |
| `status`  | string | ❌    | Filter status: `booked`, `scheduled`, `in_use`, `finished`, `cancelled` |

### Contoh Request

**Harian (hari ini):**
```bash
curl -X GET "http://localhost:8001/bookings/monitoring?period=daily" \
     -H "Authorization: Bearer {token}" \
     -H "Accept: application/json"
```

**Mingguan (minggu dari tanggal tertentu):**
```bash
curl -X GET "http://localhost:8001/bookings/monitoring?period=weekly&date=2026-04-14" \
     -H "Authorization: Bearer {token}" \
     -H "Accept: application/json"
```

**Bulanan (bulan tertentu, hanya yang scheduled):**
```bash
curl -X GET "http://localhost:8001/bookings/monitoring?period=monthly&date=2026-04-01&status=scheduled" \
     -H "Authorization: Bearer {token}" \
     -H "Accept: application/json"
```

### Contoh Response

```json
{
    "period": "weekly",
    "start_date": "2026-04-13",
    "end_date": "2026-04-19",
    "total": 3,
    "data": [
        {
            "id": 1,
            "booking_code": "CL1A2B",
            "property_id": 1,
            "status": "scheduled",
            "contact_name": "Budi Santoso",
            "contact_email": "budi@instansi.go.id",
            "contact_phone": "08123456789",
            "institution": "Kementerian Perindustrian",
            "payment_time_limit": null,
            "property": {
                "id": 1,
                "name": "Ruang Kelas Borobudur",
                "type": { "id": 1, "name": "Ruang Kelas" }
            },
            "schedules": [
                { "id": 1, "start_time": "2026-04-14 08:00:00", "end_time": "2026-04-14 12:00:00" },
                { "id": 2, "start_time": "2026-04-15 13:00:00", "end_time": "2026-04-15 16:00:00" }
            ],
            "user": { "id": 1, "name": "Admin" }
        },
        {
            "id": 2,
            "booking_code": "VP3C4D",
            "property_id": 6,
            "status": "booked",
            "contact_name": "Andi Saputra",
            "payment_time_limit": "2026-04-14 16:00:00",
            "property": {
                "id": 6,
                "name": "Kamar 101",
                "type": { "id": 3, "name": "Kamar VIP" }
            },
            "schedules": [
                { "id": 3, "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
            ],
            "user": { "id": 1, "name": "Admin" }
        }
    ]
}
```

### Logika Filter Periode

| Period    | Range yang dihasilkan                    |
|:----------|:-----------------------------------------|
| `daily`   | Awal hari → Akhir hari dari `date`       |
| `weekly`  | Senin → Minggu dari minggu `date`        |
| `monthly` | Tanggal 1 → Tanggal terakhir bulan `date`|

> **Catatan untuk Flutter:** Gunakan parameter `period` sebagai pemilih tab (Daily / Weekly / Monthly) di halaman monitoring. Response `start_date` dan `end_date` bisa ditampilkan sebagai header rentang tanggal.

---

## 4. Kalender Booking

Endpoint untuk menampilkan **jumlah properti yang dipakai per tanggal** dalam satu bulan/minggu. Cocok untuk tampilan kalender di mana setiap sel tanggal menampilkan badge angka jumlah properti terpakai.

### Endpoint

```
GET /bookings/calendar
Authorization: Bearer {token}
```

### Parameter Query

| Parameter | Tipe   | Wajib | Keterangan |
|:----------|:-------|:------|:-----------|
| `mode`    | string | ✅    | Mode tampilan: `monthly` atau `weekly` |
| `date`    | string | ❌    | Tanggal acuan, format `YYYY-MM-DD`. Default: hari ini |

### Contoh Request

**Data kalender bulanan April 2026:**
```bash
curl -X GET "http://localhost:8001/bookings/calendar?mode=monthly&date=2026-04-01" \
     -H "Authorization: Bearer {token}" \
     -H "Accept: application/json"
```

### Contoh Response

```json
{
    "mode": "monthly",
    "start_date": "2026-04-01",
    "end_date": "2026-04-30",
    "dates": [
        { "date": "2026-04-01", "property_count": 0, "booking_count": 0 },
        { "date": "2026-04-02", "property_count": 0, "booking_count": 0 },
        "...",
        { "date": "2026-04-14", "property_count": 3, "booking_count": 2 },
        { "date": "2026-04-15", "property_count": 4, "booking_count": 3 },
        { "date": "2026-04-16", "property_count": 3, "booking_count": 2 },
        { "date": "2026-04-17", "property_count": 3, "booking_count": 2 },
        "...",
        { "date": "2026-04-30", "property_count": 0, "booking_count": 0 }
    ]
}
```

### Penjelasan Field Response

| Field            | Keterangan |
|:-----------------|:-----------|
| `date`           | Tanggal dalam format `YYYY-MM-DD` |
| `property_count` | Jumlah **properti unik** yang sedang digunakan pada tanggal tersebut |
| `booking_count`  | Jumlah **booking unik** yang aktif pada tanggal tersebut |

> **Komponen Flutter yang Direkomendasikan:**
> - Gunakan `TableCalendar` package untuk menampilkan kalender.
> - Render `property_count` sebagai badge/dot di setiap sel tanggal.
> - Jika `property_count > 0`, beri warna berbeda pada tanggal tersebut.
> - Ketika pengguna **mengetuk tanggal**, panggil endpoint [Kalender Detail](#5-kalender-detail-per-tanggal) untuk mendapatkan daftar booking.

---

## 5. Kalender Detail (Per Tanggal)

Endpoint untuk menampilkan **daftar booking yang aktif pada tanggal tertentu**. Dipanggil ketika pengguna mengetuk salah satu tanggal di kalender.

### Endpoint

```
GET /bookings/calendar/{date}
Authorization: Bearer {token}
```

| Parameter | Tipe   | Wajib | Keterangan |
|:----------|:-------|:------|:-----------|
| `date`    | string (URL) | ✅ | Tanggal yang dipilih, format `YYYY-MM-DD` |

### Contoh Request

```bash
curl -X GET "http://localhost:8001/bookings/calendar/2026-04-14" \
     -H "Authorization: Bearer {token}" \
     -H "Accept: application/json"
```

### Contoh Response

```json
{
    "date": "2026-04-14",
    "total": 2,
    "data": [
        {
            "id": 1,
            "booking_code": "CL1A2B",
            "property_id": 1,
            "status": "scheduled",
            "contact_name": "Budi Santoso",
            "contact_email": "budi@instansi.go.id",
            "contact_phone": "08123456789",
            "institution": "Kementerian Perindustrian",
            "payment_time_limit": null,
            "property": {
                "id": 1,
                "name": "Ruang Kelas Borobudur",
                "type": { "id": 1, "name": "Ruang Kelas" }
            },
            "schedules": [
                { "id": 1, "start_time": "2026-04-14 08:00:00", "end_time": "2026-04-14 12:00:00" }
            ],
            "user": { "id": 1, "name": "Admin" }
        },
        {
            "id": 2,
            "booking_code": "VP3C4D",
            "property_id": 6,
            "status": "booked",
            "contact_name": "Andi Saputra",
            "contact_email": "andi@perusahaan.com",
            "contact_phone": "08987654321",
            "institution": "PT Maju Jaya",
            "payment_time_limit": "2026-04-14 16:00:00",
            "property": {
                "id": 6,
                "name": "Kamar 101",
                "type": { "id": 3, "name": "Kamar VIP" }
            },
            "schedules": [
                { "id": 3, "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
            ],
            "user": { "id": 1, "name": "Admin" }
        }
    ]
}
```

> **Alur UI Flutter:**
> 1. Tampilkan kalender bulanan → panggil `GET /bookings/calendar?mode=monthly&date=2026-04-01`
> 2. Render badge `property_count` di setiap tanggal
> 3. User tap tanggal 14 April → panggil `GET /bookings/calendar/2026-04-14`
> 4. Tampilkan daftar booking dalam BottomSheet atau halaman baru

---

## 6. Kode Error

| HTTP Code | Situasi | Contoh Message |
|:----------|:--------|:---------------|
| `200` | Sukses (GET/PUT) | — |
| `201` | Booking berhasil dibuat | — |
| `401` | Token tidak valid atau expired | `"Unauthenticated."` |
| `404` | Booking tidak ditemukan | `"Booking not found"` |
| `409` | Jadwal bentrok | `"Ruangan sudah dipesan pada jadwal tanggal dan jam tersebut"` |
| `422` | Validasi input gagal | `{ "errors": { "period": ["The period field is required."] } }` |

---

## Ringkasan Endpoint

| Method | Endpoint | Kegunaan |
|:-------|:---------|:---------|
| `POST` | `/login` | Login, dapatkan token |
| `POST` | `/logout` | Logout |
| `GET` | `/bookings` | List semua booking |
| `GET` | `/bookings/{id}` | Detail booking |
| `POST` | `/bookings` | Buat booking baru |
| `PUT` | `/bookings/{id}` | Update booking |
| `DELETE` | `/bookings/{id}` | Hapus booking |
| `GET` | `/bookings/monitoring?period=daily\|weekly\|monthly&date=YYYY-MM-DD&status=...` | Monitoring per periode |
| `GET` | `/bookings/calendar?mode=monthly\|weekly&date=YYYY-MM-DD` | Kalender (jumlah per tanggal) |
| `GET` | `/bookings/calendar/{date}` | Detail booking per tanggal |
| `GET` | `/properties` | List properti |
| `GET` | `/property-types` | List tipe properti |

---

## Alur Integrasi Flutter

```
┌─────────────────────────────────────────┐
│           HALAMAN MONITORING            │
│                                         │
│  [Tab: Harian] [Tab: Mingguan] [Tab: Bulanan]
│                                         │
│  GET /bookings/monitoring               │
│      ?period=daily|weekly|monthly        │
│      &date=2026-04-14                   │
│               │                         │
│               ▼                         │
│  ┌───────────────────────────────┐      │
│  │ Daftar Booking (ListView)    │      │
│  │ - Booking Code: CL1A2B      │      │
│  │   Status: scheduled          │      │
│  │   Ruang: Borobudur           │      │
│  │   Jadwal: 14 Apr 08:00-12:00│      │
│  │ - Booking Code: VP3C4D      │      │
│  │   Status: booked             │      │
│  │   Ruang: Kamar 101           │      │
│  └───────────────────────────────┘      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│           HALAMAN KALENDER              │
│                                         │
│  GET /bookings/calendar                 │
│      ?mode=monthly&date=2026-04-01      │
│               │                         │
│               ▼                         │
│  ┌───────────────────────────────┐      │
│  │     << April 2026 >>          │      │
│  │ Sen Sel Rab Kam Jum Sab Min  │      │
│  │           1   2   3   4   5  │      │
│  │  6   7   8   9  10  11  12  │      │
│  │ 13 [14] 15  16  17  18  19  │      │
│  │      3↑  4↑  3↑  3↑         │  ← badge
│  │ 20  21  22  23  24  25  26  │      │
│  │ 27  28  29  30              │      │
│  └───────────────────────────────┘      │
│               │                         │
│        User tap [14]                    │
│               │                         │
│               ▼                         │
│  GET /bookings/calendar/2026-04-14      │
│               │                         │
│               ▼                         │
│  ┌───────────────────────────────┐      │
│  │ BottomSheet / Detail Page    │      │
│  │ Tanggal: 14 April 2026      │      │
│  │ Total: 2 booking             │      │
│  │ ─────────────────────────    │      │
│  │ 1. CL1A2B - Borobudur       │      │
│  │    08:00 - 12:00 (scheduled) │      │
│  │ 2. VP3C4D - Kamar 101       │      │
│  │    14:00 - 17 Apr (booked)   │      │
│  └───────────────────────────────┘      │
└─────────────────────────────────────────┘
```
