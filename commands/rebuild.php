<?php
// lenv rebuild [folder]

box('Rebuild Lando Environment');

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
        abort("No project specified and not inside a project folder.\n  Usage: lenv rebuild <folder>");
    }
    $projectDir = $current['dir'];
    $folder     = $current['folder'];
}

$values = extract_lando_values(file_get_contents($projectDir . '/.lando.yml'));
echo "Project: {$folder}  (app: {$values['name']} / php {$values['php']} / {$values['database']})\n\n";
echo "What will be updated:\n";
echo "  ✔  .lando/php.ini   — always safe (no project-specific content)\n";
echo "  ✔  .lando.yml       — template reapplied, preserving name / php / database\n\n";
echo "What will NOT be updated:\n";
echo "  ✗  README.md        — may contain project-specific content\n";
echo "  ✗  docs/            — may contain project-specific content\n\n";

if (!confirm("Proceed?")) {
    abort("Aborted.");
}

echo "\nRebuilding...\n";

if (!is_dir($projectDir . '/.lando')) {
    mkdir($projectDir . '/.lando', 0755, true);
}

copy(TEMPLATE_DIR . '/.lando/php.ini', $projectDir . '/.lando/php.ini');
echo "  ✔ .lando/php.ini\n";

$lando = file_get_contents(TEMPLATE_DIR . '/.lando.yml');
$lando = apply_template($lando, $values['name'], $values['php'], $values['database']);
file_put_contents($projectDir . '/.lando.yml', $lando);
echo "  ✔ .lando.yml\n";

box('Done!');
echo "Run inside the project to apply changes:\n\n";
echo "  lando rebuild -y\n\n";
