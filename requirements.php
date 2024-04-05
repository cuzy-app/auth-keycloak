<?php

if (PHP_VERSION_ID < 80100) {
    return 'Please upgrade to PHP version 8.1 or later!';
}

if (!ini_get('allow_url_fopen')) {
    return 'You need to enable allow_url_fopen in your PHP configuration on the server.';
}

if (!extension_loaded('mbstring')) {
    return 'You need to enable MBString extension in your PHP configuration on the server.';
}

if (!extension_loaded('json')) {
    return 'You need to enable JSON extension in your PHP configuration on the server.';
}

if (!extension_loaded('gmp') && !extension_loaded('bcmath')) {
    return 'You need to enable GMP or BCMath extension in your PHP configuration on the server.';
}

return null;
