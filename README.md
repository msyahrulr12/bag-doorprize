# Aplikasi Undian BAGI HOKI

Aplikasi **Undian BAGI HOKI** adalah platform pengelolaan undian dan door prize berbasis **Laravel 12** dan **Filament v4**. Aplikasi ini dioptimalkan untuk performa tinggi menggunakan **Laravel Octane** dengan engine **FrankenPHP** untuk menangani pemrosesan data tiket dan antrean dalam jumlah besar secara efisien.

---

## Panduan Inisiasi Project

1. Clone Project

    ```Bash
    git clone <repository-url>
    cd bag-doorprize
    ```

2. Instalasi Dependencies

    ```Bash
    composer install
    npm install
    ```

3. Konfigurasi Environment

    ```Bash
    cp .env.example .env
    ```

    Edit file `.env` dan sesuaikan konfigurasi berikut:

    ```env
    DB_CONNECTION=mysql
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=bag_doorprize
    DB_USERNAME=root
    DB_PASSWORD=
    ```

4. Generate Application Key

    ```Bash
    php artisan key:generate
    ```

5. Migrasi Database

    ```Bash
    php artisan migrate
    ```

6. Seed Database

    ```Bash
    php artisan db:seed
    ```

## ⚙️ Panduan Instalasi Octane & FrankenPHP

Instalasi Laravel Octane dengan FrankenPHP bervariasi tergantung pada sistem operasi Anda.

1. Persiapan Umum
   Pasang package Octane dan pilih FrankenPHP sebagai server engine:

    ```Bash
    composer require laravel/octane
    php artisan octane:install --server=frankenphp
    ```

2. Instalasi FrankenPHP
   FrankenPHP adalah binary PHP yang dikompilasi dengan ekstensi yang diperlukan. Anda perlu mengunduh binary ini dan menyimpannya di lokasi yang dapat diakses oleh sistem.

    ```Bash
    wget https://github.com/dunglas/frankenphp/releases/download/v1.0.0/frankenphp-linux-amd64
    chmod +x frankenphp-linux-amd64
    mv frankenphp-linux-amd64 /usr/local/bin/frankenphp
    ```

3. Konfigurasi Octane
   Setelah FrankenPHP terinstal, Anda perlu mengkonfigurasi Octane untuk menggunakan FrankenPHP sebagai server engine. Edit file `config/octane.php` dan ubah konfigurasi berikut:

    ```php
    'server' => 'frankenphp',
    'frankenphp' => [
        'binary' => '/usr/local/bin/frankenphp',
        'workers' => 4,
        'port' => 8002,
    ],
    ```

4. Uji Coba
   Setelah konfigurasi selesai, Anda dapat menguji coba aplikasi dengan menjalankan perintah berikut:

    ```Bash
    php artisan octane:start --workers=4 --port=8002
    ```

## 🚀 Perintah Menjalankan Aplikasi

Aplikasi ini menggunakan environment yang dioptimalkan. Jalankan ketiga perintah berikut di terminal yang berbeda:

1. **Server Utama (Laravel Octane):**
    ```Bash
    php artisan octane:start --workers=4 --port=8002
    ```
2. Antrean Pekerjaan (Queue Worker):
   Memproses import data, pembuatan nomor tiket, dan pengundian di latar belakang.

    ```Bash
    php artisan queue:work --queue=imports,draws,reports,tickets,default
    ```

3. Frontend Assets (Vite):
    ```Bash
    npm run dev
    ```

## Panduan Deployment Menggunakan Makefile

1. Package Object

    ```Bash
    make package
    ```

2. Transfer File bag-doorprize.zip ke Server

    ```Bash
    scp bag-doorprize.zip user@server:/path/to/destination
    ```

    atau bisa menggunakan SFTP Client seperti FileZilla / Termius. Masukkan ke folder /home/sysadmin/bagi-hoki-main/YYYY-MM-DD/

3. Masuk ke folder /home/sysadmin/ lalu jalankan

    ```Bash
    sudo bash deploy.sh
    ```

4. Proses deployment selesai

### Note

1. Pastikan folder /home/sysadmin/bagi-hoki-main/YYYY-MM-DD/ sudah ada, karena proses deployment mengambil dari folder tersebut
2. Pastikan folder /home/sysadmin/bagi-hoki/bootstrap/cache diberi akses write oleh user sysadmin
   Berikut adalah perintahnya:
    ```Bash
    sudo chown -R www-data:www-data /home/sysadmin/bagi-hoki/bootstrap/cache
    ```
    ```Bash
    sudo chmod -R 775 /home/sysadmin/bagi-hoki/bootstrap/cache
    ```
    ```Bash
    sudo chmod g+s /home/sysadmin/bagi-hoki/bootstrap/cache
    ```
