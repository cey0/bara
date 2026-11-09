<article class="card">
    <p class="eyebrow">Tulisan</p>
    <h1><?= e($post->title) ?></h1>
    <p class="meta">
        oleh <?= e($post->user->name ?? 'Anonim') ?>
        · <?= e(date('d M Y H:i', strtotime((string) $post->created_at))) ?>
    </p>
    <div class="prose"><?= nl2br(e($post->body)) ?></div>

    <hr class="divider">

    <form method="post" action="/posts/<?= e($post->id) ?>" onsubmit="return confirm('Hapus tulisan ini?')">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button class="button button-ghost" type="submit">Hapus tulisan</button>
    </form>
</article>

<section style="margin-top:2.5rem">
    <h2 style="font-size:1.25rem">Komentar (<?= e($comments->count()) ?>)</h2>

    <?php if ($comments->isEmpty()): ?>
        <p class="empty">Belum ada komentar.</p>
    <?php else: ?>
        <ul class="post-list" style="margin-top:.75rem">
            <?php foreach ($comments as $comment): ?>
                <li class="card" style="padding:1rem 1.25rem">
                    <p class="meta" style="margin:0 0 .3rem"><?= e($comment->user->name ?? 'Anonim') ?></p>
                    <p style="margin:0"><?= e($comment->body) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p style="margin-top:1.5rem"><a href="/posts">&larr; Kembali ke daftar</a></p>
</section>
