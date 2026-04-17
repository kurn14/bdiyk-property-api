# HOWTO: Mengakses Web API - BDI Properties

Panduan lengkap untuk mengintegrasikan sistem eksternal (Website / Inventory Hub) dengan API BDI Properties. Dokumen ini menjelaskan cara **mengecek ketersediaan** dan **melakukan pemesanan** properti secara remote.

---

## Daftar Isi

1. [Autentikasi](#1-autentikasi)
2. [Konsep ID Properti](#2-konsep-id-properti)
3. [Mengecek Ketersediaan (Availability)](#3-mengecek-ketersediaan-availability)
4. [Melakukan Pemesanan (Booking Awal)](#4-melakukan-pemesanan-booking-awal)
5. [Konfirmasi Pembayaran (Lock Ruangan)](#5-konfirmasi-pembayaran-lock-ruangan)
6. [Membatalkan Pemesanan](#6-membatalkan-pemesanan)
7. [Kode Error](#7-kode-error)
8. [Alur Integrasi Lengkap](#8-alur-integrasi-lengkap)

---

## 1. Autentikasi

Seluruh endpoint Web API memerlukan header `X-API-Key`.

| Header | Nilai |
|:---|:---|
| `X-API-Key` | *(lihat variabel `API_KEY` di file `.env` Laravel backend)* |
| `Accept` | `application/json` |
| `Content-Type` | `application/json` *(untuk POST/PUT)* |

**Contoh:**
```bash
curl -X GET http://localhost:8000/web/properties \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

---

## 2. Konsep ID Properti

Website melihat **8 produk** yang tersedia untuk dipesan. Di balik layar, API membedakan antara **ID Fisik** (ruangan spesifik) dan **ID Virtual** (kategori kamar yang di-auto-assign).

### Tabel Katalog Produk Website

| ID | Nama Produk | Tipe | Perilaku API |
|:---|:---|:---|:---|
| **1** | Ruang Kelas Borobudur | Ruang Kelas | Booking langsung ke ruangan ini |
| **2** | Ruang Kelas Prambanan | Ruang Kelas | Booking langsung ke ruangan ini |
| **3** | Ruang Kelas Mendut | Ruang Kelas | Booking langsung ke ruangan ini |
| **4** | Ruang Kelas Boko | Ruang Kelas | Booking langsung ke ruangan ini |
| **5** | Ruang Rapat | Meeting Room | Booking langsung ke ruangan ini |
| **6** ⭐ | Kamar VIP | Kamar VIP | **Auto-assign** dari 8 unit kamar VIP |
| **7** ⭐ | Kamar Inap 2 Bed | Kamar 2 Bed | **Auto-assign** dari 14 unit kamar 2 bed |
| **8** ⭐ | Kamar Inap 3 Bed | Kamar 3 Bed | **Auto-assign** dari 27 unit kamar 3 bed |

> **⭐ ID Virtual (6, 7, 8):** Ketika website mengirim `property_id: 6`, API tidak mencari "properti nomor 6" di database. Melainkan, API mencari seluruh kamar bertipe VIP yang masih kosong pada jadwal yang diminta, lalu otomatis meng-assign unit kamar spesifik.

### Inventaris Fisik di Balik Virtual ID

| Virtual ID | Tipe | Jumlah Unit | Nomor Kamar |
|:---|:---|:---|:---|
| 6 | Kamar VIP | 8 unit | 101, 102, 103, 104, 129, 131, 133, 135 |
| 7 | Kamar 2 Bed | 14 unit | 105, 106, 107, 137, 139, 141, 201-208 |
| 8 | Kamar 3 Bed | 27 unit | 108-128, 130, 132, 134, 136, 138, 140 |

---

## 3. Mengecek Ketersediaan (Availability)

### Endpoint

```
GET /web/properties/{id}/availability?start_date={start}&end_date={end}
```

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `id` | integer | ✅ | ID Produk (1-8) |
| `start_date` | string (date) | ✅ | Tanggal mulai, format: `YYYY-MM-DD` |
| `end_date` | string (date) | ✅ | Tanggal selesai, format: `YYYY-MM-DD` (harus setelah `start_date`) |

---

### Skenario A: Cek Ruangan Spesifik (ID 1-5)

Mengecek apakah satu ruangan tertentu tersedia pada rentang tanggal.

**Request:**
```bash
curl -X GET "http://localhost:8000/web/properties/1/availability?start_date=2026-04-15&end_date=2026-04-16" \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

**Response (tersedia):**
```json
{
    "property_id": 1,
    "available": true,
    "conflicting_bookings": 0,
    "message": "Property tersedia"
}
```

**Response (tidak tersedia):**
```json
{
    "property_id": 1,
    "available": false,
    "conflicting_bookings": 2,
    "message": "Property tidak tersedia pada tanggal tersebut"
}
```

---

### Skenario B: Cek Kategori Kamar (ID 6, 7, 8)

Mengecek berapa banyak kamar dari satu kategori yang masih tersedia. Sangat berguna untuk menampilkan sisa stok kamar di website.

**Request:**
```bash
curl -X GET "http://localhost:8000/web/properties/6/availability?start_date=2026-04-15&end_date=2026-04-17" \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

**Response (tersedia):**
```json
{
    "property_id": 6,
    "available": true,
    "available_count": 5,
    "message": "Tersedia 5 kamar"
}
```

**Response (habis):**
```json
{
    "property_id": 6,
    "available": false,
    "available_count": 0,
    "message": "Kamar sudah habis pada tanggal tersebut"
}
```

> **Tips Frontend:** Gunakan nilai `available_count` untuk menampilkan informasi seperti *"Sisa 5 kamar"* atau untuk membatasi nilai maksimum input `quantity` pada form pemesanan.

---

## 4. Melakukan Pemesanan (Booking Awal)

> **Catatan Penting:** Sekarang **satu booking bisa berisi banyak properti sekaligus** (misal: 2 ruang kelas + 8 kamar). Properti yang dipesan akan memiliki status awal **`booked`** dan nilai **`payment_time_limit`** (default 2 jam). Di fase ini, ketersediaan ruangan **sudah dikunci (diblokir)** dari pengguna lain. Jika tidak segera dibayar sebelum `payment_time_limit` terlewat, sistem otomatis membatalkan seluruh pemesanan.

### Endpoint

```
POST /web/bookings
```

### Parameter Body (JSON)

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `contact_name` | string | ✅ | Nama pemesan |
| `contact_email` | string (email) | ✅ | Email pemesan |
| `contact_phone` | string | ✅ | Nomor telepon pemesan |
| `institution` | string | ❌ | Nama instansi/lembaga |
| `items` | array | ✅ | Array item yang dipesan (minimal 1) |
| `items[].property_id` | integer | ✅ | ID Produk (1-8). Lihat [Tabel Katalog](#tabel-katalog-produk-website) |
| `items[].quantity` | integer | ❌ | Jumlah kamar *(hanya untuk ID 6, 7, 8; default: 1)* |
| `items[].schedules` | array | ✅ | Array jadwal pemesanan item ini (minimal 1) |
| `items[].schedules[].start_time` | string (datetime) | ✅ | Waktu mulai, format: `YYYY-MM-DD HH:MM:SS` |
| `items[].schedules[].end_time` | string (datetime) | ✅ | Waktu selesai, format: `YYYY-MM-DD HH:MM:SS` |

---

### Skenario A: Memesan Ruang Kelas Saja (ID 1-5)

Pemesanan jadwal bisa **loncat-loncat** (disjoint). Cocok untuk kegiatan pelatihan yang tidak setiap hari.

**Request:**
```bash
curl -X POST http://localhost:8000/web/bookings \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{
       "contact_name": "Budi Santoso",
       "contact_email": "budi@instansi.go.id",
       "contact_phone": "08123456789",
       "institution": "Kementerian Perindustrian",
       "items": [
         {
           "property_id": 1,
           "schedules": [
             { "start_time": "2026-04-14 08:00:00", "end_time": "2026-04-14 12:00:00" },
             { "start_time": "2026-04-15 13:00:00", "end_time": "2026-04-15 16:00:00" },
             { "start_time": "2026-04-17 08:00:00", "end_time": "2026-04-17 16:00:00" }
           ]
         }
       ]
     }'
```

**Response Sukses (201 Created):**
```json
{
    "data": {
        "id": 1,
        "booking_code": "QW8R9Y",
        "status": "booked",
        "payment_time_limit": "2026-04-14 16:00:00",
        "contact_name": "Budi Santoso",
        "items": [
            {
                "id": 1,
                "property_id": 1,
                "property": { "id": 1, "name": "Ruang Kelas Borobudur", "type": { "name": "Ruang Kelas" } },
                "schedules": [
                    { "start_time": "2026-04-14 08:00:00", "end_time": "2026-04-14 12:00:00" },
                    { "start_time": "2026-04-15 13:00:00", "end_time": "2026-04-15 16:00:00" },
                    { "start_time": "2026-04-17 08:00:00", "end_time": "2026-04-17 16:00:00" }
                ]
            }
        ]
    }
}
```

---

### Skenario B: Memesan Kamar Inap Saja (ID 6, 7, 8)

Pemesanan kamar menggunakan jadwal **kontinu** (check-in sampai check-out). Gunakan `quantity` untuk memesan lebih dari 1 kamar.

**Request (3 kamar VIP):**
```bash
curl -X POST http://localhost:8000/web/bookings \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{
       "contact_name": "Andi Saputra",
       "contact_email": "andi@perusahaan.com",
       "contact_phone": "08987654321",
       "institution": "PT Maju Jaya",
       "items": [
         {
           "property_id": 6,
           "quantity": 3,
           "schedules": [
             { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
           ]
         }
       ]
     }'
```

**Response Sukses (201 Created):**

API mengembalikan **1 booking** dengan **3 items** (masing-masing auto-assign ke kamar VIP spesifik).

```json
{
    "data": {
        "id": 2,
        "booking_code": "X9P2L1",
        "status": "booked",
        "payment_time_limit": "2026-04-14 16:00:00",
        "items": [
            {
                "id": 2,
                "property_id": 6,
                "property": { "id": 6, "name": "Kamar 101", "type": { "name": "Kamar VIP" } },
                "schedules": [
                    { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
                ]
            },
            {
                "id": 3,
                "property_id": 7,
                "property": { "id": 7, "name": "Kamar 102", "type": { "name": "Kamar VIP" } },
                "schedules": [
                    { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
                ]
            },
            {
                "id": 4,
                "property_id": 8,
                "property": { "id": 8, "name": "Kamar 103", "type": { "name": "Kamar VIP" } },
                "schedules": [
                    { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
                ]
            }
        ]
    }
}
```

---

### ⭐ Skenario C: Memesan Campuran (Ruang Kelas + Kamar)

**Kasus:** Pelatihan 3 hari perlu 2 ruang kelas (Borobudur + Prambanan) dengan jadwal harian loncat + 8 kamar VIP untuk peserta.

**Request:**
```bash
curl -X POST http://localhost:8000/web/bookings \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{
       "contact_name": "Siti Rahayu",
       "contact_email": "siti@bumn.co.id",
       "contact_phone": "08111222333",
       "institution": "PT PLN Persero",
       "items": [
         {
           "property_id": 1,
           "schedules": [
             { "start_time": "2026-05-05 08:00:00", "end_time": "2026-05-05 16:00:00" },
             { "start_time": "2026-05-06 08:00:00", "end_time": "2026-05-06 16:00:00" },
             { "start_time": "2026-05-07 08:00:00", "end_time": "2026-05-07 12:00:00" }
           ]
         },
         {
           "property_id": 2,
           "schedules": [
             { "start_time": "2026-05-05 08:00:00", "end_time": "2026-05-05 16:00:00" },
             { "start_time": "2026-05-06 08:00:00", "end_time": "2026-05-06 16:00:00" },
             { "start_time": "2026-05-07 08:00:00", "end_time": "2026-05-07 12:00:00" }
           ]
         },
         {
           "property_id": 6,
           "quantity": 8,
           "schedules": [
             { "start_time": "2026-05-04 14:00:00", "end_time": "2026-05-07 12:00:00" }
           ]
         }
       ]
     }'
```

**Response:** Satu booking dengan `items[]` berisi 2 entry ruang kelas + 8 entry kamar VIP (total 10 items).

> **Penting:** Simpan nilai `booking_code` dari response ke database lokal Anda. Ini adalah satu-satunya kode referensi untuk seluruh paket pemesanan.

---

## 5. Konfirmasi Pembayaran (Lock Ruangan)

Setelah pengguna membayar, panggil endpoint ini. Status berubah dari `booked` menjadi `scheduled` dan **mengunci semua item** secara definitif.

### Endpoint

```
PUT /web/bookings/{id}/payment
```

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `id` | integer (URL) | ✅ | ID Booking yang akan di-lock |

**Request:**
```bash
curl -X PUT http://localhost:8000/web/bookings/2/payment \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

**Response Sukses:**
```json
{
    "success": true,
    "message": "Pembayaran berhasil. Status booking menjadi scheduled.",
    "data": {
        "id": 2,
        "status": "scheduled",
        "items": [...]
    }
}
```

> **Smart Auto-Reassign:** Jika salah satu kamar fisik sudah dibayar orang lain (bentrok), API otomatis mencari kamar kosong lain dalam tipe yang sama. Gagal hanya bila *semua unit* habis.

---

## 6. Membatalkan Pemesanan

Cukup **satu kali** cancel untuk membatalkan seluruh booking (termasuk semua item di dalamnya).

### Endpoint

```
PUT /web/bookings/{booking_code}/cancel
```

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `booking_code` | string (URL) | ✅ | Kode booking unik dari response saat booking dibuat |

**Request:**
```bash
curl -X PUT http://localhost:8000/web/bookings/X9P2L1/cancel \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

**Response Sukses:**
```json
{
    "success": true,
    "message": "Booking dibatalkan"
}
```

---

## 7. Kode Error

| HTTP Code | Situasi | Contoh Message |
|:---|:---|:---|
| `200` | Sukses / Payment Berhasil | — |
| `201` | Booking berhasil dibuat | — |
| `404` | ID properti tidak ditemukan | `"Property ID 9 tidak ditemukan"` |
| `404` | Booking tidak ditemukan | `"Booking not found"` |
| `422` | Properti sedang maintenance | `"Property tidak tersedia (status: maintenance)"` |
| `422` | Jadwal bentrok | `"Property 'Ruang Kelas Borobudur' tidak tersedia pada jadwal tersebut"` |
| `422` | Stok kamar tidak cukup | `"Kapasitas kamar tidak cukup. Hanya tersedia 2 unit."` |
| `422` | ID di luar jangkauan | `"ID Properti (9) tidak valid untuk sistem eksternal."` |
| `409` | Payment gagal, kapasitas habis | `"Pembayaran ditolak. Property sudah penuh."` |
| `422` | Status tidak sesuai untuk payment | `"Booking status is not applicable for payment"` |

---

## 8. Alur Integrasi Lengkap

```
┌─────────────────────────────────────────────┐
│  1. User memilih properti yang dibutuhkan   │
│     - Ruang kelas (ID 1-5)                  │
│     - Kamar inap (ID 6/7/8 + quantity)      │
│     - Atau KOMBINASI keduanya               │
│  2. User mengisi jadwal per jenis properti  │
│  3. User submit form                        │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
     GET /web/properties/{id}/availability
     (Opsional: cek dulu per properti)
                   │
                   ▼
         POST /web/bookings
         { items: [
             { property_id: 1, schedules: [...] },
             { property_id: 6, quantity: 8, schedules: [...] }
         ]}
                   │
               ┌───┴────┐
               │        │
            201 OK   422 Error
               │        │
               ▼        ▼
          Simpan     Tampilkan
        booking_code  pesan error
        (1 kode utk
        semua item)
               │
      [User bayar invoice...]
               │
               ▼
      PUT /bookings/{id}/payment
      (1x panggil utk seluruh booking)
               │
          ┌────┴────┐
          │         │
       200 OK    409 Gagal
     (Semua item   (Kapasitas habis)
      terkunci)
```

