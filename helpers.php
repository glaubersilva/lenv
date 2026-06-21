<?php

define('LENV_DIR', __DIR__);
define('TEMPLATE_DIR', __DIR__ . '/templates');
define('PLACEHOLDER', '__PROJECT_NAME__');

function validate_project_name(string $name): bool
{
    return (bool) preg_match('/^[a-z0-9]+([.-][a-z0-9]+)*$/', $name);
}

function validate_folder_name(string $name): bool
{
    return $name !== ''
        && !str_contains($name, '/')
        && !str_contains($name, '\\')
        && !str_contains($name, '..')
        && preg_match('/^[a-z0-9._-]+$/', $name);
}

function get_project_dir(string $folder): string
{
    return getcwd() . '/' . $folder;
}

function detect_current_project(): ?array
{
    $cwd      = getcwd();
    $landoFile = $cwd . '/.lando.yml';

    if (!file_exists($landoFile)) {
        return null;
    }

    $lando  = file_get_contents($landoFile);
    $values = extract_lando_values($lando);

    return array_merge(['dir' => $cwd, 'folder' => basename($cwd)], $values);
}

function extract_lando_values(string $content): array
{
    preg_match('/^name:\s*(.+)$/m',  $content, $m); $name     = trim($m[1] ?? PLACEHOLDER);
    preg_match("/php: '(.+?)'/",      $content, $m); $php      = $m[1] ?? '8.3';
    preg_match('/^  database: (.+)$/m', $content, $m); $database = trim($m[1] ?? 'mysql:8.0');
    preg_match('/^  via: (.+)$/m',    $content, $m); $via      = trim($m[1] ?? 'apache');
    preg_match('/^  xdebug: (.+)$/m', $content, $m); $xdebug   = trim($m[1] ?? 'off');

    return compact('name', 'php', 'database', 'via', 'xdebug');
}

function apply_template(string $content, string $name, string $php = '8.3', string $database = 'mysql:8.0', array $extra = []): string
{
    $content = str_replace(PLACEHOLDER, $name, $content);
    $content = preg_replace("/php: '[0-9.]+'/", "php: '{$php}'", $content);
    $content = preg_replace('/^  database: (?:mysql|mariadb):[0-9.]+/m', "  database: {$database}", $content);
    foreach ($extra as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
    }
    return $content;
}

function prompt(string $question, string $default = ''): string
{
    $hint  = $default !== '' ? " [{$default}]" : '';
    echo "{$question}{$hint}: ";
    $input = trim(fgets(STDIN));
    return $input !== '' ? $input : $default;
}

function confirm(string $question, string $default = 'y'): bool
{
    return strtolower(prompt("{$question} [y/n]", $default)) === 'y';
}

function abort(string $message): void
{
    echo "\nError: {$message}\n\n";
    exit(1);
}

function box(string $title): void
{
    $width    = 42;
    $padTotal = max(0, $width - strlen($title));
    $padLeft  = (int) floor($padTotal / 2);
    $padRight = (int) ceil($padTotal / 2);
    $line     = str_repeat(' ', $padLeft) . $title . str_repeat(' ', $padRight);
    echo "\n╔" . str_repeat('═', $width) . "╗\n";
    echo "║{$line}║\n";
    echo "╚" . str_repeat('═', $width) . "╝\n\n";
}
