<?php
define('PATH_TEST', __DIR__);
define('PATH_TEST_CACHE', __DIR__.'/cache');

require_once __DIR__.'/../vendor/autoload.php';

function trimWhitespace($str) {
    $lines = explode("\n", $str);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);
    return implode("\n", $lines);
}
