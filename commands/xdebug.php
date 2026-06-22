<?php
// lenv xdebug <on|off|status> [folder]

$action = $argv[2] ?? null;
$folder = $argv[3] ?? null;
$usage  = 'lenv xdebug <on|off|status> [folder]';

if (!in_array($action, ['on', 'off', 'status'], true)) {
    abort($usage);
}

$project = load_lenv_project($folder, $usage);
$values  = $project;

if ($action === 'on') {
    exit(run_lando_tooling($project['dir'], 'xdebug-on'));
}

if ($action === 'off') {
    exit(run_lando_tooling($project['dir'], 'xdebug-off'));
}

echo "Project: {$project['folder']}  (app: {$values['name']})\n\n";

$runtime = get_lando_xdebug_runtime_status($project['dir']);
print_xdebug_status_lines($values['xdebug'], $runtime);

if ($runtime === null) {
    echo "\nStart the environment to inspect runtime Xdebug: lando start\n";
    exit(1);
}

if (!$runtime['loaded']) {
    if ($values['xdebug'] === 'debug') {
        echo "\nNote: configured as debug but runtime is off. Run lando xdebug-on or lando rebuild -y.\n";
    }
    exit(0);
}

$mode = $runtime['mode'] ?? '';

if ($values['xdebug'] === 'off') {
    echo "\nNote: configured as off but Xdebug is loaded in the container.\n";
    echo "      Run lenv xdebug off (or lando xdebug-off). FrankenPHP images install the extension at build time.\n";
} elseif ($values['xdebug'] === 'debug' && $mode !== '' && $mode !== 'debug' && !str_contains($mode, 'debug')) {
    echo "\nNote: configured as debug but runtime mode is {$mode}. Run lando xdebug-on or lando rebuild -y.\n";
}
