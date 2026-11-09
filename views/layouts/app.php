<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($title) ? $title . ' · ' . config('app.name') : config('app.name')) ?></title>
    <style>
        :root {
            --bg: #faf7f2;
            --surface: #ffffff;
            --ink: #2c2620;
            --muted: #7d7267;
            --line: #e6ded2;
            --accent: #b4552d;
            --accent-dark: #8f3f1f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.65 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { color: var(--accent-dark); text-decoration: underline; }

        .container { width: min(760px, calc(100% - 3rem)); margin: 0 auto; }

        .site-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 1.25rem 0; border-bottom: 1px solid var(--line);
            width: min(760px, calc(100% - 3rem)); margin: 0 auto;
        }

        .brand {
            font: 600 1.15rem/1 ui-serif, Georgia, serif;
            letter-spacing: .06em; text-transform: uppercase; color: var(--ink);
        }
        .brand:hover { text-decoration: none; color: var(--accent); }

        nav { display: flex; align-items: center; gap: 1.25rem; font-size: .92rem; }
        nav a { color: var(--muted); }
        nav a:hover { color: var(--ink); text-decoration: none; }

        main { padding: 2.5rem 0 3rem; }

        .button {
            display: inline-block; padding: .6rem 1.1rem; border: 0; border-radius: 999px;
            background: var(--accent); color: #fff; font: inherit; font-size: .92rem;
            font-weight: 500; cursor: pointer;
        }
        .button:hover { background: var(--accent-dark); color: #fff; text-decoration: none; }
        .button-sm { padding: .35rem .8rem; font-size: .84rem; }
        .button-ghost { background: transparent; color: var(--muted); border: 1px solid var(--line); }
        .button-ghost:hover { background: #f2ece3; color: var(--ink); }

        h1, h2, h3 { font-family: ui-serif, Georgia, serif; line-height: 1.2; font-weight: 600; }
        h1 { font-size: clamp(1.9rem, 4vw, 2.6rem); margin: 0 0 .75rem; }
        h2 { font-size: 1.35rem; margin: 0 0 .35rem; }

        .lead { font-size: 1.1rem; color: var(--muted); max-width: 46ch; }

        .eyebrow {
            font-size: .78rem; letter-spacing: .14em; text-transform: uppercase;
            color: var(--accent); margin: 0 0 .6rem;
        }

        .stats { display: flex; gap: 2.5rem; margin: 2rem 0 3rem; padding: 1.25rem 0; border-block: 1px solid var(--line); }
        .stat strong { display: block; font: 600 1.6rem/1.1 ui-serif, Georgia, serif; }
        .stat span { font-size: .82rem; color: var(--muted); }

        .card {
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 1.35rem 1.5rem; margin-bottom: 1rem;
        }

        .meta { font-size: .84rem; color: var(--muted); margin: 0 0 .6rem; }
        .meta a { color: var(--muted); text-decoration: underline; }

        .post-list { list-style: none; padding: 0; margin: 0; }

        .prose p { margin: 0 0 1rem; }

        .page-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }

        .stack > * + * { margin-top: 1rem; }

        form .field { margin-bottom: 1.1rem; }
        label { display: block; font-size: .88rem; font-weight: 500; margin-bottom: .35rem; }
        input[type="text"], textarea, select {
            width: 100%; padding: .65rem .8rem; border: 1px solid var(--line); border-radius: 10px;
            background: #fff; font: inherit; color: var(--ink);
        }
        textarea { min-height: 180px; resize: vertical; }
        input:focus, textarea:focus, select:focus { outline: 2px solid #e6c9b8; outline-offset: 1px; }

        .alert {
            border: 1px solid #e5c4b6; background: #fbeee7; color: #7a3a1d;
            border-radius: 10px; padding: .7rem 1rem; margin-bottom: 1.25rem; font-size: .92rem;
        }

        .divider { border: 0; border-top: 1px solid var(--line); margin: 2rem 0; }

        .empty { color: var(--muted); font-style: italic; }

        .site-footer {
            display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            padding: 1.5rem 0 2.5rem; border-top: 1px solid var(--line);
            font-size: .8rem; color: var(--muted);
        }

        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .85em; background: #f2ece3; border-radius: 6px; padding: .1rem .35rem;
        }

        .error-code { font: 600 4rem/1 ui-serif, Georgia, serif; color: var(--line); margin: 0; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">Bara</a>
        <nav>
            <a href="/">Beranda</a>
            <a href="/posts">Tulisan</a>
            <a class="button button-sm" href="/posts/create">Tulis</a>
        </nav>
    </header>

    <main class="container">
        <?= $content ?>
    </main>

    <footer class="site-footer container">
        <span>Bara — framework PHP native, tanpa Composer.</span>
        <span>Router · Service Container · ORM di atas PDO</span>
    </footer>
</body>
</html>
