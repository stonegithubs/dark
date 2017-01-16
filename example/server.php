<?php

require_once __DIR__. '/../src/Autoloader.php';

$server = new \Dark\Server('127.0.0.1', 9501);
$server->setDriver(function() {
    return new \Dark\PdoDriver('mysql:host=localhost;dbname=ark', 'root', '');
});
$server->startup();