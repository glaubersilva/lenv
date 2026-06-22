<?php

define('LENV_DIR', __DIR__);
define('TEMPLATE_DIR', __DIR__ . '/templates');
define('PLACEHOLDER', '__PROJECT_NAME__');

function webserver_options(): array
{
    return ['apache', 'nginx', 'litespeed', 'frankenphp'];
}

function validate_webserver(string $webserver): bool
{
    return in_array($webserver, webserver_options(), true);
}

function is_litespeed_webserver(string $webserver): bool
{
    return $webserver === 'litespeed';
}

function print_litespeed_warning(): void
{
    echo "\n⚠️  LiteSpeed warning\n";
    echo "   The Lando wordpress recipe advertises via: litespeed, but Lando 3.26.x\n";
    echo "   currently fails at lando rebuild with:\n";
    echo "   TypeError: Cannot read properties of undefined (reading 'version')\n";
    echo "   Use apache, nginx, or frankenphp unless you have confirmed LiteSpeed\n";
    echo "   works on your Lando version.\n\n";
}

function confirm_litespeed_choice(): bool
{
    print_litespeed_warning();

    return confirm('Continue with LiteSpeed anyway?', 'n');
}

function is_frankenphp_webserver(string $webserver): bool
{
    return $webserver === 'frankenphp';
}

function is_frankenphp_lando(string $content): bool
{
    return str_contains($content, 'docker/frankenphp/Dockerfile');
}

function frankenphp_php_options(): array
{
    return ['8.3', '8.2', '8.1', '8.0'];
}

function validate_frankenphp_php(string $php): bool
{
    return in_array($php, frankenphp_php_options(), true);
}

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
    $cwd       = getcwd();
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
    preg_match('/^name:\s*(.+)$/m', $content, $m);
    $name = trim($m[1] ?? PLACEHOLDER);

    if (is_frankenphp_lando($content)) {
        preg_match("/# lenv-php: '(.+?)'/", $content, $m);
        $php = $m[1] ?? '8.3';
        preg_match('/# lenv-database: (.+)$/m', $content, $m);
        $database = trim($m[1] ?? 'mysql:8.0');
        preg_match('/# lenv-xdebug: (.+)$/m', $content, $m);
        $xdebug = trim($m[1] ?? 'off');
        $via = 'frankenphp';

        return compact('name', 'php', 'database', 'via', 'xdebug');
    }

    preg_match("/php: '(.+?)'/", $content, $m);
    $php = $m[1] ?? '8.3';
    preg_match('/^  database: (.+)$/m', $content, $m);
    $database = trim($m[1] ?? 'mysql:8.0');
    preg_match('/^  via: (.+)$/m', $content, $m);
    $via = trim($m[1] ?? 'apache');
    preg_match('/^  xdebug: (.+)$/m', $content, $m);
    $xdebug = trim($m[1] ?? 'off');

    return compact('name', 'php', 'database', 'via', 'xdebug');
}

function get_lando_template_path(string $webserver): string
{
    return is_frankenphp_webserver($webserver)
        ? TEMPLATE_DIR . '/.lando.frankenphp.yml'
        : TEMPLATE_DIR . '/.lando.yml';
}

function apply_template(string $content, string $name, string $php = '8.3', string $database = 'mysql:8.0', string $via = 'apache', string $xdebug = 'off', array $extra = []): string
{
    $content = str_replace(PLACEHOLDER, $name, $content);

    if (is_frankenphp_lando($content) || $via === 'frankenphp') {
        $content = preg_replace("/# lenv-php: '.+'/", "# lenv-php: '{$php}'", $content);
        $content = preg_replace('/# lenv-database: .+/', "# lenv-database: {$database}", $content);
        $content = preg_replace('/# lenv-xdebug: .+/', "# lenv-xdebug: {$xdebug}", $content);
        $content = preg_replace('/^        LENV_XDEBUG: .+$/m', "        LENV_XDEBUG: {$xdebug}", $content);
        $content = preg_replace("/PHP_VERSION:\s*'.+'/", "PHP_VERSION: '{$php}'", $content);
        $content = preg_replace('/^    type: (?:mysql|mariadb):[0-9.]+/m', "    type: {$database}", $content);
    } else {
        $content = preg_replace("/php: '[0-9.]+'/", "php: '{$php}'", $content);
        $content = preg_replace('/^  database: (?:mysql|mariadb):[0-9.]+/m', "  database: {$database}", $content);
        $content = preg_replace('/^  via: .+$/m', "  via: {$via}", $content);
        $content = preg_replace('/^  xdebug: .+$/m', "  xdebug: {$xdebug}", $content);
    }

    foreach ($extra as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
    }

    return $content;
}

function set_lando_via(string $content, string $via): string
{
    if (preg_match('/^  via: /m', $content)) {
        return preg_replace('/^  via: .+$/m', "  via: {$via}", $content);
    }

    return preg_replace('/^  database: .+$/m', "$0\n  via: {$via}", $content);
}

function copy_tree(string $src, string $dest): void
{
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $from = $src . '/' . $item;
        $to   = $dest . '/' . $item;

        if (is_dir($from)) {
            copy_tree($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            remove_tree($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

function sync_frankenphp_docker(string $projectDir): void
{
    copy_tree(TEMPLATE_DIR . '/docker', $projectDir . '/docker');
}

function remove_frankenphp_docker(string $projectDir): void
{
    remove_tree($projectDir . '/docker');
}

function sync_lando_scripts(string $projectDir): void
{
    if (!is_dir($projectDir . '/.lando')) {
        mkdir($projectDir . '/.lando', 0755, true);
    }

    foreach (['install-wp-config-https.php', 'xdebug-on.sh', 'xdebug-off.sh'] as $script) {
        copy(TEMPLATE_DIR . '/.lando/' . $script, $projectDir . '/.lando/' . $script);
    }
}

function sync_lando_php_ini(string $projectDir, string $xdebug): void
{
    if (!is_dir($projectDir . '/.lando')) {
        mkdir($projectDir . '/.lando', 0755, true);
    }

    if ($xdebug === 'off') {
        file_put_contents($projectDir . '/.lando/php.ini', ";\n");
        return;
    }

    copy(TEMPLATE_DIR . '/.lando/php.ini', $projectDir . '/.lando/php.ini');
}

function write_project_lando(string $projectDir, array $values, array $extra = []): void
{
    $template = file_get_contents(get_lando_template_path($values['via']));
    $lando    = apply_template(
        $template,
        $values['name'],
        $values['php'],
        $values['database'],
        $values['via'],
        $values['xdebug'],
        $extra
    );

    file_put_contents($projectDir . '/.lando.yml', $lando);

    if (is_frankenphp_webserver($values['via'])) {
        sync_frankenphp_docker($projectDir);
    } else {
        remove_frankenphp_docker($projectDir);
    }

    sync_lando_scripts($projectDir);
    sync_lando_php_ini($projectDir, $values['xdebug']);
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

function resolve_lenv_project(?string $folder, string $usage): array
{
    if ($folder) {
        $projectDir = get_project_dir($folder);
        if (!is_dir($projectDir) || !file_exists($projectDir . '/.lando.yml')) {
            abort("Project not found: {$folder}");
        }

        return ['dir' => $projectDir, 'folder' => $folder];
    }

    $current = detect_current_project();
    if (!$current) {
        abort("No project specified and not inside a project folder.\n  Usage: {$usage}");
    }

    return ['dir' => $current['dir'], 'folder' => $current['folder']];
}

function run_lando_tooling(string $projectDir, string $tooling): int
{
    passthru('cd ' . escapeshellarg($projectDir) . ' && lando ' . $tooling, $exitCode);

    return $exitCode;
}

function get_lando_xdebug_runtime_status(string $projectDir): ?array
{
    $php = <<<'PHP'
php -r 'if (!extension_loaded("xdebug")) { echo json_encode(["loaded" => false]); exit; } echo json_encode(["loaded" => true, "mode" => ini_get("xdebug.mode")]);'
PHP;
    $cmd    = 'cd ' . escapeshellarg($projectDir) . ' && lando ssh -s appserver -c ' . escapeshellarg($php) . ' 2>/dev/null';
    $output = shell_exec($cmd);

    if ($output === null || trim($output) === '') {
        return null;
    }

    $decoded = json_decode(trim($output), true);

    return is_array($decoded) ? $decoded : null;
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
