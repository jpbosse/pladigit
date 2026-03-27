<?php

foreach (glob(sys_get_temp_dir().'/pladigit_test_lock_*') as $f) {
    // Supprime uniquement les locks de plus de 30 minutes
    if (filemtime($f) < time() - 1800) {
        unlink($f);
    }
}

require_once __DIR__.'/../vendor/autoload.php';
