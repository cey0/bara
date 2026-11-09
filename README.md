# Bara

Framework PHP native minimal yang terasa seperti Laravel: router, service container, MVC,
dan ORM buatan sendiri di atas PDO. Tanpa Composer, tanpa library pihak ketiga — seluruh
kode inti ada di `framework/` dan bisa dibaca dalam satu duduk.

Aplikasi contoh di `app/` adalah blog kecil: daftar tulisan, detail + komentar, form tulis,
dan hapus tulisan.

---

## 1. Kebutuhan dan asumsi

| Hal | Asumsi |
| --- | --- |
| PHP | >= 8.0 (diuji di PHP 8.0.30). Memakai constructor promotion, `match`-free switch, `str_contains`, `mixed`, `static` return type. |
| Ekstensi | `pdo_sqlite` (default) atau `pdo_mysql`, plus `mbstring` untuk `mb_strimwidth`/`mb_strlen`. |
| Database | **SQLite** secara default, supaya proyek langsung jalan tanpa setup server database. MySQL didukung lewat config. |
| Autoloading | PSR-4 manual (`bootstrap/autoload.php`), bukan Composer. |
| Web server | PHP built-in server (`php -S`) untuk development; Apache/Nginx cukup arahkan document root ke `public/`. |

> Di mesin ini PHP tidak ada di `PATH`. Jika `php` tidak dikenali, pakai
> `C:\xampp\php\php.exe` sebagai ganti `php` pada semua perintah di bawah.

---

## 2. Menjalankan proyek

```bash
# 1. Buat tabel
php bin/console migrate

# 2. Isi data contoh (3 user, 4 tulisan, 3 komentar)
php bin/console seed

# 3. Jalankan server
php bin/console serve
# => http://localhost:8000
```

Buka `http://localhost:8000`. Halaman yang bisa dicoba:

- `/` — beranda, statistik dari query builder
- `/posts` — daftar tulisan (relasi `belongsTo` ke penulis)
- `/posts/1` — detail + komentar (`hasMany`) + tombol hapus
- `/posts/create` — form dengan CSRF token
- `/tidak-ada` — halaman error 404

Perintah lain:

```bash
php bin/console serve 127.0.0.1:8080   # alamat/port lain
php tests/run.php                      # 39 self-check (container, router, ORM, view, CSRF)
```

Ganti ke MySQL:

Buat dulu database-nya di MySQL (`CREATE DATABASE bara;`), lalu:

```bash
DB_DRIVER=mysql DB_DATABASE=bara DB_USERNAME=root DB_PASSWORD= php bin/console migrate
DB_DRIVER=mysql DB_DATABASE=bara php bin/console seed
DB_DRIVER=mysql DB_DATABASE=bara php bin/console serve
```

### Struktur folder

```
framework/                 Kode inti framework (tidak tahu apa pun soal aplikasi)
├── Application.php        Kernel: config, container, CSRF, dispatch, error page
├── Container.php          Binding, singleton, autowiring lewat reflection
├── Config.php             Config repository dengan dot notation
├── Http/
│   ├── Request.php        Wrapper superglobal, method spoofing, JSON body
│   └── Response.php       Response value object (html/json/redirect)
├── Routing/Router.php     Route table + `{param}` + resolusi controller
├── Database/
│   ├── Connection.php     PDO, prepared statement, transaction
│   ├── QueryBuilder.php   Query berantai + bindings
│   ├── Model.php          Active Record: CRUD, timestamps, relasi, mass assignment
│   ├── Collection.php     Collection hasil query
│   └── Relations/         Relation, HasMany, BelongsTo
├── Security/Csrf.php      Token per session
├── View/View.php          Template PHP + layout
├── Exceptions/            HttpException, ModelNotFoundException
└── Support/helpers.php    app(), config(), view(), e(), csrf_field(), ...

app/                       Kode aplikasi pengguna
├── Controllers/           HomeController, PostController
├── Models/                User, Post, Comment
└── routes.php             Definisi route

views/                     Template: layouts/app.php, home.php, posts/*, errors/error.php
config/                    app.php, database.php
database/                  schema/{sqlite,mysql}.sql, seed.php, database.sqlite (generated)
bootstrap/                 autoload.php, app.php
public/index.php           Front controller
server.php                 Router script untuk `php -S`
bin/console                migrate, seed, serve
tests/run.php              Assert-based self-check
```

Batas inti vs aplikasi jelas: `framework/` tidak pernah meng-`use` namespace `App\`.
Aplikasi hanya bergantung pada `Framework\`, bukan sebaliknya.

---

## 3. Cara pakai dari sisi developer

### Route

`app/routes.php`:

```php
use App\Controllers\PostController;
use Framework\Routing\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/posts', [PostController::class, 'index']);
$router->get('/posts/{post}', [PostController::class, 'show']);
$router->post('/posts', [PostController::class, 'store']);
$router->delete('/posts/{post}', [PostController::class, 'destroy']);
```

Tiga bentuk action yang didukung: `[Controller::class, 'method']`, `'Controller@method'`,
dan closure. Nama parameter route (`{post}`) dicocokkan dengan nama parameter method
controller, jadi tidak perlu urutan khusus:

```php
$router->get('/posts/{post}/komentar/{komentar}', function (string $post, string $komentar) {
    return "post $post komentar $komentar";
});
```

Route statis harus didaftarkan sebelum route dinamis yang bisa menangkapnya
(`/posts/create` sebelum `/posts/{post}`).

### Controller

```php
namespace App\Controllers;

use App\Models\Post;
use Framework\Http\Request;
use Framework\Http\Response;

final class PostController
{
    // Dependency di-autowire lewat container; Request adalah request aktif.
    public function show(string $post): Response
    {
        $post = Post::findOrFail($post);

        return view('posts/show', [
            'title' => $post->title,
            'post' => $post,
            'comments' => $post->comments,
        ]);
    }

    public function store(Request $request): Response
    {
        $post = Post::create([
            'user_id' => (int) $request->input('user_id'),
            'title' => trim((string) $request->input('title')),
            'body' => trim((string) $request->input('body')),
        ]);

        return redirect('/posts/' . $post->id);
    }
}
```

`view()` mengembalikan `Response`, jadi controller tidak pernah `echo` atau memanggil
`header()` sendiri. `findOrFail()` otomatis menjadi halaman 404.

### Model

```php
namespace App\Models;

use Framework\Database\Model;
use Framework\Database\Relations\BelongsTo;
use Framework\Database\Relations\HasMany;

final class Post extends Model
{
    protected array $fillable = ['user_id', 'title', 'body']; // wajib untuk mass assignment

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // foreign key ditebak: user_id
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class); // foreign key ditebak: post_id
    }
}
```

Nama tabel diturunkan dari nama kelas (`Post` → `posts`). Override dengan
`protected ?string $table = 'nama_tabel';`. Primary key default `id`, timestamps
`created_at`/`updated_at` aktif secara default (`protected bool $timestamps = false;`
untuk mematikan).

CRUD:

```php
$post = Post::create(['user_id' => 1, 'title' => 'Halo', 'body' => 'Isi']);

$post = Post::find(1);              // ?Post
$post = Post::findOrFail(1);        // Post atau 404

$post->title = 'Judul baru';
$post->save();                      // UPDATE, updated_at ikut berubah
$post->update(['title' => 'Lagi']); // fill + save

$post->delete();                    // DELETE berdasarkan primary key
```

### Query builder

```php
use App\Models\Post;

$posts = Post::query()
    ->where('user_id', 1)
    ->where('title', 'like', '%ORM%')
    ->orWhere('title', 'like', '%router%')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();                    // Collection<Post>

$post  = Post::query()->where('id', 1)->first();   // Post|null
$count = Post::query()->where('user_id', 1)->count();
$ada   = Post::query()->where('id', 1)->exists();
$judul = Post::query()->pluck('title')->all();      // ['Halo', ...]
$baru  = Post::query()->whereIn('id', [1, 2, 3])->get();

// Update/delete massal lewat builder
Post::query()->where('user_id', 1)->update(['body' => 'diarsipkan']);
Post::query()->where('user_id', 1)->delete();
```

Method builder juga tersedia sebagai static call di model:
`Post::where('user_id', 1)->orderBy('id')->get()`.

Nilai selalu masuk ke `bindings`, SQL hanya berisi `?`. Nama kolom dan arah `order by`
divalidasi dengan regex dan allowlist operator, karena PDO tidak bisa meng-bind identifier:

```php
$q = Post::query()->where('title', "x' OR '1'='1");
$q->toSql();       // select * from posts where title = ?
$q->getBindings(); // ["x' OR '1'='1"]
```

### Relasi

```php
$post = Post::find(1);

$post->user;          // User|null  (belongsTo, lazy)
$post->comments;      // Collection<Comment> (hasMany, lazy)

$post->comments->count();
$post->comments->first()->user->name;
$post->comments->pluck('body')->all();
```

Relasi di-cache per model setelah diakses pertama kali. `$post->user->name ?? 'Anonim'`
dan `isset($post->user)` juga memicu lazy load (lihat `Model::__isset`).

### View

View adalah PHP biasa; data di-`extract()` ke scope file, output di-buffer, lalu dibungkus
layout. Semua output dinamis lewat `e()`:

```php
<h1><?= e($post->title) ?></h1>
<p>oleh <?= e($post->user->name ?? 'Anonim') ?></p>
<div><?= nl2br(e($post->body)) ?></div>
```

Form yang mengubah data wajib menyertakan CSRF token, dan method non-POST di-spoof:

```php
<form method="post" action="/posts/<?= e($post->id) ?>">
    <?= csrf_field() ?>
    <?= method_field('DELETE') ?>
    <button type="submit">Hapus</button>
</form>
```

Request `POST/PUT/PATCH/DELETE` tanpa token yang cocok ditolak dengan 419 sebelum
controller dijalankan.

---

## 4. Alur satu request

```
public/index.php
  └─ bootstrap/autoload.php        PSR-4 + helper
  └─ bootstrap/app.php
       ├─ new Application(base)    load config/*.php, daftarkan singleton
       └─ loadRoutes(app/routes.php)
  └─ Application::run(Request)
       ├─ handle(): CSRF check (kecuali GET/HEAD/OPTIONS dan CLI)
       ├─ Router::dispatch()
       │    ├─ cocokkan method + regex path, ambil named params
       │    └─ Container::call(controller, params) → inject Request/dependency
       ├─ Response::send()
       └─ exception → HttpException/ModelNotFound/Throwable → halaman error
```

---

## 5. Keputusan desain penting

**Autoloader sendiri, bukan Composer.** Tujuannya agar tidak ada langkah `composer install`
dan seluruh rantai eksekusi terlihat. Formatnya PSR-4, jadi kalau nanti ingin pindah ke
Composer cukup tambahkan `autoload.psr-4` dan hapus `bootstrap/autoload.php`.

**Container melakukan autowiring lewat reflection.** Controller tidak perlu didaftarkan:
cukup type-hint dependency di constructor atau parameter method. `Connection` dan `Router`
di-bind singleton supaya satu request memakai satu koneksi dan satu route table. `Request`
aktif di-`instance()`-kan saat dispatch, sehingga `store(Request $request)` menerima request
yang benar, bukan objek baru.

**Router memakai named regex group.** `{post}` dikompilasi menjadi
`(?P<post>[^/]+)`, lalu `Container::call()` mencocokkan nama parameter method. Ini yang
membuat `show(string $post)` dan `show(string $id)` sama-sama bekerja tanpa konfigurasi.
Route dinamis sengaja sederhana: satu segmen, tanpa optional/constraint regex.

**Query builder memisahkan kompilasi SQL dari eksekusi.** `toSql()` dan `getBindings()`
bisa diinspeksi/di-log, dan `Connection` hanya menerima SQL + bindings. Nilai tidak pernah
diinterpolasi. Identifier tidak bisa di-bind PDO, jadi divalidasi regex; operator dibatasi
allowlist. Inilah dua titik yang paling sering menjadi celah SQL injection di query builder
buatan sendiri.

**Model Active Record, query builder terpisah.** Model memegang state (`attributes`,
`original`, `exists`) dan tahu cara menyimpan dirinya, sementara pengambilan data tetap
lewat builder. Pola ini yang membuat `$post->save()` dan `Post::where(...)->get()` terasa
seperti Eloquent, tanpa memaksa satu kelas besar menangani keduanya. Baris hasil query
di-*hydrate* menjadi model oleh builder (`setModel()` + `newFromBuilder()`).

**Mass assignment aman secara default.** `$fillable` wajib diisi; model tanpa `$fillable`
melempar `LogicException` saat `fill()`, bukan diam-diam menerima semua input. Ini pilihan
yang lebih ketat daripada Laravel agar kesalahan konfigurasi cepat terlihat.

**Relasi lazy + cache, belum ada eager loading.** `$post->comments` menjalankan query saat
diakses lalu menyimpan hasilnya. Konsekuensinya halaman daftar yang menampilkan penulis tiap
post menghasilkan N+1 query — sengaja dibiarkan terlihat sebagai bahan latihan menambah
`with()`. Relasi memakai objek `Relation` agar tipe relasi baru (hasOne, belongsToMany)
tinggal menambah kelas dan method di `Model`.

**View sengaja bukan template engine.** File PHP + `extract()` + `ob_start()` hanya ~40
baris, mudah di-debug (stack trace menunjuk file view asli), dan `e()` memaksa escaping
eksplisit. Tidak ada compiler/cache yang perlu di-*clear*.

**CSRF di kernel, bukan di tiap controller.** Token per session, dibandingkan dengan
`hash_equals()`, diperiksa terpusat untuk semua method yang mengubah data. Form HTML
memakai `_method` untuk spoofing `PUT/PATCH/DELETE`, sehingga route tetap RESTful tanpa
JavaScript. Cookie sesi diset `HttpOnly` + `SameSite=Lax`.

**Error handling menghasilkan response, bukan `die()`.** `HttpException` membawa status,
`ModelNotFoundException` dipetakan ke 404, exception lain ke 500. Detail stack trace hanya
tampil saat `app.debug` true.

**Migrasi berupa SQL, bukan DSL builder.** `database/schema/{driver}.sql` transparan,
mendukung fitur khas database (foreign key, engine, charset), dan tidak menambah lapisan
baru. `seed.php` justru memakai ORM supaya sekaligus menjadi contoh pemakaian model.

---

## 6. Keamanan: yang sudah dan belum

Sudah:

- Semua nilai query lewat prepared statement (`PDO::ATTR_EMULATE_PREPARES => false` di MySQL).
- Identifier & operator divalidasi; `order by` hanya `asc`/`desc`.
- Escaping output lewat `e()` di seluruh view contoh.
- CSRF untuk POST/PUT/PATCH/DELETE + method spoofing.
- Mass assignment dibatasi `$fillable`.
- Validasi input di trust boundary (`PostController::validate`): wajib isi, panjang judul,
  dan penulis harus ada di database.
- Foreign key SQLite diaktifkan per koneksi (`PRAGMA foreign_keys = ON`).
- Halaman error tidak menampilkan stack trace bila `APP_DEBUG=false`.

Belum ada (wajar untuk fondasi):

- Autentikasi/otorisasi, rate limiting, security headers, HTTPS enforcement.
- Validasi terpusat (saat ini manual di controller).
- escaping otomatis di view (harus memanggil `e()`).
- `APP_DEBUG=true` adalah default untuk development; set `APP_DEBUG=false` di produksi.

---

## 7. Yang sengaja belum ada, dan cara menambahkannya

| Fitur | Titik masuk yang disarankan |
| --- | --- |
| Eager loading (`with('user')`) | `QueryBuilder::get()` + kumpulkan foreign key, satu query `whereIn`, distribusikan ke model. |
| `hasOne`, `belongsToMany` | Tambah kelas di `framework/Database/Relations/` + method di `Model`. |
| Pagination | `QueryBuilder::paginate($perPage, $page)` yang mengembalikan total + slice. |
| Validator | Kelas `Framework\Validation\Validator`; controller memanggilnya sebelum `create()`. |
| Middleware | Bungkus `Router::dispatch()` dengan pipeline `callable(Request, next)`. |
| Route name + `route()` | Simpan nama per route di `Router`, hasilkan URL dari pola + parameter. |
| Route model binding | Di `Router::run()`, ubah `{post}` menjadi `Post::findOrFail()` bila method meminta tipe model. |
| Facade (`DB::table`) | Static proxy ke instance container; cocok setelah container stabil. |
| Soft delete | Global scope di `QueryBuilder` + kolom `deleted_at`. |
| Update UI | Tambah `GET /posts/{post}/edit` + `PUT /posts/{post}`; ORM-nya sudah mendukung. |

Contoh menambah eager loading sederhana untuk `belongsTo` ada di bagian akhir
`QueryBuilder::get()`: setelah hydrate, kumpulkan nilai foreign key, query sekali dengan
`whereIn`, lalu isi `$model->relations` — itulah inti `with()` di Eloquent.

---

## 8. Verifikasi

```bash
php tests/run.php
```

Mencakup 39 assertion: autowiring container, singleton, parameter route, 404/405,
CRUD + timestamps, `find`/`findOrFail`, chaining query, `whereIn` kosong, update via
`save()`/`update()`, `hasMany`/`belongsTo`, cache relasi, `??`/`isset` pada relasi,
mass assignment, penolakan operator/identifier berbahaya, bukti nilai masuk bindings,
escaping view, dan CSRF.

Selain itu sudah diuji lewat HTTP: halaman 200, 404, form create (validasi + redirect),
CSRF 419, dan delete via method spoofing.
