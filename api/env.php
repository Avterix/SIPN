<?php
// Loader .env sederhana (tanpa composer).
// Di Vercel nilai diambil dari Environment Variables, jadi file .env tidak diperlukan di sana.

function load_env($path)
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $q = substr($v, 0, 1);
        if (($q === '"' || $q === "'") && strlen($v) > 1 && substr($v, -1) === $q) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

function env($key, $default = null)
{
    $v = getenv($key);
    if ($v === false && isset($_ENV[$key])) {
        $v = $_ENV[$key];
    }
    if ($v === false && isset($_SERVER[$key])) {
        $v = $_SERVER[$key];
    }
    return ($v === false || $v === '') ? $default : $v;
}

load_env(__DIR__ . '/../.env');
