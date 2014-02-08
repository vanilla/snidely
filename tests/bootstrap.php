<?php

function __autoload($name) {
   $name = str_replace(array('\\', '_', '/'), DIRECTORY_SEPARATOR, $name);
   $path = __DIR__."/../$name.php";
   if (file_exists($path))
      require_once $path;
}
spl_autoload_register('__autoload');
