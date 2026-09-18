# Private Cloud Storage Access

Aplikasi cloud storage pribadi (*single-user*) berarsitektur **Modular Monolith** dengan PHP murni, JSON database, dan filesystem storage. Dirancang khusus untuk performa cepat dan kompatibilitas penuh pada shared hosting/cPanel.

---

## Fitur Utama

- **Single Owner Secure Access**: Proteksi login dengan `password_hash()` (Bcrypt cost 12), rate limiting (5 kali percobaan gagal = lockout 15 menit), serta proteksi session fixation.
- **Tanpa SQL**: Semua metadata disimpan dalam format JSON terisolasi dengan locking eksklusif (`flock`) dan *atomic rename* untuk mencegah korupsi data saat request konkuren.
- **Penyimpanan Berkas Terlindungi**: Folder `/data` dan `/storage` dilindungi dengan aturan `.htaccess` (`Require all denied` dan penonaktifan engine eksekusi script PHP). File binary tidak dapat diakses secara langsung melalui URL publik browser.
- **Universal Preview Engine**:
  - **Gambar**: JPG, PNG, GIF, WebP, SVG.
  - **Video & Audio**: Pemutar HTML5 dengan dukungan **HTTP 206 Partial Content (Range Requests)** sehingga pengguna dapat melakukan seeking video secara instan tanpa mengunduh file penuh ke memory.
  - **Dokumen PDF**: Penampil inline terintegrasi.
  - **Teks & Kode Sumber**: Penampil kode aman (menggunakan sanitasi `.textContent` murni untuk mencegah eksekusi XSS).
  - **Fallback Cerdas**: Kartu unduhan langsung untuk file Microsoft Office dan file biner lainnya.
- **Google Drive-Inspired UI (Unique Identity)**:
  - Antarmuka responsif untuk Desktop, Tablet, dan Mobile.
  - Mode tampilan Grid & List/Table dengan pengurutan kolom (Name, Size, Modified).
  - Multi-file Drag & Drop dengan panel progres mengambang di kanan bawah (*bottom-right floating upload panel*).
  - Menu aksi kontekstual klik kanan di desktop dan *Bottom Sheet Action* yang ramah satu tangan di mobile.
  - Fitur Berbintang (*Starred*), Terbaru (*Recent*), Tong Sampah (*Trash* dengan retensi 30 hari), dan Audit Aktivitas.

---

## Struktur Direktori

```text
cloud-storage/
├── public/                # Web root / public_html
│   ├── .htaccess          # URL rewrite ke index.php
│   ├── index.php          # Front Controller & API router
│   ├── app.php            # SPA Dashboard Layout
│   └── assets/            # CSS & Vanilla JavaScript
├── app/                   # Backend application core
│   ├── Config/            # App configuration
│   ├── Controllers/       # HTTP controllers (Auth, File, Folder, Preview, Trash, etc.)
│   ├── Middleware/        # Auth, CSRF, & Rate Limiting
│   ├── Services/          # Core business services
│   ├── Preview/           # Streamer & Preview Manager
│   ├── Repositories/      # Atomic JSON storage engine
│   └── Helpers/           # Security & response formatters
├── data/                  # Metadata JSON (403 Protected)
├── storage/               # Binary files (403 Protected)
│   ├── files/
│   └── trash/
└── .htaccess              # Root protection
```

---

## Kredensial Default

Setelah bootstrap pertama kali dijalankan, kredensial awal yang dibuat otomatis adalah:
- **Username**: `admin`
- **Password**: `password123`

*Catatan: Sangat disarankan untuk segera mengubah password melalui menu **Pengaturan (Settings)** setelah berhasil login.*

---

## Cara Menjalankan Secara Lokal

Untuk menguji aplikasi secara lokal menggunakan PHP built-in server:

```bash
cd public
php -S localhost:8080
```

Buka peramban di `http://localhost:8080`.
