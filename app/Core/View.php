<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight PHP view renderer with nested-section layout support.
 *
 *   In a layout file:    <?= $__yield ?>
 *   In a child template: $this->extend('layouts/app');  (and call $this->section()/$this->endSection())
 */
final class View
{
    public static function render(string $template, array $data = []): string
    {
        $renderer = new self();
        return $renderer->doRender($template, $data);
    }

    private string $layout = '';
    /** @var array<string,string> */
    private array $sections = [];
    private string $currentSection = '';

    private function doRender(string $template, array $data): string
    {
        $path = APP_PATH . '/Views/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $content = (string) ob_get_clean();

        if ($this->layout !== '') {
            $layoutPath = APP_PATH . '/Views/' . str_replace('.', '/', $this->layout) . '.php';
            if (!is_file($layoutPath)) {
                throw new \RuntimeException("Layout not found: {$this->layout}");
            }
            $__yield = $content;
            $__sections = $this->sections;
            ob_start();
            require $layoutPath;
            return (string) ob_get_clean();
        }

        return $content;
    }

    /** Used inside templates: $this->extend('layouts/app') */
    public function extend(string $layout): void { $this->layout = $layout; }

    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $this->sections[$this->currentSection] = (string) ob_get_clean();
        $this->currentSection = '';
    }
}
