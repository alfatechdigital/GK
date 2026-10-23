# Gelar Karya Informatika 1

Situs portofolio kelas berbasis PHP dan SQLite. Halaman utama menampilkan tiga proyek beserta foto dan galeri profil anggota tiap proyek. Panel admin dengan login tersedia di `/admin`.

## Menjalankan secara lokal

Persyaratan: PHP 8.1+ dengan ekstensi `pdo_sqlite` dan `fileinfo`.

Di PowerShell, dari folder proyek:

```powershell
.\start-server.ps1
```

Buka `http://127.0.0.1:8765/` untuk situs dan `http://127.0.0.1:8765/admin` untuk mengelola konten. Server pengembangan hanya terikat ke komputer lokal. Untuk mengakses situs melalui internet, deploy aplikasi pada hosting yang mendukung PHP dan atur environment variable `GK_ADMIN_PASSWORD` di konfigurasi server ke password admin yang diinginkan. Jangan menaruh password di file publik atau membuka server pengembangan langsung ke internet; gunakan HTTPS.

Database SQLite dibuat otomatis di `storage/gelar-karya.sqlite3`. Foto proyek dan foto anggota disimpan di `uploads/projects/` dan `uploads/members/`. Lokasi tersebut tidak perlu dibuat manual dan isinya tidak dimasukkan ke Git.

## Mengelola konten

- Tambahkan paling banyak tiga proyek; setiap proyek memerlukan judul, deskripsi, serta 1–6 foto JPG, PNG, GIF, atau WebP (maksimal 8 MB per foto).
- Di formulir proyek, tambahkan maksimal 12 anggota. Setiap anggota memerlukan nama, foto JPG/PNG/GIF/WebP (maksimal 8 MB), dan tautan portofolio `http://` atau `https://`.
- Di halaman utama, proyek tampil bergantian sebagai bagian tersendiri. Scroll halaman dengan Lenis untuk mengganti foto satu per satu, lalu melihat tombol anggota dan deskripsi; tidak ada area gulir vertikal terpisah di dalam panel proyek.
- Tombol anggota di setiap proyek membuka galeri profil berisi foto, nama, dan tombol menuju portofolio.
- Edit proyek dari daftar admin untuk mengubah judul, deskripsi, foto, dan anggota. Foto proyek lama tetap digunakan jika tidak memilih foto pengganti; foto anggota lama juga dipertahankan kecuali diganti atau anggotanya dihapus.
- Hapus proyek dari daftar admin untuk menghapus proyek beserta foto proyek dan foto anggota terkait.
- Berkas SQLite dapat dicadangkan dengan menyalin `storage/gelar-karya.sqlite3` setelah server dihentikan.

Halaman utama memakai SVG dan animasi GSAP yang sudah ada: gunung sebagai pembuka, daun pada bagian proyek, lalu visual api di bagian akhir. GSAP serta Three.js dimuat dari CDN, sehingga animasi tersebut memerlukan koneksi internet.
