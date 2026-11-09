<div class="page-head">
    <div>
        <p class="eyebrow">Arsip</p>
        <h1 style="margin:0">Tulisan</h1>
    </div>
    <a class="button" href="/posts/create">Tulis baru</a>
</div>

<?php if ($posts->isEmpty()): ?>
    <div class="card">
        <p class="empty" style="margin:0">
            Belum ada tulisan. Jalankan <span class="code">php bin/console seed</span>,
            atau <a href="/posts/create">tulis yang pertama</a>.
        </p>
    </div>
<?php else: ?>
    <ul class="post-list">
        <?php foreach ($posts as $post): ?>
            <li class="card">
                <h2><a href="/posts/<?= e($post->id) ?>"><?= e($post->title) ?></a></h2>
                <p class="meta">
                    oleh <?= e($post->user->name ?? 'Anonim') ?>
                    · <?= e(date('d M Y', strtotime((string) $post->created_at))) ?>
                </p>
                <p style="margin:0"><?= e(mb_strimwidth($post->body, 0, 200, '…')) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
