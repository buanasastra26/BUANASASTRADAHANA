<?php
namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewFile = ROOT_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = ROOT_PATH . '/app/Views/' . $layout . '.php';
        if (!is_file($viewFile)) throw new \RuntimeException("View tidak ditemukan: {$view}");
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require $layoutFile;
    }
}
