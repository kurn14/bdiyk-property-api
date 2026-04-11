# Dokumentasi Akses API - BDI Properties

Aplikasi ini menyediakan akses API untuk manajemen properti dan booking. Akses dapat dilakukan melalui dua metode autentikasi: **Sanctum (Token-based)** dan **API Key**.

## Autentikasi

### 1. API Key (Recommended for Server-to-Server)
Gunakan header `X-API-Key` pada setiap request.
- **Header Name:** `X-API-Key`
- **Value:** (Lihat nilai `API_KEY` di file `.env`)

Contoh menggunakan `curl`:
```bash
curl -X GET http://127.0.0.1:8000/properties \
     -H "X-API-Key: secret_api_key_123" \
     -H "Accept: application/json"
```

### 2. Laravel Sanctum (Recommended for Client/Frontend)
Lakukan login terlebih dahulu untuk mendapatkan token.
- **Endpoint:** `POST /login`
- **Body:** `{"email": "...", "password": "..."}`

Gunakan token yang didapat pada header `Authorization`:
- **Header Name:** `Authorization`
- **Value:** `Bearer {your_token}`

---

## Daftar Endpoint Utama

Semua endpoint di bawah ini memerlukan salah satu metode autentikasi di atas.

### Properti
| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `GET` | `/properties` | List semua properti (mendukung filter `search`, `type_id`, `status`) |
| `GET` | `/properties/{id}` | Detail properti spesifik |
| `GET` | `/properties/{id}/availability` | Cek ketersediaan (query: `start_date`, `end_date`) |

### Booking
| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `GET` | `/bookings` | List semua booking |
| `POST` | `/bookings` | Membuat booking baru |
| `GET` | `/bookings/{id}` | Detail booking |
| `PUT` | `/bookings/{id}/cancel` | Membatalkan booking |

### Tipe Properti
| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `GET` | `/property-types` | List semua tipe properti |

---

## Contoh Request

### Membuat Booking Baru
**Endpoint:** `POST /bookings`

**Payload:**
```json
{
    "property_id": 1,
    "contact_name": "Budi Santoso",
    "contact_email": "budi@example.com",
    "contact_phone": "08123456789",
    "institution": "Universitas Gadjah Mada",
    "start_date": "2026-04-15 08:00:00",
    "end_date": "2026-04-15 17:00:00",
    "external_reference": "REF-001"
}
```

### Cek Ketersediaan Properti
**Endpoint:** `GET /properties/1/availability?start_date=2026-04-15&end_date=2026-04-16`

**Response (Tersedia):**
```json
{
    "property_id": "1",
    "available": true,
    "conflicting_bookings": 0,
    "message": "Property tersedia"
}
```

### Filter Properti
Mencari properti dengan nama "Ruang" dan tipe ID 1:
`GET /properties?search=Ruang&type_id=1`
