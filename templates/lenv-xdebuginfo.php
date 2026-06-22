<?php
// lenv diagnostic file — local development only, do not commit

if (!extension_loaded('xdebug')) {
    echo '<h2 style="font-family:sans-serif;color:#c00">Xdebug is NOT loaded.</h2>';
    echo '<p style="font-family:sans-serif">Run <code>lenv xdebug on</code> or choose <code>debug</code> in <code>lenv update</code> then <code>lando rebuild -y</code>.</p>';
    exit;
}

xdebug_info();
