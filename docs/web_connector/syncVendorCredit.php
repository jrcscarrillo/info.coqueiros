<?php

session_start();
error_reporting(1);
if (!empty($_GET['support'])) {
    header('Location: https://loscoqueiros.info/');
    exit;
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Guayaquil');
}
require_once '../../QuickBooks.php';
require_once 'conectaDB.php';
$user = 'jrcscarrillo';
$pass = 'f9234568';
define('QB_QUICKBOOKS_CONFIG_LAST', 'last');
define('QB_QUICKBOOKS_CONFIG_CURR', 'curr');
define('QB_QUICKBOOKS_MAX_RETURNED', 1725);
define('QB_PRIORITY_CUSTOMER', 1);
$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
fwrite($myfile, "Inicio recoger fechas de sincronizacion \r\n");
$db = conecta_SYNC();
$estado = "ERR";
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $sql = 'SELECT * FROM appliedtosync ORDER BY id DESC LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($registro) {
        $estado = 'OK';
    } else {
        $estado = 'NO HAY';
    }
} catch (PDOException $e) {
    echo 'ERROR JC!!! ' . $e->getMessage() . '<br>';
    echo 'ERROR JC!!! ' . $estado . '<br>';
}
$fecha = date("Y-m-d", strtotime($registro['otrosDesde']));
define('FECHAMODIFICACION', $fecha);
fwrite($myfile, 'fechas : ' . FECHAMODIFICACION . '\r\n');
fclose($myfile);
$stmt = null;
$db = null;

$map = array(
   QUICKBOOKS_IMPORT_CUSTOMER => array('_quickbooks_customer_import_request', '_quickbooks_customer_import_response'),
);
$errmap = array(
   500 => '_quickbooks_error_e500_notfound', // Catch errors caused by searching for things not present in QuickBooks
   1 => '_quickbooks_error_e500_notfound',
   '*' => '_quickbooks_error_catchall'
);
// Catch any other errors that might occur
$hooks = array(
   QuickBooks_WebConnector_Handlers::HOOK_LOGINSUCCESS => '_quickbooks_hook_loginsuccess' // call this whenever a successful login occurs
);

$log_level = QUICKBOOKS_LOG_DEVELOP;

//$soapserver = QUICKBOOKS_SOAPSERVER_PHP;			// The PHP SOAP extension, see: www.php.net/soap);

$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN;  // A pure-PHP SOAP server (no PHP ext/soap extension required, also makes debugging easier)

$soap_options = array(// See http://www.php.net/soap
);

$handler_options = array(// See the comments in the QuickBooks/Server/Handlers.php file
   'deny_concurrent_logins' => false,
   'deny_reallyfast_logins' => false,
);

$driver_options = array(// See the comments in the QuickBooks/Driver/<YOUR DRIVER HERE>.php file ( i.e. 'Mysql.php', etc. )
);

$callback_options = array(
);
$dsn = 'mysqli://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';
define('QB_QUICKBOOKS_DSN', $dsn);
QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);
$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);
$response = $Server->handle(true, true);

function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    $date = date('y-m-d H:m:s');
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, 1, QB_PRIORITY_CUSTOMER, NULL, $user);
}

function _quickbooks_get_last_run($user, $action) {
    $type = null;
    $opts = null;
    return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $type, $opts);
}

function _quickbooks_set_last_run($user, $action, $force = null) {
    $value = date('Y-m-d') . 'T' . date('H:i:s');

    if ($force) {
        $value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
    }

    return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $value);
}

function _quickbooks_get_current_run($user, $action) {
    $type = null;
    $opts = null;
    return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $type, $opts);
}

function _quickbooks_set_current_run($user, $action, $force = null) {
    $value = date('Y-m-d') . 'T' . date('H:i:s');

    if ($force) {
        $value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
    }

    return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $value);
}

function _quickbooks_customer_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_customer_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <FromModifiedDate>' . FECHAMODIFICACION . '</FromModifiedDate>
                            <OwnerID>0</OwnerID>
			</CustomerQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_customer_initial_response() {
    $myfile = fopen("newfile1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "QB WC Paso inicial ");
    fclose($myfile);
}

function _quickbooks_customer_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, null, QB_PRIORITY_CUSTOMER, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");
    $_SESSION['customer'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
//    $myfile1 = fopen("customers.xml", "w") or die("Unable to open file!");
//    fwrite($myfile1, $xml);
    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    $existe = "JRCS";
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($cliente as $uno) {
        genLimpia_mycustomer();
        gentraverse_mycustomer($uno);
        $existe = buscaIgual_mycustomer($db, $myfile);
        if ($existe == "OK") {
            quitaslashes_mycustomer();
            fwrite($myfile, "NO!!! Existe cliente " . $_SESSION['mycustomer']['ListID'] . " \r\n");
            adiciona_mycustomer($db);
        } elseif ($existe == "ACTUALIZA") {            
            quitaslashes_mycustomer();
            fwrite($myfile, "SI Existe cliente " . $_SESSION['mycustomer']['ListID'] . " \r\n");
            actualiza_mycustomer($db, $myfile);
        }
    }
    fclose($myfile);
//    fclose($myfile1);
    $db = null;
    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_CUSTOMER) {
        return true;
    }
    return false;
}

function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $message = '';
    $message .= 'Request ID: ' . $requestID . "\r\n";
    $message .= 'User: ' . $user . "\r\n";
    $message .= 'Action: ' . $action . "\r\n";
    $message .= 'ID: ' . $ID . "\r\n";
    $message .= 'Extra: ' . print_r($extra, true) . "\r\n";
    //$message .= 'Error: ' . $err . "\r\n";
    $message .= 'Error number: ' . $errnum . "\r\n";
    $message .= 'Error message: ' . $errmsg . "\r\n";

    mail(QB_QUICKBOOKS_MAILTO, 'QuickBooks error occured!', $message);
}


function genLimpia_mycustomer() {
    $_SESSION['mycustomer']['ListID'] = ' ';
    $_SESSION['mycustomer']['TimeCreated'] = ' ';
    $_SESSION['mycustomer']['TimeModified'] = ' ';
    $_SESSION['mycustomer']['EditSequence'] = ' ';
    $_SESSION['mycustomer']['Name'] = ' ';
    $_SESSION['mycustomer']['FullName'] = ' ';
    $_SESSION['mycustomer']['IsActive'] = ' ';
    $_SESSION['mycustomer']['ClassRef_ListID'] = ' ';
    $_SESSION['mycustomer']['ClassRef_FullName'] = ' ';
    $_SESSION['mycustomer']['ParentRef_ListID'] = ' ';
    $_SESSION['mycustomer']['ParentRef_FullName'] = ' ';
    $_SESSION['mycustomer']['Sublevel'] = ' ';
    $_SESSION['mycustomer']['CompanyName'] = ' ';
    $_SESSION['mycustomer']['Salutation'] = ' ';
    $_SESSION['mycustomer']['FirstName'] = ' ';
    $_SESSION['mycustomer']['MiddleName'] = ' ';
    $_SESSION['mycustomer']['LastName'] = ' ';
    $_SESSION['mycustomer']['Suffix'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Addr1'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Addr2'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Addr3'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Addr4'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Addr5'] = ' ';
    $_SESSION['mycustomer']['BillAddress_City'] = ' ';
    $_SESSION['mycustomer']['BillAddress_State'] = ' ';
    $_SESSION['mycustomer']['BillAddress_PostalCode'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Country'] = ' ';
    $_SESSION['mycustomer']['BillAddress_Note'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Addr1'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Addr2'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Addr3'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Addr4'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Addr5'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_City'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_State'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Country'] = ' ';
    $_SESSION['mycustomer']['ShipAddress_Note'] = ' ';
    $_SESSION['mycustomer']['PrintAs'] = ' ';
    $_SESSION['mycustomer']['Phone'] = ' ';
    $_SESSION['mycustomer']['Mobile'] = ' ';
    $_SESSION['mycustomer']['Pager'] = ' ';
    $_SESSION['mycustomer']['AltPhone'] = ' ';
    $_SESSION['mycustomer']['Fax'] = ' ';
    $_SESSION['mycustomer']['Email'] = ' ';
    $_SESSION['mycustomer']['Cc'] = ' ';
    $_SESSION['mycustomer']['Contact'] = ' ';
    $_SESSION['mycustomer']['AltContact'] = ' ';
    $_SESSION['mycustomer']['CustomerTypeRef_ListID'] = ' ';
    $_SESSION['mycustomer']['CustomerTypeRef_FullName'] = ' ';
    $_SESSION['mycustomer']['TermsRef_ListID'] = ' ';
    $_SESSION['mycustomer']['TermsRef_FullName'] = ' ';
    $_SESSION['mycustomer']['SalesRepRef_ListID'] = ' ';
    $_SESSION['mycustomer']['SalesRepRef_FullName'] = ' ';
    $_SESSION['mycustomer']['Balance'] = ' ';
    $_SESSION['mycustomer']['TotalBalance'] = ' ';
    $_SESSION['mycustomer']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['mycustomer']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['mycustomer']['ItemSalesTaxRef_ListID'] = ' ';
    $_SESSION['mycustomer']['ItemSalesTaxRef_FullName'] = ' ';
    $_SESSION['mycustomer']['SalesTaxCountry'] = ' ';
    $_SESSION['mycustomer']['ResaleNumber'] = ' ';
    $_SESSION['mycustomer']['AccountNumber'] = ' ';
    $_SESSION['mycustomer']['CreditLimit'] = ' ';
    $_SESSION['mycustomer']['PreferredPaymentMethodRef_ListID'] = ' ';
    $_SESSION['mycustomer']['PreferredPaymentMethodRef_FullName'] = ' ';
    $_SESSION['mycustomer']['CreditCardNumber'] = ' ';
    $_SESSION['mycustomer']['ExpirationMonth'] = ' ';
    $_SESSION['mycustomer']['ExpirationYear'] = ' ';
    $_SESSION['mycustomer']['NameOnCard'] = ' ';
    $_SESSION['mycustomer']['CreditCardAddress'] = ' ';
    $_SESSION['mycustomer']['CreditCardPostalCode'] = ' ';
    $_SESSION['mycustomer']['JobStatus'] = ' ';
    $_SESSION['mycustomer']['JobStartDate'] = ' ';
    $_SESSION['mycustomer']['JobProjectedEndDate'] = ' ';
    $_SESSION['mycustomer']['JobEndDate'] = ' ';
    $_SESSION['mycustomer']['JobDesc'] = ' ';
    $_SESSION['mycustomer']['JobTypeRef_ListID'] = ' ';
    $_SESSION['mycustomer']['JobTypeRef_FullName'] = ' ';
    $_SESSION['mycustomer']['Notes'] = ' ';
    $_SESSION['mycustomer']['PriceLevelRef_ListID'] = ' ';
    $_SESSION['mycustomer']['PriceLevelRef_FullName'] = ' ';
    $_SESSION['mycustomer']['TaxRegistrationNumber'] = ' ';
    $_SESSION['mycustomer']['CurrencyRef_ListID'] = ' ';
    $_SESSION['mycustomer']['CurrencyRef_FullName'] = ' ';
    $_SESSION['mycustomer']['IsStatementWithParent'] = ' ';
    $_SESSION['mycustomer']['PreferredDeliveryMethod'] = ' ';
}

function gentraverse_mycustomer($node) {
    $node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['mycustomer']['ListID'] = ' ' : $_SESSION['mycustomer']['ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeCreated')->item(0) == NULL ? $_SESSION['mycustomer']['TimeCreated'] = '2010-08-10' : $_SESSION['mycustomer']['TimeCreated'] = $node->getElementsByTagName('TimeCreated')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeModified')->item(0) == NULL ? $_SESSION['mycustomer']['TimeModified'] = '2010-08-10' : $_SESSION['mycustomer']['TimeModified'] = $node->getElementsByTagName('TimeModified')->item(0)->nodeValue;
    $node->getElementsByTagName('EditSequence')->item(0) == NULL ? $_SESSION['mycustomer']['EditSequence'] = 0 : $_SESSION['mycustomer']['EditSequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
    $node->getElementsByTagName('Name')->item(0) == NULL ? $_SESSION['mycustomer']['Name'] = ' ' : $_SESSION['mycustomer']['Name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['FullName'] = ' ' : $_SESSION['mycustomer']['FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('IsActive')->item(0) == NULL ? $_SESSION['mycustomer']['IsActive'] = ' ' : $_SESSION['mycustomer']['IsActive'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(1) == NULL ? $_SESSION['mycustomer']['ClassRef_ListID'] = ' ' : $_SESSION['mycustomer']['ClassRef_ListID'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['ClassRef_FullName'] = ' ' : $_SESSION['mycustomer']['ClassRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(2) == NULL ? $_SESSION['mycustomer']['ParentRef_ListID'] = ' ' : $_SESSION['mycustomer']['ParentRef_ListID'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['ParentRef_FullName'] = ' ' : $_SESSION['mycustomer']['ParentRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('Sublevel')->item(0) == NULL ? $_SESSION['mycustomer']['Sublevel'] = 0 : $_SESSION['mycustomer']['Sublevel'] = $node->getElementsByTagName('Sublevel')->item(0)->nodeValue;
    $node->getElementsByTagName('CompanyName')->item(0) == NULL ? $_SESSION['mycustomer']['CompanyName'] = ' ' : $_SESSION['mycustomer']['CompanyName'] = $node->getElementsByTagName('CompanyName')->item(0)->nodeValue;
    $node->getElementsByTagName('Salutation')->item(0) == NULL ? $_SESSION['mycustomer']['Salutation'] = ' ' : $_SESSION['mycustomer']['Salutation'] = $node->getElementsByTagName('Salutation')->item(0)->nodeValue;
    $node->getElementsByTagName('FirstName')->item(0) == NULL ? $_SESSION['mycustomer']['FirstName'] = ' ' : $_SESSION['mycustomer']['FirstName'] = $node->getElementsByTagName('FirstName')->item(0)->nodeValue;
    $node->getElementsByTagName('MiddleName')->item(0) == NULL ? $_SESSION['mycustomer']['MiddleName'] = ' ' : $_SESSION['mycustomer']['MiddleName'] = $node->getElementsByTagName('MiddleName')->item(0)->nodeValue;
    $node->getElementsByTagName('LastName')->item(0) == NULL ? $_SESSION['mycustomer']['LastName'] = ' ' : $_SESSION['mycustomer']['LastName'] = $node->getElementsByTagName('LastName')->item(0)->nodeValue;
    $node->getElementsByTagName('Suffix')->item(0) == NULL ? $_SESSION['mycustomer']['Suffix'] = ' ' : $_SESSION['mycustomer']['Suffix'] = $node->getElementsByTagName('Suffix')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr1')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Addr1'] = ' ' : $_SESSION['mycustomer']['BillAddress_Addr1'] = $node->getElementsByTagName('Addr1')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr2')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Addr2'] = ' ' : $_SESSION['mycustomer']['BillAddress_Addr2'] = $node->getElementsByTagName('Addr2')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr3')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Addr3'] = ' ' : $_SESSION['mycustomer']['BillAddress_Addr3'] = $node->getElementsByTagName('Addr3')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr4')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Addr4'] = ' ' : $_SESSION['mycustomer']['BillAddress_Addr4'] = $node->getElementsByTagName('Addr4')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr5')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Addr5'] = ' ' : $_SESSION['mycustomer']['BillAddress_Addr5'] = $node->getElementsByTagName('Addr5')->item(0)->nodeValue;
    $node->getElementsByTagName('City')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_City'] = ' ' : $_SESSION['mycustomer']['BillAddress_City'] = $node->getElementsByTagName('City')->item(0)->nodeValue;
    $node->getElementsByTagName('State')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_State'] = ' ' : $_SESSION['mycustomer']['BillAddress_State'] = $node->getElementsByTagName('State')->item(0)->nodeValue;
    $node->getElementsByTagName('PostalCode')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_PostalCode'] = ' ' : $_SESSION['mycustomer']['BillAddress_PostalCode'] = $node->getElementsByTagName('PostalCode')->item(0)->nodeValue;
    $node->getElementsByTagName('Country')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Country'] = ' ' : $_SESSION['mycustomer']['BillAddress_Country'] = $node->getElementsByTagName('Country')->item(0)->nodeValue;
    $node->getElementsByTagName('Note')->item(0) == NULL ? $_SESSION['mycustomer']['BillAddress_Note'] = ' ' : $_SESSION['mycustomer']['BillAddress_Note'] = $node->getElementsByTagName('Note')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr1')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Addr1'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Addr1'] = $node->getElementsByTagName('Addr1')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr2')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Addr2'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Addr2'] = $node->getElementsByTagName('Addr2')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr3')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Addr3'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Addr3'] = $node->getElementsByTagName('Addr3')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr4')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Addr4'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Addr4'] = $node->getElementsByTagName('Addr4')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr5')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Addr5'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Addr5'] = $node->getElementsByTagName('Addr5')->item(0)->nodeValue;
    $node->getElementsByTagName('City')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_City'] = ' ' : $_SESSION['mycustomer']['ShipAddress_City'] = $node->getElementsByTagName('City')->item(0)->nodeValue;
    $node->getElementsByTagName('State')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_State'] = ' ' : $_SESSION['mycustomer']['ShipAddress_State'] = $node->getElementsByTagName('State')->item(0)->nodeValue;
    $node->getElementsByTagName('PostalCode')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_PostalCode'] = ' ' : $_SESSION['mycustomer']['ShipAddress_PostalCode'] = $node->getElementsByTagName('PostalCode')->item(0)->nodeValue;
    $node->getElementsByTagName('Country')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Country'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Country'] = $node->getElementsByTagName('Country')->item(0)->nodeValue;
    $node->getElementsByTagName('Note')->item(0) == NULL ? $_SESSION['mycustomer']['ShipAddress_Note'] = ' ' : $_SESSION['mycustomer']['ShipAddress_Note'] = $node->getElementsByTagName('Note')->item(0)->nodeValue;
    $node->getElementsByTagName('PrintAs')->item(0) == NULL ? $_SESSION['mycustomer']['PrintAs'] = ' ' : $_SESSION['mycustomer']['PrintAs'] = $node->getElementsByTagName('PrintAs')->item(0)->nodeValue;
    $node->getElementsByTagName('Phone')->item(0) == NULL ? $_SESSION['mycustomer']['Phone'] = ' ' : $_SESSION['mycustomer']['Phone'] = $node->getElementsByTagName('Phone')->item(0)->nodeValue;
    $node->getElementsByTagName('Mobile')->item(0) == NULL ? $_SESSION['mycustomer']['Mobile'] = ' ' : $_SESSION['mycustomer']['Mobile'] = $node->getElementsByTagName('Mobile')->item(0)->nodeValue;
    $node->getElementsByTagName('Pager')->item(0) == NULL ? $_SESSION['mycustomer']['Pager'] = ' ' : $_SESSION['mycustomer']['Pager'] = $node->getElementsByTagName('Pager')->item(0)->nodeValue;
    $node->getElementsByTagName('AltPhone')->item(0) == NULL ? $_SESSION['mycustomer']['AltPhone'] = ' ' : $_SESSION['mycustomer']['AltPhone'] = $node->getElementsByTagName('AltPhone')->item(0)->nodeValue;
    $node->getElementsByTagName('Fax')->item(0) == NULL ? $_SESSION['mycustomer']['Fax'] = ' ' : $_SESSION['mycustomer']['Fax'] = $node->getElementsByTagName('Fax')->item(0)->nodeValue;
    $node->getElementsByTagName('Email')->item(0) == NULL ? $_SESSION['mycustomer']['Email'] = ' ' : $_SESSION['mycustomer']['Email'] = $node->getElementsByTagName('Email')->item(0)->nodeValue;
    $node->getElementsByTagName('Cc')->item(0) == NULL ? $_SESSION['mycustomer']['Cc'] = ' ' : $_SESSION['mycustomer']['Cc'] = $node->getElementsByTagName('Cc')->item(0)->nodeValue;
    $node->getElementsByTagName('Contact')->item(0) == NULL ? $_SESSION['mycustomer']['Contact'] = ' ' : $_SESSION['mycustomer']['Contact'] = $node->getElementsByTagName('Contact')->item(0)->nodeValue;
    $node->getElementsByTagName('AltContact')->item(0) == NULL ? $_SESSION['mycustomer']['AltContact'] = ' ' : $_SESSION['mycustomer']['AltContact'] = $node->getElementsByTagName('AltContact')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(3) == NULL ? $_SESSION['mycustomer']['CustomerTypeRef_ListID'] = ' ' : $_SESSION['mycustomer']['CustomerTypeRef_ListID'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['CustomerTypeRef_FullName'] = ' ' : $_SESSION['mycustomer']['CustomerTypeRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(4) == NULL ? $_SESSION['mycustomer']['TermsRef_ListID'] = ' ' : $_SESSION['mycustomer']['TermsRef_ListID'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['TermsRef_FullName'] = ' ' : $_SESSION['mycustomer']['TermsRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(5) == NULL ? $_SESSION['mycustomer']['SalesRepRef_ListID'] = ' ' : $_SESSION['mycustomer']['SalesRepRef_ListID'] = $node->getElementsByTagName('ListID')->item(5)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['SalesRepRef_FullName'] = ' ' : $_SESSION['mycustomer']['SalesRepRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('Balance')->item(0) == NULL ? $_SESSION['mycustomer']['Balance'] = 0 : $_SESSION['mycustomer']['Balance'] = $node->getElementsByTagName('Balance')->item(0)->nodeValue;
    $node->getElementsByTagName('TotalBalance')->item(0) == NULL ? $_SESSION['mycustomer']['TotalBalance'] = 0 : $_SESSION['mycustomer']['TotalBalance'] = $node->getElementsByTagName('TotalBalance')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(6) == NULL ? $_SESSION['mycustomer']['SalesTaxCodeRef_ListID'] = ' ' : $_SESSION['mycustomer']['SalesTaxCodeRef_ListID'] = $node->getElementsByTagName('ListID')->item(6)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['SalesTaxCodeRef_FullName'] = ' ' : $_SESSION['mycustomer']['SalesTaxCodeRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(7) == NULL ? $_SESSION['mycustomer']['ItemSalesTaxRef_ListID'] = ' ' : $_SESSION['mycustomer']['ItemSalesTaxRef_ListID'] = $node->getElementsByTagName('ListID')->item(7)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['ItemSalesTaxRef_FullName'] = ' ' : $_SESSION['mycustomer']['ItemSalesTaxRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('SalesTaxCountry')->item(0) == NULL ? $_SESSION['mycustomer']['SalesTaxCountry'] = ' ' : $_SESSION['mycustomer']['SalesTaxCountry'] = $node->getElementsByTagName('SalesTaxCountry')->item(0)->nodeValue;
    $node->getElementsByTagName('ResaleNumber')->item(0) == NULL ? $_SESSION['mycustomer']['ResaleNumber'] = ' ' : $_SESSION['mycustomer']['ResaleNumber'] = $node->getElementsByTagName('ResaleNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('AccountNumber')->item(0) == NULL ? $_SESSION['mycustomer']['AccountNumber'] = ' ' : $_SESSION['mycustomer']['AccountNumber'] = $node->getElementsByTagName('AccountNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('CreditLimit')->item(0) == NULL ? $_SESSION['mycustomer']['CreditLimit'] = 0 : $_SESSION['mycustomer']['CreditLimit'] = $node->getElementsByTagName('CreditLimit')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(8) == NULL ? $_SESSION['mycustomer']['PreferredPaymentMethodRef_ListID'] = ' ' : $_SESSION['mycustomer']['PreferredPaymentMethodRef_ListID'] = $node->getElementsByTagName('ListID')->item(8)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['PreferredPaymentMethodRef_FullName'] = ' ' : $_SESSION['mycustomer']['PreferredPaymentMethodRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('CreditCardNumber')->item(0) == NULL ? $_SESSION['mycustomer']['CreditCardNumber'] = ' ' : $_SESSION['mycustomer']['CreditCardNumber'] = $node->getElementsByTagName('CreditCardNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('ExpirationMonth')->item(0) == NULL ? $_SESSION['mycustomer']['ExpirationMonth'] = 0 : $_SESSION['mycustomer']['ExpirationMonth'] = $node->getElementsByTagName('ExpirationMonth')->item(0)->nodeValue;
    $node->getElementsByTagName('ExpirationYear')->item(0) == NULL ? $_SESSION['mycustomer']['ExpirationYear'] = 0 : $_SESSION['mycustomer']['ExpirationYear'] = $node->getElementsByTagName('ExpirationYear')->item(0)->nodeValue;
    $node->getElementsByTagName('NameOnCard')->item(0) == NULL ? $_SESSION['mycustomer']['NameOnCard'] = ' ' : $_SESSION['mycustomer']['NameOnCard'] = $node->getElementsByTagName('NameOnCard')->item(0)->nodeValue;
    $node->getElementsByTagName('CreditCardAddress')->item(0) == NULL ? $_SESSION['mycustomer']['CreditCardAddress'] = ' ' : $_SESSION['mycustomer']['CreditCardAddress'] = $node->getElementsByTagName('CreditCardAddress')->item(0)->nodeValue;
    $node->getElementsByTagName('CreditCardPostalCode')->item(0) == NULL ? $_SESSION['mycustomer']['CreditCardPostalCode'] = ' ' : $_SESSION['mycustomer']['CreditCardPostalCode'] = $node->getElementsByTagName('CreditCardPostalCode')->item(0)->nodeValue;
    $node->getElementsByTagName('JobStatus')->item(0) == NULL ? $_SESSION['mycustomer']['JobStatus'] = ' ' : $_SESSION['mycustomer']['JobStatus'] = $node->getElementsByTagName('JobStatus')->item(0)->nodeValue;
    $node->getElementsByTagName('JobStartDate')->item(0) == NULL ? $_SESSION['mycustomer']['JobStartDate'] = '2010-08-10' : $_SESSION['mycustomer']['JobStartDate'] = $node->getElementsByTagName('JobStartDate')->item(0)->nodeValue;
    $node->getElementsByTagName('JobProjectedEndDate')->item(0) == NULL ? $_SESSION['mycustomer']['JobProjectedEndDate'] = '2010-08-10' : $_SESSION['mycustomer']['JobProjectedEndDate'] = $node->getElementsByTagName('JobProjectedEndDate')->item(0)->nodeValue;
    $node->getElementsByTagName('JobEndDate')->item(0) == NULL ? $_SESSION['mycustomer']['JobEndDate'] = '2010-08-10' : $_SESSION['mycustomer']['JobEndDate'] = $node->getElementsByTagName('JobEndDate')->item(0)->nodeValue;
    $node->getElementsByTagName('JobDesc')->item(0) == NULL ? $_SESSION['mycustomer']['JobDesc'] = ' ' : $_SESSION['mycustomer']['JobDesc'] = $node->getElementsByTagName('JobDesc')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(9) == NULL ? $_SESSION['mycustomer']['JobTypeRef_ListID'] = ' ' : $_SESSION['mycustomer']['JobTypeRef_ListID'] = $node->getElementsByTagName('ListID')->item(9)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['JobTypeRef_FullName'] = ' ' : $_SESSION['mycustomer']['JobTypeRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('Notes')->item(0) == NULL ? $_SESSION['mycustomer']['Notes'] = ' ' : $_SESSION['mycustomer']['Notes'] = $node->getElementsByTagName('Notes')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(10) == NULL ? $_SESSION['mycustomer']['PriceLevelRef_ListID'] = ' ' : $_SESSION['mycustomer']['PriceLevelRef_ListID'] = $node->getElementsByTagName('ListID')->item(10)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['PriceLevelRef_FullName'] = ' ' : $_SESSION['mycustomer']['PriceLevelRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('TaxRegistrationNumber')->item(0) == NULL ? $_SESSION['mycustomer']['TaxRegistrationNumber'] = ' ' : $_SESSION['mycustomer']['TaxRegistrationNumber'] = $node->getElementsByTagName('TaxRegistrationNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(11) == NULL ? $_SESSION['mycustomer']['CurrencyRef_ListID'] = ' ' : $_SESSION['mycustomer']['CurrencyRef_ListID'] = $node->getElementsByTagName('ListID')->item(11)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['mycustomer']['CurrencyRef_FullName'] = ' ' : $_SESSION['mycustomer']['CurrencyRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('IsStatementWithParent')->item(0) == NULL ? $_SESSION['mycustomer']['IsStatementWithParent'] = ' ' : $_SESSION['mycustomer']['IsStatementWithParent'] = $node->getElementsByTagName('IsStatementWithParent')->item(0)->nodeValue;
    $node->getElementsByTagName('PreferredDeliveryMethod')->item(0) == NULL ? $_SESSION['mycustomer']['PreferredDeliveryMethod'] = ' ' : $_SESSION['mycustomer']['PreferredDeliveryMethod'] = $node->getElementsByTagName('PreferredDeliveryMethod')->item(0)->nodeValue;
}

function adiciona_mycustomer($db, $myfile) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO my_customer_table (  timecreated, timemodified, name, fullname, fname, lname, salutation, address, city, state, zipcode, country, email, quickbooks_listid, quickbooks_editsequence, quickbooks_errnum, quickbooks_errmsg, sales_rep_ref_listid, sales_rep_ref_fullname, sales_tax_code_ref_listid, sales_tax_code_ref_fullname, tax_code_ref_listid, tax_code_ref_fullname, item_sales_tax_ref_listid, item_sales_tax_ref_fullname) VALUES ( :timecreated, :timemodified, :name, :fullname, :fname, :lname, :salutation, :address, :city, :state, :zipcode, :country, :email, :quickbooks_listid, :quickbooks_editsequence, :quickbooks_errnum, :quickbooks_errmsg, :sales_rep_ref_listid, :sales_rep_ref_fullname, :sales_tax_code_ref_listid, :sales_tax_code_ref_fullname, :tax_code_ref_listid, :tax_code_ref_fullname, :item_sales_tax_ref_listid, :item_sales_tax_ref_fullname)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':timecreated', $_SESSION['mycustomer']['TimeCreated']);
        $stmt->bindParam(':timemodified', $_SESSION['mycustomer']['TimeModified']);
        $stmt->bindParam(':name', $_SESSION['mycustomer']['Name']);
        $stmt->bindParam(':fullname', $_SESSION['mycustomer']['FullName']);
        $stmt->bindParam(':fname', $_SESSION['mycustomer']['FirstName']);
        $stmt->bindParam(':lname', $_SESSION['mycustomer']['LastName']);
        $stmt->bindParam(':salutation', $_SESSION['mycustomer']['Salutation']);
        $stmt->bindParam(':address', $_SESSION['mycustomer']['BillAddress_Addr1']);
        $stmt->bindParam(':city', $_SESSION['mycustomer']['BillAddress_City']);
        $stmt->bindParam(':state', $_SESSION['mycustomer']['BillAddress_State']);
        $stmt->bindParam(':zipcode', $_SESSION['mycustomer']['BillAddress_PostalCode']);
        $stmt->bindParam(':country', $_SESSION['mycustomer']['BillAddress_Country']);
        $stmt->bindParam(':email', $_SESSION['mycustomer']['Email']);
        $stmt->bindParam(':quickbooks_listid', $_SESSION['mycustomer']['ListID']);
        $stmt->bindParam(':quickbooks_editsequence', $_SESSION['mycustomer']['EditSequence']);
        $stmt->bindParam(':quickbooks_errnum', $_SESSION['mycustomer']['TermsRef_ListID']);
        $stmt->bindParam(':quickbooks_errmsg', $_SESSION['mycustomer']['TermsRef_FullName']);
        $stmt->bindParam(':sales_rep_ref_listid', $_SESSION['mycustomer']['SalesRepRef_ListID']);
        $stmt->bindParam(':sales_rep_ref_fullname', $_SESSION['mycustomer']['SalesRepRef_FullName']);
        $stmt->bindParam(':sales_tax_code_ref_listid', $_SESSION['mycustomer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':sales_tax_code_ref_fullname', $_SESSION['mycustomer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':tax_code_ref_listid', $_SESSION['mycustomer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':tax_code_ref_fullname', $_SESSION['mycustomer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':item_sales_tax_ref_listid', $_SESSION['mycustomer']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':item_sales_tax_ref_fullname', $_SESSION['mycustomer']['ItemSalesTaxRef_FullName']);
        $stmt->execute();
    } catch (PDOException $e) {
        fwrite($myfile, $e->getMessage() . $estado . ' ' . $_SESSION['mycustomer']['ListID'] . ' campo ' . $_SESSION['mycustomer']['Name'] . '\r\n');
    }
}

function quitaslashes_mycustomer() {

    $_SESSION['mycustomer']['ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ListID']));
    $_SESSION['mycustomer']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['mycustomer']['TimeCreated']));
    $_SESSION['mycustomer']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['mycustomer']['TimeCreated']));
    $_SESSION['mycustomer']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['EditSequence']));
    $_SESSION['mycustomer']['Name'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Name']));
    $_SESSION['mycustomer']['FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['FullName']));
    $_SESSION['mycustomer']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['IsActive']));
    $_SESSION['mycustomer']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ClassRef_ListID']));
    $_SESSION['mycustomer']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ClassRef_FullName']));
    $_SESSION['mycustomer']['ParentRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ParentRef_ListID']));
    $_SESSION['mycustomer']['ParentRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ParentRef_FullName']));
    $_SESSION['mycustomer']['Sublevel'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Sublevel']));
    $_SESSION['mycustomer']['CompanyName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CompanyName']));
    $_SESSION['mycustomer']['Salutation'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Salutation']));
    $_SESSION['mycustomer']['FirstName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['FirstName']));
    $_SESSION['mycustomer']['MiddleName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['MiddleName']));
    $_SESSION['mycustomer']['LastName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['LastName']));
    $_SESSION['mycustomer']['Suffix'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Suffix']));
    $_SESSION['mycustomer']['BillAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Addr1']));
    $_SESSION['mycustomer']['BillAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Addr2']));
    $_SESSION['mycustomer']['BillAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Addr3']));
    $_SESSION['mycustomer']['BillAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Addr4']));
    $_SESSION['mycustomer']['BillAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Addr5']));
    $_SESSION['mycustomer']['BillAddress_City'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_City']));
    $_SESSION['mycustomer']['BillAddress_State'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_State']));
    $_SESSION['mycustomer']['BillAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_PostalCode']));
    $_SESSION['mycustomer']['BillAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Country']));
    $_SESSION['mycustomer']['BillAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['BillAddress_Note']));
    $_SESSION['mycustomer']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Addr1']));
    $_SESSION['mycustomer']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Addr2']));
    $_SESSION['mycustomer']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Addr3']));
    $_SESSION['mycustomer']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Addr4']));
    $_SESSION['mycustomer']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Addr5']));
    $_SESSION['mycustomer']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_City']));
    $_SESSION['mycustomer']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_State']));
    $_SESSION['mycustomer']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_PostalCode']));
    $_SESSION['mycustomer']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Country']));
    $_SESSION['mycustomer']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ShipAddress_Note']));
    $_SESSION['mycustomer']['PrintAs'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PrintAs']));
    $_SESSION['mycustomer']['Phone'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Phone']));
    $_SESSION['mycustomer']['Mobile'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Mobile']));
    $_SESSION['mycustomer']['Pager'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Pager']));
    $_SESSION['mycustomer']['AltPhone'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['AltPhone']));
    $_SESSION['mycustomer']['Fax'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Fax']));
    $_SESSION['mycustomer']['Email'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Email']));
    $_SESSION['mycustomer']['Cc'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Cc']));
    $_SESSION['mycustomer']['Contact'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Contact']));
    $_SESSION['mycustomer']['AltContact'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['AltContact']));
    $_SESSION['mycustomer']['CustomerTypeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CustomerTypeRef_ListID']));
    $_SESSION['mycustomer']['CustomerTypeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CustomerTypeRef_FullName']));
    $_SESSION['mycustomer']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['TermsRef_ListID']));
    $_SESSION['mycustomer']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['TermsRef_FullName']));
    $_SESSION['mycustomer']['SalesRepRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['SalesRepRef_ListID']));
    $_SESSION['mycustomer']['SalesRepRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['SalesRepRef_FullName']));
    $_SESSION['mycustomer']['Balance'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Balance']));
    $_SESSION['mycustomer']['TotalBalance'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['TotalBalance']));
    $_SESSION['mycustomer']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['SalesTaxCodeRef_ListID']));
    $_SESSION['mycustomer']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['SalesTaxCodeRef_FullName']));
    $_SESSION['mycustomer']['ItemSalesTaxRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ItemSalesTaxRef_ListID']));
    $_SESSION['mycustomer']['ItemSalesTaxRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ItemSalesTaxRef_FullName']));
    $_SESSION['mycustomer']['SalesTaxCountry'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['SalesTaxCountry']));
    $_SESSION['mycustomer']['ResaleNumber'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ResaleNumber']));
    $_SESSION['mycustomer']['AccountNumber'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['AccountNumber']));
    $_SESSION['mycustomer']['CreditLimit'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CreditLimit']));
    $_SESSION['mycustomer']['PreferredPaymentMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PreferredPaymentMethodRef_ListID']));
    $_SESSION['mycustomer']['PreferredPaymentMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PreferredPaymentMethodRef_FullName']));
    $_SESSION['mycustomer']['CreditCardNumber'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CreditCardNumber']));
    $_SESSION['mycustomer']['ExpirationMonth'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ExpirationMonth']));
    $_SESSION['mycustomer']['ExpirationYear'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['ExpirationYear']));
    $_SESSION['mycustomer']['NameOnCard'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['NameOnCard']));
    $_SESSION['mycustomer']['CreditCardAddress'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CreditCardAddress']));
    $_SESSION['mycustomer']['CreditCardPostalCode'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CreditCardPostalCode']));
    $_SESSION['mycustomer']['JobStatus'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobStatus']));
    $_SESSION['mycustomer']['JobStartDate'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobStartDate']));
    $_SESSION['mycustomer']['JobProjectedEndDate'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobProjectedEndDate']));
    $_SESSION['mycustomer']['JobEndDate'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobEndDate']));
    $_SESSION['mycustomer']['JobDesc'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobDesc']));
    $_SESSION['mycustomer']['JobTypeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobTypeRef_ListID']));
    $_SESSION['mycustomer']['JobTypeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['JobTypeRef_FullName']));
    $_SESSION['mycustomer']['Notes'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['Notes']));
    $_SESSION['mycustomer']['PriceLevelRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PriceLevelRef_ListID']));
    $_SESSION['mycustomer']['PriceLevelRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PriceLevelRef_FullName']));
    $_SESSION['mycustomer']['TaxRegistrationNumber'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['TaxRegistrationNumber']));
    $_SESSION['mycustomer']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CurrencyRef_ListID']));
    $_SESSION['mycustomer']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['CurrencyRef_FullName']));
    $_SESSION['mycustomer']['IsStatementWithParent'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['IsStatementWithParent']));
    $_SESSION['mycustomer']['PreferredDeliveryMethod'] = htmlspecialchars(strip_tags($_SESSION['mycustomer']['PreferredDeliveryMethod']));
}

function buscaIgual_mycustomer($db, $myfile) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM my_customer_table WHERE quickbooks_listid = :clavesita;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clavesita', $_SESSION['mycustomer']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['quickbooks_listid'] === $_SESSION['mycustomer']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        fwrite($myfile, $e->getMessage() . $estado . ' ' . $_SESSION['mycustomer']['ListID'] . ' campo ' . $_SESSION['mycustomer']['Name'] . '\r\n');
    }

    return $estado;
}

function actualiza_mycustomer($db, $myfile) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE my_customer_table SET timecreated=:timecreated, timemodified=:timemodified, name=:name, fullname=:fullname, fname=:fname, lname=:lname, salutation=:salutation, address=:address, city=:city, state=:state, zipcode=:zipcode, country=:country, email=:email, quickbooks_listid=:quickbooks_listid, quickbooks_editsequence=:quickbooks_editsequence, quickbooks_errnum=:quickbooks_errnum, quickbooks_errmsg=:quickbooks_errmsg, sales_rep_ref_listid=:sales_rep_ref_listid, sales_rep_ref_fullname=:sales_rep_ref_fullname, sales_tax_code_ref_listid=:sales_tax_code_ref_listid, sales_tax_code_ref_fullname=:sales_tax_code_ref_fullname, tax_code_ref_listid=:tax_code_ref_listid, tax_code_ref_fullname=:tax_code_ref_fullname, item_sales_tax_ref_listid=:item_sales_tax_ref_listid, item_sales_tax_ref_fullname=:item_sales_tax_ref_fullname WHERE quickbooks_listid = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':timecreated', $_SESSION['mycustomer']['TimeCreated']);
        $stmt->bindParam(':timemodified', $_SESSION['mycustomer']['TimeModified']);
        $stmt->bindParam(':name', $_SESSION['mycustomer']['Name']);
        $stmt->bindParam(':fullname', $_SESSION['mycustomer']['FullName']);
        $stmt->bindParam(':fname', $_SESSION['mycustomer']['FirstName']);
        $stmt->bindParam(':lname', $_SESSION['mycustomer']['LastName']);
        $stmt->bindParam(':salutation', $_SESSION['mycustomer']['Salutation']);
        $stmt->bindParam(':address', $_SESSION['mycustomer']['BillAddress_Addr1']);
        $stmt->bindParam(':city', $_SESSION['mycustomer']['BillAddress_City']);
        $stmt->bindParam(':state', $_SESSION['mycustomer']['BillAddress_State']);
        $stmt->bindParam(':zipcode', $_SESSION['mycustomer']['BillAddress_PostalCode']);
        $stmt->bindParam(':country', $_SESSION['mycustomer']['BillAddress_Country']);
        $stmt->bindParam(':email', $_SESSION['mycustomer']['Email']);
        $stmt->bindParam(':quickbooks_listid', $_SESSION['mycustomer']['ListID']);
        $stmt->bindParam(':quickbooks_editsequence', $_SESSION['mycustomer']['EditSequence']);
        $stmt->bindParam(':quickbooks_errnum', $_SESSION['mycustomer']['TermsRef_ListID']);
        $stmt->bindParam(':quickbooks_errmsg', $_SESSION['mycustomer']['TermsRef_FullName']);
        $stmt->bindParam(':sales_rep_ref_listid', $_SESSION['mycustomer']['SalesRepRef_ListID']);
        $stmt->bindParam(':sales_rep_ref_fullname', $_SESSION['mycustomer']['SalesRepRef_FullName']);
        $stmt->bindParam(':sales_tax_code_ref_listid', $_SESSION['mycustomer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':sales_tax_code_ref_fullname', $_SESSION['mycustomer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':tax_code_ref_listid', $_SESSION['mycustomer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':tax_code_ref_fullname', $_SESSION['mycustomer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':item_sales_tax_ref_listid', $_SESSION['mycustomer']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':item_sales_tax_ref_fullname', $_SESSION['mycustomer']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':clave', $_SESSION['mycustomer']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        fwrite($myfile, "SI Existe cliente " . $e->getMessage() . " " . $estado  . " \r\n");
    }
}
