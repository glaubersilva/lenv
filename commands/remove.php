<?php
// lenv remove [folder]

box('Remove Project');

$project = load_lenv_project($argv[2] ?? null, 'lenv remove [folder]');

echo "Folder:   {$project['folder']}\n";
echo "Path:     {$project['dir']}\n";
echo "App name: {$project['name']}\n\n";
echo "This will:\n";
echo "  • run lando destroy (remove containers, networks, and Lando app state)\n";
echo "  • delete the project folder and all files inside it\n\n";

if (!confirm('Remove this project permanently?', 'n')) {
    abort('Aborted.');
}

echo "\nRemoving...\n";

destroy_lando_app($project['dir']);
echo "  ✔ lando destroy\n";

remove_lenv_project($project['dir']);
echo "  ✔ {$project['folder']}/ deleted\n\n";
