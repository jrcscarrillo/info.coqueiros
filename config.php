<?php

if (function_exists('date_default_timezone_set'))
{
	// * MAKE SURE YOU SET THIS TO THE CORRECT TIMEZONE! *
	// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
	date_default_timezone_set('America/Guayaquil');
}

// I always program in E_STRICT error mode... 
error_reporting(1);

// Require the framework
require_once 'QuickBooks.php';

// Your .QWC file username/password
$qbwc_user = 'jrcscarrillo';
$qbwc_pass = 'f9234568';

// * MAKE SURE YOU CHANGE THE DATABASE CONNECTION STRING BELOW TO A VALID MYSQL USERNAME/PASSWORD/HOSTNAME *
$dsn = 'mysqli://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';

