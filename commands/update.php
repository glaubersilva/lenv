<?php
// lenv update [folder]

box('Update Environment Settings');

$targetFolder = $argv[2] ?? null;

if ($targetFolder) {
    $projectDir = get_project_dir($targetFolder);
    if (!is_dir($projectDir) || !file_exists($projectDir . '/.lando.yml')) {
        abort("Project not found: {$targetFolder}");
    }
    $folder = $targetFolder;
} else {
    $current = detect_current_project();
    if (!$current) {
        abort("No project specified and not inside a project folder.\n  Usage: lenv update <folder>");
    }
    $projectDir = $current['dir'];
    $folder     = $current['folder'];
}

$landoFile = $projectDir . '/.lando.yml';
$lando     = file_get_contents($landoFile);
$values    = extract_lando_values($lando);

echo "Project: {$folder}  (app: {$values['name']})\n\n";
echo "Current settings:

";

$phpOptions = ['8.3', '8.2', '8.1', '8.0', '7.4'];
echo "  ⓘ  Press Enter to keep current. Options: " . implode(', ', $phpOptions) . "
";
$newPhp = prompt("PHP version", $values['php']);
if (!in_array($newPhp, $phpOptions)) {
    abort("Invalid PHP version. Choose from: " . implode(', ', $phpOptions));
}

$dbOptions = ['mysql:8.0', 'mysql:5.7', 'mariadb:11.4', 'mariadb:10.6'];
echo "
  ⓘ  Press Enter to keep current. Options: " . implode(', ', $dbOptions) . "
";
$newDatabase = prompt("Database", $values['database']);
if (!in_array($newDatabase, $dbOptions)) {
    abort("Invalid database. Choose from: " . implode(', ', $dbOptions));
}

$viaOptions = ['apache', 'nginx'];
echo "
  ⓘ  Press Enter to keep current. Options: " . implode(', ', $viaOptions) . "
";
$newVia = prompt("Webserver", $values['via']);
if (!in_array($newVia, $viaOptions)) {
    abort("Invalid webserver. Choose from: " . implode(', ', $viaOptions));
}

$xdebugOptions = ['off', 'debug'];
echo "
  ⓘ  Press Enter to keep current. Options: " . implode(', ', $xdebugOptions) . "
";
$newXdebug = prompt("Xdebug", $values['xdebug']);
if (!in_array($newXdebug, $xdebugOptions)) {
    abort("Invalid Xdebug value. Choose from: " . implode(', ', $xdebugOptions));
}

$changed = $newPhp !== $values['php']
    || $newDatabase !== $values['database']
    || $newVia !== $values['via']
    || $newXdebug !== $values['xdebug'];

if (!$changed) {
    echo "\nNo changes made.\n\n";
    exit(0);
}

echo "\n";
echo "┌─────────────────────────────────────────────────────────\n";
echo "│  Changes\n";
echo "├─────────────────────────────────────────────────────────\n";
if ($newPhp !== $values['php'])           echo "│  PHP:       {$values['php']}  →  {$newPhp}\n";
if ($newDatabase !== $values['database']) echo "│  Database:  {$values['database']}  →  {$newDatabase}\n";
if ($newVia !== $values['via'])           echo "│  Webserver: {$values['via']}  →  {$newVia}\n";
if ($newXdebug !== $values['xdebug'])     echo "│  Xdebug:    {$values['xdebug']}  →  {$newXdebug}\n";
echo "└─────────────────────────────────────────────────────────\n\n";

if ($newDatabase !== $values['database']) {
    echo "⚠️  Changing the database will destroy existing data.\n";
    echo "   Export first:  lando wp db export --allow-root backup.sql\n\n";
}

if (!confirm("Apply changes?")) {
    abort("Aborted.");
}

// Apply changes to .lando.yml
$new = $lando;
$new = preg_replace("/php: '" . preg_quote($values['php'], '/') . "'/",
                    "php: '{$newPhp}'", $new);
$new = preg_replace('/database: ' . preg_quote($values['database'], '/') . '/',
                    "database: {$newDatabase}", $new);

if (preg_match('/^  via: /m', $new)) {
    $new = preg_replace('/^  via: .+$/m', "  via: {$newVia}", $new);
} elseif ($newVia !== 'apache') {
    $new = preg_replace('/^  database: .+$/m', "$0\n  via: {$newVia}", $new);
}

$new = preg_replace('/^  xdebug: .+$/m', "  xdebug: {$newXdebug}", $new);

file_put_contents($landoFile, $new);
echo "\n  ✔ .lando.yml updated\n";

box('Done!');
echo "Run inside the project to apply changes:\n\n";
echo "  lando rebuild -y\n\n";
