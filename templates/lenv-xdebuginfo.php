<?php
// lenv diagnostic file — local development only, do not commit

if (!extension_loaded('xdebug')) {
    echo '<h2 style="font-family:sans-serif;color:#c00">Xdebug is NOT loaded.</h2>';
    echo '<p style="font-family:sans-serif">Enable it in <code>.lando.yml</code>: <code>xdebug: debug</code> then run <code>lando rebuild -y</code>.</p>';
    exit;
}

xdebug_info();
