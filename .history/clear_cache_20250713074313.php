<?php
// Clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully.\n";
} else {
    echo "OPcache not available.\n";
}

// Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu cache cleared successfully.\n";
} else {
    echo "APCu not available.\n";
}

echo "Cache clearing attempt completed.\n";
?>
