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
- Daftar bot umum (crawlers) bisa dikustomisasi admin
- **[BARU]** Daftar terpisah AI bots: GPTBot, ClaudeBot, PerplexityBot, CCBot, Google-Extended, Amazonbot, dll.
- **[BARU]** Opsi blokir per-bot (return 403) atau hanya catat di statistik
- **[BARU]** Dashboard statistik bot: bot apa saja yang paling sering berkunjung, frekuensi, halaman yang dikunjungi

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
- **[BARU]** Statistik jenis device: Mobile / Tablet / Desktop (pie chart)
- **[BARU]** Statistik browser: Chrome, Firefox, Safari, Edge, dll.
- **[BARU]** Statistik sistem operasi: Windows, Android, iOS, macOS, dll.
- **[BARU]** Statistik lokasi: negara → wilayah/provinsi → kota (drill-down)
- **[BARU]** Statistik per penulis (author): artikel dan views per author
- **[BARU]** Halaman paling banyak dilihat (detailed, dengan filter periode)
- **[BARU]** URL keluar yang paling banyak diklik (outbound link tracking)
- **[BARU]** Rata-rata waktu baca per halaman (reading time heatmap)
- **[BARU]** Statistik bot visitor: bot name, frekuensi, halaman dikunjungi

#### 4. Lacak Sumber Kunjungan
- Parse HTTP Referrer header
- UTM parameter support (utm_source, utm_medium, utm_campaign)
- Kategori otomatis: Direct (tidak ada referrer), Search (dari search engine), Social (dari sosmed), Referral (dari website lain), Email (dari email client)
- **[DIPERBARUI]** Deteksi lokasi via IP: negara + wilayah/provinsi + kota (gunakan MaxMind GeoLite2-City, bukan hanya GeoLite2-Country)
- **[BARU]** Deteksi jenis device dari User-Agent: Mobile / Tablet / Desktop
- **[BARU]** Deteksi browser dan sistem operasi dari User-Agent (parsing library ringan, tidak pakai API)
- **[BARU]** Catat author_id dari post yang dikunjungi untuk statistik per penulis

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

#### 10. [BARU] Statistik Device, Browser & OS
Buat class `MeowPack_DeviceDetector`:
- Parse User-Agent string menggunakan library ringan PHP (tanpa dependency besar)
- Kategori device: **Mobile**, **Tablet**, **Desktop**, **Bot**
- Deteksi browser: Chrome, Firefox, Safari, Edge, Opera, Samsung Browser, dll.
- Deteksi OS: Windows, macOS, Android, iOS, Linux, Chrome OS, dll.
- Simpan ke kolom tambahan di `meow_visits`: `device_type`, `browser`, `os`
- Tampilkan di dashboard: 3 donut chart (device / browser / OS)
- Filter statistik berdasarkan device (contoh: lihat pageview hanya dari mobile)

#### 11. [BARU] Statistik Lokasi (Negara, Wilayah, Kota)
Perbarui modul GeoIP:
- Gunakan **MaxMind GeoLite2-City** (bukan hanya Country) — file database `.mmdb` disimpan lokal
- Kolom di `meow_visits`: `country_code`, `region` (provinsi/state), `city`
- Dashboard: tabel top countries, top regions, top cities
- Peta dunia interaktif opsional (gunakan library ringan seperti `jsvectormap`)
- Auto-download/update file GeoIP2-City via cron bulanan (dari MaxMind jika punya lisensi; fallback: DB111-City gratis dari db-ip.com)

#### 12. [BARU] Pelacakan URL Keluar (Outbound Click Tracking)
Buat class `MeowPack_ClickTracker`:
- Inject JavaScript kecil yang menangkap klik pada link eksternal (link dengan domain berbeda)
- Kirim data via AJAX ke endpoint `wp-json/meowpack/v1/click`: `url`, `post_id`, `anchor_text`
- Simpan ke tabel `meow_click_logs`: `post_id`, `url`, `anchor_text`, `click_count`, `last_clicked`
- Dashboard: Top 20 URL keluar yang paling banyak diklik
- Filter: per halaman/artikel, per periode waktu
- Opsi aktifkan/nonaktifkan per post type
- **Ide bonus**: tandai link afiliasi (URL mengandung `/ref=`, `?aff=`, dll.) secara terpisah

#### 13. [BARU] Statistik Per Penulis (Author Stats)
- Tambahkan kolom `author_id` di `meow_visits` (ambil dari `$post->post_author`)
- Tambahkan kolom `author_id` di agregasi `meow_daily_stats`
- Dashboard sub-halaman "Per Penulis": daftar author dengan total views, unique visitors, artikel terpopuler
- Shortcode `[meowpack_author_stats author_id="1"]` untuk tampilan publik profil penulis
- Kompatibel dengan multi-author blog

#### 14. [BARU] Waktu Baca & Scroll Depth
Buat class `MeowPack_ReadingTime`:
- **Estimasi waktu baca** (calculated): hitung dari jumlah kata artikel → tampilkan "5 menit baca" di bawah judul
  - Formula: jumlah kata ÷ 200 (rata-rata WPM orang Indonesia)
  - Shortcode `[meowpack_reading_time]` atau otomatis via filter `the_content`
- **Waktu aktual di halaman**: JavaScript kirim sinyal every 30 detik selama user aktif di halaman
  - Simpan ke `meow_visits`: kolom `time_on_page` (dalam detik)
  - Dashboard: rata-rata waktu baca per artikel (sorted: artikel dengan engagement tinggi)
- **Scroll depth**: rekam seberapa jauh user scroll (25%, 50%, 75%, 100%)
  - Simpan ke `meow_visits`: kolom `scroll_depth` (0-100)
  - Dashboard: "artikel dengan scroll depth rendah" = indikator konten kurang menarik
- Semua ini non-blocking: gunakan `requestIdleCallback` + beacon API

#### 15. [BARU] AI Bot Manager
Buat class `MeowPack_AIBotManager`:

**15a. Daftar AI Bot**
Daftar default AI bots yang dikenali (bisa dikustomisasi admin):
| Bot Name | User-Agent String |
|---|---|
| OpenAI GPTBot | `GPTBot` |
| OpenAI ChatGPT-User | `ChatGPT-User` |
| Anthropic ClaudeBot | `ClaudeBot`, `anthropic-ai` |
| Google Gemini | `Google-Extended` |
| Perplexity | `PerplexityBot` |
| Common Crawl | `CCBot` |
| Amazon Alexa | `Amazonbot` |
| Meta AI | `FacebookBot` |
| Apple Applebot-Extended | `Applebot-Extended` |
| Bytedance | `Bytespider` |
| Diffbot | `Diffbot` |
| Cohere | `cohere-ai` |
| You.com | `YouBot` |
| Timpibot | `Timpibot` |
| Omgili | `omgilibot` |
| DataForSEO | `DataForSeoBot` |
| Scrapy | `Scrapy` |

**15b. Mode Per-Bot**
Untuk setiap bot, admin bisa memilih:
- `allow` — izinkan, catat di statistik saja
- `stats_only` — sama seperti allow tapi tandai di laporan sebagai "AI bot"
- `block` — return HTTP 403 Forbidden
- `block_redirect` — redirect ke halaman tertentu (misal: halaman kebijakan penggunaan AI)

**15c. Dashboard Statistik Bot**
- Tabel: nama bot, jumlah kunjungan hari ini/bulan ini/all-time, halaman yang paling sering dikunjungi
- Grafik tren bot traffic vs human traffic
- Tombol cepat "Blokir Bot Ini" langsung dari tabel statistik
- Log detail kunjungan bot: IP (hash), user-agent, halaman, waktu

**15d. Implementasi Blocking**
- Hook ke `init` — cek user-agent di awal request, sebelum WordPress load penuh
- Untuk blocking: return `wp_die('403 Forbidden', 'Access Denied', ['response' => 403])`
- Opsi tambahan: update `robots.txt` via filter `robots_txt` untuk Disallow bot tertentu
- Jangan blokir bot search engine (Google, Bing) secara default — hanya AI scraper

#### 16. [BARU] Anti-Hotlink Protection
Buat class `MeowPack_AntiHotlink`:

**Cara Kerja:**
- Hook ke request gambar (jpg/jpeg/png/gif/webp/svg)
- Cek HTTP Referer header:
  - Jika Referer kosong → **izinkan** (download langsung, bookmarks, dll.)
  - Jika Referer = domain sendiri → **izinkan**
  - Jika Referer = domain lain → **blokir** (hotlink)

**Metode Implementasi (pilih salah satu, bisa kombinasi):**
1. **PHP handler** via `add_rewrite_rule` + WordPress endpoint — lebih portable
2. **Modifikasi .htaccess** via WordPress API (`insert_with_markers()`) — lebih efisien di Apache
3. **Nginx config snippet** — tampilkan di settings untuk server Nginx

**Opsi Admin:**
- Whitelist domain tertentu (misal: izinkan Google Images, Bing, Facebook crawler)
- Pilih ekstensi file yang dilindungi (default: jpg, jpeg, png, gif, webp)
- Pilih respons saat terdeteksi hotlink:
  - **Gambar placeholder** ("No Hotlinking" image yang bisa dikustomisasi)
  - **HTTP 403**
  - **Redirect ke URL tertentu**
- Statistik: berapa kali hotlink diblokir, dari domain mana
- Jangan lindungi file di direktori lain selain `/wp-content/uploads/`

**Catatan Penting:**
- Pastikan tidak blokir media dari CDN sendiri jika menggunakan CDN
- Whitelist otomatis: `*.googleusercontent.com`, `*.fbcdn.net` (Facebook preview), `*.whatsapp.net`

#### 17. [BARU] Simple Captcha (Anti-Spam)
Buat class `MeowPack_Captcha`:

**Jenis Captcha yang Tersedia:**
1. **Math Captcha** (default) — Penjumlahan/pengurangan angka sederhana, contoh: "Berapa hasil 4 + 7?"
2. **Text Captcha** — Pertanyaan logis sederhana, contoh: "Warna langit di siang hari?"
3. **Honeypot field** — Field tersembunyi via CSS, bot mengisi → ditolak (tidak terlihat user)

**Lokasi Penerapan (bisa dipilih di settings):**
- ✅ Form komentar WordPress
- ✅ Form login WordPress
- ✅ Form register WordPress
- ✅ Form lupa password
- ✅ Form WooCommerce checkout (jika WooCommerce aktif)
- ✅ Integrasi Contact Form 7 (via filter hook)

**Implementasi Math Captcha:**
```php
// Generate soal
$a = rand(1, 20);
$b = rand(1, 20);
$op = (rand(0, 1)) ? '+' : '-';
$answer = ($op === '+') ? ($a + $b) : ($a - $b);
set_transient('meow_captcha_' . session_id(), $answer, 600); // 10 menit
// Tampilkan: "Berapa 12 + 5? [___]"
```

**Apakah Math Captcha Aman?**
> ✅ **Cukup aman untuk spam biasa** — efektif memblokir spam bot generik
> ⚠️ **Tidak aman dari bot canggih** — AI bisa membacanya dengan mudah
> 💡 **Rekomendasi**: Kombinasikan Math Captcha + Honeypot untuk perlindungan berlapis tanpa mengorbankan UX
> 🔒 **Jika butuh keamanan tinggi**: Tambahkan opsi integrasi **Cloudflare Turnstile** (gratis, privacy-friendly, tidak ada "pilih semua traffic light")

**Perbandingan Captcha:**
| Metode | Kemudahan User | Keamanan | Privacy | Eksternal? |
|---|---|---|---|---|
| Math Captcha | ⭐⭐⭐⭐⭐ | ⭐⭐ | ✅ Lokal | ❌ |
| Honeypot | ⭐⭐⭐⭐⭐ (tak terlihat) | ⭐⭐⭐ | ✅ Lokal | ❌ |
| Math + Honeypot | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ✅ Lokal | ❌ |
| Cloudflare Turnstile | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ CF | ✅ |
| reCAPTCHA v3 | ⭐⭐⭐⭐⭐ (invisible) | ⭐⭐⭐⭐ | ❌ Google | ✅ |

#### 18. [BARU] Filter Konten Berbahaya (Content Moderation)
Buat class `MeowPack_Content_Moderation`:

**Apa itu?**
Sistem deteksi kata kunci berbahaya di komentar, konten post, judul, dan username. Admin bisa membuat "kamus" kata kunci per kategori dan menentukan aksi otomatis saat terdeteksi.

**Kategori Bawaan (seed keywords Bahasa Indonesia):**
| Kategori | Contoh Kata Kunci |
|---|---|
| 🎰 Judi / Gambling | slot, judi online, togel, bet, casino, poker, bandar, taruhan, gacor, maxwin |
| 💊 Obat Terlarang | obat penggugur kandungan, aborsi, narkoba, sabu, ganja, tramadol, pil bius |
| 🔞 Pornografi | (daftar tersembunyi — dikelola di admin) |
| 🩸 Kekerasan | bom, bunuh, teror, jihad (konteks kekerasan), ancam |
| 💸 Penipuan / Scam | pinjol ilegal, investasi bodong, money game, arisan online, transfer dulu |
| ⚠️ SARA | (konfigurasi sensitif — kosong by default, admin isi sendiri) |
| 📦 Kustom | kata kunci tambahan buatan admin |

**Target Pemindaian (Otomatis):**
- ✅ **Komentar baru** — paling umum, default aktif
- ✅ **Username saat registrasi** — cegah username seperti "slot_gacor_2024"
- ⬜ **Konten post saat dipublish** — scan isi artikel baru (off by default, berat)
- ⬜ **Judul post** — scan judul artikel
- ⬜ **Excerpt / ringkasan**

**Scanner Manual (Pemindaian Menyeluruh On-Demand):**
Jika website pernah diretas/disusupi (injected SEO spam), fitur ini memungkinkan admin melakukan pemindaian menyeluruh ke database lama. Berjalan via AJAX (batch processing) agar tidak timeout:
1. **Pos & Halaman (Posts & Pages)**: Scan `post_title` dan `post_content`.
2. **Komentar Lama**: Scan seluruh isi tabel komentar.
3. **Menu Navigasi**: Scan tipe post `nav_menu_item`.
4. **Gambar / Attachment**: Scan teks alt, deskripsi, dan judul attachment gambar.
5. **Widget (Sidebar/Footer)**: Scan tabel `wp_options` untuk widget text, HTML, dan block.

**Aksi saat Terdeteksi:**
- `hold` — tahan untuk review manual (komentar masuk ke antrian moderasi)
- `block` — tolak langsung (komentar gagal, konten tidak dipublish)
- `flag` — izinkan lewat tapi tandai dan notifikasi admin via email
- `replace` — ganti kata dengan *** (sensor otomatis)

**Fitur Admin:**
- Tabel kamus: per kategori, bisa tambah/edit/hapus keyword
- Import/Export kamus (format CSV atau JSON)
- Log deteksi: kapan, di mana (post ID), kata apa yang ditemukan, konten lengkap, aksi yang diambil
- Statistik: kategori apa yang paling banyak terdeteksi
- Mode "Strict" vs "Loose" (strict: substring match; loose: hanya whole-word match)
- Opsi false-positive handling: whitelist kata tertentu (contoh: "slot" di konteks elektronik)

**Database:**
```sql
-- Kamus kata kunci moderat
CREATE TABLE meow_content_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  keyword VARCHAR(200) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'custom',
  action VARCHAR(20) NOT NULL DEFAULT 'hold',
  match_mode VARCHAR(10) DEFAULT 'substring',  -- substring|word
  is_active TINYINT DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Log deteksi
CREATE TABLE meow_content_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  context VARCHAR(30) NOT NULL,  -- comment|post|username
  object_id BIGINT DEFAULT 0,    -- comment_id atau post_id
  matched_keyword VARCHAR(200),
  matched_category VARCHAR(50),
  action_taken VARCHAR(20),
  content_excerpt TEXT,          -- 200 karakter pertama konten
  INDEX idx_date (detected_at),
  INDEX idx_context (context),
  INDEX idx_category (matched_category)
);
```

**Implementasi Komentar:**
- Hook `preprocess_comment` — scan sebelum komentar disimpan
- Jika `hold`: set `comment_approved = 0` (masuk antrian)
- Jika `block`: `wp_die()` dengan pesan yang sopan
- Jika `replace`: ganti kata dengan `***` lalu simpan

---

### Struktur Database

```sql
-- Kunjungan mentah (disimpan 30 hari, lalu diagregasi)
-- [DIPERBARUI] Ditambah kolom baru untuk fitur statistik lanjutan
CREATE TABLE meow_visits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL DEFAULT 0,
  author_id BIGINT DEFAULT 0,            -- [BARU] ID penulis artikel
  visit_date DATE NOT NULL,
  visit_hour TINYINT NOT NULL,
  ip_hash VARCHAR(64) NOT NULL,          -- hash SHA256(IP + salt)
  source_type VARCHAR(20),               -- direct/search/social/referral/email
  source_name VARCHAR(100),              -- google, facebook, dll.
  utm_source VARCHAR(100),
  utm_medium VARCHAR(100),
  utm_campaign VARCHAR(100),
  country_code CHAR(2),
  region VARCHAR(100),                   -- [BARU] provinsi/state
  city VARCHAR(100),                     -- [BARU] kota
  device_type VARCHAR(20),               -- [BARU] mobile/tablet/desktop
  browser VARCHAR(50),                   -- [BARU] Chrome, Firefox, Safari, dll.
  os VARCHAR(50),                        -- [BARU] Windows, Android, iOS, dll.
  time_on_page SMALLINT UNSIGNED,        -- [BARU] detik aktif di halaman
  scroll_depth TINYINT UNSIGNED,         -- [BARU] 0-100 persen scroll
  is_bot TINYINT DEFAULT 0,
  bot_name VARCHAR(100),                 -- [BARU] nama bot jika is_bot=1
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_date (post_id, visit_date),
  INDEX idx_date (visit_date),
  INDEX idx_author (author_id),          -- [BARU]
  INDEX idx_device (device_type),        -- [BARU]
  INDEX idx_country (country_code)       -- [BARU]
);

-- Agregasi harian (dipertahankan selamanya)
-- [DIPERBARUI] Ditambah kolom author dan device
CREATE TABLE meow_daily_stats (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL,
  post_id BIGINT NOT NULL DEFAULT 0,     -- 0 = site-wide
  author_id BIGINT DEFAULT 0,            -- [BARU] 0 = site-wide
  unique_visitors INT DEFAULT 0,
  total_views INT DEFAULT 0,
  source_direct INT DEFAULT 0,
  source_search INT DEFAULT 0,
  source_social INT DEFAULT 0,
  source_referral INT DEFAULT 0,
  mobile_views INT DEFAULT 0,            -- [BARU]
  tablet_views INT DEFAULT 0,            -- [BARU]
  desktop_views INT DEFAULT 0,           -- [BARU]
  avg_time_on_page SMALLINT DEFAULT 0,   -- [BARU] rata-rata detik
  avg_scroll_depth TINYINT DEFAULT 0,    -- [BARU] rata-rata scroll %
  UNIQUE KEY unique_date_post (stat_date, post_id),
  INDEX idx_date (stat_date)
);

-- [BARU] Log klik URL keluar (outbound links)
CREATE TABLE meow_click_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL DEFAULT 0,
  url TEXT NOT NULL,
  url_hash VARCHAR(64) NOT NULL,         -- hash URL untuk indexing
  anchor_text VARCHAR(255),
  click_count INT DEFAULT 1,
  last_clicked DATETIME,
  UNIQUE KEY unique_url_post (url_hash, post_id),
  INDEX idx_post (post_id),
  INDEX idx_click_count (click_count)
);

-- [BARU] Statistik dan aturan bot
CREATE TABLE meow_bot_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bot_name VARCHAR(100) NOT NULL,        -- nama display bot
  user_agent_pattern VARCHAR(200) NOT NULL, -- string untuk deteksi
  bot_type VARCHAR(20) DEFAULT 'crawler', -- crawler/ai_bot/spam
  action VARCHAR(20) DEFAULT 'allow',    -- allow/stats_only/block/block_redirect
  redirect_url VARCHAR(500),             -- jika action=block_redirect
  is_active TINYINT DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_bot (user_agent_pattern)
);

-- [BARU] Statistik kunjungan bot (agregasi harian)
CREATE TABLE meow_bot_stats (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL,
  bot_name VARCHAR(100) NOT NULL,
  bot_type VARCHAR(20),
  visit_count INT DEFAULT 0,
  unique_ips INT DEFAULT 0,              -- jumlah IP unik
  top_pages TEXT,                        -- JSON: array URL paling banyak dikunjungi
  UNIQUE KEY unique_date_bot (stat_date, bot_name),
  INDEX idx_date (stat_date)
);

-- [BARU] Log hotlink yang diblokir
CREATE TABLE meow_hotlink_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  blocked_url VARCHAR(500),              -- URL gambar yang dicoba di-hotlink
  referrer_domain VARCHAR(200),          -- domain yang mencoba hotlink
  blocked_date DATE NOT NULL,
  block_count INT DEFAULT 1,
  last_blocked DATETIME,
  UNIQUE KEY unique_url_domain (blocked_url(200), referrer_domain),
  INDEX idx_date (blocked_date)
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
│   ├── class-meowpack-database.php
│   ├── class-meowpack-core.php
│   ├── class-meowpack-bot-filter.php      (crawler bots)
│   ├── class-meowpack-ai-bot-manager.php  [BARU] AI bot blocking & stats
│   ├── class-meowpack-tracker.php
│   ├── class-meowpack-device-detector.php [BARU] device/browser/OS parser
│   ├── class-meowpack-stats.php
│   ├── class-meowpack-click-tracker.php   [BARU] outbound link tracking
│   ├── class-meowpack-autoshare.php
│   ├── class-meowpack-share-buttons.php
│   ├── class-meowpack-view-counter.php
│   ├── class-meowpack-reading-time.php    [BARU] reading time & scroll depth
│   ├── class-meowpack-widget.php
│   ├── class-meowpack-anti-hotlink.php    [BARU] hotlink protection
│   ├── class-meowpack-captcha.php         [BARU] simple captcha
│   └── class-meowpack-importer.php
│   └── data/
│       ├── geoip/                         [BARU] folder database GeoLite2-City.mmdb
│       └── bot-list.php                   [BARU] daftar default AI bots
├── admin/
│   ├── class-meowpack-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── page-device-stats.php          [BARU]
│   │   ├── page-location-stats.php        [BARU]
│   │   ├── page-author-stats.php          [BARU]
│   │   ├── page-click-tracker.php         [BARU]
│   │   ├── page-bot-manager.php           [BARU]
│   │   ├── page-hotlink.php               [BARU]
│   │   ├── settings-general.php
│   │   ├── settings-autoshare.php
│   │   ├── settings-captcha.php           [BARU]
│   │   ├── settings-widgets.php
│   │   └── importer.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
├── public/
│   ├── class-meowpack-public.php
│   └── assets/
│       ├── meowpack-tracker.js   (< 5KB, async — ditambah reading time & click tracker)
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
