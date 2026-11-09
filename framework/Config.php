<?php

declare(strict_types=1);

namespace Framework;

/**
 * Config repository. Every PHP file in config/ is loaded and keyed by filename,
 * so config/database.php becomes config('database.driver').
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function load(string $directory): void
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $this->items[basename($file, '.php')] = require $file;
        }
    }

    public function get(string $key, $default = null)
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[$segments[0]] = $value;
    }
}
