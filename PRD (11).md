# PRD — Tobapia (Storefront + Admin Dashboard)

| | |
|---|---|
| **Versi** | 1.0 (Draft) |
| **Tanggal** | 20 November 2026 |
| **Status** | Menunggu review pemilik |
| **Nama project / brand** | **Tobapia** |

---

## 1. Ringkasan

Membangun website katalog + pemesanan **Tobapia** untuk menjual produk sendiri, dengan:

1. **Storefront** (untuk pelanggan) — tampilan dan alur mengikuti referensi *Order catalog | TOBAPIA* (situs contoh): gerbang usia, filter produk, keranjang, dan kirim pesanan. Ditambah halaman akun & riwayat pesanan seperti *Orders – CALLE DE LARACHE*.
2. **Admin Dashboard** (untuk pemilik/staf) — mengelola produk, harga, stok, pesanan, pembayaran, ongkir, dan pelanggan dari satu tempat tanpa perlu edit spreadsheet manual.

**Prinsip utama:** sederhana dipakai pelanggan (pilih produk → atur jumlah → checkout → bayar), dan cepat diproses admin (semua pesanan masuk ke satu antrean dengan status jelas).

---

## 2. Tujuan & Non-Tujuan

### Tujuan
- Pelanggan bisa menjelajah katalog, memfilter, dan checkout dalam < 2 menit.
- Harga & ongkir terhitung otomatis dan konsisten dengan data di spreadsheet harga.
- Admin bisa memproses pesanan (konfirmasi → bayar → kirim) dari satu dashboard.
- Admin bisa mengubah harga/produk/gambar tanpa developer.
- Mobile-first (mayoritas pelanggan memesan lewat HP).

### Non-Tujuan (versi 1)
- **Marlboro dan Esse tidak ditambahkan dulu** (aset gambar belum ada). Data harganya disimpan di sistem dengan status *Draft/Hidden*, tidak tampil di storefront (lihat §6.4).
- Payment gateway apa pun (Midtrans, Xendit, dll.) — **tidak dipakai sama sekali**. Pembayaran hanya menampilkan QR Code + isi pesanan seperti situs referensi; admin menandai "Dibayar" secara manual.
- Aplikasi mobile native.
- Marketplace multi-vendor.

---

## 3. Sumber Data & Referensi

| Sumber | Isi | Catatan |
|---|---|---|
| Google Drive folder "IQOS" | Foto produk | Folder tidak bisa dibaca otomatis (butuh login/JS). **Perlu di-share *Anyone with the link* atau file di-upload/zip.** |
| Google Sheets "Product List – Assets" | 5 tab: Device, Terea Sticks, Marlboro, Esse, Delivery Fee | Dibaca dan dirangkum di §6 & Lampiran |
| TOBAPIA (referensi tampilan) | Katalog + filter + keranjang + kirim lewat WhatsApp, multi-bahasa | Acuan UI/UX storefront |
| Calle de Larache (referensi akun) | Navigasi, halaman akun, halaman `/orders`, navigasi & akun | Acuan halaman akun & riwayat pesanan |

---

## 4. Pengguna

| Persona | Kebutuhan |
|---|---|
| **Pelanggan** (dewasa, 21+) | Lihat produk & harga jelas, pesan cepat, tahu total + ongkir, lacak status pesanan |
| **Admin / Owner** | Terima pesanan, cek pembayaran, ubah status, kelola harga & produk, lihat rekap penjualan |
| **Staf (opsional)** | Hanya proses pesanan (tanpa akses harga/pengaturan) |

---

## 5. Kepatuhan & Batasan Usia (wajib diperhatikan)

Produk ini adalah produk tembakau. Berdasarkan hasil penelusuran terhadap pemberitaan PP No. 28 Tahun 2024 (turunan UU Kesehatan):

- Penjualan produk tembakau dan rokok elektronik **dilarang kepada orang di bawah 21 tahun** dan perempuan hamil.
- Penjualan lewat situs web/aplikasi elektronik dan media sosial **dikecualikan dari larangan jika ada verifikasi umur**.
- Iklan di situs web/aplikasi diperbolehkan dengan syarat: mencantumkan peringatan kesehatan, mencantumkan tulisan larangan menjual/memberi kepada orang di bawah 21 tahun dan perempuan hamil, serta menerapkan verifikasi umur.

**Konsekuensi untuk produk:**
- Age gate 21+ di setiap kunjungan pertama (seperti referensi), dengan opsi "Saya di bawah 21" yang menutup akses.
- Checkout cukup mewajibkan **centang pernyataan usia ≥ 21 tahun** (tanpa input tanggal lahir, tanpa verifikasi KTP/dokumen). Tanpa centang, pesanan tidak bisa dibuat. Waktu centang dan teks pernyataan yang disetujui disimpan di data pesanan.
- Footer/header menampilkan peringatan kesehatan dan teks larangan penjualan ke < 21 tahun & ibu hamil.
- Tanpa diskon/hadiah gratis sebagai promosi (PP tersebut juga membatasi hal ini) — fitur kupon **tidak** dibuat di v1.

> ⚠️ Dokumen ini bukan nasihat hukum. Sebelum launching, mohon konfirmasi ke konsultan hukum/perizinan terkait: izin usaha, legalitas impor & pita cukai untuk varian asal Korea/Jepang/Uzbekistan/Italia, serta aturan iklan online. Teks peringatan di UI dibuat **dapat diedit admin** agar mudah disesuaikan.

---

## 6. Katalog Produk (Scope v1)

### 6.1 Struktur kategori (mengikuti TOBAPIA)
- **TEREA** (stik) — filter *Edisi negara*: Semua / Indonesia / Korea Selatan / Jepang / Uzbekistan / Italia
- **IQOS ILUMA i**
- **IQOS ILUMA i ONE**
- **IQOS ILUMA i PRIME**

Satuan Terea: **1 pack = 20 stik; 1 slof (carton) = 10 pack = 200 stik.** Device dijual satuan.

### 6.2 Device (14 SKU, harga siap pakai)

| Seri | Varian warna | Harga |
|---|---|---|
| IQOS ILUMA i | Breeze Blue, Digital Violet, Midnight Purple, Leaf Green, Midnight Black | Rp899.000 |
| IQOS ILUMA i ONE | Breeze Blue, Midnight Purple, Leaf Green, Midnight Black | Rp349.000 |
| IQOS ILUMA i PRIME | Breeze Blue, Midnight Purple, Garnet Red, Midnight Black, Aspen Green | Rp1.599.000 |

Spreadsheet memiliki kolom *Origin Price* dan *Custom Price* (saat ini sama). Sistem menyimpan keduanya; yang ditampilkan ke pelanggan adalah *Custom Price*. **Harga Indonesia (IDR) sudah final. Kolom harga CNY di spreadsheet tidak dipakai dan tidak diimpor** (belum pasti).

### 6.3 Terea Stik — Edisi Indonesia (21 SKU, harga siap pakai)

Harga per pack (20 stik) dan per slof (200 stik); nama Mandarin/Korea dan rasa ikut disimpan (harga CNY tidak dipakai). Rentang harga: Rp35.000–Rp40.000/pack (Rp350.000–Rp400.000/slof). Daftar lengkap: Lampiran A.

### 6.4 Produk yang belum/tidak tampil

| Kelompok | Jumlah | Status v1 | Alasan |
|---|---|---|---|
| **Marlboro** | 12 varian | Data diimpor, status **Hidden** | Belum ada aset gambar (permintaan pemilik) |
| **Esse** | 15 varian | Data diimpor, status **Hidden** | Belum ada aset gambar (permintaan pemilik) |
| Terea Korea | 22 varian | Tampil **hanya jika harga & gambar terisi**; jika tidak → Hidden | Spreadsheet belum memuat harga |
| Terea Uzbekistan | 11 varian | Sama seperti di atas | Belum ada harga |
| Terea Italia | 8 varian | Sama seperti di atas | Belum ada harga |
| Terea Jepang | ±13 varian | Sama seperti di atas | Belum ada harga |

Aturan sistem: **produk tidak bisa di-publish jika harga kosong atau gambar belum ada.** Saat aset Marlboro/Esse tersedia, admin cukup upload gambar lalu klik *Publish* — tanpa perubahan kode.

---

## 7. Aturan Bisnis

### 7.1 Ongkir Device
- Angka di spreadsheet (¥120 per unit) **belum final, sehingga tidak ditampilkan dan tidak dihitung** di website.
- Untuk v1: keranjang, halaman pembayaran, dan PDF **tidak memuat baris ongkir Device**; total Device = subtotal harga produk.
- Sistem tetap menyediakan pengaturan tarif Device (kosong/nonaktif secara default). Saat pemilik sudah menetapkan nominal Rupiah, cukup diisi di dashboard dan ongkir Device otomatis muncul — tanpa perubahan kode.
- Admin juga dapat menambahkan biaya ongkir manual pada satu pesanan dari detail pesanan jika diperlukan.

### 7.2 Ongkir Terea (per slof)
Dari tabel "Delivery Fee" dan contoh kondisinya, aturan yang terbaca adalah pembagian kardus maksimal 4 slof:

| Jumlah slof di satu kardus | Ongkir |
|---|---|
| 1–2 slof | Rp400.000 |
| 3–4 slof | Rp450.000 |

Pesanan dipecah menjadi kardus 4 slof (sisanya satu kardus tambahan), lalu ongkir dijumlahkan. Pencocokan dengan contoh di spreadsheet:

| Pesanan | Pemecahan | Total ongkir | Cocok dengan spreadsheet? |
|---|---|---|---|
| 7 slof | 4 + 3 | 450.000 + 450.000 = **Rp900.000** | ✅ |
| 9 slof | 4 + 4 + 1 | 450.000 + 450.000 + 400.000 = **Rp1.300.000** | ✅ |
| 5 slof | 4 + 1 | Rp850.000 | (turunan) |
| 6 slof | 4 + 2 | Rp850.000 | (turunan) |

Aturan ini **dapat dikonfigurasi admin** (ukuran kardus, tarif per rentang) di menu Pengaturan Ongkir, tidak hard-coded.

### 7.3 Harga
- Harga disimpan per SKU per satuan (pack / slof / unit).
- Admin dapat mengubah harga massal (naik/turun nominal atau persen per kategori/edisi). Header spreadsheet menyebut "Increase 5K" (pack) dan "Increase 50K" (slof) — fitur *bulk markup* di dashboard menggantikan perhitungan manual ini.
- Setiap perubahan harga tercatat (siapa, kapan, harga lama → baru).
- Harga pada pesanan **dikunci** saat checkout (perubahan harga berikutnya tidak mengubah pesanan lama).

### 7.4 Total pesanan
`Total = Σ(harga × qty) + ongkir Terea (+ ongkir Device hanya jika tarifnya sudah diaktifkan admin)`

---

## 8. Alur Pelanggan (Storefront)

1. **Age gate** → pilih "Saya 21+" atau "Di bawah 21" (di bawah 21 = akses ditutup).
2. **Katalog** → filter kategori & edisi negara, cari nama/rasa, atur qty langsung di kartu produk.
3. **Keranjang** → ringkasan item, subtotal, ongkir otomatis, total.
4. **Checkout** → nama, WhatsApp, alamat lengkap, catatan, dan **centang pernyataan usia 21+** (wajib).
5. **Halaman Detail Pesanan** → ringkasan item, ongkir, total, dan nomor pesanan.
6. **Pembayaran (mengikuti alur IPT-Produk)** → alur sesuai spreadsheet: *Download PDF → Order Detail → QR CODE IPT*. Halaman pembayaran **hanya menampilkan QR Code pembayaran** (gambar QR dari admin, tampil jelas dan bisa disimpan/di-zoom) bersama **isi pesanan** (daftar item, qty, harga, ongkir, total, nama & alamat penerima, nomor pesanan). Pelanggan dapat **mengunduh PDF Order Detail yang memuat isi yang sama + QR Code**. Tidak ada integrasi payment gateway dan tidak ada pengecekan pembayaran otomatis.
7. **Konfirmasi** → setelah membayar, pelanggan mengirim pesanan/bukti bayar via WhatsApp (tombol "Kirim ke WhatsApp" dengan teks pesanan terisi otomatis, seperti situs referensi). Admin mengecek pembayaran sendiri lalu mengubah status pesanan menjadi *Dibayar*.

> **Catatan:** Halaman pembayaran situs IPT-Produk tidak dapat saya buka (hanya halaman katalog yang terbaca), jadi tata letaknya mengacu pada deskripsi alur di spreadsheet. Mohon kirim screenshot halaman pembayaran + gambar QR agar bisa ditiru persis.
8. **Akun & Pesanan** → riwayat pesanan, status, detail, unduh ulang PDF, pesan ulang.

---

## 9. Kebutuhan Fungsional — Storefront

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-S1 | Age gate 21+ (disimpan di cookie, tampil ulang tiap sesi baru) | P0 |
| FR-S2 | Pilihan 4 bahasa: **English (default)**, Indonesia, 中文 (Mandarin), 한국어 (Korea). Mencakup teks UI, PDF Order Detail, dan nama produk. Mandarin & Korea diambil dari kolom spreadsheet (kolom Chinese Name; nama Korea tersedia untuk edisi Korea). Pilihan bahasa diingat di cookie; jika nama produk belum ada terjemahannya → fallback ke English | P0 |
| FR-S3 | Katalog grid dengan foto, nama, rasa, harga, stepper qty (− / +) | P0 |
| FR-S4 | Filter kategori (Terea / Iluma i / One / Prime) + filter edisi negara + pencarian | P0 |
| FR-S5 | Keranjang persisten (tersimpan di browser, sinkron ke akun jika login), badge jumlah di header | P0 |
| FR-S6 | Kalkulasi ongkir otomatis sesuai §7 | P0 |
| FR-S7 | Checkout dengan validasi (WhatsApp, alamat, centang pernyataan usia 21+) | P0 |
| FR-S8 | Halaman pembayaran: tampilkan QR Code (gambar dari admin) + isi pesanan lengkap + total + nomor pesanan; tombol unduh PDF Order Detail (isi pesanan + QR yang sama). Tanpa payment gateway | P0 |
| FR-S9 | Tombol "Kirim ke WhatsApp" + "Salin pesanan" (modal salin manual jika clipboard gagal) | P0 |
| FR-S10 | Dialog konfirmasi "Hapus pesanan?" untuk mengosongkan keranjang | P1 |
| FR-S11 | Registrasi/login (email atau nomor WhatsApp + OTP/password) | P0 |
| FR-S12 | Halaman `/account` dan `/orders` (daftar + detail + status timeline) | P0 |
| FR-S13 | Halaman pesanan dapat membuka kembali halaman pembayaran (QR + isi pesanan) selama status belum *Dibayar* | P1 |
| FR-S14 | Pesan ulang (reorder) satu klik | P2 |
| FR-S15 | Checkout tamu (tanpa akun) dengan lacak pesanan via nomor pesanan + WhatsApp | P1 |
| FR-S16 | Banner peringatan kesehatan + larangan < 21 tahun (teks diedit admin) | P0 |
| FR-S17 | Produk *out of stock* tampil abu-abu & tidak bisa ditambah | P1 |

---

## 10. Kebutuhan Fungsional — Admin Dashboard

Akses: `/admin` (login terpisah, 2 peran: **Owner**, **Staf**).

| ID | Modul | Fungsi | Prioritas |
|---|---|---|---|
| FR-A1 | **Ringkasan (Home)** | Kartu: pesanan baru, menunggu pembayaran, perlu dikirim, omzet hari ini/minggu/bulan; daftar pesanan terbaru; peringatan stok menipis | P0 |
| FR-A2 | **Pesanan** | Tabel dengan filter status/tanggal/pencarian; detail pesanan; ubah status; catatan internal; cetak/unduh PDF; tombol cepat "Hubungi via WhatsApp" | P0 |
| FR-A3 | **Alur status** | `Baru → Menunggu Pembayaran → Dibayar (verifikasi) → Diproses → Dikirim → Selesai` + `Dibatalkan`. Input nomor resi saat *Dikirim*. Riwayat perubahan status tercatat | P0 |
| FR-A4 | **Konfirmasi Pembayaran (manual)** | Admin menandai pesanan *Dibayar* setelah mengecek mutasi/WhatsApp; catatan internal opsional | P0 |
| FR-A5 | **Produk** | CRUD produk & varian, upload gambar (multi), nama English/Indonesia/中文/한국어, rasa, edisi negara, satuan, status Draft/Published/Hidden | P0 |
| FR-A6 | **Harga** | Edit inline per SKU (pack/slof/unit, hanya IDR), edit massal (± nominal/%), riwayat perubahan | P0 |
| FR-A7 | **Stok** | Stok per SKU, penyesuaian manual, otomatis berkurang saat status *Dibayar* | P1 |
| FR-A8 | **Pengaturan Ongkir** | Aturan kardus Terea, tarif Device (IDR, nonaktif sampai ditetapkan) | P0 |
| FR-A9 | **Catatan Pernyataan Usia** | Detail pesanan menampilkan bukti pelanggan sudah mencentang pernyataan 21+ (waktu & teks). Tidak ada verifikasi KTP | P1 |
| FR-A10 | **Pelanggan** | Daftar pelanggan, riwayat pesanan, total belanja, blokir pelanggan | P1 |
| FR-A11 | **Impor/Ekspor** | Impor produk & harga dari Google Sheets/CSV (template mengikuti kolom spreadsheet saat ini); ekspor pesanan ke CSV/Excel | P0 |
| FR-A12 | **Laporan** | Penjualan per periode, produk terlaris, per edisi negara; ekspor | P1 |
| FR-A13 | **Pengaturan Toko** | Logo, nomor WhatsApp penerima pesanan, QR Code pembayaran, rekening, teks peringatan, template pesan WhatsApp | P0 |
| FR-A14 | **Pengguna & Peran** | Tambah staf, atur peran | P1 |
| FR-A15 | **Audit Log** | Siapa mengubah apa & kapan (harga, status, pengaturan) | P1 |
| FR-A16 | **Notifikasi pesanan baru** | Notifikasi email/WhatsApp ke admin saat ada pesanan baru | P1 |

---

## 11. Desain & UX

**Mengikuti TOBAPIA:**
- Header: logo Tobapia, tombol Keranjang dengan badge jumlah, pill "Order catalog", badge **21+**, pemilih bahasa (EN / ID / 中文 / 한국어), default **EN**.
- Chip filter: *Country edition* dan *All products / kategori*.
- Teks bantuan satuan di atas katalog (20 stik/pack, 10 pack/slof).
- Modal: age confirmation, clear order, copy order.
- **Warna website mengikuti situs TOBAPIA.** Warna utama: hijau gelap `#183e35` (terbaca dari tema situs referensi), dipakai untuk header, tombol utama, dan elemen aksen. Warna lain (latar, teks, aksen sekunder, tombol, border) diambil dari stylesheet situs TOBAPIA saat pembangunan dan disimpan sebagai *design tokens* (CSS variables) agar mudah diubah di satu tempat. Logo memakai logo Tobapia yang sama dengan situs referensi.
- Admin dashboard memakai palet yang sama agar konsisten.
- Kontras teks tetap dicek agar memenuhi WCAG AA.
- Link "Skip to products" (aksesibilitas) dan navigasi ← Previous / Next → pada galeri gambar.

**Mengikuti Calle de Larache:**
- Menu atas: Home, All Products, kategori, About; ikon keranjang & orders.
- Halaman akun & `/orders` yang bersih dan minimal.
- Seluruh harga ditampilkan **hanya dalam Rupiah (IDR)**; tidak ada selector mata uang dan tidak ada tampilan CNY.

**Umum:** mobile-first, target Lighthouse Performance ≥ 90 di mobile, gambar produk dioptimasi (WebP, lazy-load), kontras warna memenuhi WCAG AA.

---

## 12. Rekomendasi Teknis

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Frontend | **Next.js (App Router) + TypeScript + Tailwind CSS** | SEO baik, cepat, satu codebase untuk storefront & admin |
| i18n | `next-intl` (en default / id / zh / ko) | 4 bahasa; font CJK (Noto Sans SC/KR) untuk Mandarin & Korea |
| Backend & DB | **Supabase** (PostgreSQL, Auth, Storage, Row Level Security) | Auth + upload gambar produk & QR + database dalam satu layanan, biaya awal rendah |
| PDF + QR | `@react-pdf/renderer` + `qrcode` | Membuat PDF Order Detail dengan QR di sisi server |
| Admin UI | shadcn/ui + TanStack Table | Tabel, filter, dan form cepat dibuat |
| Hosting | **Vercel** (+ domain sendiri) | Deploy otomatis dari Git |
| Impor data | Skrip impor CSV/Google Sheets API | Seed awal dari spreadsheet harga |

_Alternatif lebih ringan:_ situs statis seperti referensi (GitHub Pages) + Google Sheets sebagai database. Cepat dibuat, tetapi **tidak aman untuk akun pelanggan, admin dashboard, dan data pesanan** — tidak direkomendasikan untuk kebutuhan ini.

### Model data (ringkas)

- `products` (id, category, series, name_id/en/zh, flavour, origin_edition, image_urls, status)
- `variants` (id, product_id, unit [pack|slof|unit], price_idr, stock)
- `orders` (id, order_no, customer_id, status, subtotal, shipping_device, shipping_terea, total, address, notes, age_confirmed, age_confirmed_at, age_statement_text, created_at)
- `order_items` (order_id, variant_id, name_snapshot, price_snapshot, qty)
- `payments` (order_id, status, marked_paid_by, marked_paid_at, note)
- `shipping_rules` (type, box_size, min_qty, max_qty, fee) + `settings` (WhatsApp, QR, teks peringatan)
- `customers`, `admin_users` (role), `price_history`, `audit_log`

### Keamanan
- Row Level Security: pelanggan hanya melihat pesanannya sendiri.
- Admin route dilindungi auth + role; rate limiting login.
- Validasi harga & ongkir **di server** (tidak percaya angka dari browser).

---

## 13. Pertanyaan Terbuka (perlu jawaban pemilik)

1. **Foto produk:** folder Drive tidak dapat dibuka otomatis. Mohon ubah akses menjadi *Anyone with the link – Viewer* atau kirim zip. Mohon juga konfirmasi penamaan file mengikuti nama produk.
2. **Ongkir Device:** ditunda sampai angkanya fix (¥120 di spreadsheet tidak ditampilkan). Nanti cukup diisi nominal Rupiah di dashboard.
3. **Ongkir Terea:** apakah tebakan aturan kardus 4 slof di §7.2 sudah benar, dan bagaimana untuk 5, 6, dan 8 slof?
4. **Header "Increase 5K / 50K":** apakah harga Terea Indonesia di spreadsheet sudah termasuk kenaikan itu, atau kenaikan masih harus ditambahkan?
5. **Satuan pembelian Terea:** pelanggan boleh beli per **pack**, per **slof**, atau keduanya? (Ongkir dihitung per slof.)
6. **Edisi Korea/Jepang/Uzbekistan/Italia:** kapan harga & gambar tersedia? Sementara disembunyikan.
7. **Pembayaran:** mohon kirim gambar QR yang dipakai ("QR CODE IPT") dan screenshot halaman pembayaran IPT-Produk. QR yang sama untuk semua pesanan (statis), atau berbeda per pesanan?
8. **Wilayah pengiriman:** seluruh Indonesia atau area tertentu? Ekspedisi apa?
9. **Terjemahan:** siapa yang menyediakan terjemahan Korea untuk teks UI dan nama produk non-Korea (draf awal bisa dibuat Claude lalu direview penutur asli)?
10. **Logo (file asli) dan domain.** Nama brand Tobapia dan warna mengikuti situs TOBAPIA sudah ditetapkan.
11. **Legalitas:** konfirmasi izin usaha, impor/pita cukai, dan persyaratan iklan online (lihat §5).

---

## 14. Rencana Rilis

| Fase | Cakupan | Estimasi |
|---|---|---|
| **0 — Persiapan** | Jawaban §13, aset gambar, brand, setup repo & Supabase | 2–3 hari |
| **1 — MVP Storefront** | Age gate, katalog (Device + Terea Indonesia), filter, keranjang, ongkir, checkout, PDF + QR, kirim WhatsApp, 4 bahasa (EN default, ID, 中文, 한국어) | 1–2 minggu |
| **2 — Admin Core** | Login, pesanan + status, verifikasi bayar, produk, harga, ongkir, impor CSV, pengaturan toko | 1–2 minggu |
| **3 — Akun & Orders** | Registrasi/login pelanggan, riwayat pesanan, lacak pesanan | 1 minggu |
| **4 — Penyempurnaan** | Stok, laporan, audit log, notifikasi, publish Marlboro/Esse & edisi negara lain | 1–2 minggu |

---

## 15. Kriteria Penerimaan (MVP)

- [ ] Age gate tampil di kunjungan pertama; "di bawah 21" memblokir akses.
- [ ] Katalog menampilkan 14 Device + 21 Terea Indonesia dengan harga persis sama seperti spreadsheet.
- [ ] Marlboro & Esse **tidak** tampil di storefront.
- [ ] Ongkir: 7 slof = Rp900.000; 9 slof = Rp1.300.000; 1–2 slof = Rp400.000; 3–4 slof = Rp450.000.
- [ ] Pesanan dibuat, nomor pesanan unik; halaman pembayaran menampilkan QR Code + isi pesanan + total; PDF Order Detail dengan isi & QR yang sama dapat diunduh; tidak ada integrasi payment gateway.
- [ ] Teks WhatsApp otomatis berisi item, qty, total, nama, alamat, catatan.
- [ ] Admin dapat mengubah status pesanan dan melihat riwayat perubahannya.
- [ ] Admin dapat mengubah harga; pesanan lama tidak berubah.
- [ ] Produk dengan harga/gambar kosong tidak dapat di-publish.
- [ ] Bahasa default English; pergantian ke ID / 中文 / 한국어 berfungsi di seluruh halaman dan PDF.
- [ ] Tampilan nyaman di layar 360 px; Lighthouse mobile Performance ≥ 90.

---

## Lampiran A — Data Seed

### A.1 Terea Indonesia (harga per pack / per slof, IDR)

| Produk | Rasa (dari spreadsheet) | Pack | Slof |
|---|---|---|---|
| Auburn Edition | Vanilla, Apricot, Clove | 35.000 | 350.000 |
| Bermin Pearl Edition | Blueberry, Capsule, Menthol | 39.000 | 390.000 |
| Berrine Edition | Cooling & Floral | 39.000 | 390.000 |
| Black Green | Green Fruit Aroma | 39.000 | 390.000 |
| Black Ruby | Red Berry | 39.000 | 390.000 |
| Blue | Peppermint, Creamy | 39.000 | 390.000 |
| Bright Wave | Pear, Menthol | 39.000 | 390.000 |
| Bronze | Cocoa, Dry Fruit | 39.000 | 390.000 |
| Dimension Apricity | Caramel with Apricot | 39.000 | 390.000 |
| Golden Edition | Plum, Clove | 35.000 | 350.000 |
| Green | Pear, Menthol | 39.000 | 390.000 |
| Mulint Edition | Cooling & Fruity | 35.000 | 350.000 |
| Oasis Pearl | Tropic Fruity, Menthol, Pearl | 40.000 | 400.000 |
| Passion Fruit | Passion Fruit, Capsule, Menthol, Clove | 37.000 | 370.000 |
| Perint Pearl Edition | Pear, Cengkeh, Capsule | 39.000 | 390.000 |
| Purple Wave / Mauve | Blueberry | 39.000 | 390.000 |
| Riviera Pearl | Raspberry, Floral, Capsule | 40.000 | 400.000 |
| Sienna | Tea aroma, Woody | 39.000 | 390.000 |
| Strawberry Pearl | Strawberry, Basil, Capsule, Clove, Menthol | 37.000 | 370.000 |
| Sun Pearl | Exotic Fruit & Menthol | 40.000 | 400.000 |
| Dimension Yugen | Fruity & Floral | 39.000 | 390.000 |

_(Nama Mandarin tiap produk diimpor langsung dari kolom "Chinese Name" spreadsheet.)_

### A.2 Device — lihat §6.2.

### A.3 Edisi lain (disembunyikan sampai harga & gambar ada)

- **Korea (22):** Amber, Silver, Blue, Green, Purple Wave, Black Purple, Black Green, Black Yellow, Russet, Teak, Oasis Pearl, Sun Pearl, Arbor Pearl, Riviera Pearl, Starling Pearl, Green Zing, Summer Wave, Yugen, Shine Pearl, Glacier Twilight Pearl, Onyx Amang Pearl, Onyx Cali Pearl
- **Uzbekistan (11):** Amber, Provence Pearl, Purple Wave, Silver, Starling Pearl, Summer Wave, Sun Pearl, Tidal Pearl, Turquoise, Twilight Pearl, Yellow
- **Italia (8):** Amber, Bronze, Purple Wave, Teak, Silver, Sienna, Turquoise, Yellow
- **Jepang (±13):** Balanced Regular, Black Menthol, Black Purple, Bold Regular, Black Ruby Menthol, Bright Menthol, Fusion Menthol, Menthol, Purple Menthol, Oasis Pearl, Mint, Regular, Rich Regular _(tampilan spreadsheet terpotong di baris 100; kemungkinan ada baris lanjutan)_

### A.4 Ditunda (Hidden, tidak tampil v1)
- **Marlboro** (12 varian) dan **Esse** (15 varian) — data harga sudah diimpor ke database, menunggu aset gambar.

---

_Akhir dokumen._
