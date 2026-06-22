<?php
// lenv new

box('New Lando Environment');

echo "Project name (Lando app name and URL subdomain).\n";
echo "  Example: mysite  →  https://mysite.lndo.site\n\n";
$projectName = prompt("Project name");

if (empty($projectName)) {
    abort("Project name is required.");
}
if (!validate_project_name($projectName)) {
    abort("Project name must be lowercase letters, numbers, hyphens and dots (no leading/trailing dot or hyphen).");
}

$defaultFolder = "{$projectName}.lndo.site";
echo "
  ⓘ  Press Enter to use default
";
$folderName = prompt("Folder name", $defaultFolder);
if (!validate_folder_name($folderName)) {
    abort("Folder name must be lowercase letters, numbers, hyphens, dots and underscores only.");
}
$projectDir = get_project_dir($folderName);

if (is_dir($projectDir)) {
    abort("Folder already exists: {$folderName}");
}

$phpOptions = ['8.3', '8.2', '8.1', '8.0', '7.4'];
echo "
  ⓘ  Press Enter to use default. Options: " . implode(', ', $phpOptions) . "
";
$phpVersion = prompt("PHP version", '8.3');
if (!in_array($phpVersion, $phpOptions)) {
    abort("Invalid PHP version. Choose from: " . implode(', ', $phpOptions));
}

$dbOptions = ['mysql:8.0', 'mysql:5.7', 'mariadb:11.4', 'mariadb:10.6'];
echo "
  ⓘ  Press Enter to use default. Options: " . implode(', ', $dbOptions) . "
";
$database = prompt("Database", 'mysql:8.0');
if (!in_array($database, $dbOptions)) {
    abort("Invalid database. Choose from: " . implode(', ', $dbOptions));
}

$viaOptions = webserver_options();
echo "
  ⓘ  Press Enter to use default. Options: " . implode(', ', $viaOptions) . "
  ⚠  litespeed may fail on Lando 3.26.x — you will be warned before proceeding
";
$webserver = prompt("Webserver", 'apache');
if (!validate_webserver($webserver)) {
    abort("Invalid webserver. Choose from: " . implode(', ', $viaOptions));
}
if (is_litespeed_webserver($webserver) && !confirm_litespeed_choice()) {
    abort("Aborted.");
}
if (is_frankenphp_webserver($webserver) && !validate_frankenphp_php($phpVersion)) {
    abort("FrankenPHP requires PHP 8.0 or newer. Choose from: " . implode(', ', frankenphp_php_options()));
}

$xdebugOptions = ['off', 'debug'];
echo "
  ⓘ  Press Enter to use default. Options: " . implode(', ', $xdebugOptions) . "
";
$xdebug = prompt("Xdebug", 'off');
if (!in_array($xdebug, $xdebugOptions)) {
    abort("Invalid Xdebug value. Choose from: " . implode(', ', $xdebugOptions));
}

echo "\n";
echo "┌─────────────────────────────────────────────────────────\n";
echo "│  Summary\n";
echo "├─────────────────────────────────────────────────────────\n";
echo "│  App name:   {$projectName}\n";
echo "│  Folder:     {$folderName}/\n";
echo "│  PHP:        {$phpVersion}\n";
echo "│  Database:   {$database}\n";
echo "│  Webserver:  {$webserver}\n";
echo "│  Xdebug:     {$xdebug}\n";
echo "├─────────────────────────────────────────────────────────\n";
echo "│  URLs\n";
echo "│    Site:       https://{$projectName}.lndo.site\n";
echo "│    WP Admin:   https://{$projectName}.lndo.site/wp-admin\n";
echo "│    phpMyAdmin: http://phpmyadmin.{$projectName}.lndo.site\n";
echo "│    Mailhog:    http://mailhog.{$projectName}.lndo.site\n";
echo "└─────────────────────────────────────────────────────────\n\n";

if (!confirm("Create this environment?")) {
    abort("Aborted.");
}

echo "\nCreating environment...\n";

mkdir($projectDir, 0755, true);

$isWsl = file_exists('/proc/version') && str_contains(file_get_contents('/proc/version'), 'microsoft');
$ideProjectPath = $isWsl
    ? trim(shell_exec('wslpath -w ' . escapeshellarg($projectDir)) ?? $projectDir)
    : $projectDir;
$extra = ['__PROJECT_IDE_PATH__' => $ideProjectPath];

write_project_lando($projectDir, [
    'name'     => $projectName,
    'php'      => $phpVersion,
    'database' => $database,
    'via'      => $webserver,
    'xdebug'   => $xdebug,
], $extra);
echo "  ✔ .lando.yml\n";
echo "  ✔ .lando/php.ini\n";
echo "  ✔ .lando/xdebug-on.sh\n";
echo "  ✔ .lando/xdebug-off.sh\n";
if (is_frankenphp_webserver($webserver)) {
    echo "  ✔ docker/\n";
}

$readme = apply_template(
    file_get_contents(TEMPLATE_DIR . '/README.md'),
    $projectName,
    $phpVersion,
    $database,
    $webserver,
    $xdebug,
    $extra
);
file_put_contents($projectDir . '/README.md', $readme);
echo "  ✔ README.md\n";

if (is_dir(TEMPLATE_DIR . '/docs')) {
    mkdir($projectDir . '/docs', 0755, true);
    foreach (glob(TEMPLATE_DIR . '/docs/*.md') as $doc) {
        $content = apply_template(
            file_get_contents($doc),
            $projectName,
            $phpVersion,
            $database,
            $webserver,
            $xdebug,
            $extra
        );
        file_put_contents($projectDir . '/docs/' . basename($doc), $content);
        echo "  ✔ docs/" . basename($doc) . "\n";
    }
}

foreach (['lenv-phpinfo.php', 'lenv-xdebuginfo.php'] as $file) {
    copy(TEMPLATE_DIR . '/' . $file, $projectDir . '/' . $file);
    echo "  ✔ {$file}\n";
}

box('Environment created successfully!');
echo "Next steps:\n\n";
echo "  cd {$folderName}\n";
if ($isWsl) {
    echo "  lando start         # use lenv fix if start fails on WSL2\n";
} else {
    echo "  lando start\n";
}
echo "  lando wp-install\n\n";
if ($isWsl) {
    echo "If start fails, run `lenv doctor` then `lenv fix` — see docs/troubleshooting.md.\n\n";
}
