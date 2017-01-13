<?php

require_once __DIR__. '/../src/Autoloader.php';

$oci = new \Dark\Client\Adapter('127.0.0.1', 9501);
print_r($oci->fetch('select job, log_user,interval from user_jobs where job=542'));
print_r($oci->fetch('select job, log_user,interval from user_jobs where job=540'));