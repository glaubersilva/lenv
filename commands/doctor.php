<?php
// lenv doctor [folder]

box('Lando / WSL Doctor');

$project = null;
$folder  = $argv[2] ?? null;

if ($folder) {
    $project = load_lenv_project($folder, 'lenv doctor [folder]');
} else {
    $current = detect_current_project();
    if ($current) {
        $project = array_merge($current, extract_lando_values(file_get_contents($current['dir'] . '/.lando.yml')));
    }
}

exit(run_lando_doctor_checks($project));
