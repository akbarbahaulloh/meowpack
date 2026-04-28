# 🔧 Troubleshooting Auto Share

Panduan mengatasi masalah umum pada fitur Auto Share MeowPack.

---

## 📋 Daftar Masalah Umum

- [Auto Share Tidak Bekerja](#-auto-share-tidak-bekerja)
- [Error 401 Unauthorized](#-error-401-unauthorized)
- [Error 403 Forbidden](#-error-403-forbidden)
- [Error 400 Bad Request](#-error-400-bad-request)
- [Error 429 Rate Limit](#-error-429-rate-limit)
- [Instagram Gagal Share](#-instagram-gagal-share)
- [Twitter Terpotong](#-twitter-terpotong)
- [Facebook Token Expired](#-facebook-token-expired)
- [Telegram Bot Tidak Respon](#-telegram-bot-tidak-respon)
- [WhatsApp Tidak Terkirim](#-whatsapp-tidak-terkirim)

---

## 🚫 Auto Share Tidak Bekerja

### Gejala:
- Post baru tidak otomatis di-share
- Tidak ada log di meta box
- Tombol manual share tidak muncul

### Solusi:

#### 1. Cek Pengaturan Global
```
Dashboard → MeowPack → Pengaturan
```
- ✅ Pastikan **"Enable Auto Share"** dicentang
- ✅ Klik **Simpan Pengaturan**

#### 2. Cek Token Platform
```
Dashboard → MeowPack → Auto Share
```
- ✅ Pilih tab platform yang ingin digunakan
- ✅ Pastikan token sudah diisi
- ✅ Klik **"🚀 Kirim Pesan Tes"**
- ✅ Jika gagal, regenerate token

#### 3. Cek Platform Selection
Saat edit post:
- ✅ Scroll ke sidebar kanan
- ✅ Cari meta box **"MeowPack — Auto Share"**
- ✅ Centang platform yang diinginkan
- ✅ Update post

#### 4. Cek Log Error
Di meta box post editor:
- ✅ Lihat **"Riwayat Share"**
- ✅ Cek status: ✅ success, ❌ failed, ⏳ pending
- ✅ Jika failed, lihat response code

#### 5. Cek WordPress Cron
```php
// Tambahkan di wp-config.php untuk debug
define('ALTERNATE_WP_CRON', true);
```

---

## 🔐 Error 401 Unauthorized

### Gejala:
```
Response Code: 401
Status: failed
```

### Penyebab:
- Token invalid atau expired
- Token tidak punya permission yang cukup

### Solusi:

#### Telegram:
1. Cek bot token masih valid
2. Test dengan curl:
   ```bash
   curl https://api.telegram.org/bot<TOKEN>/getMe
   ```
3. Jika error, generate token baru dari @BotFather

#### Facebook/Instagram:
1. Token expired (berlaku 60 hari)
2. Generate token baru:
   - Buka [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
   - Generate new token
   - Extend token di [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)
3. Update token di MeowPack

#### Twitter:
1. Cek semua 4 credentials benar
2. Regenerate Access Token di [Developer Portal](https://developer.twitter.com/en/portal/dashboard)
3. Pastikan app permissions = **Read and Write**

#### LinkedIn:
1. Token expired (60 hari)
2. Regenerate OAuth token
3. Pastikan scope `w_member_social` aktif

---

## 🚷 Error 403 Forbidden

### Gejala:
```
Response Code: 403
Status: failed
```

### Penyebab:
- Tidak punya permission untuk action tersebut
- API access belum di-approve
- Rate limit atau spam detection

### Solusi:

#### Facebook/Instagram:
1. Cek Page Role:
   - Anda harus **Admin** atau **Editor** page
2. Cek App Review:
   - Buka Facebook App Dashboard
   - Pastikan **"Share on Facebook"** approved
3. Cek Page Access:
   - Token harus untuk page yang benar
   - Bukan personal profile

#### Twitter:
1. Cek App Permissions:
   - Harus **Read and Write**
   - Bukan **Read-only**
2. Regenerate token setelah ubah permissions

#### Pinterest:
1. Cek Board Ownership:
   - Anda harus owner board
   - Bukan collaborator
2. Cek API Access:
   - App harus approved untuk **Pins API**

---

## ⚠️ Error 400 Bad Request

### Gejala:
```
Response Code: 400
Status: failed
```

### Penyebab:
- Format data salah
- Required field kosong
- URL tidak valid

### Solusi:

#### Instagram:
**Paling Sering: Tidak Ada Featured Image**
1. Edit post
2. Set **Featured Image** (gambar utama)
3. Gambar harus:
   - ✅ Format: JPG, PNG
   - ✅ Ukuran: Min 320x320px
   - ✅ URL: Harus HTTPS
   - ✅ Accessible: Bisa diakses publik
4. Publish ulang

#### Pinterest:
**Sama: Butuh Featured Image**
1. Set Featured Image
2. Pastikan URL gambar valid
3. Gambar tidak di-protect (no hotlink protection)

#### Twitter:
**Text Terlalu Panjang**
1. Edit template message
2. Kurangi panjang (maks 280 karakter)
3. Contoh singkat:
   ```
   {title}
   {url}
   ```

#### WhatsApp:
**Nomor Penerima Invalid**
1. Format harus: `628xxxxxxxxx`
2. Dengan kode negara (62 untuk Indonesia)
3. Tanpa tanda `+` atau `-`
4. Tanpa spasi

---

## 🚦 Error 429 Rate Limit

### Gejala:
```
Response Code: 429
Status: failed
Message: Too Many Requests
```

### Penyebab:
- Terlalu banyak request dalam waktu singkat
- Melebihi quota API

### Solusi:

#### Immediate Fix:
1. **Tunggu 15-30 menit**
2. Jangan retry berkali-kali
3. Sistem akan auto-retry via cron

#### Long-term Fix:

**Telegram:**
- Limit: 30 pesan/detik
- Solusi: Tambah delay di settings

**Twitter:**
- Limit: 300 tweet/3 jam
- Solusi: Jangan share terlalu sering

**Facebook:**
- Limit: 200 calls/jam/user
- Solusi: Gunakan page token, bukan user token

**Instagram:**
- Limit: 25 posts/hari
- Solusi: Pilih post penting saja

**LinkedIn:**
- Limit: 100 posts/hari
- Solusi: Share selective

#### Konfigurasi Delay:
```
MeowPack → Pengaturan → Auto Share Delay
```
Set delay 1-2 jam untuk menghindari rate limit.

---

## 📸 Instagram Gagal Share

### Gejala:
- Instagram selalu failed
- Platform lain berhasil
- Response code 400

### Checklist Lengkap:

#### 1. Featured Image
- ✅ Post HARUS punya Featured Image
- ✅ Gambar format JPG atau PNG
- ✅ Ukuran minimal 320x320px (ideal: 1080x1080px)
- ✅ Aspect ratio 1:1 (square) atau 4:5 (portrait)
- ✅ URL gambar HTTPS (bukan HTTP)

#### 2. Account Type
- ✅ Instagram harus **Business** atau **Creator** account
- ✅ Bukan Personal account
- ✅ Terhubung ke Facebook Page

#### 3. Token & Permissions
- ✅ Token harus punya permission:
  - `instagram_basic`
  - `instagram_content_publish`
  - `pages_read_engagement`
- ✅ Token belum expired

#### 4. API Eligibility
- ✅ Akun tidak di-restrict
- ✅ Tidak melanggar Instagram policies
- ✅ Akun sudah verified (untuk high volume)

#### 5. Test Manual:
```bash
# Test upload gambar
curl -X POST "https://graph.facebook.com/v19.0/{IG_USER_ID}/media" \
  -d "image_url=https://example.com/image.jpg" \
  -d "caption=Test" \
  -d "access_token={TOKEN}"
```

---

## ✂️ Twitter Terpotong

### Gejala:
- Tweet terpotong dengan "..."
- Tidak semua text muncul

### Penyebab:
- Twitter limit: **280 karakter**
- Template terlalu panjang

### Solusi:

#### 1. Gunakan Template Pendek
```
{title}

{url}
```

#### 2. Atau Dengan Hashtag
```
{title}

{url}

{tags}
```

#### 3. Hindari Template Panjang
❌ **Jangan:**
```
📰 Artikel Baru: {title}

{excerpt}

Baca selengkapnya di: {url}

Kategori: {tags}
```

✅ **Gunakan:**
```
{title}

{url}
```

#### 4. Auto-Truncate
MeowPack otomatis memotong di 277 karakter + "..."

---

## ⏰ Facebook Token Expired

### Gejala:
```
Error: Token expired
Response Code: 401
```

### Penyebab:
- Facebook token berlaku 60 hari
- Perlu di-extend atau regenerate

### Solusi:

#### Opsi 1: Extend Token (Cepat)
1. Buka [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)
2. Paste token lama
3. Klik **"Extend Access Token"**
4. Copy token baru
5. Update di MeowPack

#### Opsi 2: Generate Token Baru
1. Buka [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Pilih app Anda
3. Generate new token dengan permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
4. Extend token (lihat Opsi 1)
5. Update di MeowPack

#### Opsi 3: Long-Lived Token (Recommended)
Generate token yang tidak expired:
1. Gunakan **System User** di Business Manager
2. Generate token untuk System User
3. Token ini tidak expired selama System User aktif

**Tutorial:**
1. Buka [Business Settings](https://business.facebook.com/settings/)
2. Users → System Users
3. Add System User
4. Assign Assets (pilih Page)
5. Generate Token
6. Token ini permanent!

---

## 🤖 Telegram Bot Tidak Respon

### Gejala:
- Test message gagal
- Bot tidak kirim pesan
- Error "Chat not found"

### Solusi:

#### 1. Cek Bot Token
```bash
curl https://api.telegram.org/bot<TOKEN>/getMe
```
Response harus:
```json
{
  "ok": true,
  "result": {
    "id": 123456789,
    "is_bot": true,
    "first_name": "Your Bot"
  }
}
```

#### 2. Cek Chat ID

**Untuk Channel:**
- Chat ID harus diawali `-100`
- Contoh: `-1001234567890`
- Bot harus jadi **Admin** channel

**Untuk Group:**
- Chat ID diawali `-`
- Contoh: `-1234567890`
- Bot harus jadi **Member** group

**Untuk Private Chat:**
- Chat ID angka positif
- Contoh: `123456789`
- User harus `/start` bot dulu

#### 3. Verifikasi Chat ID
```bash
# Kirim test message
curl -X POST "https://api.telegram.org/bot<TOKEN>/sendMessage" \
  -d "chat_id=<CHAT_ID>" \
  -d "text=Test from MeowPack"
```

#### 4. Cek Bot Permissions
Di channel/group:
- ✅ Bot harus jadi Admin
- ✅ Permission: **Post Messages** aktif

---

## 📱 WhatsApp Tidak Terkirim

### Gejala:
- WhatsApp failed
- Error "Recipient not found"
- Error 400

### Solusi:

#### 1. Format Nomor Benar
```
❌ Salah: +62 812-3456-7890
❌ Salah: 0812-3456-7890
✅ Benar: 628123456789
```

Format:
- Kode negara (62 untuk Indonesia)
- Nomor tanpa 0 di depan
- Tanpa spasi, tanda `-` atau `+`

#### 2. Nomor Harus Verified
1. Nomor penerima harus opt-in
2. Cara verify:
   - Kirim test message dari WhatsApp Business dashboard
   - Penerima harus reply
   - Setelah reply, nomor verified

#### 3. Cek Phone Number ID
1. Buka [Meta Business](https://business.facebook.com/)
2. WhatsApp Manager
3. Copy **Phone Number ID** yang benar
4. Update di MeowPack

#### 4. Cek Quota
WhatsApp Business API:
- Free tier: 1000 pesan/bulan
- Setelah itu berbayar
- Cek quota di dashboard

#### 5. Template Message
Untuk production, harus pakai approved template:
1. Buat template di WhatsApp Manager
2. Submit untuk approval
3. Gunakan template name di MeowPack

---

## 🔍 Debug Mode

### Enable Debug Logging

Tambahkan di `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Log akan tersimpan di: `wp-content/debug.log`

### Cek Log Auto Share

Di meta box post editor:
```
MeowPack — Auto Share
└── Riwayat Share
    ├── ✅ Telegram — 2024-01-15 10:30
    ├── ❌ Facebook — 2024-01-15 10:30 (Code: 401)
    └── ⏳ Instagram — pending
```

### Manual Test via REST API

```bash
# Test tracking endpoint
curl -X POST "https://yoursite.com/wp-json/meowpack/v1/search" \
  -H "Content-Type: application/json" \
  -d '{"post_id":123,"nonce":"xxx"}'
```

---

## 📞 Masih Butuh Bantuan?

### Checklist Sebelum Minta Bantuan:

- [ ] Sudah baca panduan ini
- [ ] Sudah coba regenerate token
- [ ] Sudah test koneksi dengan tombol "Kirim Pesan Tes"
- [ ] Sudah cek log error di meta box
- [ ] Sudah cek WordPress debug.log

### Informasi yang Dibutuhkan:

1. **Platform**: Platform mana yang bermasalah?
2. **Error Code**: Response code berapa? (401, 403, 400, 429?)
3. **Error Message**: Pesan error lengkap
4. **Screenshot**: Screenshot error di admin
5. **Log**: Copy log dari meta box atau debug.log

### Kontak Support:

- 📧 Email: support@meowpack.dev
- 💬 GitHub Issues: [github.com/meowpack/issues](https://github.com/meowpack/issues)
- 📖 Dokumentasi: [docs.meowpack.dev](https://docs.meowpack.dev)

---

## 💡 Tips Mencegah Masalah

### ✅ Best Practices:

1. **Backup Token**
   - Simpan semua token di password manager
   - Jangan share ke orang lain

2. **Monitor Expiry**
   - Set reminder untuk extend token
   - Facebook/LinkedIn: setiap 60 hari

3. **Test Regularly**
   - Test koneksi setiap bulan
   - Pastikan masih berfungsi

4. **Use Delay**
   - Set delay 1-2 jam untuk auto-share
   - Hindari rate limit

5. **Check Logs**
   - Cek riwayat share secara berkala
   - Fix failed shares segera

6. **Update Plugin**
   - Selalu gunakan versi terbaru
   - Update otomatis dari GitHub

---

**Semoga membantu! 🚀**

Dibuat dengan ❤️ oleh MeowPack Team
