<?php
// lenv status [folder]

box('Project Status');

$project = load_lenv_project($argv[2] ?? null, 'lenv status [folder]');

print_project_status($project);
echo "\n";
