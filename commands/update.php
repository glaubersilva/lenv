<?php
// lenv update [folder]

box('Update Environment Settings');

$project    = load_lenv_project($argv[2] ?? null, 'lenv update [folder]');
$projectDir = $project['dir'];
$folder     = $project['folder'];
$values     = $project;

echo "Project: {$folder}  (app: {$values['name']})\n\n";
echo "Current settings:\n\n";

$phpOptions = ['8.3', '8.2', '8.1', '8.0', '7.4'];
echo "  ⓘ  Press Enter to keep current. Options: " . implode(', ', $phpOptions) . "\n";
$newPhp = prompt("PHP version", $values['php']);
if (!in_array($newPhp, $phpOptions)) {
    abort("Invalid PHP version. Choose from: " . implode(', ', $phpOptions));
}

$dbOptions = ['mysql:8.0', 'mysql:5.7', 'mariadb:11.4', 'mariadb:10.6'];
echo "\n  ⓘ  Press Enter to keep current. Options: " . implode(', ', $dbOptions) . "\n";
$newDatabase = prompt("Database", $values['database']);
if (!in_array($newDatabase, $dbOptions)) {
    abort("Invalid database. Choose from: " . implode(', ', $dbOptions));
}

$viaOptions = webserver_options();
echo "\n  ⓘ  Press Enter to keep current. Options: " . implode(', ', $viaOptions) . "\n";
echo "  ⚠  litespeed may fail on Lando 3.26.x — you will be warned before proceeding\n";
if (is_litespeed_webserver($values['via'])) {
    echo "  ⚠  Current webserver is litespeed, which is known to fail on Lando 3.26.x.\n";
}
$newVia = prompt("Webserver", $values['via']);
if (!validate_webserver($newVia)) {
    abort("Invalid webserver. Choose from: " . implode(', ', $viaOptions));
}
if (is_litespeed_webserver($newVia) && $newVia !== $values['via'] && !confirm_litespeed_choice()) {
    abort("Aborted.");
}
if (is_frankenphp_webserver($newVia) && !validate_frankenphp_php($newPhp)) {
    abort("FrankenPHP requires PHP 8.0 or newer. Choose from: " . implode(', ', frankenphp_php_options()));
}

$xdebugOptions = ['off', 'debug'];
echo "\n  ⓘ  Press Enter to keep current. Options: " . implode(', ', $xdebugOptions) . "\n";
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
    echo "   Export first:  lando wp db export backup.sql\n\n";
}

if ($newVia !== $values['via']) {
    echo "⚠️  Changing the webserver replaces .lando.yml";
    if (is_frankenphp_webserver($newVia) || is_frankenphp_webserver($values['via'])) {
        echo " and docker/ files";
    }
    echo ".\n\n";
}

if (!confirm("Apply changes?")) {
    abort("Aborted.");
}

write_project_lando($projectDir, [
    'name'     => $values['name'],
    'php'      => $newPhp,
    'database' => $newDatabase,
    'via'      => $newVia,
    'xdebug'   => $newXdebug,
]);

echo "\n  ✔ .lando.yml updated\n";
if (is_frankenphp_webserver($newVia)) {
    echo "  ✔ docker/ synced\n";
}

box('Done!');
echo "Run inside the project to apply changes:\n\n";
echo "  lando rebuild -y\n\n";
