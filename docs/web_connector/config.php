<?php

if (function_exists('date_default_timezone_set'))
{
	// * MAKE SURE YOU SET THIS TO THE CORRECT TIMEZONE! *
	// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
	date_default_timezone_set('America/Guayaquil');
}

error_reporting(1);

require_once '../../QuickBooks.php';

$qbwc_user = 'jrcscarrillo';
$qbwc_pass = 'f9234568';
$user = 'jrcscarrillo';
$pass = 'f9234568';
$dsn = 'mysqli://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';

if (!QuickBooks_Utilities::initialized($dsn))
{
	QuickBooks_Utilities::initialize($dsn);
	QuickBooks_Utilities::createUser($dsn, $qbwc_user, $qbwc_pass);
	mysql_query("CREATE TABLE my_customer_table (
	  id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  name varchar(64) NOT NULL,
	  fname varchar(64) NOT NULL,
	  lname varchar(64) NOT NULL,
	  quickbooks_listid varchar(255) DEFAULT NULL,
	  quickbooks_editsequence varchar(255) DEFAULT NULL,
	  quickbooks_errnum varchar(255) DEFAULT NULL,
	  quickbooks_errmsg varchar(255) DEFAULT NULL,
	  PRIMARY KEY (id)
	) ENGINE=InnoDB");
}
