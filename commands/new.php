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
$projectDir    = get_project_dir($folderName);

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

echo "\n";
echo "┌─────────────────────────────────────────────────────────\n";
echo "│  Summary\n";
echo "├─────────────────────────────────────────────────────────\n";
echo "│  App name:   {$projectName}\n";
echo "│  Folder:     {$folderName}/\n";
echo "│  PHP:        {$phpVersion}\n";
echo "│  Database:   {$database}\n";
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
mkdir($projectDir . '/.lando', 0755, true);

// .lando.yml
// Compute the IDE-friendly path dynamically (Windows UNC on WSL, POSIX on macOS/Linux)
$isWsl = file_exists('/proc/version') && str_contains(file_get_contents('/proc/version'), 'microsoft');
$ideProjectPath = $isWsl
    ? trim(shell_exec('wslpath -w ' . escapeshellarg($projectDir)) ?? $projectDir)
    : $projectDir;
$extra = ['__PROJECT_IDE_PATH__' => $ideProjectPath];

$lando = file_get_contents(TEMPLATE_DIR . '/.lando.yml');
$lando = apply_template($lando, $projectName, $phpVersion, $database, $extra);
file_put_contents($projectDir . '/.lando.yml', $lando);
echo "  ✔ .lando.yml\n";

// .lando/php.ini
copy(TEMPLATE_DIR . '/.lando/php.ini', $projectDir . '/.lando/php.ini');
echo "  ✔ .lando/php.ini\n";

// README.md
$readme = file_get_contents(TEMPLATE_DIR . '/README.md');
$readme = apply_template($readme, $projectName, $phpVersion, $database, $extra);
file_put_contents($projectDir . '/README.md', $readme);
echo "  ✔ README.md\n";

// docs/
if (is_dir(TEMPLATE_DIR . '/docs')) {
    mkdir($projectDir . '/docs', 0755, true);
    foreach (glob(TEMPLATE_DIR . '/docs/*.md') as $doc) {
        $content = file_get_contents($doc);
        $content = apply_template($content, $projectName, $phpVersion, $database, $extra);
        file_put_contents($projectDir . '/docs/' . basename($doc), $content);
        echo "  ✔ docs/" . basename($doc) . "\n";
    }
}

// diagnostic files
foreach (['lenv-phpinfo.php', 'lenv-xdebuginfo.php'] as $file) {
    copy(TEMPLATE_DIR . '/' . $file, $projectDir . '/' . $file);
    echo "  ✔ {$file}
";
}

box('Environment created successfully!');
echo "Next steps:\n\n";
echo "  cd {$folderName}\n";
echo "  lando start\n";
echo "  lando install\n\n";
