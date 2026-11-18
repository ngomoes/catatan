CatatanKu - aplikasi catatan ringan (HTML + PHP + sedikit JavaScript)
=====================================================================

KEBUTUHAN
- PHP 7.4 atau lebih baru (ekstensi bawaan sudah cukup)
- Tanpa database: data disimpan di data/users.json dan data/notes.json

CARA PAKAI
1. Ekstrak zip ke folder web server (htdocs / public_html), atau jalankan lokal:
       cd catatanku
       php -S localhost:8000
   lalu buka http://localhost:8000
2. Pastikan folder data/ dan uploads/ bisa ditulis oleh PHP (chmod 775 / 777 bila perlu).
3. Masuk sebagai admin pertama:
       username : admin
       password : admin123
   >>> SEGERA ganti password-nya lewat Panel Admin > Edit. <<<

FITUR
- Daftar & masuk (password di-hash, data pengguna di JSON)
- Tema terang (default) / gelap, pilihan tersimpan di browser
- Catatan dengan judul, isi, dan upload gambar (JPG/PNG/GIF/WebP, maks. 2 MB)
- Hyperlink: URL otomatis jadi link, atau tulis [teks](https://alamat.com)
- Hapus catatan
- Panel admin: tambah/edit/hapus pengguna, ban/unban, hapus catatan siapa pun

KEAMANAN
- Proteksi CSRF, output di-escape (anti XSS), validasi file gambar dari isi file
- Pengguna yang di-ban atau dihapus langsung keluar di permintaan berikutnya
- Folder data/ dilindungi lewat .htaccess (Apache). Jika memakai NGINX, tambahkan:
       location ~ ^/data/ { deny all; }
       location ~* ^/uploads/.*\.php$ { deny all; }
- Gunakan HTTPS saat dipublikasikan.

Untuk mereset semua data: hapus isi data/users.json dan data/notes.json
(akun admin/admin123 dibuat ulang otomatis).
