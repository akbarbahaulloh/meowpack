# MeowPack — Rancangan Plugin WordPress & Prompt Agent Claude

---

## BAGIAN 1: RANCANGAN PLUGIN MEOWPACK

### Deskripsi Singkat
MeowPack adalah plugin WordPress ringan pengganti Jetpack yang menyimpan semua data di database lokal (MySQL WordPress), tanpa ketergantungan pihak ketiga. Fokus pada efisiensi hosting dan privasi data. Plugin ini dikembangkan oleh Akbar Bahaulloh.

---

### Modul Utama

#### 1. Core Engine
- Inisialisasi plugin, manajemen tabel database, caching layer
- Request normalizer (menghilangkan duplikat kunjungan dalam satu sesi)
- Cron job harian untuk agregasi data dan cleanup otomatis (hapus data detail > 12 bulan, pertahankan data agregasi)
- Async queue untuk penulisan ke database (batch insert, bukan per-request)

#### 2. Bot Filter Layer
- Deteksi berdasarkan user-agent string (daftar bot umum: Googlebot, Bingbot, dll. — dicatat terpisah sebagai "bot traffic")
- IP rate limiting (lebih dari N request/menit dari 1 IP = bot)
- Honeypot endpoint tersembunyi
- Deteksi headless browser (tidak ada JavaScript execution)
- Referrer spam filter
- Daftar bot bisa dikustomisasi admin

#### 3. Modul Statistik Admin
Halaman admin WordPress dengan:
- Grafik pengunjung harian/mingguan/bulanan
- Halaman paling banyak dibaca (top 10/50/100)
- Sumber kunjungan: Direct, Search, Social, Referral, Email, lain-lain
- Breakdown per search engine (Google, Bing, Yahoo, dll.)
- Breakdown per platform sosial (Facebook, Twitter/X, WhatsApp, dll.)
- Pengunjung unik vs total pageview
- Data real-time (hari ini) + historical
- Export data CSV

#### 4. Lacak Sumber Kunjungan
- Parse HTTP Referrer header
- UTM parameter support (utm_source, utm_medium, utm_campaign)
- Kategori otomatis: Direct (tidak ada referrer), Search (dari search engine), Social (dari sosmed), Referral (dari website lain), Email (dari email client)
- Deteksi country via IP (tanpa API eksternal — gunakan library MaxMind GeoIP2 Lite lokal)

#### 5. Auto Share ke Sosial Media
Platform yang didukung:
- **Facebook** — via Graph API (perlu akses token halaman)
- **Threads** — via Threads API (Instagram Graph API)
- **X (Twitter)** — via Twitter API v2
- **Telegram** — via Bot API (mudah, gratis, tidak perlu review)
- **LinkedIn** — via LinkedIn Share API
- **Pinterest** — via Pinterest API v5
- **WhatsApp** — via WhatsApp Business API atau via wa.me link (untuk trigger manual)
- **Line** — via Line Notify API

Rekomendasi prioritas:
1. Telegram (paling mudah, gratis, banyak dipakai komunitas Indonesia)
2. Facebook + X (standar blog)
3. WhatsApp (relevan untuk audiens Indonesia)
4. LinkedIn (untuk konten profesional/pemerintahan)

Fitur auto share:
- Share otomatis saat post dipublish
- Template pesan custom per platform (variabel: {judul}, {url}, {excerpt}, {tags})
- Jadwal share terjadwal (share X jam setelah publish)
- Cek/uncheck per platform saat edit post
- Log history share (berhasil/gagal)
- Retry otomatis jika gagal (maksimal 3x)

#### 6. Tombol Share di Artikel
- Shortcode `[meowpack_share]` dan block Gutenberg
- Posisi otomatis: sebelum konten, sesudah konten, atau keduanya (via pengaturan)
- Platform yang ditampilkan bisa dikustomisasi
- Hitung share count (disimpan lokal, bukan dari API)
- Style: ikon saja / ikon + teks / pill button (bisa pilih di pengaturan)
- Counter klik share disimpan ke database

#### 7. View Counter di Artikel
- Tampilkan jumlah views di bawah judul artikel (bisa dimatikan per-post)
- Shortcode `[meowpack_views post_id="123"]`
- Format: "1.2rb kali dibaca" atau "1,234 views" (setting lokal)
- Trending widget: daftar post terpopuler dalam periode tertentu (hari ini, minggu ini, bulan ini, all time)

#### 8. Widget Statistik Pengunjung (untuk tampilan publik)
Widget sidebar/footer yang menampilkan:
- Total pengunjung all-time
- Pengunjung hari ini
- Pengunjung bulan ini
- Total halaman dibaca
- Tombol-style: angka besar dengan label (cocok untuk website pemerintahan)
- Shortcode `[meowpack_counter type="today|month|total|pageviews"]`
- Bisa dikombinasikan jadi satu widget 4-box statistik

#### 9. Jetpack Importer
- Deteksi otomatis apakah Jetpack aktif
- Import statistik dari tabel Jetpack (`wp_jetpack_sites_blog_information`, `jetpack_stats_*`)
- Fallback: import dari file export CSV Jetpack (dari WordPress.com)
- Progress bar import (background AJAX)
- Mapping data: tanggal + halaman + jumlah views

---

### Struktur Database

```sql
-- Kunjungan mentah (disimpan 30 hari, lalu diagregasi)
CREATE TABLE meow_visits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL DEFAULT 0,
  visit_date DATE NOT NULL,
  visit_hour TINYINT NOT NULL,
  ip_hash VARCHAR(64) NOT NULL,  -- hash IP untuk privasi
  source_type VARCHAR(20),       -- direct/search/social/referral/email
  source_name VARCHAR(100),      -- google, facebook, dll.
  utm_source VARCHAR(100),
  utm_medium VARCHAR(100),
  utm_campaign VARCHAR(100),
  country_code CHAR(2),
  is_bot TINYINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_date (post_id, visit_date),
  INDEX idx_date (visit_date)
);

-- Agregasi harian (dipertahankan selamanya)
CREATE TABLE meow_daily_stats (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL,
  post_id BIGINT NOT NULL DEFAULT 0,  -- 0 = site-wide
  unique_visitors INT DEFAULT 0,
  total_views INT DEFAULT 0,
  source_direct INT DEFAULT 0,
  source_search INT DEFAULT 0,
  source_social INT DEFAULT 0,
  source_referral INT DEFAULT 0,
  UNIQUE KEY unique_date_post (stat_date, post_id),
  INDEX idx_date (stat_date)
);

-- Log auto share
CREATE TABLE meow_share_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  platform VARCHAR(30) NOT NULL,
  status VARCHAR(20) DEFAULT 'pending',  -- pending/success/failed
  response_code INT,
  shared_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_platform (post_id, platform)
);

-- Settings
CREATE TABLE meow_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value LONGTEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Token sosial media (terenkripsi)
CREATE TABLE meow_social_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  platform VARCHAR(30) UNIQUE NOT NULL,
  access_token TEXT,
  token_data LONGTEXT,  -- JSON data tambahan
  expires_at DATETIME,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

### Strategi Efisiensi Hosting

1. **Async tracking**: Gunakan pixel 1x1 atau fetch API untuk kirim data tracking tanpa memblok render halaman
2. **Batch insert**: Kumpulkan data kunjungan dalam transient/cache, flush ke DB setiap 5 menit via cron
3. **Query caching**: Cache hasil query statistik dengan WordPress Transients API (TTL 1 jam untuk data historis)
4. **Agregasi otomatis**: Cron harian merekap tabel `meow_visits` ke `meow_daily_stats`, lalu hapus data mentah > 30 hari
5. **Lazy load admin chart**: Grafik admin dimuat via AJAX saat halaman dibuka, bukan saat WordPress load
6. **Index database**: Semua query statistik menggunakan composite index
7. **Minimal dependency**: Tidak ada library PHP eksternal yang besar; JavaScript di frontend = 1 file kecil < 3KB

---

### Struktur File Plugin

```
meowpack/
├── meowpack.php              (main plugin file)
├── uninstall.php
├── includes/
│   ├── class-meowpack-core.php
│   ├── class-meowpack-tracker.php
│   ├── class-meowpack-bot-filter.php
│   ├── class-meowpack-stats.php
│   ├── class-meowpack-autoshare.php
│   ├── class-meowpack-share-buttons.php
│   ├── class-meowpack-view-counter.php
│   ├── class-meowpack-widget.php
│   ├── class-meowpack-importer.php
│   └── class-meowpack-database.php
├── admin/
│   ├── class-meowpack-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── settings-general.php
│   │   ├── settings-autoshare.php
│   │   ├── settings-widgets.php
│   │   └── importer.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
├── public/
│   ├── class-meowpack-public.php
│   └── assets/
│       ├── meowpack-tracker.js   (< 3KB, async)
│       └── share-buttons.css
└── languages/
    └── meowpack-id_ID.po
```

---

## BAGIAN 2: PROMPT UNTUK AGENT CLAUDE

Salin prompt di bawah ini ke sesi agent Claude baru (atau Claude Code):

---

```
Kamu adalah senior WordPress plugin developer. Buatkan plugin WordPress bernama **MeowPack** secara lengkap dan siap pakai. Ini adalah plugin pengganti Jetpack yang menyimpan semua data di database lokal WordPress (MySQL) tanpa ketergantungan API pihak ketiga, kecuali untuk auto-share ke sosial media.

## TUJUAN UTAMA
Plugin ringan, tidak memberatkan hosting shared, kode bersih mengikuti WordPress Coding Standards.

## FITUR YANG HARUS DIBANGUN

### 1. Core & Database
- Buat class `MeowPack_Database` yang handle aktivasi plugin: buat tabel-tabel berikut jika belum ada:
  - `{prefix}meow_visits` — kunjungan mentah dengan kolom: id, post_id, visit_date, visit_hour, ip_hash (sha256 dari IP), source_type (direct/search/social/referral/email), source_name, utm_source, utm_medium, utm_campaign, country_code, is_bot, created_at
  - `{prefix}meow_daily_stats` — agregasi harian: stat_date, post_id (0=sitewide), unique_visitors, total_views, source_direct, source_search, source_social, source_referral
  - `{prefix}meow_share_logs` — log auto-share: post_id, platform, status (pending/success/failed), response_code, shared_at
  - `{prefix}meow_social_tokens` — token sosmed: platform, access_token (terenkripsi pakai wp_hash), token_data JSON, expires_at
  - `{prefix}meow_settings` — key-value settings plugin
- Buat cron harian `meowpack_daily_cron` yang:
  1. Agregasi data dari `meow_visits` ke `meow_daily_stats` untuk kemarin
  2. Hapus data `meow_visits` yang lebih dari 30 hari
  3. Retry share log yang `status = failed` dan belum lebih dari 3 percobaan

### 2. Bot Filter
Buat class `MeowPack_Bot_Filter` dengan method `is_bot()` yang mengembalikan true jika:
- User-agent mengandung string dari daftar bot umum (Googlebot, Bingbot, YandexBot, Baiduspider, facebookexternalhit, Twitterbot, dll. — buat daftar lengkap ~50 bot)
- User-agent kosong atau sangat pendek (< 10 karakter)
- IP melakukan lebih dari 30 request dalam 1 menit (gunakan WordPress Transients)
- Request tidak punya header Accept-Language (kemungkinan bot)
Jangan blok bot, hanya tandai `is_bot = 1` di database untuk difilter dari statistik manusia.

### 3. Tracker (Inti Pelacak Kunjungan)
Buat class `MeowPack_Tracker`:
- Hook ke `wp_head` untuk inject pixel tracking (1x1 transparent GIF via REST API endpoint)
- Buat REST API endpoint `wp-json/meowpack/v1/track` yang menerima: post_id, referrer, utm params
- Di endpoint ini: deteksi bot, parse sumber kunjungan, hash IP, simpan ke `meow_visits`
- Parse sumber kunjungan:
  - Jika ada utm_source → gunakan itu
  - Jika referrer mengandung domain search engine → source_type=search
  - Jika referrer mengandung domain sosmed → source_type=social
  - Jika referrer diset tapi bukan kategori di atas → source_type=referral
  - Jika tidak ada referrer → source_type=direct
- Untuk menghindari double-count: cek session/cookie meowpack_{post_id} sebelum track, set cookie 4 jam
- Gunakan async JavaScript (fetch non-blocking) agar tidak memperlambat halaman

JavaScript tracker (injeksi di wp_head, letakkan di footer):
```javascript
// meowpack-tracker.js — non-blocking, < 3KB
(function() {
  var d = document, pid = meowpack_data.post_id;
  if (!pid || sessionStorage.getItem('mp_' + pid)) return;
  sessionStorage.setItem('mp_' + pid, '1');
  var ref = d.referrer, url = new URL(location.href);
  fetch(meowpack_data.endpoint, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      post_id: pid,
      referrer: ref,
      utm_source: url.searchParams.get('utm_source') || '',
      utm_medium: url.searchParams.get('utm_medium') || '',
      utm_campaign: url.searchParams.get('utm_campaign') || '',
      nonce: meowpack_data.nonce
    })
  });
})();
```

### 4. Statistik Admin (Dashboard)
Buat halaman admin di `Settings → MeowPack` dengan sub-menu:
- **Dashboard**: Grafik pengunjung dengan Chart.js (load via CDN, lazy via AJAX). Tampilkan: grafik 30 hari terakhir (unique visitors + pageviews), top 10 artikel terbaca bulan ini, sumber kunjungan (pie chart), total sitewide (hari ini, minggu ini, bulan ini, all time)
- **Pengaturan Umum**: aktifkan/nonaktifkan fitur, view counter on/off per post type, format angka (ribuan pakai titik/koma), simpan data berapa bulan
- **Auto Share**: form konfigurasi per platform (lihat detail di bagian Auto Share)
- **Widget & Tombol Share**: pengaturan tampilan widget dan tombol share
- **Import Jetpack**: tombol scan + import
- Semua query harus menggunakan `$wpdb->prepare()` untuk keamanan

### 5. Auto Share ke Sosial Media
Buat class `MeowPack_AutoShare` dengan method `share_post($post_id, $platforms = [])`:

Dukung platform berikut. Untuk setiap platform, buat method terpisah:

**a. Telegram**
- Gunakan Bot API: `https://api.telegram.org/bot{TOKEN}/sendMessage`
- Setting: bot_token, chat_id (bisa channel @nama atau -100xxxxx)
- Format pesan: "{judul}\n\n{excerpt}\n\n{url}"

**b. Facebook**
- Gunakan Graph API: `https://graph.facebook.com/{PAGE_ID}/feed`
- Setting: page_id, access_token (long-lived page token)
- Format: message + link

**c. X (Twitter)**
- Gunakan Twitter API v2: `https://api.twitter.com/2/tweets`
- Auth: OAuth 2.0 Bearer Token atau OAuth 1.0a (sediakan keduanya)
- Setting: api_key, api_secret, access_token, access_secret
- Batasi teks + URL sesuai 280 karakter

**d. LinkedIn**
- Gunakan LinkedIn Share API v2: `https://api.linkedin.com/v2/ugcPosts`
- Setting: access_token, author (urn:li:person:{ID} atau urn:li:organization:{ID})

**e. Bluesky**
- Gunakan AT Protocol: `https://bsky.social/xrpc/com.atproto.repo.createRecord`
- Auth: identifier + password → dapat JWT
- Setting: handle (username.bsky.social), password (app password)
- Format: text dengan embed card (title + description + URL + thumbnail)

**f. Threads**
- Gunakan Threads API (bagian Meta): `https://graph.threads.net/v1.0/{USER_ID}/threads`
- Setting: user_id, access_token

**g. Pinterest**
- Gunakan Pinterest API v5: `https://api.pinterest.com/v5/pins`
- Setting: access_token, board_id

**h. Line Notify**
- Gunakan Line Notify: `https://notify-api.line.me/api/notify`
- Setting: notify_token (paling mudah dari semua platform Line)

**i. WhatsApp**
- Gunakan WhatsApp Business Cloud API
- Setting: phone_number_id, access_token, recipient_number
- Jika tidak punya API, sediakan opsi "generate wa.me link saja" untuk klik manual

Untuk semua platform:
- Simpan hasil ke `meow_share_logs`
- Template pesan bisa dikustomisasi dengan placeholder: {title}, {url}, {excerpt}, {tags}, {sitename}
- Tambahkan meta box di editor post: checkbox per platform "Share ke: [x] Telegram [ ] Facebook..."
- Hook ke `publish_post` untuk trigger auto-share

### 6. Tombol Share di Artikel
Buat class `MeowPack_ShareButtons`:
- Filter `the_content` untuk tambah tombol share sebelum/sesudah konten (setting: before/after/both/none)
- Shortcode `[meowpack_share]`
- Gutenberg block (gunakan `register_block_type` dengan render_callback)
- Platform yang ditampilkan: dapat dipilih di pengaturan
- Tiga style: icon-only, icon+text, pill-button
- Klik tombol dicatat di database (AJAX endpoint)
- CSS minimal, tidak import framework besar

### 7. View Counter
Buat class `MeowPack_ViewCounter`:
- Filter `the_content` untuk tampilkan view count (bisa di-disable per-post via custom field `_meow_hide_views`)
- Shortcode `[meowpack_views]` dan `[meowpack_trending days="7" count="5"]`
- Data diambil dari `meow_daily_stats` dengan query di-cache pakai Transients (TTL 1 jam)
- Format angka: < 1000 = angka biasa, >= 1000 = "1.2rb" (format Indonesia) atau "1.2K" (setting)

### 8. Widget Statistik Publik
Buat class `MeowPack_Stats_Widget` extends `WP_Widget`:
- Tampilkan kotak statistik untuk pengunjung: hari ini, bulan ini, total, total halaman dibaca
- Tampilan: angka besar dengan label kecil di bawah
- Warna dan style bisa dikustomisasi dari widget settings
- Shortcode `[meowpack_counter type="today"]` — type: today, month, total, pageviews, atau all (tampilkan semua 4 kotak)
- Data di-cache 5 menit (Transients)

### 9. Jetpack Importer
Buat class `MeowPack_Importer`:
- Deteksi apakah data Jetpack ada (cek opsi WordPress `jetpack_options` dan tabel `jetpack_*`)
- Method `import_from_jetpack()`: ambil data dari tabel Jetpack dan masukkan ke `meow_daily_stats`
- Method `import_from_csv($file_path)`: parse CSV dari export WordPress.com (format: date, post_id, views)
- Tampilkan halaman admin dengan progress (AJAX polling)
- Jangan duplikat data (cek UNIQUE constraint di database)

## STRUKTUR FILE YANG HARUS DIBUAT

```
meowpack/
├── meowpack.php
├── uninstall.php
├── includes/
│   ├── class-meowpack-database.php
│   ├── class-meowpack-core.php
│   ├── class-meowpack-bot-filter.php
│   ├── class-meowpack-tracker.php
│   ├── class-meowpack-stats.php
│   ├── class-meowpack-autoshare.php
│   ├── class-meowpack-share-buttons.php
│   ├── class-meowpack-view-counter.php
│   ├── class-meowpack-widget.php
│   └── class-meowpack-importer.php
├── admin/
│   ├── class-meowpack-admin.php
│   └── views/
│       ├── page-dashboard.php
│       ├── page-settings.php
│       ├── page-autoshare.php
│       └── page-importer.php
├── public/
│   ├── assets/
│   │   ├── meowpack-tracker.js
│   │   └── meowpack-public.css
├── languages/
│   └── meowpack-id_ID.pot
└── readme.txt
```

## STANDAR KODE
- Ikuti WordPress Coding Standards (WPCS)
- Semua input user di-sanitize dengan `sanitize_text_field()`, `absint()`, dll.
- Semua output di-escape dengan `esc_html()`, `esc_url()`, `esc_attr()`
- Semua query DB pakai `$wpdb->prepare()`
- Gunakan nonces untuk semua form dan AJAX request
- Buat README.md dengan instruksi instalasi dan konfigurasi
- Berikan komentar PHPDoc di setiap class dan method

## CATATAN PERFORMA
- JavaScript tracker harus non-blocking (defer atau async, gunakan fetch bukan XMLHttpRequest sync)
- Semua query agregasi statistik harus menggunakan index
- Gunakan WordPress Transients API untuk cache query berat (TTL minimal 5 menit)
- Jangan load CSS/JS admin di halaman publik, dan sebaliknya

## DELIVERABLE
Buat semua file plugin di atas secara lengkap dan fungsional. Mulai dari `meowpack.php` sebagai entry point, lalu buat satu per satu class sesuai urutan dependensi (Database → Core → BotFilter → Tracker → Stats → AutoShare → ShareButtons → ViewCounter → Widget → Importer → Admin). Terakhir buat file `readme.txt` dan `README.md`.
```

---

## CATATAN IMPLEMENTASI TAMBAHAN

### Saran Sosial Media untuk Website Pemerintahan Indonesia
Prioritaskan: Telegram (komunitas warga), Facebook (jangkauan luas), WhatsApp (paling populer di Indonesia), YouTube (jika punya video — tambahkan share button). Line kurang relevan dibanding yang lain untuk konteks pemerintahan.

### Keamanan Token Sosial Media
Enkripsi token menggunakan `wp_hash()` + `openssl_encrypt()` dengan key dari `AUTH_KEY` di wp-config.php.

### GDPR / Privasi Data Lokal
Hash IP menggunakan `hash('sha256', $ip . wp_salt())` — tidak bisa di-reverse, comply dengan prinsip data minimization.

### Kompatibilitas
- WordPress minimum: 5.9
- PHP minimum: 7.4
- MySQL minimum: 5.7 atau MariaDB 10.3
- Compatible dengan WooCommerce, Elementor, Divi, dan page builder populer lainnya
