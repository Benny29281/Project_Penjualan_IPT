# Tobapia

Storefront dan dashboard pemesanan berdasarkan [PRD Tobapia](<PRD (11).md>). Versi lokal memakai PHP dan SQLite dari Laragon. Penyimpanan berjalan di folder `storage`.

## Menjalankan di Windows

1. Dari folder ini, jalankan `start.bat` atau klik dua kali file tersebut.
2. Buka <http://127.0.0.1:8080/>. Jalankan `start.bat 8081` jika port 8080 sudah dipakai.
3. Di terminal lain, jalankan `start.bat owner email-anda@example.com` dan masukkan kata sandi minimal 10 karakter saat diminta. Masuk melalui <http://127.0.0.1:8080/admin>.

`start.bat` mencari PHP di PATH dan folder Laragon. Aplikasi memerlukan PHP 8.1 atau lebih tinggi dengan PDO SQLite, GD, dan Mbstring. Jika menjalankan langsung melalui Apache Laragon, aktifkan `extension=pdo_sqlite`, pastikan `mod_rewrite` dan `.htaccess` berlaku, lalu restart Apache. Gunakan `start.bat` untuk pratinjau tanpa mengubah konfigurasi Apache.

## Data awal dan pengaturan

Untuk konfirmasi pesanan, pelanggan mengunduh PDF pesanan dan melampirkannya bersama bukti transfer ke email `tobapia06@gmail.com`. Setelah mengirim email dari aplikasi emailnya, pelanggan mencentang pernyataan dan menekan **Konfirmasi** untuk kembali ke beranda. Toko memverifikasi pembayaran secara manual.

Pelanggan punya 20 jam sejak pesanan dibuat untuk menekan **Konfirmasi**. Pesanan yang belum dikonfirmasi pelanggan dan belum ditandai **Dibayar** akan dibatalkan otomatis. Stok baru berkurang saat admin menandai pesanan operasional **Dibayar**. Aplikasi memeriksa pesanan kedaluwarsa saat menerima permintaan. Untuk memproses pembatalan saat situs sepi, jalankan `php app/console.php expire-orders` lewat penjadwal server dengan PHP yang memiliki PDO SQLite dan menggunakan direktori data toko yang sama.

- Mode demo memperlihatkan 14 varian perangkat dan 21 TEREA Indonesia dengan harga IDR dari PRD, stok simulasi dan ilustrasi sementara. Pesanan demo dikecualikan dari rekap penjualan dan pengurangan stok. Empat edisi luar negeri dimulai sebagai Hidden.
- Satuan demo adalah pack dan slof. Pack sisa dibulatkan ke satu slof untuk ongkir. Owner dapat mengubah satuan, aturan pack, ukuran kardus, tarif TEREA dan tarif Device di menu **Ongkir**.
- Di **Pengaturan toko**, Owner dapat mengunggah logo dan QR statis, memasukkan nomor WhatsApp penerima, mengubah peringatan per bahasa dan menyunting template pesan. Pengiriman email memerlukan konfigurasi `mail()` pada PHP. Pengiriman otomatis melalui WhatsApp memerlukan integrasi terpisah.
- Di **Produk**, isi deskripsi per bahasa dan spesifikasi dengan format `Nama: nilai` per baris. Unggah hingga delapan foto JPG, PNG, atau WebP dengan batas 6 MB per foto. Foto pertama menjadi gambar utama. Isi harga, perbarui stok, lalu pilih Published untuk produk yang siap ditampilkan. Nama terjemahan dapat diisi di editor atau diimpor dengan CSV. Nama English digunakan jika terjemahan kosong.
- Di **Impor & ekspor**, isi template CSV dari Google Sheets. Gunakan header yang sama dengan template. Data asli untuk Marlboro, Esse, dan nama dari kolom Mandarin/Korea memerlukan sumber spreadsheet. Produk baru hasil impor masuk sebagai Hidden.
- PDF dibuat di server dari isi pesanan. Font Windows Arial, Microsoft YaHei dan Malgun Gothic dipakai sesuai bahasa. Untuk instalasi lain, jalur font dapat diatur dengan `TOBAPIA_PDF_FONT_EN`, `TOBAPIA_PDF_FONT_ID`, `TOBAPIA_PDF_FONT_ZH` dan `TOBAPIA_PDF_FONT_KO`.
- Dokumen pesanan memakai tanggal dan urutan harian WIB dengan akhiran `_05`. Invoice mencantumkan harga dan ongkir hingga baris total. QR pembayaran ditampilkan di halaman detail pesanan.
- Checkout memakai data lokal untuk provinsi, kabupaten/kota, kecamatan, desa/kelurahan dan kode pos Indonesia. Alamat di negara lain diisi manual. Sumber berlisensi MIT tercantum di [lisensi data wilayah](app/delivery-regions-license.txt). [Referensi wilayah](https://github.com/cahyadsn/wilayah) · [Referensi kode pos](https://github.com/cahyadsn/wilayah_kodepos).

Mode awal adalah demo lokal. Peluncuran operasional memerlukan aset dan pengaturan dari pemilik serta pemeriksaan persyaratan verifikasi usia dan iklan. Rujuk [PP 28 Tahun 2024](https://peraturan.bpk.go.id/Details/294077/pp-no-28-tahun-2024) dan [Keputusan Menteri Kesehatan 963/2026](https://jdih.kemkes.go.id/documents/keputusan-menteri-kesehatan-nomor-hk0107menkes9632026).

## Demo di Railway

1. Hubungkan repo ke layanan Railway dengan builder **Railpack**. Gunakan start command bawaan agar Railpack menjalankan FrankenPHP dengan `Caddyfile`.
2. Railpack membaca ekstensi PHP dari `composer.json` dan memasang font PDF dari `railpack.json`. Batas unggahan diatur lewat `Caddyfile`.
3. Tambah **Volume** ke layanan dengan mount path `/app/storage` agar pesanan, SQLite, dan unggahan tersimpan saat redeploy. Untuk demo baru, biarkan aplikasi mengisi database secara otomatis pada kunjungan pertama.
4. Setelah deployment aktif, buka **Settings → Networking → Public Networking → Generate Domain**. Pakai port yang terdeteksi otomatis. Jika Railway meminta port dan variabel `PORT` belum diatur, gunakan `8080`.
5. Buka URL HTTPS. Pastikan permintaan ke `/storage/tobapia.sqlite` mengembalikan HTTP 404. Cek katalog dan detail produk, lalu lakukan satu checkout demo dan coba unduh PDF.
