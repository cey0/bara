<section>
    <p class="eyebrow">Framework PHP native</p>
    <h1>Fondasi kecil yang serasa Laravel.</h1>
    <p class="lead">
        Router dengan parameter, service container dengan autowiring, MVC, dan ORM
        buatan sendiri di atas PDO. Semua kode inti terbaca, tanpa dependensi.
    </p>

    <div class="stats">
        <div class="stat"><strong><?= e($postCount) ?></strong><span>tulisan</span></div>
        <div class="stat"><strong><?= e($userCount) ?></strong><span>penulis</span></div>
        <div class="stat"><strong><?= e($commentCount) ?></strong><span>komentar</span></div>
    </div>

    <div class="page-head">
        <h2 style="font-size:1.6rem;margin:0">Tulisan terbaru</h2>
        <a href="/posts">Semua tulisan &rarr;</a>
    </div>

    <?php if ($latest->isEmpty()): ?>
        <p class="empty">Belum ada tulisan. Jalankan <span class="code">php bin/console seed</span> untuk data contoh.</p>
    <?php else: ?>
        <ul class="post-list">
            <?php foreach ($latest as $post): ?>
                <li class="card">
                    <h2><a href="/posts/<?= e($post->id) ?>"><?= e($post->title) ?></a></h2>
                    <p class="meta">
                        oleh <?= e($post->user->name ?? 'Anonim') ?>
                        · <?= e(date('d M Y', strtotime((string) $post->created_at))) ?>
                    </p>
                    <p style="margin:0"><?= e(mb_strimwidth($post->body, 0, 180, '…')) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section style="margin-top:3rem">
    <h2 style="font-size:1.15rem">Yang ada di dalamnya</h2>
    <ul class="post-list" style="margin-top:.75rem">
        <li class="card">
            <strong>Router</strong>
            <p class="meta" style="margin:.25rem 0 0">Route dengan <span class="code">{parameter}</span>, controller resolved lewat container, plus CSRF untuk request yang mengubah data.</p>
        </li>
        <li class="card">
            <strong>Service container</strong>
            <p class="meta" style="margin:.25rem 0 0">Binding, singleton, dan autowiring lewat reflection. Cukup type-hint di constructor.</p>
        </li>
        <li class="card">
            <strong>ORM</strong>
            <p class="meta" style="margin:.25rem 0 0">Model, CRUD, query builder berantai, dan relasi <span class="code">hasMany</span> / <span class="code">belongsTo</span> dengan prepared statements.</p>
        </li>
    </ul>
</section>
