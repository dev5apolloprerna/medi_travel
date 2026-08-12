<?php
/**
 * TEMPORARY FILE - Upload this to your live server's ROOT folder
 * (same place where your main index.php / app folder is),
 * open it once in the browser, then DELETE it immediately after use.
 *
 * This clears PHP's OPcache so file changes (like routes.php) take
 * effect immediately instead of waiting for PHP-FPM/Apache restart.
 */

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    if ($result) {
        echo '<h2 style="color:green;font-family:sans-serif">✅ OPcache cleared successfully.</h2>';
        echo '<p style="font-family:sans-serif">Ave tame aa file delete kari nakho ane tamaru admin URL try karo.</p>';
    } else {
        echo '<h2 style="color:red;font-family:sans-serif">❌ OPcache reset failed (maybe disabled by hosting).</h2>';
    }
} else {
    echo '<h2 style="color:orange;font-family:sans-serif">⚠️ OPcache function not available on this server.</h2>';
    echo '<p style="font-family:sans-serif">Aa server par OPcache use nathi thatu — matlab issue kaink bijae hovu joie. Hosting support ne PHP-FPM/Apache restart karva kaho.</p>';
}

// Also clear any realpath cache just in case
clearstatcache(true);

echo '<hr><p style="font-family:sans-serif;color:#888">Server time: ' . date('Y-m-d H:i:s') . '</p>';