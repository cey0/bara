<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Framework\Container;
use Framework\Database\Collection;
use Framework\Database\Model;
use Framework\Exceptions\HttpException;
use Framework\Exceptions\ModelNotFoundException;
use Framework\Http\Request;
use Framework\Routing\Router;
use Framework\Security\Csrf;
use Framework\View\View;

// Must be set before the application boots: config/database.php reads it.
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=:memory:');

require __DIR__ . '/../bootstrap/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

final class ContainerProbe
{
    public function __construct(public Container $container)
    {
    }
}

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  OK   $label\n";

        return;
    }

    $failed++;
    echo "  FAIL $label\n";
}

function expectException(string $label, callable $callback, string $exceptionClass): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        check($label, $e instanceof $exceptionClass);

        return;
    }

    check($label, false);
}

echo "\nContainer\n";
check('kelas tanpa binding di-autowire', $app->container()->make(ContainerProbe::class)->container === $app->container());

$probe = $app->container()->make(ContainerProbe::class);
check('make() membuat instance baru untuk binding non-shared', $probe !== $app->container()->make(ContainerProbe::class));
check('singleton mengembalikan instance yang sama', $app->container()->make(Router::class) === $app->container()->make(Router::class));

echo "\nRouter\n";
$router = new Router(new Container());
$router->get('/posts/{post}', static fn (string $post): string => 'post:' . $post);
$router->post('/only-post', static fn (): string => 'ok');

check('parameter route diteruskan ke closure', $router->dispatch(new Request('GET', '/posts/42'))->content() === 'post:42');
check('trailing slash diterima', $router->dispatch(new Request('GET', '/posts/42/'))->content() === 'post:42');

$dotRouter = new Router(new Container());
$dotRouter->get('/feed.xml', static fn (): string => 'feed');
check('titik pada route diperlakukan literal', $dotRouter->dispatch(new Request('GET', '/feed.xml'))->content() === 'feed');
expectException('route dengan titik tidak cocok dengan karakter lain', static function () use ($dotRouter): void {
    $dotRouter->dispatch(new Request('GET', '/feedXxml'));
}, HttpException::class);

expectException('route tidak dikenal => 404', static function () use ($router): void {
    $router->dispatch(new Request('GET', '/missing'));
}, HttpException::class);

try {
    $router->dispatch(new Request('GET', '/only-post'));
    check('method salah => 405', false);
} catch (HttpException $e) {
    check('method salah => 405', $e->getStatusCode() === 405);
}

echo "\nORM: CRUD\n";
$connection = $app->container()->make(Framework\Database\Connection::class);
$connection->pdo()->exec((string) file_get_contents($app->basePath('database/schema/sqlite.sql')));

$user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
check('create() mengisi primary key', is_int($user->id) && $user->id > 0);
check('timestamps terisi otomatis', preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $user->created_at) === 1);

$post = Post::create([
    'user_id' => $user->id,
    'title' => 'Halo ORM',
    'body' => 'Isi tulisan pertama.',
]);

check('find() mengembalikan model', Post::find($post->id)?->title === 'Halo ORM');
check('find() mengembalikan null untuk id tidak ada', Post::find(999999) === null);
expectException('findOrFail() melempar ModelNotFoundException', static function (): void {
    Post::findOrFail(999999);
}, ModelNotFoundException::class);

check('query builder bisa di-chain', Post::query()->where('title', 'like', '%ORM%')->orderBy('id', 'desc')->count() === 1);
check('first() mengembalikan satu model', Post::query()->where('title', 'Halo ORM')->first() instanceof Post);
check('pluck() mengambil satu kolom', Post::query()->pluck('title')->all() === ['Halo ORM']);
check('whereIn() dengan array kosong tidak mencocokkan apa pun', Post::query()->whereIn('id', [])->get()->isEmpty());

$post->title = 'Judul diubah';
$post->save();
check('save() pada model existing melakukan update', Post::find($post->id)?->title === 'Judul diubah');

$post->update(['title' => 'Judul kedua']);
check('update() massal pada instance', Post::find($post->id)?->title === 'Judul kedua');

echo "\nORM: relasi\n";
$comment = Comment::create(['post_id' => $post->id, 'user_id' => $user->id, 'body' => 'Komentar pertama.']);
$fresh = Post::find($post->id);

check('hasMany memuat koleksi', $fresh->comments instanceof Collection && $fresh->comments->count() === 1);
check('belongsTo memuat model induk', $fresh->comments->first()->user?->name === 'Test User');
check('relasi di-cache setelah diakses', $fresh->comments === $fresh->comments);

$another = Post::find($post->id);
check('operator ?? memicu lazy load relasi', ($another->user->name ?? 'fallback') === 'Test User');
check('isset() pada relasi memicu lazy load', isset($another->user) && ! isset($another->missing));

check('delete() menghapus baris', $comment->delete() && Comment::find($comment->id) === null);

$before = Post::query()->count();
$post->delete();
check('delete() model mengurangi baris', Post::query()->count() === $before - 1 && Post::find($post->id) === null);

echo "\nORM: keamanan\n";
$unguarded = new class extends Model {
    protected array $fillable = ['name'];
};
$unguarded->fill(['name' => 'Aman', 'role' => 'admin']);
check('mass assignment hanya mengisi kolom $fillable', $unguarded->getAttribute('role') === null);

$noFillable = new class extends Model {
};
expectException('model tanpa $fillable menolak fill()', static function () use ($noFillable): void {
    $noFillable->fill(['name' => 'x']);
}, LogicException::class);

expectException('operator SQL tidak dikenal ditolak', static function (): void {
    Post::query()->where('title', 'DROP', 'x');
}, InvalidArgumentException::class);

expectException('identifier berbahaya ditolak', static function (): void {
    Post::query()->where('title; drop table users', '=', 'x');
}, InvalidArgumentException::class);

$injected = Post::query()->where('title', "x' OR '1'='1");
check('nilai user masuk ke bindings, bukan SQL', ! str_contains($injected->toSql(), "OR '1'") && $injected->getBindings() === ["x' OR '1'='1"]);
check('SQL memakai placeholder', substr_count($injected->toSql(), '?') === 1);

echo "\nView & CSRF\n";
$html = $app->container()->make(View::class)->render('errors/error', [
    'status' => 404,
    'message' => '<script>alert(1)</script>',
    'exception' => null,
], 'layouts/app');

check('view dirender ke dalam layout', str_contains($html, '<!DOCTYPE html>') && str_contains($html, 'Halaman tidak ditemukan'));
check('output view di-escape', str_contains($html, '&lt;script&gt;') && ! str_contains($html, '<script>'));

$token = Csrf::token();
check('token CSRF bertipe string 64 hex', preg_match('/^[0-9a-f]{64}$/', $token) === 1);
check('token CSRF valid diterima', Csrf::check($token));
check('token CSRF salah ditolak', ! Csrf::check(str_repeat('0', 64)));
check('token kosong ditolak', ! Csrf::check(null));

echo "\n----------------------------------------\n";
echo "Lulus: $passed, gagal: $failed\n";

exit($failed === 0 ? 0 : 1);
