<?php

require_once __DIR__. '/../src/Autoloader.php';

$server = new \Dark\Pool\Server('127.0.0.1', 9501);
$server->setDriver(function() {
    return new \Dark\Pool\Driver\Pdo('oci://xinhua:sys@orcl_198.198.198.177/?charset=utf-8', array());
});
$server->startup();