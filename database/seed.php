<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Framework\Application;
use Framework\Database\Connection;

if (User::query()->count() > 0) {
    echo "Seed dilewati: tabel users sudah berisi data.\n";
    return;
}

$application = Application::getInstance();

$application->container()->make(Connection::class)->transaction(static function (): void {
    $ada = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $grace = User::create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    $alan = User::create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $router = Post::create([
        'user_id' => $ada->id,
        'title' => 'Kenapa framework ini punya router sendiri',
        'body' => "Router-nya cuma satu file. Ia mencocokkan method + path, mengubah {parameter} "
            . "menjadi named group, lalu memanggil controller lewat container.\n\n"
            . "Karena container yang memanggil, controller bisa minta dependency lewat "
            . "type-hint di constructor tanpa registrasi manual.",
    ]);

    $orm = Post::create([
        'user_id' => $grace->id,
        'title' => 'ORM: prepared statement, bukan string concat',
        'body' => "Query builder selalu menaruh nilai di bindings dan menulis ? di SQL. "
            . "Identifier (nama kolom, arah order) divalidasi dengan regex, karena keduanya "
            . "tidak bisa di-bind oleh PDO.\n\n"
            . "Model cukup tahu tabel, primary key, dan kolom yang boleh diisi massal.",
    ]);

    $views = Post::create([
        'user_id' => $alan->id,
        'title' => 'View tanpa template engine',
        'body' => 'View adalah file PHP biasa yang di-render dengan ob_start(). '
            . 'Satu helper e() untuk escaping, satu layout untuk membungkus konten.',
    ]);

    Post::create([
        'user_id' => $ada->id,
        'title' => 'Relasi: hasMany dan belongsTo',
        'body' => 'Post punya banyak Comment, Comment milik satu User. '
            . 'Relasi dimuat malas (lazy) saat properti diakses, lalu di-cache di model.',
    ]);

    Comment::create([
        'post_id' => $router->id,
        'user_id' => $grace->id,
        'body' => 'Bagian container-nya yang paling menarik. Autowiring lewat reflection terasa ajaib sampai kodenya dibaca.',
    ]);

    Comment::create([
        'post_id' => $router->id,
        'user_id' => $alan->id,
        'body' => 'Setuju. Dan karena tidak ada magic tersembunyi, debugging-nya mudah.',
    ]);

    Comment::create([
        'post_id' => $orm->id,
        'user_id' => $ada->id,
        'body' => 'Validasi identifier-nya penting: PDO hanya bisa meng-bind value, bukan nama kolom.',
    ]);
});

echo "Seed selesai: 3 user, 4 post, 3 komentar.\n";
