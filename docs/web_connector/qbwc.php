<?php

require_once dirname(__FILE__) . '/config.php';

require_once dirname(__FILE__) . '/functions.php';

$map = array(
    QUICKBOOKS_ADD_CUSTOMER     => array( '_quickbooks_customer_add_request'        , '_quickbooks_customer_add_response'       ),
    QUICKBOOKS_MOD_CUSTOMER     => array( '_quickbooks_customer_mod_request'        , '_quickbooks_customer_mod_response'       ),
    QUICKBOOKS_ADD_CLASS        => array( '_quickbooks_class_add_request'           , '_quickbooks_class_add_response'          ),
    QUICKBOOKS_ADD_SERVICEITEM  => array( '_quickbooks_item_add_request'            , '_quickbooks_item_add_response'           ),
    QUICKBOOKS_ADD_SALESORDER   => array( '_quickbooks_sales_order_add_request'     , '_quickbooks_sales_order_add_response'    ),   
);

$errmap = array(
	'*' => '_quickbooks_error_catchall', 				// Using a key value of '*' will catch any errors which were not caught by another error handler
	);

$hooks = array(
	);

$log_level = QUICKBOOKS_LOG_DEVELOP;		// Use this level until you're sure everything works!!!

$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN;		// A pure-PHP SOAP server (no PHP ext/soap extension required, also makes debugging easier)

$soap_options = array(		// See http://www.php.net/soap
	);

$handler_options = array(
	'deny_concurrent_logins' => false, 
	'deny_reallyfast_logins' => false, 
	);		// See the comments in the QuickBooks/Server/Handlers.php file

$driver_options = array(		// See the comments in the QuickBooks/Driver/<YOUR DRIVER HERE>.php file ( i.e. 'Mysql.php', etc. )
	);

$callback_options = array(
	);

$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);
$response = $Server->handle(true, true);
