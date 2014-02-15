<?php

$paths = glob(__DIR__.'/*.yml');

foreach ($paths as $path) {
    $data = yaml_parse_file($path);

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $basename = basename($path, '.yml');
    $json_path = __DIR__."/$basename.json";

    file_put_contents($json_path, $json);
}

