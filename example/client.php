<?php

require_once __DIR__. '/../src/Autoloader.php';

$mysql = new \Dark\Client('127.0.0.1', 9501);
print_r($mysql->fetchAll('select * from user'));