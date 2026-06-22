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

    foreach (['install-wp-config-https.php', 'ensure-db-creds.sh', 'xdebug-on.sh', 'xdebug-off.sh'] as $script) {
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
        if (!validate_folder_name($folder)) {
            abort("Invalid folder name: {$folder}");
        }

        $projectDir = get_project_dir($folder);
        if (!is_dir($projectDir) || !file_exists($projectDir . '/.lando.yml')) {
            abort("Project not found: {$folder}");
        }

        return ['dir' => realpath($projectDir), 'folder' => $folder];
    }

    $current = detect_current_project();
    if (!$current) {
        abort("No project specified and not inside a project folder.\n  Usage: {$usage}");
    }

    return ['dir' => $current['dir'], 'folder' => $current['folder']];
}

function load_lenv_project(?string $folder, string $usage): array
{
    $project = resolve_lenv_project($folder, $usage);
    $values  = extract_lando_values(file_get_contents($project['dir'] . '/.lando.yml'));

    return array_merge($project, $values);
}

function format_xdebug_runtime_status(?array $runtime): string
{
    if ($runtime === null) {
        return 'n/a — lando not running';
    }

    if (!$runtime['loaded']) {
        return 'disabled';
    }

    $mode = $runtime['mode'] ?? '';

    return 'enabled' . ($mode !== '' ? " (mode={$mode})" : '');
}

function print_xdebug_status_lines(string $configured, ?array $runtime): void
{
    echo "Xdebug:\n";
    echo "  configured:  {$configured}\n";
    echo "  runtime:     " . format_xdebug_runtime_status($runtime) . "\n";
}

function print_project_status(array $project): void
{
    echo "Folder:      {$project['folder']}\n";
    echo "Path:        {$project['dir']}\n";
    echo "App name:    {$project['name']}\n";
    echo "Site URL:    https://{$project['name']}.lndo.site\n";
    echo "PHP:         {$project['php']}\n";
    echo "Database:    {$project['database']}\n";
    echo "Webserver:   {$project['via']}\n";
    print_xdebug_status_lines($project['xdebug'], get_lando_xdebug_runtime_status($project['dir']));
}

function destroy_lando_app(string $projectDir): void
{
    passthru(
        'cd ' . escapeshellarg($projectDir) . ' && lando destroy -y 2>/dev/null',
        $exitCode
    );
}

function remove_lenv_project(string $projectDir): void
{
    $projectDir = realpath($projectDir);

    if ($projectDir === false || !is_dir($projectDir)) {
        abort('Project directory not found.');
    }

    $parentDir = dirname($projectDir);
    $cwd       = realpath(getcwd());

    if ($cwd === $projectDir || str_starts_with($cwd, $projectDir . DIRECTORY_SEPARATOR)) {
        chdir($parentDir);
    }

    remove_tree($projectDir);
}

function run_lando_tooling(string $projectDir, string $tooling): int
{
    passthru('cd ' . escapeshellarg($projectDir) . ' && lando ' . $tooling, $exitCode);

    return $exitCode;
}

function is_wsl(): bool
{
    return file_exists('/proc/version')
        && str_contains((string) file_get_contents('/proc/version'), 'microsoft');
}

function find_orphan_build_engine_exes(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $exes = [];
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_file($path) && str_ends_with(strtolower($item), '.exe')) {
            $exes[] = $path;
        }
    }

    return $exes;
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

function remove_orphan_build_engine_exes(string $dir): int
{
    $removed = 0;
    foreach (find_orphan_build_engine_exes($dir) as $file) {
        if (@unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}

function command_exists(string $command): bool
{
    $path = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));

    return $path !== '';
}

function run_host_command(string $command, ?int $timeoutSeconds = null): array
{
    if ($timeoutSeconds !== null && command_exists('timeout')) {
        $command = 'timeout ' . $timeoutSeconds . ' ' . $command;
    }

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    return ['exit' => $exitCode, 'output' => implode("\n", $output)];
}

function get_docker_info_status(): array
{
    $result = run_host_command('docker info --format "{{.ServerVersion}}"');
    if ($result['exit'] !== 0) {
        return [
            'ok' => false,
            'detail' => trim($result['output']) ?: 'docker info failed',
        ];
    }

    return [
        'ok' => true,
        'detail' => 'Server ' . trim($result['output']),
    ];
}

function get_lando_version_status(): array
{
    if (!command_exists('lando')) {
        return ['ok' => false, 'detail' => 'lando not found in PATH'];
    }

    $result = run_host_command('lando version');
    if ($result['exit'] !== 0) {
        return ['ok' => false, 'detail' => trim($result['output']) ?: 'lando version failed'];
    }

    return ['ok' => true, 'detail' => trim($result['output'])];
}

function get_powershell_status(): array
{
    if (!command_exists('powershell.exe')) {
        return [
            'ok' => false,
            'detail' => 'powershell.exe not in PATH — Lando setup-build-engine needs it on WSL2',
        ];
    }

    $result = run_host_command(
        'powershell.exe -NoProfile -Command ' . escapeshellarg('$PSVersionTable.PSVersion.ToString()'),
        5
    );
    if ($result['exit'] !== 0) {
        $detail = trim($result['output']) ?: 'powershell.exe failed to run';
        if ($result['exit'] === 124 || str_contains($detail, 'UtilAcceptVsock') || str_contains($detail, 'timed out')) {
            $detail = 'WSL ↔ Windows interop broken (UtilAcceptVsock) — run wsl --shutdown from Windows; if that fails, double-click scripts/windows/fix-wsl-interop.bat as Administrator';
        }

        return [
            'ok' => false,
            'detail' => $detail,
        ];
    }

    return ['ok' => true, 'detail' => 'PowerShell ' . trim($result['output'])];
}

function get_wslvar_status(): array
{
    if (!command_exists('wslvar')) {
        return [
            'ok' => false,
            'detail' => 'wslvar not found — install wslu: sudo apt install wslu',
        ];
    }

    return ['ok' => true, 'detail' => trim((string) shell_exec('command -v wslvar'))];
}

function print_doctor_check(string $label, bool $ok, string $detail, bool $warn = false): void
{
    if ($ok) {
        echo "  ✔ {$label}: {$detail}\n";
        return;
    }

    $icon = $warn ? '⚠' : '✖';
    echo "  {$icon} {$label}: {$detail}\n";
}

function run_lando_doctor_checks(?array $project = null): int
{
    $issues = 0;

    echo "Host checks\n";

    if (is_wsl()) {
        print_doctor_check('Platform', true, 'WSL2');
    } else {
        print_doctor_check('Platform', true, 'Linux/macOS (non-WSL)');
    }

    $docker = get_docker_info_status();
    print_doctor_check('Docker', $docker['ok'], $docker['detail']);
    if (!$docker['ok']) {
        $issues++;
    }

    $lando = get_lando_version_status();
    print_doctor_check('Lando', $lando['ok'], $lando['detail']);
    if (!$lando['ok']) {
        $issues++;
    }

    if (is_wsl()) {
        $powershell = get_powershell_status();
        print_doctor_check('PowerShell', $powershell['ok'], $powershell['detail']);
        if (!$powershell['ok']) {
            $issues++;
        }

        $wslvar = get_wslvar_status();
        print_doctor_check('wslvar', $wslvar['ok'], $wslvar['detail'], !$wslvar['ok']);
        if (!$wslvar['ok']) {
            $issues++;
        }
    }

    if ($project !== null) {
        echo "\nProject checks ({$project['folder']})\n";

        $exes = find_orphan_build_engine_exes($project['dir']);
        if ($exes === []) {
            print_doctor_check('Build engine orphans', true, 'none');
        } else {
            $totalBytes = 0;
            foreach ($exes as $file) {
                $totalBytes += (int) filesize($file);
            }
            print_doctor_check(
                'Build engine orphans',
                false,
                count($exes) . ' file(s), ' . format_bytes($totalBytes) . ' — run lenv fix or find . -maxdepth 1 -name \'*.exe\' -delete'
            );
            $issues++;
        }
    }

    echo "\n";
    if ($issues === 0) {
        echo "All checks passed.\n";
        if (is_wsl()) {
            echo "Use `lando start` day to day. Run `lenv fix` when recovering from WSL2/Docker issues.\n";
        }
        return 0;
    }

    echo "{$issues} issue(s) found.\n\n";
    echo "Quick recovery on WSL2:\n";
    echo "  1. wsl --shutdown   (PowerShell/CMD on Windows)\n";
    echo "  2. Reopen WSL, run: lenv doctor\n";
    if ($project !== null) {
        echo "  3. lenv fix {$project['folder']}\n\n";
    } else {
        echo "  3. cd <project-folder> && lenv fix\n\n";
    }
    echo "If PowerShell is still broken after step 1, double-click scripts/windows/fix-wsl-interop.bat\n";
    echo "as Administrator on Windows (keep the .ps1 beside it — see docs/troubleshooting.md).\n\n";
    echo "Do not run lenv fix while PowerShell/interop is broken — Lando will\n";
    echo "download a ~500 MB Windows build-engine .exe into the project and fail anyway.\n\n";
    echo "See docs/troubleshooting.md for the full guide.\n";

    return 1;
}

function assert_wsl_interop_for_lando(): void
{
    if (!is_wsl()) {
        return;
    }

    $powershell = get_powershell_status();
    if ($powershell['ok']) {
        return;
    }

    abort(
        "WSL ↔ Windows interop is broken — Lando cannot start yet.\n\n"
        . "  {$powershell['detail']}\n\n"
        . "Why this matters on WSL2:\n"
        . "  Lando + Docker Desktop uses a Windows build engine (.exe) and PowerShell\n"
        . "  to install its CA and build images. Docker CLI works in Ubuntu, but Lando\n"
        . "  setup still needs Windows interop. When it fails, Lando downloads a ~500 MB\n"
        . "  .exe into your project folder and then errors out.\n\n"
        . "Fix first (PowerShell/CMD on Windows, not inside WSL):\n"
        . "  wsl --shutdown\n\n"
        . "If that does not restore interop, on Windows double-click as Administrator:\n"
        . "  scripts/windows/fix-wsl-interop.bat\n"
        . "  (copy fix-wsl-interop.bat + .ps1 together — see scripts/windows/README.md)\n\n"
        . "Then reopen WSL and run:\n"
        . "  lenv doctor\n"
        . "  lenv fix\n\n"
        . "This cannot be avoided via .lando.yml or lenv templates — it is Lando host setup.\n"
        . "See docs/troubleshooting.md for more."
    );
}

function prepare_lenv_fix(string $projectDir): void
{
    $removed = remove_orphan_build_engine_exes($projectDir);
    if ($removed > 0) {
        echo "Removed {$removed} orphan build-engine .exe file(s).\n\n";
    }

    $docker = get_docker_info_status();
    if (!$docker['ok']) {
        abort("Docker is not reachable: {$docker['detail']}\n\n"
            . "On WSL2: open Docker Desktop, check WSL Integration for Ubuntu, then run `docker info`.");
    }

    assert_wsl_interop_for_lando();

    if (!is_wsl()) {
        return;
    }

    echo "WSL2: syncing Lando with Docker Desktop (`lando update`)...\n\n";
    passthru('cd ' . escapeshellarg($projectDir) . ' && lando update', $exitCode);
    if ($exitCode !== 0) {
        abort('lando update failed. Run `lenv doctor` for details.');
    }

    echo "\n";
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
