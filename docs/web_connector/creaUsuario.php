<?php
session_start();
if (!isset($_POST['idForm']) || !isset($_POST['claveForm'])) {
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('*** ERROR NO no ha ingresado los datos del usuario');" .
    "})" .
    "</script>";
    exit();
}
//error_reporting(E_ALL | E_STRICT);
//ini_set('display_errors', true);
if (function_exists('date_default_timezone_set'))
{
	date_default_timezone_set('America/Guayaquil');
}

require_once '../../QuickBooks.php';
$user = $_POST['idForm'];
$pass = $_POST['claveForm'];
$map = array(
	QUICKBOOKS_ADD_CUSTOMER => array( '_quickbooks_customer_add_request', '_quickbooks_customer_add_response' ),
	);
$errmap = array();
$hooks = array();
$log_level = QUICKBOOKS_LOG_DEVELOP;		// Use this level until you're sure everything works!!!
$soap = QUICKBOOKS_SOAPSERVER_BUILTIN;
$soap_options = array();
$dsn = 'mysql://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';
$handler_options = array(
	'authenticate' => '_quickbooks_custom_auth', 
	'deny_concurrent_logins' => false, 
	);
	QuickBooks_Utilities::createUser($dsn, $user, $pass);
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('*** El usuario se ha creado satisfactoriamente');" .
    "})" .
    "</script>";	
        