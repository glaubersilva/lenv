<?php
// lenv cert [status]

box('Lando CA Certificate');

$action = $argv[2] ?? 'install';

if (in_array($action, ['--help', '-h', 'help'], true)) {
    echo "Usage:\n";
    echo "  lenv cert              Trust the Lando CA so https://*.lndo.site is valid\n";
    echo "  lenv cert status       Check whether the Lando CA is already trusted\n\n";
    echo "Fixes the browser \"not secure\" warning for Lando sites.\n";
    echo "Firefox needs a manual import — the command prints instructions.\n";
    exit(0);
}

if (!in_array($action, ['status', 'install'], true)) {
    abort("Usage: lenv cert [status]");
}

$plan = get_cert_install_plan();

if ($plan === null) {
    abort(
        "No CA found at ~/.lando/certs/LandoCA.crt.\n"
        . "  Run `lando start` once so Lando generates its certificate authority first."
    );
}

echo "Platform: {$plan['label']}\n";
echo "CA file:  {$plan['ca']}\n\n";

if ($action === 'status') {
    if ($plan['status_ok'] === null) {
        echo "Trust status: unknown for this platform.\n\n{$plan['note']}\n";
        exit(0);
    }

    $nss    = $plan['nss'] ?? null;
    $hasNss = is_array($nss) && $nss['db'] !== null;

    if ($plan['status_ok'] && (!$hasNss || $nss['installed'])) {
        echo "Trust status: ✓ installed — the browser should show a valid lock.\n";
        exit(0);
    }

    echo "Trust status: ✗ not fully installed yet.\n";
    echo "  System trust store:       " . ($plan['status_ok'] ? 'installed' : 'not installed') . "\n";
    if ($hasNss) {
        echo "  Browser NSS (Chromium/Edge): " . ($nss['installed'] ? 'installed' : 'not installed') . "\n";
    }

    if ($plan['commands'] !== []) {
        echo "\nFix with: lenv cert\n";
    }

    if ($plan['note'] !== '') {
        echo "\n{$plan['note']}\n";
    }

    exit(1);
}

if ($plan['commands'] === []) {
    echo ($plan['note'] !== '' ? $plan['note'] . "\n" : "No automatic install available for {$plan['label']}.\n");
    exit(1);
}

$nss    = $plan['nss'] ?? null;
$hasNss = is_array($nss) && $nss['db'] !== null;

if ($plan['status_ok'] && (!$hasNss || $nss['installed'])) {
    echo "The Lando CA is already trusted. Run `lenv cert status` for details.\n";
    exit(0);
}

foreach ($plan['commands'] as $step) {
    if (!empty($step['skip'])) {
        continue;
    }

    echo "→ {$step['label']}:\n";
    passthru($step['cmd'], $exitCode);
    if ($exitCode !== 0) {
        echo "\nError: command failed (exit {$exitCode}).\n";
        exit(1);
    }
    echo "\n";
}

echo "Done. The Lando CA is now trusted for https://*.lndo.site.\n";

if ($plan['note'] !== '') {
    echo "\n{$plan['note']}\n";
}

exit(0);