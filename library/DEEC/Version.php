<?php

function dewawiVersion(): ?string
{
    $file = dirname(__DIR__, 2) . '/configs/version.php';
    if (!is_file($file) || !is_readable($file)) return null;
    ob_start();
    try {
        $version = require $file;
        return ob_get_length() === 0 && is_string($version) && preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D', $version) ? $version : null;
    } catch (Throwable $error) {
        return null;
    } finally {
        ob_end_clean();
    }
}
