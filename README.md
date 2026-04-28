# 🐱 MeowPack

Plugin WordPress all-in-one untuk analytics, tracking, auto-share, dan engagement tools.

## ✨ Fitur Utama

### 📊 Analytics & Tracking
- **View Counter** - Hitung pageviews dengan akurat
- **Stats Dashboard** - Visualisasi data dengan charts
- **Device & Browser Detection** - Analisis perangkat pengunjung
- **Location Tracking** - Tracking berdasarkan negara (GeoIP)
- **Author Stats** - Statistik per penulis
- **Click Tracker** - Track outbound links
- **Reading Time** - Estimasi waktu baca + scroll depth
- **UTM Campaign Tracking** - Track sumber traffic

### 📡 Auto Share (10 Platform!)
- ✅ **Telegram** - Bot API
- ✅ **Facebook** - Graph API
- ✅ **Instagram** - Business API
- ✅ **X (Twitter)** - OAuth 1.0a
- ✅ **LinkedIn** - UGC Posts API
- ✅ **Bluesky** - AT Protocol
- ✅ **Threads** - Meta Threads API
- ✅ **Pinterest** - Pins API
- ✅ **Line Notify** - Notify API
- ✅ **WhatsApp** - Business Cloud API

**📖 Panduan Setup:**
- [Panduan Lengkap Auto Share](PANDUAN-AUTOSHARE.md) - Tutorial detail setiap platform
- [Quick Start Guide](QUICK-START-AUTOSHARE.md) - Mulai cepat dalam 5 menit

### 🛡️ Security & Protection
- **AI Bot Manager** - Deteksi & filter bot AI (GPTBot, ClaudeBot, dll)
- **Anti-Hotlink** - Proteksi gambar dari hotlinking
- **Captcha** - Proteksi form (comments, login, register)
- **Content Moderation** - Filter kata kasar & spam
- **Malware Scanner** - Scan file berbahaya

### 🎨 Frontend Enhancement
- **Share Buttons** - Tombol share sosial media
- **Reactions** - Emoji reactions untuk post
- **Related Posts** - Artikel terkait
- **Table of Contents** - Daftar isi otomatis
- **Post Meta Bar** - Info views, reading time, dll

### ⚙️ Advanced Features
- **Widget** - Popular posts widget
- **Shortcodes** - Embed stats di konten
- **REST API** - Akses data via API
- **CSV Export** - Export data statistik
- **Cron Jobs** - Automated tasks
- **GitHub Auto-Update** - Update otomatis dari GitHub

## 🚀 Instalasi

1. Upload folder `meowpack` ke `/wp-content/plugins/`
2. Aktifkan plugin melalui menu 'Plugins' di WordPress
3. Buka **MeowPack → Pengaturan** untuk konfigurasi
4. Selesai!

## 📖 Dokumentasi

### Quick Start
- [Setup Auto Share dalam 5 Menit](QUICK-START-AUTOSHARE.md)
- [Panduan Lengkap Auto Share](PANDUAN-AUTOSHARE.md)

### Fitur Tracking
- View counter otomatis aktif setelah instalasi
- Data disimpan di database terpisah (tidak memperlambat WordPress)
- Agregasi harian untuk performa optimal

### Fitur Auto Share
1. Aktifkan di **MeowPack → Pengaturan**
2. Konfigurasi platform di **MeowPack → Auto Share**
3. Pilih platform per-post via meta box
4. Post otomatis di-share saat publish

### Fitur Security
- **AI Bot Manager**: Deteksi otomatis 50+ bot AI
- **Anti-Hotlink**: Proteksi gambar dengan .htaccess
- **Captcha**: Support math captcha & custom
- **Content Moderation**: 1000+ kata kasar built-in

## 🎯 Penggunaan

### Melihat Statistik
```
Dashboard → MeowPack → Statistik
```

### Setup Auto Share
```
Dashboard → MeowPack → Auto Share → Pilih Platform
```

### Manual Share Post
```
Posts → All Posts → Hover post → "🚀 Bagikan ke Sosmed"
```

### Shortcode
```php
// Tampilkan view count
[meowpack_views]

// Tampilkan reading time
[meowpack_reading_time]

// Tampilkan share buttons
[meowpack_share_buttons]
```

## 🔧 Konfigurasi

### Settings Penting

**Tracking:**
- Enable/disable tracking
- Exclude admins
- Data retention (hari)
- Post types yang di-track

**Auto Share:**
- Enable/disable auto share
- Pilih platform default
- Delay posting (jam)
- Template pesan

**Frontend:**
- Posisi share buttons
- Format angka views
- Show/hide reading time
- Enable related posts

## 🗄️ Database Tables

Plugin membuat 10 tabel:
- `meow_visits` - Raw visit data
- `meow_daily_stats` - Aggregated daily stats
- `meow_share_logs` - Auto-share history
- `meow_social_tokens` - Encrypted tokens
- `meow_settings` - Plugin settings
- `meow_click_logs` - Outbound click logs
- `meow_bot_rules` - Bot detection rules
- `meow_bot_logs` - Bot access logs
- `meow_content_rules` - Content moderation rules
- `meow_reactions` - Post reactions

## 🔐 Keamanan

### Token Encryption
Semua token sosial media dienkripsi menggunakan:
- **AES-256-CBC** encryption
- **WordPress AUTH_KEY** sebagai key
- Stored encrypted di database

### Data Privacy
- IP address di-hash untuk privacy
- User agent disimpan untuk bot detection
- Data bisa dihapus otomatis (retention policy)

## 🌐 REST API Endpoints

```
GET  /wp-json/meowpack/v1/stats
POST /wp-json/meowpack/v1/search (tracking)
POST /wp-json/meowpack/v1/engagement
POST /wp-json/meowpack/v1/click
POST /wp-json/meowpack/v1/share-click
GET  /wp-json/meowpack/v1/cron?token=xxx
```

## 🔄 Cron Jobs

### Daily Tasks (Otomatis)
- Agregasi data kemarin ke daily_stats
- Hapus data lama sesuai retention
- Retry failed auto-share
- Aggregate bot stats

### Manual Trigger
```
GET /wp-json/meowpack/v1/cron?token=YOUR_SECRET_TOKEN
```

## 🎨 Customization

### Custom CSS
```css
/* Share buttons */
.meowpack-share-buttons { }

/* View counter */
.meowpack-view-count { }

/* Reading time */
.meowpack-reading-time { }
```

### Hooks & Filters
```php
// Modify view count display
add_filter('meowpack_view_count_format', function($count) {
    return number_format($count) . ' views';
});

// Modify share platforms
add_filter('meowpack_share_platforms', function($platforms) {
    return array('telegram', 'twitter', 'facebook');
});
```

## 📊 Performance

### Optimizations
- ✅ Lightweight tracking (< 5KB JS)
- ✅ Async REST API calls
- ✅ Database indexes
- ✅ Daily aggregation
- ✅ Caching support
- ✅ Lazy loading

### Benchmarks
- Tracking overhead: < 10ms
- Database queries: 1-2 per pageview
- Memory usage: < 5MB
- JS bundle size: 4.2KB (minified)

## 🐛 Troubleshooting

### Auto Share Tidak Bekerja
1. Cek **MeowPack → Pengaturan** - pastikan "Enable Auto Share" aktif
2. Cek **MeowPack → Auto Share** - pastikan token sudah diisi
3. Klik "🚀 Kirim Pesan Tes" untuk test koneksi
4. Cek log di meta box post editor

### View Counter Tidak Muncul
1. Cek **MeowPack → Pengaturan** - pastikan "Enable View Counter" aktif
2. Clear cache (jika pakai caching plugin)
3. Cek console browser untuk error JS

### Bot Detection Terlalu Agresif
1. Buka **MeowPack → AI Bot Manager**
2. Edit rule yang terlalu strict
3. Atau disable bot detection sementara

## 🔄 Update

### Auto-Update dari GitHub
Plugin support auto-update dari GitHub repository:
1. Push update ke GitHub
2. Plugin akan cek update otomatis
3. Notifikasi muncul di WordPress admin
4. Klik "Update Now"

### Manual Update
1. Download versi terbaru
2. Deactivate plugin lama
3. Delete folder lama
4. Upload folder baru
5. Activate plugin

## 📝 Changelog

### v2.2.0 (Current)
- ✨ Auto Share: 10 platform sosial media
- ✨ AI Bot Manager: Deteksi 50+ bot AI
- ✨ Content Moderation: Filter konten
- ✨ Malware Scanner: Scan file berbahaya
- 🔧 Performance improvements
- 🐛 Bug fixes

### v2.1.0
- ✨ Click Tracker
- ✨ Reading Time & Scroll Depth
- ✨ Device & Location Stats
- ✨ Author Stats

### v2.0.0
- ✨ Complete rewrite
- ✨ REST API
- ✨ Modern dashboard
- ✨ Better performance

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## 📄 License

GPL v2 or later

## 👨‍💻 Author

**MeowPack Team**
- Website: [meowpack.dev](https://meowpack.dev)
- GitHub: [@meowpack](https://github.com/meowpack)

## 💖 Support

Jika plugin ini membantu, consider:
- ⭐ Star di GitHub
- 📝 Review di WordPress.org
- ☕ Buy me a coffee
- 🐛 Report bugs
- 💡 Suggest features

---

**Dibuat dengan ❤️ untuk WordPress Community**
