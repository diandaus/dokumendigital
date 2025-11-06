# Fitur Editor Halaman PDF

## Deskripsi
Fitur ini memungkinkan pengguna untuk mengedit PDF multi-halaman dengan kemampuan:
- Melihat preview setiap halaman PDF secara terpisah
- Mengubah urutan halaman dengan drag & drop
- Memutar halaman (90°, 180°, 270°)
- Menghapus halaman yang tidak diinginkan
- Menyimpan hasil editan sebagai PDF baru

## Cara Menggunakan

### 1. Akses Fitur
- Dari halaman [Manajemen Berkas Digital](resources/views/documents/index.blade.php), klik tombol **"Edit Halaman PDF"**
- Atau akses langsung melalui URL: `/documents/page-editor`

### 2. Upload PDF
**Upload Single PDF:**
- Drag & drop file PDF ke area upload, atau
- Klik tombol **"Pilih File PDF"** untuk memilih 1 file dari komputer

**Upload Multiple PDF (Otomatis Digabungkan):**
- Klik tombol **"Pilih File PDF"** dan pilih beberapa file sekaligus (tekan Ctrl/Cmd untuk multiple select), atau
- Drag & drop beberapa file PDF sekaligus ke area upload
- Sistem akan otomatis menggabungkan semua PDF dalam urutan yang dipilih
- Preview akan langsung menampilkan hasil gabungan untuk diedit

### 3. Edit Halaman
Setelah PDF dimuat, Anda akan melihat semua halaman ditampilkan sebagai thumbnail:

#### Mengubah Urutan Halaman
- Klik dan drag halaman ke posisi yang diinginkan
- Nomor urut akan otomatis diperbarui

#### Memutar Halaman
- Klik tombol **putar** (ikon redo) pada halaman yang ingin diputar
- Setiap klik akan memutar 90° searah jarum jam
- Preview akan menampilkan hasil rotasi

#### Menghapus Halaman
- Klik tombol **hapus** (ikon trash) pada halaman yang ingin dihapus
- Konfirmasi penghapusan
- Minimal harus ada 1 halaman tersisa

#### Menambah Halaman dari PDF Lain
- Klik tombol **"Tambah Halaman"** di bagian atas
- Pilih file PDF yang ingin ditambahkan
- Sistem akan menggabungkan PDF baru dengan PDF yang sedang diedit
- Semua halaman dari PDF baru akan ditambahkan di akhir
- Preview akan otomatis dimuat ulang dengan halaman baru
- Anda bisa mengubah urutan halaman baru setelah ditambahkan

### 4. Simpan PDF
- Klik tombol **"Simpan PDF"** di bagian atas
- PDF hasil editan akan otomatis didownload dengan nama `dokumen-edited.pdf`

## Teknologi yang Digunakan

### Frontend
- **PDF.js**: Library untuk render dan manipulasi PDF di browser
- **SortableJS**: Library untuk drag & drop functionality
- **Bootstrap 5**: Framework CSS untuk UI
- **SweetAlert2**: Library untuk notifikasi yang menarik

### Backend
- **Laravel**: PHP Framework
- **FPDI**: PHP Library untuk manipulasi PDF
- **Ghostscript**: Command-line tool untuk processing PDF
- **FPDF**: PHP Library untuk generate PDF

## File yang Ditambahkan/Dimodifikasi

### File Baru
1. [resources/views/documents/page-editor.blade.php](resources/views/documents/page-editor.blade.php)
   - View utama untuk editor halaman PDF

### File yang Dimodifikasi
1. [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php:564-1027)
   - Menambahkan method `pageEditor()` - Line 564
   - Menambahkan method `saveEditedPdf()` - Line 569
   - Menambahkan method `mergeAdditionalPdf()` - Line 765
   - Menambahkan method `mergeMultiplePdfs()` - Line 932

2. [routes/web.php](routes/web.php:16-19)
   - Menambahkan route GET `/documents/page-editor`
   - Menambahkan route POST `/documents/save-edited-pdf`
   - Menambahkan route POST `/documents/merge-additional-pdf`
   - Menambahkan route POST `/documents/merge-multiple-pdfs`

3. [resources/views/documents/index.blade.php](resources/views/documents/index.blade.php:62-64)
   - Menambahkan tombol navigasi "Edit Halaman PDF"

## Cara Kerja Backend

### Proses Save Edited PDF
1. **Validasi**: Menerima file PDF dan data JSON berisi informasi halaman
2. **Ekstraksi**: Menggunakan Ghostscript untuk ekstrak setiap halaman yang dipilih
3. **Rotasi**: Menggunakan FPDI untuk memutar halaman jika diperlukan
4. **Merge**: Menggabungkan kembali halaman-halaman sesuai urutan baru dengan Ghostscript
5. **Output**: Mengirim PDF hasil editan ke browser untuk didownload
6. **Cleanup**: Menghapus semua file temporary

### Proses Merge Additional PDF
1. **Validasi**: Menerima PDF original, PDF baru, dan data halaman
2. **Ekstraksi**: Ekstrak halaman dari PDF original sesuai urutan dan rotasi yang sudah diedit
3. **Rotasi**: Terapkan rotasi pada halaman yang diperlukan
4. **Append**: Tambahkan semua halaman dari PDF baru ke array
5. **Merge**: Gabungkan semua halaman (original + baru) dengan Ghostscript
6. **Output**: Kirim PDF hasil gabungan ke browser sebagai temporary file
7. **Reload**: Frontend akan reload PDF baru untuk diedit lebih lanjut
8. **Cleanup**: Hapus semua file temporary

### Proses Merge Multiple PDFs (Initial Upload)
1. **Validasi**: Menerima array file PDF
2. **Single File Check**: Jika hanya 1 file, langsung return tanpa merge
3. **Save Temp**: Simpan semua file ke folder temporary
4. **Merge**: Gabungkan semua PDF dalam urutan upload dengan Ghostscript
5. **Output**: Kirim PDF hasil gabungan ke browser
6. **Frontend Load**: Frontend akan load PDF gabungan untuk preview dan edit
7. **Cleanup**: Hapus semua file temporary

### Contoh Request JSON
```json
{
  "pdf": "file.pdf",
  "pages": [
    {
      "originalPage": 1,
      "rotation": 0,
      "deleted": false,
      "order": 0
    },
    {
      "originalPage": 3,
      "rotation": 90,
      "deleted": false,
      "order": 1
    }
  ]
}
```

## Requirements
- PHP >= 7.4
- Laravel >= 8.x
- Ghostscript (sudah terinstall)
- FPDI Library (sudah terinstall via composer)
- Browser modern dengan support untuk:
  - Canvas API
  - Web Workers
  - Drag and Drop API

## Batasan
- Ukuran file maksimal: 50MB per file
- Total request maksimal: 100MB (untuk multiple upload)
- Format file: Hanya PDF
- Minimal 1 halaman harus tersisa setelah edit
- Rotasi hanya dalam kelipatan 90°

## Troubleshooting

### Error 404 saat klik Simpan
Jika mendapat error 404, lakukan langkah berikut:

1. **Clear cache Laravel**:
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

2. **Restart web server**:
```bash
# Untuk Apache
sudo systemctl restart apache2

# Atau untuk Nginx
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm  # sesuaikan versi PHP
```

3. **Periksa urutan route** - Pastikan route spesifik berada di ATAS route dengan parameter dinamis di `routes/web.php`

4. **Periksa rewrite module** (Apache):
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### PDF tidak ter-render
- Pastikan browser mendukung Canvas API
- Cek console browser untuk error
- Pastikan file PDF tidak corrupt

### Error saat save
- Cek log Laravel: `storage/logs/laravel.log`
- Pastikan Ghostscript terinstall: `gs --version`
- Pastikan folder temp bisa ditulis: `storage/app/temp`
```bash
chmod -R 775 storage/app/temp
```

### Rotasi tidak berfungsi
- Pastikan FPDI library terinstall
- Cek versi PHP >= 7.4

### Permission denied pada file temporary
```bash
sudo chown -R www-data:www-data storage/app/temp
chmod -R 775 storage/app/temp
```

### Error 413 - Payload Too Large
Jika mendapat error "HTTP 413 Payload Too Large" saat upload PDF:

**Penyebab**: Ukuran file melebihi batas yang diizinkan oleh web server atau PHP.

**Solusi**:

1. **File `.htaccess` sudah diupdate** dengan setting berikut:
```apache
# Increase request body limit to 100MB
LimitRequestBody 104857600

php_value upload_max_filesize 50M
php_value post_max_size 100M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
```

2. **Restart Apache**:
```bash
sudo systemctl restart apache2
```

3. **Jika masih error**, cek konfigurasi Apache global di `/etc/apache2/apache2.conf` atau `/etc/apache2/sites-available/`:
```apache
# Tambahkan di dalam <VirtualHost> atau <Directory>
LimitRequestBody 104857600
```

4. **Untuk Nginx**, edit `/etc/nginx/nginx.conf` atau site config:
```nginx
client_max_body_size 100M;
```

5. **Cek PHP-FPM** (jika menggunakan FPM):
```bash
# Edit /etc/php/8.4/fpm/php.ini
upload_max_filesize = 50M
post_max_size = 100M

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

**Batasan saat ini**: Maksimal 50MB per file, maksimal 100MB total request

### Error 422 saat upload multiple PDF
Jika mendapat error "HTTP 422" saat upload multiple PDF:

1. **Cek ukuran file**: Pastikan setiap file tidak lebih dari 50MB
2. **Cek format**: Pastikan semua file adalah PDF valid
3. **Cek PHP upload settings**:
```bash
# Cek setting saat ini
php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"
```

4. **Update php.ini jika perlu**:
```ini
upload_max_filesize = 50M
post_max_size = 100M
max_file_uploads = 20
```

5. **Restart web server setelah perubahan**:
```bash
sudo systemctl restart apache2
# atau
sudo systemctl restart php8.4-fpm
```

6. **Cek browser console** (F12) untuk error detail
7. **Cek Laravel log**: `tail -f storage/logs/laravel.log`

## Future Improvements
1. ✅ ~~Menambah halaman dari PDF lain~~ (Sudah diimplementasi)
2. Crop halaman
3. Adjust brightness/contrast
4. Add watermark
5. Split PDF menjadi multiple files
6. Extract text dari halaman
7. Add annotations/comments
8. Insert halaman kosong

## Support
Untuk bantuan lebih lanjut, silakan hubungi tim development atau buka issue di repository.
