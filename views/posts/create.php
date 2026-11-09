<p class="eyebrow">Tulis</p>
<h1>Tulisan baru</h1>

<?php if (! empty($error)): ?>
    <div class="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/posts" class="card">
    <?= csrf_field() ?>

    <div class="field">
        <label for="user_id">Penulis</label>
        <select id="user_id" name="user_id">
            <?php foreach ($users as $user): ?>
                <option value="<?= e($user->id) ?>" <?= isset($old['user_id']) && (int) $old['user_id'] === (int) $user->id ? 'selected' : '' ?>>
                    <?= e($user->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="title">Judul</label>
        <input id="title" type="text" name="title" maxlength="120" value="<?= e($old['title'] ?? '') ?>" autofocus>
    </div>

    <div class="field">
        <label for="body">Isi</label>
        <textarea id="body" name="body"><?= e($old['body'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;align-items:center;gap:1rem">
        <button class="button" type="submit">Simpan</button>
        <a href="/posts" style="color:var(--muted)">Batal</a>
    </div>
</form>
