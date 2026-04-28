# 📡 Panduan Lengkap Auto Share MeowPack

Panduan step-by-step untuk mengkonfigurasi setiap platform sosial media di fitur Auto Share MeowPack.

---

## 📋 Daftar Isi

1. [Telegram](#-1-telegram)
2. [Facebook](#-2-facebook)
3. [Instagram](#-3-instagram)
4. [X (Twitter)](#-4-x-twitter)
5. [LinkedIn](#-5-linkedin)
6. [Bluesky](#-6-bluesky)
7. [Threads](#-7-threads)
8. [Pinterest](#-8-pinterest)
9. [Line Notify](#-9-line-notify)
10. [WhatsApp Business](#-10-whatsapp-business)

---

## ✈️ 1. Telegram

### Cara Mendapatkan Bot Token & Chat ID:

#### A. Buat Bot Telegram
1. Buka Telegram dan cari **@BotFather**
2. Kirim perintah: `/newbot`
3. Ikuti instruksi:
   - Masukkan nama bot (contoh: `MeowPack Blog Bot`)
   - Masukkan username bot (harus diakhiri `bot`, contoh: `meowpack_blog_bot`)
4. BotFather akan memberikan **Bot Token** seperti:
   ```
   123456789:ABCdefGhIJKlmNoPQRstuVWX
   ```
5. **SIMPAN TOKEN INI!**

#### B. Dapatkan Chat ID

**Opsi 1: Kirim ke Channel**
1. Buat channel Telegram atau gunakan yang sudah ada
2. Tambahkan bot Anda sebagai admin channel
3. Kirim 1 pesan ke channel
4. Buka browser dan akses:
   ```
   https://api.telegram.org/bot<BOT_TOKEN>/getUpdates
   ```
   Ganti `<BOT_TOKEN>` dengan token Anda
5. Cari `"chat":{"id":-100xxxxxxxxx}` - ini Chat ID Anda
6. Chat ID channel selalu diawali `-100`

**Opsi 2: Kirim ke Private Chat**
1. Cari bot Anda di Telegram
2. Klik **Start** atau kirim pesan `/start`
3. Buka: `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates`
4. Cari `"chat":{"id":123456789}` - ini Chat ID Anda

#### C. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Telegram**
2. Isi:
   - **Bot Token**: Token dari BotFather
   - **Chat ID / Channel**: Chat ID yang didapat (dengan tanda `-` jika channel)
   - **Template Pesan**: Sesuaikan, contoh:
     ```
     📰 <b>{title}</b>
     
     {excerpt}
     
     🔗 Baca selengkapnya: {url}
     
     {tags}
     ```
3. Klik **Simpan Telegram**
4. Klik **🚀 Kirim Pesan Tes** untuk verifikasi

---

## 📘 2. Facebook

### Cara Mendapatkan Page Access Token:

#### A. Persiapan
1. Anda harus punya **Facebook Page** (bukan profil pribadi)
2. Anda harus jadi admin page tersebut

#### B. Buat Facebook App
1. Buka [Facebook Developers](https://developers.facebook.com/)
2. Klik **My Apps** → **Create App**
3. Pilih tipe: **Business**
4. Isi nama app, email kontak
5. Setelah dibuat, masuk ke dashboard app

#### C. Setup Permissions
1. Di sidebar, klik **Add Product**
2. Tambahkan **Facebook Login**
3. Di sidebar, klik **Facebook Login → Settings**
4. Tambahkan **Valid OAuth Redirect URIs**: `https://domain-anda.com/`

#### D. Dapatkan Page Access Token
1. Buka [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Pilih app Anda di dropdown
3. Klik **Generate Access Token**
4. Centang permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
5. Klik **Generate Access Token** dan login
6. Copy token yang muncul
7. **PENTING**: Token ini short-lived, perlu dikonversi ke long-lived:
   - Buka [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)
   - Paste token Anda
   - Klik **Extend Access Token**
   - Copy token baru (berlaku 60 hari)

#### E. Dapatkan Page ID
1. Buka halaman Facebook Page Anda
2. Klik **About**
3. Scroll ke bawah, lihat **Page ID** atau
4. Lihat di URL: `facebook.com/[page-id]`

#### F. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Facebook**
2. Isi:
   - **Page Access Token**: Token yang sudah di-extend
   - **Page ID**: ID page Anda
   - **Template Pesan**: Contoh:
     ```
     {title} — {sitename}
     
     {excerpt}
     ```
3. Klik **Simpan Facebook**
4. Test koneksi

**⚠️ Catatan**: Token Facebook perlu diperpanjang setiap 60 hari.

---

## 📸 3. Instagram

### Cara Mendapatkan Instagram Business ID & Token:

#### A. Persiapan
1. Akun Instagram harus **Business Account** atau **Creator Account**
2. Instagram harus terhubung ke Facebook Page
3. Gunakan Facebook App yang sama dengan setup Facebook di atas

#### B. Hubungkan Instagram ke Facebook Page
1. Buka Instagram → **Settings → Account**
2. Pilih **Switch to Professional Account**
3. Pilih **Business** atau **Creator**
4. Hubungkan ke Facebook Page Anda

#### C. Dapatkan Instagram Business ID
1. Buka [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Pilih app Anda
3. Generate token dengan permissions:
   - `instagram_basic`
   - `instagram_content_publish`
   - `pages_read_engagement`
4. Di query field, masukkan:
   ```
   me/accounts
   ```
5. Klik **Submit**
6. Cari page Anda, copy `id` nya
7. Sekarang query:
   ```
   [PAGE_ID]?fields=instagram_business_account
   ```
8. Copy `instagram_business_account.id` - ini Instagram Business ID Anda

#### D. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Instagram**
2. Isi:
   - **Page Access Token**: Token Facebook yang sama
   - **Instagram Business ID**: ID yang didapat dari Graph API
   - **Caption Template**: Contoh:
     ```
     {title}
     
     {excerpt}
     
     🔗 Link di bio!
     
     {tags}
     ```
3. Klik **Simpan Instagram**

**⚠️ PENTING**: 
- Post HARUS punya **Featured Image** (gambar utama)
- Gambar harus bisa diakses publik (URL https)
- Instagram tidak support link di caption (kecuali di bio)

---

## 🐦 4. X (Twitter)

### Cara Mendapatkan API Keys (OAuth 1.0a):

#### A. Buat Twitter Developer Account
1. Buka [Twitter Developer Portal](https://developer.twitter.com/)
2. Klik **Sign up** (gunakan akun Twitter Anda)
3. Isi form aplikasi:
   - Nama aplikasi
   - Deskripsi penggunaan
   - Website URL
4. Setujui terms dan submit

#### B. Buat App & Dapatkan Keys
1. Setelah approved, masuk ke [Developer Portal](https://developer.twitter.com/en/portal/dashboard)
2. Klik **Create Project** → **Create App**
3. Isi nama app
4. Setelah dibuat, masuk ke **Keys and Tokens**
5. Anda akan melihat:
   - **API Key** (Consumer Key)
   - **API Secret Key** (Consumer Secret)
   - **SIMPAN KEDUANYA!**

#### C. Generate Access Token
1. Di halaman yang sama, scroll ke **Authentication Tokens**
2. Klik **Generate** di bagian **Access Token and Secret**
3. Anda akan dapat:
   - **Access Token**
   - **Access Token Secret**
   - **SIMPAN KEDUANYA!**

#### D. Set Permissions
1. Di app settings, klik **Settings**
2. Scroll ke **App permissions**
3. Pastikan set ke **Read and Write** (bukan Read-only)
4. Save changes

#### E. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → X (Twitter)**
2. Isi **4 credentials**:
   - **API Key**: Consumer Key
   - **API Secret**: Consumer Secret
   - **Access Token**: Access Token
   - **Access Token Secret**: Access Token Secret
3. **Template Pesan** (maks 280 karakter):
   ```
   {title}
   
   {url}
   
   {tags}
   ```
4. Klik **Simpan X (Twitter)**

**💡 Tips**: Tweet akan otomatis dipotong jika lebih dari 280 karakter.

---

## 💼 5. LinkedIn

### Cara Mendapatkan Access Token & Author URN:

#### A. Buat LinkedIn App
1. Buka [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Klik **Create App**
3. Isi:
   - App name
   - LinkedIn Page (pilih company page Anda)
   - App logo
4. Setujui terms dan create

#### B. Request API Access
1. Di app dashboard, klik **Products**
2. Request akses untuk:
   - **Share on LinkedIn**
   - **Sign In with LinkedIn**
3. Tunggu approval (biasanya instant untuk Share on LinkedIn)

#### C. Setup OAuth
1. Di tab **Auth**, tambahkan **Redirect URLs**:
   ```
   https://domain-anda.com/oauth/callback
   ```
2. Copy **Client ID** dan **Client Secret**

#### D. Generate Access Token (Manual)
1. Buat URL OAuth:
   ```
   https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=[CLIENT_ID]&redirect_uri=[REDIRECT_URI]&scope=w_member_social
   ```
2. Ganti `[CLIENT_ID]` dan `[REDIRECT_URI]`
3. Buka URL di browser, authorize
4. Anda akan di-redirect dengan `code` di URL
5. Exchange code untuk token dengan POST request:
   ```bash
   curl -X POST https://www.linkedin.com/oauth/v2/accessToken \
     -d grant_type=authorization_code \
     -d code=[CODE] \
     -d client_id=[CLIENT_ID] \
     -d client_secret=[CLIENT_SECRET] \
     -d redirect_uri=[REDIRECT_URI]
   ```
6. Response akan berisi `access_token`

#### E. Dapatkan Author URN
1. Dengan access token, request:
   ```bash
   curl -X GET https://api.linkedin.com/v2/me \
     -H "Authorization: Bearer [ACCESS_TOKEN]"
   ```
2. Response berisi `id` - ini adalah person ID
3. Author URN format: `urn:li:person:[ID]`

#### F. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → LinkedIn**
2. Isi:
   - **Access Token**: Token OAuth
   - **Author URN**: `urn:li:person:xxxxx`
   - **Template Pesan**:
     ```
     {title}
     
     {excerpt}
     
     Baca selengkapnya: {url}
     ```
3. Klik **Simpan LinkedIn**

**⚠️ Catatan**: LinkedIn token berlaku 60 hari, perlu refresh.

---

## 🦋 6. Bluesky

### Cara Setup Bluesky (Paling Mudah!):

#### A. Buat App Password
1. Login ke [Bluesky](https://bsky.app/)
2. Buka **Settings → Privacy and Security**
3. Scroll ke **App Passwords**
4. Klik **Add App Password**
5. Beri nama: `MeowPack AutoShare`
6. Copy password yang muncul (format: `xxxx-xxxx-xxxx-xxxx`)
7. **SIMPAN! Password hanya muncul sekali**

#### B. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Bluesky**
2. Isi:
   - **Handle**: Username Bluesky Anda (contoh: `username.bsky.social`)
   - **App Password**: Password yang baru dibuat
   - **Template Pesan** (maks 300 karakter):
     ```
     {title}
     
     {excerpt}
     
     {url}
     ```
3. Klik **Simpan Bluesky**

**✅ Paling Simple!** Tidak perlu API key atau OAuth yang ribet.

---

## 🧵 7. Threads

### Cara Mendapatkan Threads Access Token:

#### A. Persiapan
1. Akun Instagram harus **Professional Account**
2. Threads harus terhubung ke Instagram
3. Gunakan Facebook App yang sama

#### B. Dapatkan Threads User ID
1. Buka [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Generate token dengan permissions:
   - `threads_basic`
   - `threads_content_publish`
3. Query:
   ```
   me/threads_publishing_limit
   ```
4. Jika berhasil, berarti akun Anda eligible
5. Query untuk dapat User ID:
   ```
   me?fields=threads_profile
   ```
6. Copy `threads_profile.id`

#### C. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Threads**
2. Isi:
   - **Access Token**: Token Facebook/Instagram
   - **User ID**: Threads User ID
   - **Template Pesan**:
     ```
     {title}
     
     {excerpt}
     
     {url}
     ```
3. Klik **Simpan Threads**

**⚠️ Catatan**: Threads API masih beta, tidak semua akun eligible.

---

## 📌 8. Pinterest

### Cara Mendapatkan Access Token & Board ID:

#### A. Buat Pinterest App
1. Buka [Pinterest Developers](https://developers.pinterest.com/)
2. Klik **My Apps → Create App**
3. Isi nama app dan deskripsi
4. Setujui terms

#### B. Setup OAuth
1. Di app dashboard, klik **OAuth**
2. Tambahkan **Redirect URI**:
   ```
   https://domain-anda.com/oauth/callback
   ```
3. Copy **App ID** dan **App Secret**

#### C. Generate Access Token
1. Buat OAuth URL:
   ```
   https://www.pinterest.com/oauth/?client_id=[APP_ID]&redirect_uri=[REDIRECT_URI]&response_type=code&scope=boards:read,pins:write
   ```
2. Buka di browser, authorize
3. Dapat `code` dari redirect URL
4. Exchange untuk token:
   ```bash
   curl -X POST https://api.pinterest.com/v5/oauth/token \
     -d grant_type=authorization_code \
     -d code=[CODE] \
     -d redirect_uri=[REDIRECT_URI] \
     -u [APP_ID]:[APP_SECRET]
   ```
5. Response berisi `access_token`

#### D. Dapatkan Board ID
1. Request list boards:
   ```bash
   curl -X GET https://api.pinterest.com/v5/boards \
     -H "Authorization: Bearer [ACCESS_TOKEN]"
   ```
2. Pilih board yang ingin digunakan, copy `id` nya

#### E. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Pinterest**
2. Isi:
   - **Access Token**: OAuth token
   - **Board ID**: ID board tujuan
   - **Deskripsi Pin**:
     ```
     {title}
     
     {excerpt}
     ```
3. Klik **Simpan Pinterest**

**⚠️ Penting**: Post harus punya Featured Image untuk Pinterest.

---

## 💬 9. Line Notify

### Cara Mendapatkan Line Notify Token (Termudah!):

#### A. Generate Token
1. Buka [Line Notify](https://notify-bot.line.me/)
2. Login dengan akun Line Anda
3. Klik **My Page** (pojok kanan atas)
4. Scroll ke bawah, klik **Generate Token**
5. Isi:
   - **Token name**: `MeowPack Blog`
   - **Select chat**: Pilih grup atau `1-on-1 chat with LINE Notify`
6. Klik **Generate token**
7. **COPY TOKEN!** (hanya muncul sekali)

#### B. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → Line Notify**
2. Isi:
   - **Notify Token**: Token yang baru dibuat
   - **Template Pesan**:
     ```
     📰 {title}
     
     {excerpt}
     
     🔗 {url}
     ```
3. Klik **Simpan Line Notify**

**✅ Super Mudah!** Hanya perlu 1 token, tidak perlu API key atau OAuth.

---

## 📱 10. WhatsApp Business

### Cara Setup WhatsApp Business Cloud API:

#### A. Persiapan
1. Anda perlu **Facebook Business Manager**
2. Nomor telepon yang belum terdaftar di WhatsApp

#### B. Setup WhatsApp Business
1. Buka [Meta for Developers](https://developers.facebook.com/)
2. Buat app baru, pilih **Business**
3. Tambahkan product **WhatsApp**
4. Ikuti wizard setup:
   - Pilih Business Portfolio
   - Buat WhatsApp Business Account
   - Tambahkan nomor telepon

#### C. Dapatkan Credentials
1. Di WhatsApp dashboard, klik **API Setup**
2. Copy:
   - **Phone Number ID** (dari dropdown)
   - **WhatsApp Business Account ID**
3. Generate **Access Token**:
   - Klik **Generate Token** (temporary) atau
   - Buat System User untuk permanent token

#### D. Verifikasi Nomor Penerima
1. Nomor penerima harus diverifikasi dulu
2. Kirim test message dari dashboard
3. Penerima harus reply untuk verifikasi

#### E. Konfigurasi di MeowPack
1. Buka **MeowPack → Auto Share → WhatsApp**
2. Isi:
   - **Access Token**: Token dari Meta
   - **Phone Number ID**: ID dari dashboard
   - **Nomor Penerima**: Format `628xxxxxxxxx` (dengan kode negara, tanpa +)
   - **Template Pesan**:
     ```
     📰 *{title}*
     
     {excerpt}
     
     🔗 {url}
     ```
3. Klik **Simpan WhatsApp**

**⚠️ Catatan**: 
- WhatsApp Business API berbayar setelah 1000 pesan gratis/bulan
- Nomor penerima harus opt-in (setuju menerima pesan)

---

## 🎯 Tips Umum

### Template Variables
Semua platform support variabel berikut:
- `{title}` - Judul post
- `{url}` - URL post
- `{excerpt}` - Ringkasan post
- `{tags}` - Hashtags dari post tags
- `{sitename}` - Nama website
- `{featured_image}` - URL gambar utama (untuk platform yang support)

### Best Practices
1. **Test Koneksi**: Selalu klik tombol "🚀 Kirim Pesan Tes" setelah konfigurasi
2. **Token Security**: Jangan share token ke orang lain
3. **Backup Token**: Simpan token di tempat aman (password manager)
4. **Monitor Logs**: Cek riwayat share di meta box post editor
5. **Rate Limits**: Jangan spam, ikuti rate limit setiap platform

### Troubleshooting
- **Error 401/403**: Token expired atau invalid, generate ulang
- **Error 400**: Format data salah, cek template message
- **Error 429**: Rate limit exceeded, tunggu beberapa menit
- **Instagram gagal**: Pastikan post punya Featured Image
- **Twitter terpotong**: Kurangi panjang template (maks 280 karakter)

---

## 📞 Butuh Bantuan?

Jika mengalami kesulitan:
1. Cek log error di meta box post editor
2. Test koneksi dengan tombol "Kirim Pesan Tes"
3. Pastikan token belum expired
4. Verifikasi permissions API sudah benar

---

**Dibuat dengan ❤️ oleh MeowPack**
