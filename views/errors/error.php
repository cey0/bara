<div class="card">
    <p class="error-code"><?= e($status) ?></p>
    <h1 style="font-size:1.5rem"><?= $status === 404 ? 'Halaman tidak ditemukan' : 'Ada yang tidak beres' ?></h1>
    <p style="color:var(--muted);margin-bottom:1.25rem"><?= e($message) ?></p>

    <?php if ($exception !== null): ?>
        <details>
            <summary style="cursor:pointer;color:var(--muted);font-size:.88rem">Detail exception (mode debug)</summary>
            <pre style="overflow:auto;background:#f2ece3;padding:1rem;border-radius:10px;font-size:.8rem"><?= e((string) $exception) ?></pre>
        </details>
    <?php endif; ?>

    <p style="margin-top:1.5rem"><a href="/">&larr; Kembali ke beranda</a></p>
</div>
