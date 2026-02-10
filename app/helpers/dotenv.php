<?php

function loadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Пропускаем комментарии
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Разбираем KEY=VALUE
        [$name, $value] = array_pad(explode('=', $line, 2), 2, null);

        if ($name === null || $value === null) {
            continue;
        }

        $name  = trim($name);
        $value = trim($value);

        // Убираем кавычки
        $value = trim($value, '\'""');

        // Не перезаписываем уже существующие значения окружения
        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
