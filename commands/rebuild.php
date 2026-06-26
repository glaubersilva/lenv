<?php
// lenv rebuild [folder]

box('Rebuild Lando Environment');

$project    = load_lenv_project($argv[2] ?? null, 'lenv rebuild [folder]');
$projectDir = $project['dir'];
$folder     = $project['folder'];
$values     = $project;

echo "Project: {$folder}  (app: {$values['name']} / php {$values['php']} / {$values['database']} / {$values['via']})\n\n";
echo "Always overwritten from templates:\n";
echo "  ✔  .lando.yml       — full template; reapplies name, PHP, database, webserver, xdebug\n";
echo "  ✔  .lando/php.ini   — template base (+ php-xdebug.ini when xdebug is debug)\n";
echo "  ✔  .lando/xdebug-*.sh — runtime Xdebug toggle scripts\n";
echo "  ✔  .lando/ensure-dev-extensions.sh — uopz + Git safe.directory\n";
echo "  ✔  .lando/ensure-wp-rewrites.sh — rewrite flush + Apache .htaccess\n";
if (is_frankenphp_webserver($values['via'])) {
    echo "  ✔  docker/          — FrankenPHP Dockerfile, Caddyfile, entrypoint\n";
}
echo "  ✔  docs/*.md        — guides and troubleshooting\n\n";
echo "Kept unless you opt in below:\n";
echo "  ○  README.md        — default: keep your existing file\n\n";
echo "Custom edits in .lando.yml are also lost (extra services, tooling, overrides, etc.).\n\n";

$keepReadme = confirm(
    "Keep existing README.md? (answer n to replace it with the template)",
    'y'
);
$updateReadme = !$keepReadme;

if (!confirm("Proceed?")) {
    abort("Aborted.");
}

echo "\nRebuilding...\n";

write_project_lando($projectDir, $values);
echo "  ✔ .lando.yml\n";
echo "  ✔ .lando/php.ini\n";
echo "  ✔ .lando/xdebug-on.sh\n";
echo "  ✔ .lando/xdebug-off.sh\n";
echo "  ✔ .lando/ensure-dev-extensions.sh\n";
if (is_frankenphp_webserver($values['via'])) {
    echo "  ✔ docker/\n";
}

if (is_dir(TEMPLATE_DIR . '/docs')) {
    if (!is_dir($projectDir . '/docs')) {
        mkdir($projectDir . '/docs', 0755, true);
    }
    foreach (glob(TEMPLATE_DIR . '/docs/*.md') as $doc) {
        $content = apply_template(
            file_get_contents($doc),
            $values['name'],
            $values['php'],
            $values['database'],
            $values['via'],
            $values['xdebug']
        );
        file_put_contents($projectDir . '/docs/' . basename($doc), $content);
        echo "  ✔ docs/" . basename($doc) . "\n";
    }
}

if ($updateReadme) {
    $readme = apply_template(
        file_get_contents(TEMPLATE_DIR . '/README.md'),
        $values['name'],
        $values['php'],
        $values['database'],
        $values['via'],
        $values['xdebug']
    );
    file_put_contents($projectDir . '/README.md', $readme);
    echo "  ✔ README.md (replaced with template)\n";
} else {
    echo "  ○ README.md (kept existing file)\n";
}

box('Done!');
echo "Run inside the project to apply changes:\n\n";
echo "  lando rebuild -y\n\n";
