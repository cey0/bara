<?php

declare(strict_types=1);

namespace Framework\View;

use RuntimeException;

/**
 * Plain PHP templates. `posts.index` maps to views/posts/index.php.
 * The rendered template is injected into the layout as $content.
 */
final class View
{
    public function __construct(private string $path)
    {
    }

    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, array_merge($data, ['content' => $content]));
    }

    private function renderFile(string $name, array $data): string
    {
        $file = $this->path . '/' . str_replace('.', '/', $name) . '.php';

        if (! is_file($file)) {
            throw new RuntimeException("View [$name] not found at $file.");
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
