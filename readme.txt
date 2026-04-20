=== MeowPack ===
Contributors: akbarbahaulloh
Tags: analytics, statistics, social sharing, jetpack alternative, page views
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress ringan pengganti Jetpack. Statistik pengunjung, auto-share sosial media, view counter — semua data tersimpan lokal.

== Description ==

**MeowPack** adalah plugin WordPress ringan yang menggantikan Jetpack dengan menyimpan semua data di database lokal (MySQL WordPress) tanpa ketergantungan API pihak ketiga untuk statistik.

= Fitur Utama =

* **Statistik Pengunjung** — Grafik harian/mingguan/bulanan, pengunjung unik vs pageviews
* **Lacak Sumber** — Otomatis kategorikan: Direct, Search, Social, Referral, Email
* **Bot Filter** — Deteksi ~50 bot populer, IP rate limiting, filter admin
* **Auto Share** — Share otomatis ke 9 platform: Telegram, Facebook, X, LinkedIn, Bluesky, Threads, Pinterest, Line, WhatsApp
* **Tombol Share** — Shortcode, Gutenberg block, 3 gaya (ikon/ikon+teks/pill)
* **View Counter** — Tampilkan jumlah baca, shortcode `[meowpack_views]`, trending widget
* **Widget Statistik** — Kotak statistik publik untuk sidebar/footer
* **Import Jetpack** — Migrasi data dari Jetpack atau CSV WordPress.com

= Shortcodes =

* `[meowpack_share]` — Tombol share di mana saja
* `[meowpack_views]` — Tampilkan jumlah views artikel
* `[meowpack_trending days="7" count="5"]` — Artikel populer
* `[meowpack_counter type="today|month|total|pageviews|all"]` — Counter pengunjung

= Persyaratan =

* WordPress 5.9+
* PHP 7.4+
* MySQL 5.7+ atau MariaDB 10.3+

== Installation ==

1. Upload folder `meowpack` ke `/wp-content/plugins/`
2. Aktifkan plugin melalui menu 'Plugins' di WordPress
3. Buka **MeowPack → Pengaturan** untuk konfigurasi awal
4. Untuk auto-share, buka **MeowPack → Auto Share** dan masukkan token platform

== Frequently Asked Questions ==

= Apakah MeowPack memperlambat website? =

Tidak. Tracking menggunakan JavaScript non-blocking (`sendBeacon` API) dan data ditulis ke database secara asinkron. Script tracker < 3KB.

= Bagaimana privasi data pengunjung? =

IP address di-hash menggunakan SHA-256 + WordPress salt, sehingga tidak bisa di-reverse. Data mentah dihapus setelah 30 hari (dapat dikonfigurasi).

= Bisakah saya import data dari Jetpack? =

Ya. Buka **MeowPack → Import Jetpack**. Plugin akan mendeteksi data Jetpack secara otomatis, atau Anda bisa upload file CSV dari ekspor WordPress.com.

= Platform sosial media apa yang didukung? =

Telegram, Facebook, X (Twitter), LinkedIn, Bluesky, Threads, Pinterest, Line Notify, WhatsApp Business API.

== Screenshots ==

1. Dashboard statistik pengunjung dengan grafik 30 hari
2. Breakdown sumber kunjungan (pie chart)
3. Konfigurasi Auto Share per platform
4. Tombol share di artikel (3 gaya)
5. Widget statistik pengunjung publik

== Changelog ==

= 1.0.0 =
* Rilis pertama.
* 9 modul: Core, Bot Filter, Tracker, Stats, AutoShare, ShareButtons, ViewCounter, Widget, Importer.
* 9 platform auto-share.
* Dashboard admin dengan Chart.js.

== Upgrade Notice ==

= 1.0.0 =
Rilis pertama MeowPack.
