# Setup Integrasi Java - Laravel Merge PDF

Dokumentasi konfigurasi integrasi antara Aplikasi Java (SIMRS Khanza), Server Webapps, dan Laravel Merge PDF.

## Arsitektur System

```
┌─────────────────────────┐
│   Aplikasi Java         │
│   (SIMRS Khanza)        │
│   - DlgViewPdf.java     │
└──────────┬──────────────┘
           │
           │ 1. Klik "Merge PDF (Laravel)"
           │    Parameter: no_rawat, path
           │
           ▼
┌─────────────────────────┐
│   Server Laravel        │
│   192.168.1.174         │
│   - DocumentController  │
│   - page-editor.blade   │
└──────────┬──────────────┘
           │
           │ 2. Query file PDF
           │    dari server webapps
           │
           ▼
┌─────────────────────────┐
│   Server Webapps        │
│   192.168.1.6:80        │
│   /webapps/berkasdigital│
│   - File PDF Storage    │
└─────────────────────────┘
```

## Konfigurasi Server

### 1. Server Java (Aplikasi SIMRS Khanza)

**File:** `setting/database.xml`

```xml
<entry key="HOSTHYBRIDWEB">5k7+C7EnUw9nUv2Nix+DBA==</entry>  <!-- Encrypted: 192.168.1.6 -->
<entry key="HYBRIDWEB">webapps</entry>
<entry key="PORTWEB">80</entry>
```

**Database:** `ibnusinadev`

**Tabel:** `app_setting`
```sql
INSERT INTO app_setting (nama, isi, keterangan)
VALUES ('URL_LARAVEL_MERGE_PDF', 'http://192.168.1.174/documents/page-editor', 'URL endpoint Laravel untuk merge PDF');
```

### 2. Server Webapps (192.168.1.6)

**Fungsi:** Static file server untuk menyimpan PDF

**Struktur Folder:**
```
/webapps/
└── berkasrawat/
    └── pages/
        └── upload/
            ├── SEP_2025_11_11_000001.pdf
            ├── Gruper_2025_11_11_000001.pdf
            ├── Resume_2025_11_11_000001_signed.pdf
            ├── RiwayatPerawatan_2025_11_11_000001_signed.pdf
            ├── Lab_2025_11_11_000001_signed.pdf
            └── ...
```

**Requirements:**
- Web server (Apache/Nginx/Tomcat) running di port 80
- CORS enabled untuk allow akses dari Laravel
- Public access ke folder `/webapps/berkasdigital/`

**Konfigurasi Apache (contoh):**
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html

    <Directory /var/www/html/webapps/berkasrawat/pages/upload>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted

        # CORS Headers untuk akses dari Laravel
        Header set Access-Control-Allow-Origin "*"
        Header set Access-Control-Allow-Methods "GET, OPTIONS"
        Header set Access-Control-Allow-Headers "Content-Type"
    </Directory>
</VirtualHost>
```

**Konfigurasi Nginx (contoh):**
```nginx
server {
    listen 80;
    root /var/www/html;

    location /webapps/berkasrawat/pages/upload {
        autoindex on;

        # CORS Headers
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type";
    }
}
```

### 3. Server Laravel (192.168.1.174)

**File:** `.env`

```env
APP_URL=http://192.168.1.174

# Database (sama dengan Java)
DB_CONNECTION=mysql
DB_HOST=192.168.1.220
DB_PORT=3306
DB_DATABASE=ibnusina
DB_USERNAME=root
DB_PASSWORD=server

# Webapps Server Configuration
WEBAPPS_HOST=192.168.1.6
WEBAPPS_PORT=80
WEBAPPS_BASE_PATH=webapps
```

**Requirements:**
- PHP 8.1+
- Ghostscript (untuk merge PDF)
- Composer dependencies installed

**Install Ghostscript:**
```bash
# Ubuntu/Debian
sudo apt-get install ghostscript

# CentOS/RHEL
sudo yum install ghostscript
```

## Cara Kerja

### Flow Lengkap:

1. **User di Aplikasi Java:**
   - Buka form berkas digital
   - Klik button "View"
   - Pilih "Merge PDF (Laravel)"

2. **Aplikasi Java:**
   - Ambil `no_rawat` dari field txtNoRawat
   - Ambil `path` dari field txtLokasiFile (default: `berkasrawat/pages/upload`)
   - Buka browser dengan URL:
     ```
     http://192.168.1.174/documents/page-editor?no_rawat=2025/11/11/000001&path=berkasrawat/pages/upload
     ```

3. **Laravel Controller:**
   - Terima parameter `no_rawat` dan `path`
   - Format `no_rawat`: `2025/11/11/000001` → `2025_11_11_000001`
   - Cari file PDF di server webapps dengan pattern:
     ```
     SEP_2025_11_11_000001.pdf
     Resume_2025_11_11_000001_signed.pdf
     Lab_2025_11_11_000001_signed.pdf
     ...
     ```

4. **Laravel View (page-editor):**
   - Auto-download semua file PDF dari server webapps
   - Jika multiple files → merge otomatis
   - Tampilkan PDF editor:
     - Drag & drop untuk ubah urutan halaman
     - Rotate halaman
     - Delete halaman
     - Tambah PDF lain
     - Save hasil merge

5. **User Download:**
   - User klik "Simpan PDF"
   - Download hasil merge ke komputer

## Pattern Nama File

File PDF mengikuti pattern dari aplikasi Java:

### File dengan TTe (Tanda Tangan Elektronik):
```
{JenisBerkas}_{NoRawat}_signed.pdf

Contoh:
Resume_2025_11_11_000001_signed.pdf
Lab_2025_11_11_000001_signed.pdf
RiwayatPerawatan_2025_11_11_000001_signed.pdf
```

### File Bridging (tanpa TTe):
```
{JenisBerkas}_{NoRawat}.pdf

Contoh:
SEP_2025_11_11_000001.pdf
Gruper_2025_11_11_000001.pdf
SKDP_2025_11_11_000001.pdf
Billing_2025_11_11_000001.pdf
```

### Urutan Jenis Berkas:
1. SEP (Surat Eligibilitas Peserta)
2. Gruper
3. Resume (Resume Medis)
4. RiwayatPerawatan
5. SKDP (Surat Kontrol Dokter Poli)
6. SPRI
7. Awal_Medis_IGD
8. Triase
9. Lab (Hasil Laboratorium)
10. Radiologi (Hasil Radiologi)
11. Billing (Billing/Tagihan)
12. LaporanOperasi
13. LaporanAnastesi

## Testing

### Test Koneksi ke Server Webapps:

```bash
# Dari server Laravel, test akses ke webapps
curl -I http://192.168.1.6/webapps/berkasrawat/pages/upload/

# Expected: HTTP/1.1 200 OK atau 403 Forbidden (folder listing)
```

### Test File Exists:

```bash
# Test akses ke file PDF tertentu
curl -I http://192.168.1.6/webapps/berkasrawat/pages/upload/SEP_2025_11_11_000001.pdf

# Expected: HTTP/1.1 200 OK (jika file ada)
# Expected: HTTP/1.1 404 Not Found (jika file tidak ada)
```

### Test dari Browser:

1. Buka URL: `http://192.168.1.174/documents/page-editor?no_rawat=2025/11/11/000001&path=berkasrawat/pages/upload`
2. Cek console browser (F12) untuk error
3. Pastikan file PDF ter-load

## Troubleshooting

### File PDF tidak ter-load di Laravel

**Penyebab:**
- Server webapps tidak bisa diakses
- CORS error

**Solusi:**
```bash
# Cek dari server Laravel
curl http://192.168.1.6/webapps/berkasrawat/pages/upload/

# Cek CORS headers
curl -I http://192.168.1.6/webapps/berkasrawat/pages/upload/test.pdf

# Pastikan ada header:
# Access-Control-Allow-Origin: *
```

### Error "Failed to load PDF"

**Penyebab:**
- File tidak ada di server webapps
- Permission denied

**Solusi:**
```bash
# Cek file exists
ls -la /var/www/html/webapps/berkasrawat/pages/upload/

# Fix permission
chmod -R 755 /var/www/html/webapps/berkasrawat/pages/upload/
```

### Merge PDF gagal

**Penyebab:**
- Ghostscript tidak terinstall
- Memory limit

**Solusi:**
```bash
# Install ghostscript
sudo apt-get install ghostscript

# Cek ghostscript
gs --version

# Increase PHP memory limit di .env atau php.ini
memory_limit = 512M
```

## Security Notes

1. **Authentication:** Tambahkan authentication di Laravel jika diperlukan
2. **CORS:** Batasi CORS hanya untuk IP Laravel jika lebih secure
3. **File Access:** Pastikan hanya file PDF yang bisa diakses public
4. **Encryption:** Gunakan HTTPS jika data sensitif

## Maintenance

### Update URL Laravel di Database:

```sql
UPDATE app_setting
SET isi = 'http://192.168.1.174/documents/page-editor'
WHERE nama = 'URL_LARAVEL_MERGE_PDF';
```

### Ganti IP Server Webapps:

1. Edit `.env` Laravel:
   ```env
   WEBAPPS_HOST=192.168.1.XXX
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

## Kontak & Support

Untuk pertanyaan dan support, hubungi tim IT RS Ibnu Sina.
