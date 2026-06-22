<?php
// lenv fix [folder]

box('Fix Lando Environment');

$project    = load_lenv_project($argv[2] ?? null, 'lenv fix [folder]');
$projectDir = $project['dir'];
$folder     = $project['folder'];

echo "Project: {$folder}  (app: {$project['name']})\n\n";

prepare_lenv_fix($projectDir);

echo "Starting Lando...\n\n";
passthru('cd ' . escapeshellarg($projectDir) . ' && lando start', $exitCode);

if ($exitCode !== 0 && is_wsl()) {
    echo "\n";
    echo "Start failed. Common WSL2 recovery:\n";
    echo "  1. lenv doctor\n";
    echo "  2. wsl --shutdown   (PowerShell/CMD on Windows)\n";
    echo "  3. lenv fix\n\n";
    echo "See docs/troubleshooting.md for more steps.\n\n";
}

exit($exitCode);
