# Dokumentasi Logika Pemesanan Properti (PNBP) - Web BDIYK

Dokumen ini menjelaskan batasan dan alur pemesanan properti pada sisi Frontend/Web BDIYK yang diselaraskan dengan Property API.

## 1. Batasan Sistem (Single Property Service)

Berdasarkan struktur database lokal (tabel `bookings`) dan model `Property`, satu transaksi pemesanan di web BDIYK hanya berlaku untuk **satu tipe properti** dalam satu waktu.

- **Polymorphic Relationship**: Kolom `bookable_id` dan `bookable_type` hanya menyimpan referensi ke satu entri di tabel `properties`.
- **Interaksi Satu Item**: Meskipun API dapat menerima array `items`, Web BDIYK hanya akan mengirimkan **satu objek item** di dalam array tersebut.

---

## 2. Pemetaan ID & Contoh Request (ID 1-8)

Sistem membedakan alur pemesanan menjadi dua skenario utama berdasarkan entri di `PropertySeeder`:

### A. Skenario Ruang Kelas & Rapat (ID 1-5)
Target properti adalah ruangan spesifik (Physical ID). Kuantitas di database lokal mencatat total jam penggunaan.

**Contoh Request (Booking Ruang Kelas Borobudur):**
```json
{
  "contact_name": "Budi Santoso",
  "contact_email": "budi@agency.go.id",
  "contact_phone": "08123456789",
  "institution": "BDI Yogyakarta",
  "items": [
    {
      "property_id": 1, // ID Ruang Kelas Borobudur
      "schedules": [
        { 
          "start_time": "2026-05-10 08:00:00", 
          "end_time": "2026-05-10 12:00:00" 
        },
        { 
          "start_time": "2026-05-11 13:00:00", 
          "end_time": "2026-05-11 17:00:00" 
        }
      ]
    }
  ]
}
```
*Catatan: `quantity` tidak wajib dikirim untuk tipe ruang karena API akan menghitung durasi jam dari selisih waktu.*

### B. Skenario Kamar Inap (ID 6-8)
Target properti adalah kategori (Virtual ID). Kuantitas di database lokal mencatat jumlah unit kamar.

**Contoh Request (Booking 3 Kamar VIP):**
```json
{
  "contact_name": "Siti Rahayu",
  "contact_email": "siti@email.com",
  "contact_phone": "08987654321",
  "items": [
    {
      "property_id": 6, // ID Kategori Kamar VIP
      "quantity": 3,    // Memesan 3 kamar sekaligus
      "schedules": [
        { 
          "start_time": "2026-05-10 14:00:00", 
          "end_time": "2026-05-13 12:00:00" 
        }
      ]
    }
  ]
}
```

---

## 3. Output & Penyimpanan Lokal

### Response dari API
Setelah request dikirim, API akan membalas dengan struktur berikut:
1. **Booking Code**: Kode unik (misal: `PNBP-A1B2C`) yang disimpan di kolom `booking_code`.
2. **Items Detail**: Array berisi unit fisik yang di-assign. 
   - Untuk Ruang (ID 1-5): Berisi 1 item ruangan tersebut.
   - Untuk Kamar (ID 6-8): Berisi $N$ item kamar fisik (misal: Kamar 101, Kamar 102, dst).
3. **Schedules**: Detail jam operasional per item yang kemudian disimpan ke tabel `booking_schedules` lokal.

### Penyimpanan Lokal (`bookings` table)
- `bookable_id`: ID 1-8 sesuai pilihan user.
- `quantity`: 
    - (Ruang): Hasil perhitungan seluruh jam di `schedules`.
    - (Kamar): Sesuai input jumlah kamar dari user.

---

## 4. Prompt Perbaikan Proyek API

> **Prompt:**
> "Optimalkan endpoint `POST /web/bookings` agar mendukung skenario web berikut secara sinkron:
>
> 1. **Ruang Kelas/Rapat (ID 1-5)**: Terima beberapa entri dalam array `schedules`. Hitung total jam dan pastikan tidak ada bentrok jadwal pada ruangan fisik tersebut.
> 
> 2. **Kamar Inap (ID 6-8)**: Jika request menggunakan Virtual ID ini, lakukan auto-assign unit fisik berdasarkan `quantity` yang diminta. 
> 
> 3. **Response Consistency**: Pastikan response mengembalikan daftar properti fisik yang final (nomor kamar/nama ruang) di dalam array `items` agar web dapat menampilkan rincian 'Booking-Item' kepada user."
