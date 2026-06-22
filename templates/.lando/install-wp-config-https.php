<?php
/**
 * Inject HTTPS detection into wp-config.php for Lando's TLS-terminating proxy.
 * Run once during lando wp-install (and safe to re-run).
 */

$file = '/app/wp-config.php';

if (!is_readable($file)) {
    fwrite(STDERR, "[lenv] wp-config.php not found — skipping HTTPS snippet.\n");
    exit(0);
}

$content = file_get_contents($file);

if (str_contains($content, 'HTTP_X_FORWARDED_PROTO')) {
    exit(0);
}

$snippet = <<<'PHP'


// lenv: HTTPS behind Lando proxy (appserver receives HTTP; Lando terminates TLS)
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) {
    $_SERVER['HTTPS'] = 'on';
}

PHP;

$content = preg_replace('/^<\?php/', '<?php' . $snippet, $content, 1);
file_put_contents($file, $content);

echo "[lenv] wp-config.php updated for HTTPS behind Lando proxy.\n";
