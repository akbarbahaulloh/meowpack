# 🚀 Quick Start: Auto Share

Panduan cepat untuk mulai menggunakan fitur Auto Share MeowPack.

## 📋 Langkah Cepat

### 1️⃣ Aktifkan Fitur
1. Buka **MeowPack → Pengaturan**
2. Centang **Enable Auto Share**
3. Pilih platform default
4. Klik **Simpan**

### 2️⃣ Konfigurasi Platform
1. Buka **MeowPack → Auto Share**
2. Pilih tab platform yang ingin digunakan
3. Isi credentials (lihat panduan per platform di bawah)
4. Klik **Simpan**
5. Klik **🚀 Kirim Pesan Tes** untuk verifikasi

### 3️⃣ Mulai Sharing!
- **Otomatis**: Setiap post baru akan auto-share ke platform yang dipilih
- **Manual**: Klik "🚀 Bagikan ke Sosmed" di daftar post
- **Per-Post**: Pilih platform di meta box saat edit post

---

## 🎯 Platform Termudah untuk Pemula

### ⭐ Paling Mudah: Telegram
**Waktu setup: 5 menit**

1. Chat [@BotFather](https://t.me/BotFather) di Telegram
2. Kirim `/newbot` dan ikuti instruksi
3. Dapat **Bot Token**
4. Buat channel/grup, tambahkan bot sebagai admin
5. Kirim 1 pesan, lalu buka:
   ```
   https://api.telegram.org/bot<TOKEN>/getUpdates
   ```
6. Copy **Chat ID** dari response
7. Masukkan ke MeowPack → Auto Share → Telegram

**✅ Selesai!** Paling simple dan gratis selamanya.

---

### ⭐ Mudah: Bluesky
**Waktu setup: 3 menit**

1. Login ke [bsky.app](https://bsky.app)
2. Settings → Privacy and Security → App Passwords
3. Klik **Add App Password**
4. Copy password yang muncul
5. Masukkan ke MeowPack:
   - Handle: `username.bsky.social`
   - App Password: password tadi

**✅ Selesai!** Tidak perlu API key atau OAuth.

---

### ⭐ Mudah: Line Notify
**Waktu setup: 3 menit**

1. Buka [notify-bot.line.me](https://notify-bot.line.me/)
2. Login dengan Line
3. My Page → Generate Token
4. Pilih chat tujuan
5. Copy token
6. Masukkan ke MeowPack → Auto Share → Line Notify

**✅ Selesai!** Gratis dan mudah.

---

## 🔧 Platform Menengah

### Facebook & Instagram
**Waktu setup: 15-20 menit**

Perlu:
- Facebook Page (bukan profil pribadi)
- Facebook Developer App
- Page Access Token

📖 **Baca**: [Panduan Facebook](PANDUAN-AUTOSHARE.md#-2-facebook) & [Panduan Instagram](PANDUAN-AUTOSHARE.md#-3-instagram)

### X (Twitter)
**Waktu setup: 10-15 menit**

Perlu:
- Twitter Developer Account
- 4 credentials: API Key, API Secret, Access Token, Access Secret

📖 **Baca**: [Panduan Twitter](PANDUAN-AUTOSHARE.md#-4-x-twitter)

---

## 🎓 Platform Advanced

### LinkedIn, Threads, Pinterest, WhatsApp
**Waktu setup: 20-30 menit**

Perlu OAuth setup dan API configuration yang lebih kompleks.

📖 **Baca**: [Panduan Lengkap](PANDUAN-AUTOSHARE.md) untuk tutorial detail.

---

## 💡 Tips Penting

### ✅ DO:
- ✅ Test koneksi setelah setup
- ✅ Simpan token di tempat aman
- ✅ Gunakan template yang menarik
- ✅ Cek riwayat share di meta box post
- ✅ Monitor rate limits

### ❌ DON'T:
- ❌ Share token ke orang lain
- ❌ Spam terlalu banyak post sekaligus
- ❌ Lupa backup token
- ❌ Gunakan token expired
- ❌ Skip test koneksi

---

## 🔐 Keamanan Token

MeowPack mengenkripsi semua token menggunakan:
- **AES-256-CBC** encryption
- **WordPress AUTH_KEY** sebagai encryption key
- Token disimpan encrypted di database

**Aman!** Token tidak bisa dibaca langsung dari database.

---

## 📊 Template Variables

Gunakan variabel ini di template pesan:

| Variable | Deskripsi | Contoh |
|----------|-----------|--------|
| `{title}` | Judul post | "Cara Membuat Blog" |
| `{url}` | URL post | "https://site.com/post" |
| `{excerpt}` | Ringkasan | "Panduan lengkap..." |
| `{tags}` | Hashtags | "#tutorial #blog" |
| `{sitename}` | Nama site | "MeowPack Blog" |
| `{featured_image}` | URL gambar | "https://site.com/img.jpg" |

### Contoh Template Bagus:

**Telegram:**
```
📰 <b>{title}</b>

{excerpt}

🔗 Baca: {url}

{tags}
```

**Twitter:**
```
{title}

{url}

{tags}
```

**Facebook:**
```
{title} — {sitename}

{excerpt}
```

---

## 🐛 Troubleshooting Cepat

| Error | Solusi |
|-------|--------|
| ❌ Token invalid | Generate token baru |
| ❌ 403 Forbidden | Cek permissions API |
| ❌ 429 Rate limit | Tunggu beberapa menit |
| ❌ Instagram gagal | Pastikan ada Featured Image |
| ❌ Twitter terpotong | Kurangi panjang template (<280 char) |

---

## 📞 Butuh Bantuan Lebih?

1. 📖 Baca [Panduan Lengkap](PANDUAN-AUTOSHARE.md)
2. 🔍 Cek log error di meta box post editor
3. 🧪 Test koneksi dengan tombol "Kirim Pesan Tes"
4. 🔄 Regenerate token jika expired

---

## 🎯 Rekomendasi Setup

### Untuk Blog Personal:
- ✅ Telegram (mudah & gratis)
- ✅ Twitter/X (jangkauan luas)
- ✅ Bluesky (alternatif Twitter)

### Untuk Blog Bisnis:
- ✅ Facebook (engagement tinggi)
- ✅ LinkedIn (profesional)
- ✅ Instagram (visual content)
- ✅ WhatsApp (direct to customer)

### Untuk Blog Berita:
- ✅ Telegram (instant notification)
- ✅ Twitter/X (real-time)
- ✅ Line Notify (populer di Asia)

---

**Selamat mencoba! 🚀**

Dibuat dengan ❤️ oleh MeowPack
