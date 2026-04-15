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

> **Catatan Penting:** Properti yang dipesan menggunakan endpoint ini akan memiliki status awal **`booked`** dan nilai **`payment_time_limit`** (default 2 jam). Di fase ini, ketersediaan ruangan **sudah dikunci (diblokir)** dari pengguna lain. Tapi jika tidak segera dibayar dan dikonfirmasi lewat [Konfirmasi Pembayaran](#5-konfirmasi-pembayaran-lock-ruangan) sebelum `payment_time_limit` tersebut lewat, maka sistem otomatis akan membatalkan pemesanan dan melepas kembali ketersediaan ruangannya ke publik.

### Endpoint

```
POST /web/bookings
```

### Parameter Body (JSON)

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `property_id` | integer | ✅ | ID Produk (1-8). Lihat [Tabel Katalog](#tabel-katalog-produk-website) |
| `contact_name` | string | ✅ | Nama pemesan |
| `contact_email` | string (email) | ✅ | Email pemesan |
| `contact_phone` | string | ✅ | Nomor telepon pemesan |
| `institution` | string | ❌ | Nama instansi/lembaga |
| `quantity` | integer | ❌ | Jumlah kamar *(hanya untuk ID 6, 7, 8; default: 1)* |
| `schedules` | array | ✅ | Array jadwal pemesanan (minimal 1 item) |
| `schedules[].start_time` | string (datetime) | ✅ | Waktu mulai, format: `YYYY-MM-DD HH:MM:SS` |
| `schedules[].end_time` | string (datetime) | ✅ | Waktu selesai, format: `YYYY-MM-DD HH:MM:SS` |

---

### Skenario A: Memesan Ruang Kelas / Ruang Rapat (ID 1-5)

Pemesanan jadwal bisa **loncat-loncat** (disjoint). Cocok untuk kegiatan pelatihan yang tidak setiap hari.

**Request:**
```bash
curl -X POST http://localhost:8000/web/bookings \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{
       "property_id": 1,
       "contact_name": "Budi Santoso",
       "contact_email": "budi@instansi.go.id",
       "contact_phone": "08123456789",
       "institution": "Kementerian Perindustrian",
       "schedules": [
         {
           "start_time": "2026-04-14 08:00:00",
           "end_time": "2026-04-14 12:00:00"
         },
         {
           "start_time": "2026-04-15 13:00:00",
           "end_time": "2026-04-15 16:00:00"
         },
         {
           "start_time": "2026-04-17 08:00:00",
           "end_time": "2026-04-17 16:00:00"
         }
       ]
     }'
```

> **Catatan:** Perhatikan bahwa tanggal 16 April tidak diisi — jadwal boleh loncat tanpa masalah.

**Response Sukses (201 Created):**
```json
{
    "data": [
         {
            "id": 1,
            "booking_code": "CL1A2B",
            "property_id": 1,
            "contact_name": "Budi Santoso",
            "contact_email": "budi@instansi.go.id",
            "contact_phone": "08123456789",
            "institution": "Kementerian Perindustrian",
            "status": "booked",
            "payment_time_limit": "2026-04-14 16:00:00",
            "schedules": [
                {
                    "id": 1,
                    "start_time": "2026-04-14 08:00:00",
                    "end_time": "2026-04-14 12:00:00"
                },
                {
                    "id": 2,
                    "start_time": "2026-04-15 13:00:00",
                    "end_time": "2026-04-15 16:00:00"
                },
                {
                    "id": 3,
                    "start_time": "2026-04-17 08:00:00",
                    "end_time": "2026-04-17 16:00:00"
                }
            ],
            "property": {
                "id": 1,
                "name": "Ruang Kelas Borobudur",
                "type": { "id": 1, "name": "Ruang Kelas" }
            }
        }
    ]
}
```

> **Penting:** Simpan nilai `booking_code` (contoh: `CL1A2B`) dari response ke database lokal Anda. Kode ini adalah identifier unik yang dihasilkan otomatis oleh server dan dapat digunakan untuk referensi silang antar sistem.

---

### Skenario B: Memesan Kamar Inap (ID 6, 7, 8)

Pemesanan kamar menggunakan jadwal **kontinu** (check-in sampai check-out). Tambahkan parameter `quantity` untuk memesan lebih dari 1 kamar.

**Request (3 kamar VIP, check-in 14 April check-out 17 April):**
```bash
curl -X POST http://localhost:8000/web/bookings \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{
       "property_id": 6,
       "quantity": 3,
       "contact_name": "Andi Saputra",
       "contact_email": "andi@perusahaan.com",
       "contact_phone": "08987654321",
       "institution": "PT Maju Jaya",
       "schedules": [
         {
           "start_time": "2026-04-14 14:00:00",
           "end_time": "2026-04-17 12:00:00"
         }
       ]
     }'
```

**Response Sukses (201 Created):**

API mengembalikan **3 booking terpisah**, masing-masing sudah di-assign ke kamar VIP spesifik yang tersedia.

```json
{
    "data": [
         {
            "id": 2,
            "booking_code": "VP3C4D",
            "property_id": 6,
            "status": "booked",
            "payment_time_limit": "2026-04-14 16:00:00",
            "schedules": [
                { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
            ],
            "property": { "id": 6, "name": "Kamar 101", "type": { "name": "Kamar VIP" } }
        },
        {
            "id": 3,
            "booking_code": "VP5E6F",
            "property_id": 7,
            "status": "booked",
            "payment_time_limit": "2026-04-14 16:00:00",
            "schedules": [
                { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
            ],
            "property": { "id": 7, "name": "Kamar 102", "type": { "name": "Kamar VIP" } }
        },
        {
            "id": 4,
            "booking_code": "VP7G8H",
            "property_id": 8,
            "status": "booked",
            "payment_time_limit": "2026-04-14 16:00:00",
            "schedules": [
                { "start_time": "2026-04-14 14:00:00", "end_time": "2026-04-17 12:00:00" }
            ],
            "property": { "id": 8, "name": "Kamar 103", "type": { "name": "Kamar VIP" } }
        }
    ]
}
```

> **Penting:** Simpan `booking_code` dan `id` dari setiap item response ke database lokal Anda. Gunakan `id` untuk membatalkan booking, dan `booking_code` sebagai kode referensi unik lintas sistem.

---

## 5. Konfirmasi Pembayaran (Lock Ruangan)

Setelah pengguna berhasil melakukan checkout bayar di aplikasi Anda, segera panggil endpoint payment. Endpoint ini akan mengubah status `booked` menjadi `scheduled` dan benar-benar **mengunci ketersediaan kamar** secara definitif.

### Endpoint

```
PUT /web/bookings/{id}/payment
```

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `id` | integer (URL) | ✅ | ID Booking (bukan ID property) yang akan di-lock |

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
        "property_id": 6
    }
}
```

> **Smart Auto-Reassign:** Khusus untuk pemesanan kamar (virtual ID 6, 7, 8), jika kamar fisik yang ditetapkan di awal ternyata sudah dibooking dan dibayar duluan oleh orang lain (bentrok), API ini akan bertindak pintar dan otomatis mencari unit kamar kosong lain dalam tipe yang sama sisa stoknya, lalu merespon dengan `success`! Kegagalan transfer pembayaran (bentrok yang tidak tertolong) hanya terjadi jika *"semua unit kamar"* bertipe tersebut serentak dibayar orang lain hingga habis.

---

## 6. Membatalkan Pemesanan

### Endpoint

```
PUT /web/bookings/{id}/cancel
```

| Parameter | Tipe | Wajib | Keterangan |
|:---|:---|:---|:---|
| `id` | integer (URL) | ✅ | ID Booking yang akan dibatalkan (dari response saat booking dibuat) |

**Request:**
```bash
curl -X PUT http://localhost:8000/web/bookings/2/cancel \
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

> **Catatan untuk Kamar:** Jika Anda memesan 3 kamar dan ingin membatalkan semua, Anda perlu mengirim 3 request cancel terpisah (satu per booking ID).

---

## 7. Kode Error

| HTTP Code | Situasi | Contoh Message |
|:---|:---|:---|
| `200` | Sukses / Payment Berhasil | — |
| `201` | Booking berhasil dibuat | — |
| `404` | ID properti tidak ditemukan | `"Property tidak ditemukan"` |
| `404` | ID booking tidak ditemukan | `"Booking not found"` |
| `404` | ID di luar jangkauan (bukan 1-8) | `"ID Properti (9) tidak valid untuk sistem eksternal."` |
| `422` | Properti sedang maintenance | `"Property tidak tersedia (status: maintenance)"` |
| `422` | Jadwal bentrok dengan booking lain | `"Property tidak tersedia pada jadwal tanggal tersebut"` |
| `422` | Stok kamar tidak cukup | `"Kapasitas kamar tidak cukup. Hanya tersedia 2 unit."` |
| `409` | Payment Gagal, kapasitas diserobot habis | `"Pembayaran ditolak. Kapasitas property sudah penuh karena pengguna lain telah membayar lebih dulu."` |
| `422` | Payment status tidak diizinkan | `"Booking status is not applicable for payment"` |
| `422` | Validasi input gagal | `{ "errors": { "contact_email": ["..."] } }` |

---

## 8. Alur Integrasi Lengkap

Berikut adalah alur yang direkomendasikan untuk implementasi di sisi website:

### Alur Pemesanan Ruang Kelas / Rapat (ID 1-5)

```
┌─────────────────────────────────────────────┐
│  1. User memilih ruangan (ID 1-5)           │
│  2. User mengisi jadwal (boleh loncat)      │
│  3. User submit form                        │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
     GET /web/properties/{id}/availability
     (Opsional: cek dulu sebelum submit)
                   │
                   ▼
         POST /web/bookings
         { property_id: 1, schedules: [...] }
                   │
               ┌────┴────┐
               │         │
            201 OK    422 Error
               │         │
               ▼         ▼
          Simpan      Tampilkan
        (ID booked)   pesan error
               │
      [User bayar invoice...]
               │
               ▼
      PUT /bookings/{id}/payment
               │
          ┌────┴────┐
          │         │
       200 OK    409 Gagal
     (Terkunci) (Direbut orang)
```

### Alur Pemesanan Kamar Inap (ID 6, 7, 8)

```
┌─────────────────────────────────────────────┐
│  1. User memilih tipe kamar (ID 6/7/8)      │
│  2. User mengisi tanggal check-in/out       │
│  3. User mengisi jumlah kamar (quantity)     │
│  4. User submit form                        │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
     GET /web/properties/{id}/availability
     → Gunakan available_count untuk
       validasi max quantity di form
                   │
                   ▼
         POST /web/bookings
         { property_id: 6, quantity: 3,
           schedules: [{ check-in → check-out }] }
                   │
               ┌────┴────┐
               │         │
            201 OK    422 Error
               │         │
               ▼         ▼
           Simpan     Tampilkan
         (ID booked)  pesan error
               │
      [User bayar invoice...]
               │
               ▼
     PUT /bookings/{id}/payment (loop per ID)
               │
          ┌────┴────┐
          │         │
       200 OK    409 Gagal  
   (Otomatis cari (Semua ludes
   kamar kosong   dibayar orang)
    lain jk bentrok)
