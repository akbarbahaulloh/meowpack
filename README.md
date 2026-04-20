# MeowPack — WordPress Plugin

> Plugin WordPress ringan pengganti Jetpack. Statistik, auto-share ke 9 platform sosial media, view counter — semua data tersimpan lokal tanpa ketergantungan pihak ketiga.

**Author:** Akbar Bahaulloh  
**Version:** 1.0.0  
**License:** GPL-2.0-or-later  
**Requires:** WordPress 5.9+, PHP 7.4+, MySQL 5.7+

---

## Fitur

| Modul | Deskripsi |
|-------|-----------|
| 🔍 **Bot Filter** | ~50 UA bot, IP rate limit, filter admin |
| 📊 **Statistik** | Grafik 30 hari, top artikel, sumber kunjungan |
| 🎯 **Tracker** | Non-blocking JS, sendBeacon, UTM support |
| 📡 **Auto Share** | 9 platform: Telegram, FB, X, LinkedIn, Bluesky, Threads, Pinterest, Line, WA |
| 🔘 **Tombol Share** | Shortcode, Gutenberg block, 3 gaya |
| 👁 **View Counter** | Counter per-post, trending widget |
| 📦 **Widget Publik** | Counter hari ini/bulan/total untuk sidebar |
| 📥 **Import Jetpack** | Migrasi dari Jetpack DB atau CSV |

---

## Instalasi

1. Salin folder `meowpack/` ke `/wp-content/plugins/`
2. Aktifkan melalui **Plugins → Plugin yang Terinstal**
3. Buka **MeowPack → Pengaturan** untuk konfigurasi

---

## Struktur File

```
meowpack/
├── meowpack.php                         ← Entry point plugin
├── uninstall.php                        ← Cleanup saat uninstall
├── readme.txt                           ← WordPress.org readme
├── includes/
│   ├── class-meowpack-database.php      ← DB schema & settings
│   ├── class-meowpack-core.php          ← Singleton bootstrap & REST routes
│   ├── class-meowpack-bot-filter.php    ← Deteksi bot, hash IP, parse sumber
│   ├── class-meowpack-tracker.php       ← Inject JS + REST endpoint tracking
│   ├── class-meowpack-stats.php         ← Query & aggregasi statistik
│   ├── class-meowpack-autoshare.php     ← Auto share 9 platform
│   ├── class-meowpack-share-buttons.php ← Tombol share frontend
│   ├── class-meowpack-view-counter.php  ← View count & trending
│   ├── class-meowpack-widget.php        ← WP_Widget statistik publik
│   └── class-meowpack-importer.php      ← Import dari Jetpack/CSV
├── admin/
│   ├── class-meowpack-admin.php         ← Registrasi menu & form handler
│   └── views/
│       ├── page-dashboard.php           ← Dashboard dengan grafik
│       ├── page-settings.php            ← Pengaturan umum
│       ├── page-autoshare.php           ← Konfigurasi token platform
│       └── page-importer.php            ← Import Jetpack/CSV
├── public/
│   └── assets/
│       ├── meowpack-tracker.js          ← Frontend tracker < 3KB
│       └── meowpack-public.css          ← CSS tombol share & widget
├── languages/
│   └── meowpack-id_ID.pot              ← Template terjemahan
└── README.md
```

---

## Database Tables

| Tabel | Fungsi |
|-------|--------|
| `{prefix}meow_visits` | Data kunjungan mentah (30 hari) |
| `{prefix}meow_daily_stats` | Agregasi harian (permanen) |
| `{prefix}meow_share_logs` | Log auto-share & klik share |
| `{prefix}meow_social_tokens` | Token platform (terenkripsi AES-256) |
| `{prefix}meow_settings` | Key-value settings plugin |

---

## Shortcodes

```
[meowpack_share]                          ← Tombol share
[meowpack_views post_id="123"]           ← View count artikel
[meowpack_trending days="7" count="5"]   ← Artikel populer
[meowpack_counter type="all"]            ← Counter publik (today/month/total/pageviews)
```

---

## REST API Endpoints

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| `POST` | `/wp-json/meowpack/v1/track` | Kirim data kunjungan |
| `GET`  | `/wp-json/meowpack/v1/stats` | Ambil data statistik (admin) |
| `POST` | `/wp-json/meowpack/v1/share-click` | Catat klik tombol share |
| `POST` | `/wp-json/meowpack/v1/import` | Jalankan import batch |

---

## Konfigurasi Auto Share

Buka **MeowPack → Auto Share**, pilih platform, masuk token:

### Telegram
- Bot Token dari [@BotFather](https://t.me/botfather)
- Chat ID (channel `@nama` atau grup `-100xxxxxxx`)

### Facebook
- Page Access Token (long-lived) dari Meta Developer Console
- Page ID halaman Facebook

### X (Twitter)
- API Key, API Secret, Access Token, Access Token Secret dari [developer.twitter.com](https://developer.twitter.com)

### LinkedIn
- Access Token dari LinkedIn Developer Portal
- Author URN: `urn:li:person:xxxxx` atau `urn:li:organization:xxxxx`

### Bluesky
- Handle: `username.bsky.social`
- App Password (buat di Settings → App Passwords)

### Threads
- User ID & Access Token dari Meta Threads API

### Pinterest
- Access Token dari Pinterest Developer Console
- Board ID tujuan

### Line Notify
- Notify Token dari [notify-bot.line.me](https://notify-bot.line.me)

### WhatsApp
- Phone Number ID, Access Token dari Meta Business Suite
- Nomor penerima dengan kode negara (contoh: `628xxxxxxxxx`)

---

## Performa

- **JS Tracker:** < 3KB, menggunakan `sendBeacon` API (non-blocking)
- **Caching:** Semua query berat di-cache dengan WordPress Transients (5 menit–1 jam)
- **Database:** Composite index di semua kolom yang sering di-query
- **Agregasi:** Data mentah diagregasi setiap hari via WP Cron, lalu dihapus setelah 30 hari

---

## Privasi & GDPR

- IP address di-hash (SHA-256 + WordPress salt) — tidak dapat di-reverse
- Tidak ada data yang dikirim ke server pihak ketiga (kecuali saat auto-share ke platform tujuan)
- Token sosial media dienkripsi menggunakan AES-256-CBC dengan key dari `AUTH_KEY` WordPress

---

## Kompatibilitas

- ✅ WordPress 5.9+
- ✅ PHP 7.4+
- ✅ MySQL 5.7+ / MariaDB 10.3+
- ✅ WooCommerce, Elementor, Divi, Gutenberg
- ✅ Caching plugins (AJAX-based tracking bypass cache)
- ✅ Cloudflare (deteksi IP via `CF-Connecting-IP`)

---

## Lisensi

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)  
© 2025 Akbar Bahaulloh
