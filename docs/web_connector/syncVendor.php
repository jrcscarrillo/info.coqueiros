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
define('QB_QUICKBOOKS_MAX_RETURNED', 3725);
define('QB_QUICKBOOKS_MAILTO', 'jrcscarrillo@gmail.com');
define('QB_PRIORITY_VENDOR', 1);
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
define("AU_DESDE", $fecha);
define('FECHAMODIFICACION', $fecha);
$fecha = date("Y-m-d", strtotime($registro['otrosHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);

$map = array(
   QUICKBOOKS_IMPORT_VENDOR => array('_quickbooks_vendor_import_request', '_quickbooks_vendor_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_VENDOR)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_VENDOR, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_VENDOR, 1, QB_PRIORITY_VENDOR, NULL, $user);
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

function _quickbooks_vendor_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_vendor_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<VendorQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <FromModifiedDate>' . FECHAMODIFICACION . '</FromModifiedDate>
                            <OwnerID>0</OwnerID>
			</VendorQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_vendor_initial_response() {
    
}

function _quickbooks_vendor_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_VENDOR, null, QB_PRIORITY_VENDOR, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    $_SESSION['vendor'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("vendors.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "VendorRet";
    $proveedor = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($proveedor as $uno) {
        genLimpia_vendor();
        gentraverse_vendor($uno, $myfile, $k);
        $existe = buscaIgual_vendor($db);
        if ($existe == "OK") {
            quitaslashes_vendor();
            $retorna = adiciona_vendor($db);
            fwrite($myfile, "Proveedor nuevo" . $retorna . " \r\n");
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_vendor();
            $paso = actualiza_vendor($db);
            fwrite($myfile, "Existe proveedor " . $paso . " \r\n");
        } else {
            fwrite($myfile, $existe . " \r\n");
        }

        $k++;
    }
    fwrite($myfile, "-------->  FIN DEL LOG \r\n");
    fclose($myfile);
    fclose($myfile1);

    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_VENDOR) {
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

function genLimpia_vendor() {
    $_SESSION['vendor']['ListID'] = ' ';
    $_SESSION['vendor']['TimeCreated'] = ' ';
    $_SESSION['vendor']['TimeModified'] = ' ';
    $_SESSION['vendor']['EditSequence'] = ' ';
    $_SESSION['vendor']['Name'] = ' ';
    $_SESSION['vendor']['IsActive'] = 'true';
    $_SESSION['vendor']['ClassRef_ListID'] = ' ';
    $_SESSION['vendor']['ClassRef_FullName'] = ' ';
    $_SESSION['vendor']['CompanyName'] = ' ';
    $_SESSION['vendor']['Salutation'] = ' ';
    $_SESSION['vendor']['FirstName'] = ' ';
    $_SESSION['vendor']['MiddleName'] = ' ';
    $_SESSION['vendor']['LastName'] = ' ';
    $_SESSION['vendor']['JobTitle'] = ' ';
    $_SESSION['vendor']['Suffix'] = ' ';
    $_SESSION['vendor']['VendorAddress_Addr1'] = ' ';
    $_SESSION['vendor']['VendorAddress_Addr2'] = ' ';
    $_SESSION['vendor']['VendorAddress_Addr3'] = ' ';
    $_SESSION['vendor']['VendorAddress_Addr4'] = ' ';
    $_SESSION['vendor']['VendorAddress_Addr5'] = ' ';
    $_SESSION['vendor']['VendorAddress_City'] = ' ';
    $_SESSION['vendor']['VendorAddress_State'] = ' ';
    $_SESSION['vendor']['VendorAddress_PostalCode'] = ' ';
    $_SESSION['vendor']['VendorAddress_Country'] = ' ';
    $_SESSION['vendor']['VendorAddress_Note'] = ' ';
    $_SESSION['vendor']['ShipAddress_Addr1'] = ' ';
    $_SESSION['vendor']['ShipAddress_Addr2'] = ' ';
    $_SESSION['vendor']['ShipAddress_Addr3'] = ' ';
    $_SESSION['vendor']['ShipAddress_Addr4'] = ' ';
    $_SESSION['vendor']['ShipAddress_Addr5'] = ' ';
    $_SESSION['vendor']['ShipAddress_City'] = ' ';
    $_SESSION['vendor']['ShipAddress_State'] = ' ';
    $_SESSION['vendor']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['vendor']['ShipAddress_Country'] = ' ';
    $_SESSION['vendor']['ShipAddress_Note'] = ' ';
    $_SESSION['vendor']['Phone'] = ' ';
    $_SESSION['vendor']['Mobile'] = ' ';
    $_SESSION['vendor']['Pager'] = ' ';
    $_SESSION['vendor']['AltPhone'] = ' ';
    $_SESSION['vendor']['Fax'] = ' ';
    $_SESSION['vendor']['Email'] = ' ';
    $_SESSION['vendor']['Cc'] = ' ';
    $_SESSION['vendor']['Contact'] = ' ';
    $_SESSION['vendor']['AltContact'] = ' ';
    $_SESSION['vendor']['NameOnCheck'] = ' ';
    $_SESSION['vendor']['Notes'] = ' ';
    $_SESSION['vendor']['AccountNumber'] = ' ';
    $_SESSION['vendor']['VendorTypeRef_ListID'] = ' ';
    $_SESSION['vendor']['VendorTypeRef_FullName'] = ' ';
    $_SESSION['vendor']['TermsRef_ListID'] = ' ';
    $_SESSION['vendor']['TermsRef_FullName'] = ' ';
    $_SESSION['vendor']['CreditLimit'] = 0;
    $_SESSION['vendor']['VendorTaxIdent'] = ' ';
    $_SESSION['vendor']['IsVendorEligibleFor1099'] = 'false';
    $_SESSION['vendor']['Balance'] = 0;
    $_SESSION['vendor']['CurrencyRef_ListID'] = ' ';
    $_SESSION['vendor']['CurrencyRef_FullName'] = ' ';
    $_SESSION['vendor']['BillingRateRef_ListID'] = ' ';
    $_SESSION['vendor']['BillingRateRef_FullName'] = ' ';
    $_SESSION['vendor']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['vendor']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['vendor']['SalesTaxCountry'] = ' ';
    $_SESSION['vendor']['IsSalesTaxAgency'] = 0;
    $_SESSION['vendor']['SalesTaxReturnRef_ListID'] = ' ';
    $_SESSION['vendor']['SalesTaxReturnRef_FullName'] = ' ';
    $_SESSION['vendor']['TaxRegistrationNumber'] = ' ';
    $_SESSION['vendor']['ReportingPeriod'] = ' ';
    $_SESSION['vendor']['IsTaxTrackedOnPurchases'] = 'false';
    $_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID'] = ' ';
    $_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName'] = ' ';
    $_SESSION['vendor']['IsTaxTrackedOnSales'] = 'false';
    $_SESSION['vendor']['TaxOnSalesAccountRef_ListID'] = ' ';
    $_SESSION['vendor']['TaxOnSalesAccountRef_FullName'] = ' ';
    $_SESSION['vendor']['IsTaxOnTax'] = 'false';
    $_SESSION['vendor']['PrefillAccountRef_ListID'] = ' ';
    $_SESSION['vendor']['PrefillAccountRef_FullName'] = ' ';
    $_SESSION['vendor']['CustomField1'] = ' ';
    $_SESSION['vendor']['CustomField2'] = ' ';
    $_SESSION['vendor']['CustomField3'] = ' ';
    $_SESSION['vendor']['CustomField4'] = ' ';
    $_SESSION['vendor']['CustomField5'] = ' ';
    $_SESSION['vendor']['CustomField6'] = ' ';
    $_SESSION['vendor']['CustomField7'] = ' ';
    $_SESSION['vendor']['CustomField8'] = ' ';
    $_SESSION['vendor']['CustomField9'] = ' ';
    $_SESSION['vendor']['CustomField10'] = 'SIN IMPRIMIR';
    $_SESSION['vendor']['CustomField11'] = ' ';
    $_SESSION['vendor']['CustomField12'] = ' ';
    $_SESSION['vendor']['CustomField13'] = ' ';
    $_SESSION['vendor']['CustomField14'] = ' ';
    $_SESSION['vendor']['CustomField15'] = 'SIN FIRMAR';
    $_SESSION['vendor']['Status'] = ' ';    
}

function genTraverse_vendor($node, $myfile, $k) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['vendor']['ListID'] = $nivel1->nodeValue;
//                    fwrite($myfile, 'Numero de nodo VendorRet ' . $k . '  DATOS  ' . $nivel1->nodeValue);
                    break;
                case 'TimeCreated':
                    $_SESSION['vendor']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['vendor']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['vendor']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['vendor']['Name'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['vendor']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'CompanyName':
                    $_SESSION['vendor']['CompanyName'] = $nivel1->nodeValue;
                    break;
                case 'Salutation':
                    $_SESSION['vendor']['Salutation'] = $nivel1->nodeValue;
                    break;
                case 'FirstName':
                    $_SESSION['vendor']['FirstName'] = $nivel1->nodeValue;
                    break;
                case 'MiddleName':
                    $_SESSION['vendor']['MiddleName'] = $nivel1->nodeValue;
                    break;
                case 'LastName':
                    $_SESSION['vendor']['LastName'] = $nivel1->nodeValue;
                    break;
                case 'JobTitle':
                    $_SESSION['vendor']['JobTitle'] = $nivel1->nodeValue;
                    break;
                case 'Suffix':
                    $_SESSION['vendor']['Suffix'] = $nivel1->nodeValue;
                    break;
                case 'Phone':
                    $_SESSION['vendor']['Phone'] = $nivel1->nodeValue;
                    break;
                case 'Mobile':
                    $_SESSION['vendor']['Mobile'] = $nivel1->nodeValue;
                    break;
                case 'Pager':
                    $_SESSION['vendor']['Pager'] = $nivel1->nodeValue;
                    break;
                case 'AltPhone':
                    $_SESSION['vendor']['AltPhone'] = $nivel1->nodeValue;
                    break;
                case 'Fax':
                    $_SESSION['vendor']['Fax'] = $nivel1->nodeValue;
                    break;
                case 'Email':
                    $_SESSION['vendor']['Email'] = $nivel1->nodeValue;
                    break;
                case 'Cc':
                    $_SESSION['vendor']['Cc'] = $nivel1->nodeValue;
                    break;
                case 'Contact':
                    $_SESSION['vendor']['Contact'] = $nivel1->nodeValue;
                    break;
                case 'AltContact':
                    $_SESSION['vendor']['AltContact'] = $nivel1->nodeValue;
                    break;
                case 'NameOnCheck':
                    $_SESSION['vendor']['NameOnCheck'] = $nivel1->nodeValue;
                    break;
                case 'Notes':
                    $_SESSION['vendor']['Notes'] = $nivel1->nodeValue;
                    break;
                case 'AccountNumber':
                    $_SESSION['vendor']['AccountNumber'] = $nivel1->nodeValue;
                    break;
                case 'VendorAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['vendor']['VendorAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['vendor']['VendorAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['vendor']['VendorAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['vendor']['VendorAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['vendor']['VendorAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['vendor']['VendorAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['vendor']['VendorAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['vendor']['VendorAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['vendor']['VendorAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['vendor']['VendorAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'ShipAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['vendor']['ShipAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['vendor']['ShipAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['vendor']['ShipAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['vendor']['ShipAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['vendor']['ShipAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['vendor']['ShipAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['vendor']['ShipAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['vendor']['ShipAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['vendor']['ShipAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['vendor']['VendorAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'ClassRef':
                case 'VendorTypeRef':
                case 'TermsRef':
                case 'CurrencyRef':
                case 'BillingRateRef':
                case 'SalesTaxCodeRef':
                case 'SalesTaxReturnRef':
                case 'TaxOnPurchasesAccountRef':
                case 'TaxOnSalesAccountRef':
                case 'PrefillAccountRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'VendorTypeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['VendorTypeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['VendorTypeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TermsRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['TermsRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['TermsRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CurrencyRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['CurrencyRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['CurrencyRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'BillingRateRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['BillingRateRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['BillingRateRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxCodeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['SalesTaxCodeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['SalesTaxCodeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxReturnRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['SalesTaxReturnRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['SalesTaxReturnRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TaxOnPurchasesAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TaxOnSalesAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['TaxOnSalesAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['TaxOnSalesAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PrefillAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendor']['PrefillAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendor']['PrefillAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
                case 'CreditLimit':
                    $_SESSION['vendor']['CreditLimit'] = $nivel1->nodeValue;
                    break;
                case 'VendorTaxIdent':
                    $_SESSION['vendor']['VendorTaxIdent'] = $nivel1->nodeValue;
                    break;
                case 'IsVendorEligibleFor1099':
                    $_SESSION['vendor']['IsVendorEligibleFor1099'] = $nivel1->nodeValue;
                    break;
                case 'Balance':
                    $_SESSION['vendor']['Balance'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxCountry':
                    $_SESSION['vendor']['SalesTaxCountry'] = $nivel1->nodeValue;
                    break;
                case 'IsSalesTaxAgency':
                    $_SESSION['vendor']['IsSalesTaxAgency'] = $nivel1->nodeValue;
                    break;
                case 'TaxRegistrationNumber':
                    $_SESSION['vendor']['TaxRegistrationNumber'] = $nivel1->nodeValue;
                    break;
                case 'ReportingPeriod':
                    $_SESSION['vendor']['ReportingPeriod'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxTrackedOnPurchases':
                    $_SESSION['vendor']['IsTaxTrackedOnPurchases'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxTrackedOnSales':
                    $_SESSION['vendor']['IsTaxTrackedOnSales'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxOnTax':
                    $_SESSION['vendor']['IsTaxOnTax'] = $nivel1->nodeValue;
                    break;
                case 'Status':
                    $_SESSION['vendor']['Status'] = $nivel1->nodeValue;
                    break;
                default :
//                    fwrite($myfile, 'Numero de nodo VendorRet ' . $k . '  DATOS  ' .$nivel1->nodeName);
                    break;
            }
        }
    }
}

function buscaIgual_vendor($db) {
        $estado = 'INIT';
        try {
            $sql =  'SELECT * FROM vendor WHERE ListID = :clave ';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':clave', $_SESSION['vendor']['ListID']);
            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            if ( ! $registro){
                $estado = 'OK';
            } else {
                if ($registro['ListID'] === $_SESSION['vendor']['ListID']) {
                $estado = 'ACTUALIZA';
                }
            }
            
        } catch(PDOException $e) {
            $estado = $e->getMessage();
        } 
    
    return $estado;
}

function quitaSlashes_vendor() {
    $_SESSION['vendor']['ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ListID']));
    $_SESSION['vendor']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['vendor']['TimeCreated']));
    $_SESSION['vendor']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['vendor']['TimeCreated']));
    $_SESSION['vendor']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['vendor']['EditSequence']));
    $_SESSION['vendor']['Name'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Name']));
    $_SESSION['vendor']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsActive']));
    $_SESSION['vendor']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ClassRef_ListID']));
    $_SESSION['vendor']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ClassRef_FullName']));
    $_SESSION['vendor']['CompanyName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CompanyName']));
    $_SESSION['vendor']['Salutation'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Salutation']));
    $_SESSION['vendor']['FirstName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['FirstName']));
    $_SESSION['vendor']['MiddleName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['MiddleName']));
    $_SESSION['vendor']['LastName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['LastName']));
    $_SESSION['vendor']['JobTitle'] = htmlspecialchars(strip_tags($_SESSION['vendor']['JobTitle']));
    $_SESSION['vendor']['Suffix'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Suffix']));
    $_SESSION['vendor']['VendorAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Addr1']));
    $_SESSION['vendor']['VendorAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Addr2']));
    $_SESSION['vendor']['VendorAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Addr3']));
    $_SESSION['vendor']['VendorAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Addr4']));
    $_SESSION['vendor']['VendorAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Addr5']));
    $_SESSION['vendor']['VendorAddress_City'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_City']));
    $_SESSION['vendor']['VendorAddress_State'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_State']));
    $_SESSION['vendor']['VendorAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_PostalCode']));
    $_SESSION['vendor']['VendorAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Country']));
    $_SESSION['vendor']['VendorAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorAddress_Note']));
    $_SESSION['vendor']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Addr1']));
    $_SESSION['vendor']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Addr2']));
    $_SESSION['vendor']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Addr3']));
    $_SESSION['vendor']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Addr4']));
    $_SESSION['vendor']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Addr5']));
    $_SESSION['vendor']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_City']));
    $_SESSION['vendor']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_State']));
    $_SESSION['vendor']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_PostalCode']));
    $_SESSION['vendor']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Country']));
    $_SESSION['vendor']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ShipAddress_Note']));
    $_SESSION['vendor']['Phone'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Phone']));
    $_SESSION['vendor']['Mobile'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Mobile']));
    $_SESSION['vendor']['Pager'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Pager']));
    $_SESSION['vendor']['AltPhone'] = htmlspecialchars(strip_tags($_SESSION['vendor']['AltPhone']));
    $_SESSION['vendor']['Fax'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Fax']));
    $_SESSION['vendor']['Email'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Email']));
    $_SESSION['vendor']['Cc'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Cc']));
    $_SESSION['vendor']['Contact'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Contact']));
    $_SESSION['vendor']['AltContact'] = htmlspecialchars(strip_tags($_SESSION['vendor']['AltContact']));
    $_SESSION['vendor']['NameOnCheck'] = htmlspecialchars(strip_tags($_SESSION['vendor']['NameOnCheck']));
    $_SESSION['vendor']['Notes'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Notes']));
    $_SESSION['vendor']['AccountNumber'] = htmlspecialchars(strip_tags($_SESSION['vendor']['AccountNumber']));
    $_SESSION['vendor']['VendorTypeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorTypeRef_ListID']));
    $_SESSION['vendor']['VendorTypeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorTypeRef_FullName']));
    $_SESSION['vendor']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TermsRef_ListID']));
    $_SESSION['vendor']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TermsRef_FullName']));
    $_SESSION['vendor']['CreditLimit'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CreditLimit']));
    $_SESSION['vendor']['VendorTaxIdent'] = htmlspecialchars(strip_tags($_SESSION['vendor']['VendorTaxIdent']));
    $_SESSION['vendor']['IsVendorEligibleFor1099'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsVendorEligibleFor1099']));
    $_SESSION['vendor']['Balance'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Balance']));
    $_SESSION['vendor']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CurrencyRef_ListID']));
    $_SESSION['vendor']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CurrencyRef_FullName']));
    $_SESSION['vendor']['BillingRateRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['BillingRateRef_ListID']));
    $_SESSION['vendor']['BillingRateRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['BillingRateRef_FullName']));
    $_SESSION['vendor']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['SalesTaxCodeRef_ListID']));
    $_SESSION['vendor']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['SalesTaxCodeRef_FullName']));
    $_SESSION['vendor']['SalesTaxCountry'] = htmlspecialchars(strip_tags($_SESSION['vendor']['SalesTaxCountry']));
    $_SESSION['vendor']['IsSalesTaxAgency'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsSalesTaxAgency']));
    $_SESSION['vendor']['SalesTaxReturnRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['SalesTaxReturnRef_ListID']));
    $_SESSION['vendor']['SalesTaxReturnRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['SalesTaxReturnRef_FullName']));
    $_SESSION['vendor']['TaxRegistrationNumber'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TaxRegistrationNumber']));
    $_SESSION['vendor']['ReportingPeriod'] = htmlspecialchars(strip_tags($_SESSION['vendor']['ReportingPeriod']));
    $_SESSION['vendor']['IsTaxTrackedOnPurchases'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsTaxTrackedOnPurchases']));
    $_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID']));
    $_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName']));
    $_SESSION['vendor']['IsTaxTrackedOnSales'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsTaxTrackedOnSales']));
    $_SESSION['vendor']['TaxOnSalesAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TaxOnSalesAccountRef_ListID']));
    $_SESSION['vendor']['TaxOnSalesAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['TaxOnSalesAccountRef_FullName']));
    $_SESSION['vendor']['IsTaxOnTax'] = htmlspecialchars(strip_tags($_SESSION['vendor']['IsTaxOnTax']));
    $_SESSION['vendor']['PrefillAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendor']['PrefillAccountRef_ListID']));
    $_SESSION['vendor']['PrefillAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendor']['PrefillAccountRef_FullName']));
    $_SESSION['vendor']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField1']));
    $_SESSION['vendor']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField2']));
    $_SESSION['vendor']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField3']));
    $_SESSION['vendor']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField4']));
    $_SESSION['vendor']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField5']));
    $_SESSION['vendor']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField6']));
    $_SESSION['vendor']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField7']));
    $_SESSION['vendor']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField8']));
    $_SESSION['vendor']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField9']));
    $_SESSION['vendor']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField10']));
    $_SESSION['vendor']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField11']));
    $_SESSION['vendor']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField12']));
    $_SESSION['vendor']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField13']));
    $_SESSION['vendor']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField14']));
    $_SESSION['vendor']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['vendor']['CustomField15']));
    $_SESSION['vendor']['Status'] = htmlspecialchars(strip_tags($_SESSION['vendor']['Status']));    
}

function adiciona_vendor($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO vendor (  ListID, TimeCreated, TimeModified, EditSequence, Name, IsActive, ClassRef_ListID, ClassRef_FullName, CompanyName, Salutation, FirstName, MiddleName, LastName, JobTitle, Suffix, VendorAddress_Addr1, VendorAddress_Addr2, VendorAddress_Addr3, VendorAddress_Addr4, VendorAddress_Addr5, VendorAddress_City, VendorAddress_State, VendorAddress_PostalCode, VendorAddress_Country, VendorAddress_Note, ShipAddress_Addr1, ShipAddress_Addr2, ShipAddress_Addr3, ShipAddress_Addr4, ShipAddress_Addr5, ShipAddress_City, ShipAddress_State, ShipAddress_PostalCode, ShipAddress_Country, ShipAddress_Note, Phone, Mobile, Pager, AltPhone, Fax, Email, Cc, Contact, AltContact, NameOnCheck, Notes, AccountNumber, VendorTypeRef_ListID, VendorTypeRef_FullName, TermsRef_ListID, TermsRef_FullName, CreditLimit, VendorTaxIdent, IsVendorEligibleFor1099, Balance, CurrencyRef_ListID, CurrencyRef_FullName, BillingRateRef_ListID, BillingRateRef_FullName, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, SalesTaxCountry, IsSalesTaxAgency, SalesTaxReturnRef_ListID, SalesTaxReturnRef_FullName, TaxRegistrationNumber, ReportingPeriod, IsTaxTrackedOnPurchases, TaxOnPurchasesAccountRef_ListID, TaxOnPurchasesAccountRef_FullName, IsTaxTrackedOnSales, TaxOnSalesAccountRef_ListID, TaxOnSalesAccountRef_FullName, IsTaxOnTax, PrefillAccountRef_ListID, PrefillAccountRef_FullName, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :IsActive, :ClassRef_ListID, :ClassRef_FullName, :CompanyName, :Salutation, :FirstName, :MiddleName, :LastName, :JobTitle, :Suffix, :VendorAddress_Addr1, :VendorAddress_Addr2, :VendorAddress_Addr3, :VendorAddress_Addr4, :VendorAddress_Addr5, :VendorAddress_City, :VendorAddress_State, :VendorAddress_PostalCode, :VendorAddress_Country, :VendorAddress_Note, :ShipAddress_Addr1, :ShipAddress_Addr2, :ShipAddress_Addr3, :ShipAddress_Addr4, :ShipAddress_Addr5, :ShipAddress_City, :ShipAddress_State, :ShipAddress_PostalCode, :ShipAddress_Country, :ShipAddress_Note, :Phone, :Mobile, :Pager, :AltPhone, :Fax, :Email, :Cc, :Contact, :AltContact, :NameOnCheck, :Notes, :AccountNumber, :VendorTypeRef_ListID, :VendorTypeRef_FullName, :TermsRef_ListID, :TermsRef_FullName, :CreditLimit, :VendorTaxIdent, :IsVendorEligibleFor1099, :Balance, :CurrencyRef_ListID, :CurrencyRef_FullName, :BillingRateRef_ListID, :BillingRateRef_FullName, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, :SalesTaxCountry, :IsSalesTaxAgency, :SalesTaxReturnRef_ListID, :SalesTaxReturnRef_FullName, :TaxRegistrationNumber, :ReportingPeriod, :IsTaxTrackedOnPurchases, :TaxOnPurchasesAccountRef_ListID, :TaxOnPurchasesAccountRef_FullName, :IsTaxTrackedOnSales, :TaxOnSalesAccountRef_ListID, :TaxOnSalesAccountRef_FullName, :IsTaxOnTax, :PrefillAccountRef_ListID, :PrefillAccountRef_FullName, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';

        $stmt = $db->prepare($sql);
	 $stmt->bindParam(':ListID', $_SESSION['vendor']['ListID'] );
	$stmt->bindParam(':TimeCreated', $_SESSION['vendor']['TimeCreated'] );
	$stmt->bindParam(':TimeModified', $_SESSION['vendor']['TimeModified'] );
	$stmt->bindParam(':EditSequence', $_SESSION['vendor']['EditSequence'] );
	$stmt->bindParam(':Name', $_SESSION['vendor']['Name'] );
	$stmt->bindParam(':IsActive', $_SESSION['vendor']['IsActive'] );
	$stmt->bindParam(':ClassRef_ListID', $_SESSION['vendor']['ClassRef_ListID'] );
	$stmt->bindParam(':ClassRef_FullName', $_SESSION['vendor']['ClassRef_FullName'] );
	$stmt->bindParam(':CompanyName', $_SESSION['vendor']['CompanyName'] );
	$stmt->bindParam(':Salutation', $_SESSION['vendor']['Salutation'] );
	$stmt->bindParam(':FirstName', $_SESSION['vendor']['FirstName'] );
	$stmt->bindParam(':MiddleName', $_SESSION['vendor']['MiddleName'] );
	$stmt->bindParam(':LastName', $_SESSION['vendor']['LastName'] );
	$stmt->bindParam(':JobTitle', $_SESSION['vendor']['JobTitle'] );
	$stmt->bindParam(':Suffix', $_SESSION['vendor']['Suffix'] );
	$stmt->bindParam(':VendorAddress_Addr1', $_SESSION['vendor']['VendorAddress_Addr1'] );
	$stmt->bindParam(':VendorAddress_Addr2', $_SESSION['vendor']['VendorAddress_Addr2'] );
	$stmt->bindParam(':VendorAddress_Addr3', $_SESSION['vendor']['VendorAddress_Addr3'] );
	$stmt->bindParam(':VendorAddress_Addr4', $_SESSION['vendor']['VendorAddress_Addr4'] );
	$stmt->bindParam(':VendorAddress_Addr5', $_SESSION['vendor']['VendorAddress_Addr5'] );
	$stmt->bindParam(':VendorAddress_City', $_SESSION['vendor']['VendorAddress_City'] );
	$stmt->bindParam(':VendorAddress_State', $_SESSION['vendor']['VendorAddress_State'] );
	$stmt->bindParam(':VendorAddress_PostalCode', $_SESSION['vendor']['VendorAddress_PostalCode'] );
	$stmt->bindParam(':VendorAddress_Country', $_SESSION['vendor']['VendorAddress_Country'] );
	$stmt->bindParam(':VendorAddress_Note', $_SESSION['vendor']['VendorAddress_Note'] );
	$stmt->bindParam(':ShipAddress_Addr1', $_SESSION['vendor']['ShipAddress_Addr1'] );
	$stmt->bindParam(':ShipAddress_Addr2', $_SESSION['vendor']['ShipAddress_Addr2'] );
	$stmt->bindParam(':ShipAddress_Addr3', $_SESSION['vendor']['ShipAddress_Addr3'] );
	$stmt->bindParam(':ShipAddress_Addr4', $_SESSION['vendor']['ShipAddress_Addr4'] );
	$stmt->bindParam(':ShipAddress_Addr5', $_SESSION['vendor']['ShipAddress_Addr5'] );
	$stmt->bindParam(':ShipAddress_City', $_SESSION['vendor']['ShipAddress_City'] );
	$stmt->bindParam(':ShipAddress_State', $_SESSION['vendor']['ShipAddress_State'] );
	$stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['vendor']['ShipAddress_PostalCode'] );
	$stmt->bindParam(':ShipAddress_Country', $_SESSION['vendor']['ShipAddress_Country'] );
	$stmt->bindParam(':ShipAddress_Note', $_SESSION['vendor']['ShipAddress_Note'] );
	$stmt->bindParam(':Phone', $_SESSION['vendor']['Phone'] );
	$stmt->bindParam(':Mobile', $_SESSION['vendor']['Mobile'] );
	$stmt->bindParam(':Pager', $_SESSION['vendor']['Pager'] );
	$stmt->bindParam(':AltPhone', $_SESSION['vendor']['AltPhone'] );
	$stmt->bindParam(':Fax', $_SESSION['vendor']['Fax'] );
	$stmt->bindParam(':Email', $_SESSION['vendor']['Email'] );
	$stmt->bindParam(':Cc', $_SESSION['vendor']['Cc'] );
	$stmt->bindParam(':Contact', $_SESSION['vendor']['Contact'] );
	$stmt->bindParam(':AltContact', $_SESSION['vendor']['AltContact'] );
	$stmt->bindParam(':NameOnCheck', $_SESSION['vendor']['NameOnCheck'] );
	$stmt->bindParam(':Notes', $_SESSION['vendor']['Notes'] );
	$stmt->bindParam(':AccountNumber', $_SESSION['vendor']['AccountNumber'] );
	$stmt->bindParam(':VendorTypeRef_ListID', $_SESSION['vendor']['VendorTypeRef_ListID'] );
	$stmt->bindParam(':VendorTypeRef_FullName', $_SESSION['vendor']['VendorTypeRef_FullName'] );
	$stmt->bindParam(':TermsRef_ListID', $_SESSION['vendor']['TermsRef_ListID'] );
	$stmt->bindParam(':TermsRef_FullName', $_SESSION['vendor']['TermsRef_FullName'] );
	$stmt->bindParam(':CreditLimit', $_SESSION['vendor']['CreditLimit'] );
	$stmt->bindParam(':VendorTaxIdent', $_SESSION['vendor']['VendorTaxIdent'] );
	$stmt->bindParam(':IsVendorEligibleFor1099', $_SESSION['vendor']['IsVendorEligibleFor1099'] );
	$stmt->bindParam(':Balance', $_SESSION['vendor']['Balance'] );
	$stmt->bindParam(':CurrencyRef_ListID', $_SESSION['vendor']['CurrencyRef_ListID'] );
	$stmt->bindParam(':CurrencyRef_FullName', $_SESSION['vendor']['CurrencyRef_FullName'] );
	$stmt->bindParam(':BillingRateRef_ListID', $_SESSION['vendor']['BillingRateRef_ListID'] );
	$stmt->bindParam(':BillingRateRef_FullName', $_SESSION['vendor']['BillingRateRef_FullName'] );
	$stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['vendor']['SalesTaxCodeRef_ListID'] );
	$stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['vendor']['SalesTaxCodeRef_FullName'] );
	$stmt->bindParam(':SalesTaxCountry', $_SESSION['vendor']['SalesTaxCountry'] );
	$stmt->bindParam(':IsSalesTaxAgency', $_SESSION['vendor']['IsSalesTaxAgency'] );
	$stmt->bindParam(':SalesTaxReturnRef_ListID', $_SESSION['vendor']['SalesTaxReturnRef_ListID'] );
	$stmt->bindParam(':SalesTaxReturnRef_FullName', $_SESSION['vendor']['SalesTaxReturnRef_FullName'] );
	$stmt->bindParam(':TaxRegistrationNumber', $_SESSION['vendor']['TaxRegistrationNumber'] );
	$stmt->bindParam(':ReportingPeriod', $_SESSION['vendor']['ReportingPeriod'] );
	$stmt->bindParam(':IsTaxTrackedOnPurchases', $_SESSION['vendor']['IsTaxTrackedOnPurchases'] );
	$stmt->bindParam(':TaxOnPurchasesAccountRef_ListID', $_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID'] );
	$stmt->bindParam(':TaxOnPurchasesAccountRef_FullName', $_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName'] );
	$stmt->bindParam(':IsTaxTrackedOnSales', $_SESSION['vendor']['IsTaxTrackedOnSales'] );
	$stmt->bindParam(':TaxOnSalesAccountRef_ListID', $_SESSION['vendor']['TaxOnSalesAccountRef_ListID'] );
	$stmt->bindParam(':TaxOnSalesAccountRef_FullName', $_SESSION['vendor']['TaxOnSalesAccountRef_FullName'] );
	$stmt->bindParam(':IsTaxOnTax', $_SESSION['vendor']['IsTaxOnTax'] );
	$stmt->bindParam(':PrefillAccountRef_ListID', $_SESSION['vendor']['PrefillAccountRef_ListID'] );
	$stmt->bindParam(':PrefillAccountRef_FullName', $_SESSION['vendor']['PrefillAccountRef_FullName'] );
	$stmt->bindParam(':CustomField1', $_SESSION['vendor']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['vendor']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['vendor']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['vendor']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['vendor']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['vendor']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['vendor']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['vendor']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['vendor']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['vendor']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['vendor']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['vendor']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['vendor']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['vendor']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['vendor']['CustomField15'] );
	$stmt->bindParam(':Status', $_SESSION['vendor']['Status'] );
	$stmt->execute();
    } catch (PDOException $e) {
        $estado = $e->getTraceAsString();
    }
    return $estado;    
}

function actualiza_vendor($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE vendor SET TimeCreated=:TimeCreated,TimeModified=:TimeModified,EditSequence=:EditSequence,Name=:Name,IsActive=:IsActive,ClassRef_ListID=:ClassRef_ListID,ClassRef_FullName=:ClassRef_FullName,CompanyName=:CompanyName,Salutation=:Salutation,FirstName=:FirstName,MiddleName=:MiddleName,LastName=:LastName,JobTitle=:JobTitle,Suffix=:Suffix,VendorAddress_Addr1=:VendorAddress_Addr1,VendorAddress_Addr2=:VendorAddress_Addr2,VendorAddress_Addr3=:VendorAddress_Addr3,VendorAddress_Addr4=:VendorAddress_Addr4,VendorAddress_Addr5=:VendorAddress_Addr5,VendorAddress_City=:VendorAddress_City,VendorAddress_State=:VendorAddress_State,VendorAddress_PostalCode=:VendorAddress_PostalCode,VendorAddress_Country=:VendorAddress_Country,VendorAddress_Note=:VendorAddress_Note,ShipAddress_Addr1=:ShipAddress_Addr1,ShipAddress_Addr2=:ShipAddress_Addr2,ShipAddress_Addr3=:ShipAddress_Addr3,ShipAddress_Addr4=:ShipAddress_Addr4,ShipAddress_Addr5=:ShipAddress_Addr5,ShipAddress_City=:ShipAddress_City,ShipAddress_State=:ShipAddress_State,ShipAddress_PostalCode=:ShipAddress_PostalCode,ShipAddress_Country=:ShipAddress_Country,ShipAddress_Note=:ShipAddress_Note,Phone=:Phone,Mobile=:Mobile,Pager=:Pager,AltPhone=:AltPhone,Fax=:Fax,Email=:Email,Cc=:Cc,Contact=:Contact,AltContact=:AltContact,NameOnCheck=:NameOnCheck,Notes=:Notes,AccountNumber=:AccountNumber,VendorTypeRef_ListID=:VendorTypeRef_ListID,VendorTypeRef_FullName=:VendorTypeRef_FullName,TermsRef_ListID=:TermsRef_ListID,TermsRef_FullName=:TermsRef_FullName,CreditLimit=:CreditLimit,VendorTaxIdent=:VendorTaxIdent,IsVendorEligibleFor1099=:IsVendorEligibleFor1099,Balance=:Balance,CurrencyRef_ListID=:CurrencyRef_ListID,CurrencyRef_FullName=:CurrencyRef_FullName,BillingRateRef_ListID=:BillingRateRef_ListID,BillingRateRef_FullName=:BillingRateRef_FullName,SalesTaxCodeRef_ListID=:SalesTaxCodeRef_ListID,SalesTaxCodeRef_FullName=:SalesTaxCodeRef_FullName,SalesTaxCountry=:SalesTaxCountry,IsSalesTaxAgency=:IsSalesTaxAgency,SalesTaxReturnRef_ListID=:SalesTaxReturnRef_ListID,SalesTaxReturnRef_FullName=:SalesTaxReturnRef_FullName,TaxRegistrationNumber=:TaxRegistrationNumber,ReportingPeriod=:ReportingPeriod,IsTaxTrackedOnPurchases=:IsTaxTrackedOnPurchases,TaxOnPurchasesAccountRef_ListID=:TaxOnPurchasesAccountRef_ListID,TaxOnPurchasesAccountRef_FullName=:TaxOnPurchasesAccountRef_FullName,IsTaxTrackedOnSales=:IsTaxTrackedOnSales,TaxOnSalesAccountRef_ListID=:TaxOnSalesAccountRef_ListID,TaxOnSalesAccountRef_FullName=:TaxOnSalesAccountRef_FullName,IsTaxOnTax=:IsTaxOnTax,PrefillAccountRef_ListID=:PrefillAccountRef_ListID,PrefillAccountRef_FullName=:PrefillAccountRef_FullName,CustomField1=:CustomField1,CustomField2=:CustomField2,CustomField3=:CustomField3,CustomField4=:CustomField4,CustomField5=:CustomField5,CustomField6=:CustomField6,CustomField7=:CustomField7,CustomField8=:CustomField8,CustomField9=:CustomField9,CustomField10=:CustomField10,CustomField11=:CustomField11,CustomField12=:CustomField12,CustomField13=:CustomField13,CustomField14=:CustomField14,CustomField15=:CustomField15,Status=:Status WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['vendor']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['vendor']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['vendor']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['vendor']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['vendor']['IsActive']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['vendor']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['vendor']['ClassRef_FullName']);
        $stmt->bindParam(':CompanyName', $_SESSION['vendor']['CompanyName']);
        $stmt->bindParam(':Salutation', $_SESSION['vendor']['Salutation']);
        $stmt->bindParam(':FirstName', $_SESSION['vendor']['FirstName']);
        $stmt->bindParam(':MiddleName', $_SESSION['vendor']['MiddleName']);
        $stmt->bindParam(':LastName', $_SESSION['vendor']['LastName']);
        $stmt->bindParam(':JobTitle', $_SESSION['vendor']['JobTitle']);
        $stmt->bindParam(':Suffix', $_SESSION['vendor']['Suffix']);
        $stmt->bindParam(':VendorAddress_Addr1', $_SESSION['vendor']['VendorAddress_Addr1']);
        $stmt->bindParam(':VendorAddress_Addr2', $_SESSION['vendor']['VendorAddress_Addr2']);
        $stmt->bindParam(':VendorAddress_Addr3', $_SESSION['vendor']['VendorAddress_Addr3']);
        $stmt->bindParam(':VendorAddress_Addr4', $_SESSION['vendor']['VendorAddress_Addr4']);
        $stmt->bindParam(':VendorAddress_Addr5', $_SESSION['vendor']['VendorAddress_Addr5']);
        $stmt->bindParam(':VendorAddress_City', $_SESSION['vendor']['VendorAddress_City']);
        $stmt->bindParam(':VendorAddress_State', $_SESSION['vendor']['VendorAddress_State']);
        $stmt->bindParam(':VendorAddress_PostalCode', $_SESSION['vendor']['VendorAddress_PostalCode']);
        $stmt->bindParam(':VendorAddress_Country', $_SESSION['vendor']['VendorAddress_Country']);
        $stmt->bindParam(':VendorAddress_Note', $_SESSION['vendor']['VendorAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['vendor']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['vendor']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['vendor']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['vendor']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['vendor']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['vendor']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['vendor']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['vendor']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['vendor']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['vendor']['ShipAddress_Note']);
        $stmt->bindParam(':Phone', $_SESSION['vendor']['Phone']);
        $stmt->bindParam(':Mobile', $_SESSION['vendor']['Mobile']);
        $stmt->bindParam(':Pager', $_SESSION['vendor']['Pager']);
        $stmt->bindParam(':AltPhone', $_SESSION['vendor']['AltPhone']);
        $stmt->bindParam(':Fax', $_SESSION['vendor']['Fax']);
        $stmt->bindParam(':Email', $_SESSION['vendor']['Email']);
        $stmt->bindParam(':Cc', $_SESSION['vendor']['Cc']);
        $stmt->bindParam(':Contact', $_SESSION['vendor']['Contact']);
        $stmt->bindParam(':AltContact', $_SESSION['vendor']['AltContact']);
        $stmt->bindParam(':NameOnCheck', $_SESSION['vendor']['NameOnCheck']);
        $stmt->bindParam(':Notes', $_SESSION['vendor']['Notes']);
        $stmt->bindParam(':AccountNumber', $_SESSION['vendor']['AccountNumber']);
        $stmt->bindParam(':VendorTypeRef_ListID', $_SESSION['vendor']['VendorTypeRef_ListID']);
        $stmt->bindParam(':VendorTypeRef_FullName', $_SESSION['vendor']['VendorTypeRef_FullName']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['vendor']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['vendor']['TermsRef_FullName']);
        $stmt->bindParam(':CreditLimit', $_SESSION['vendor']['CreditLimit']);
        $stmt->bindParam(':VendorTaxIdent', $_SESSION['vendor']['VendorTaxIdent']);
        $stmt->bindParam(':IsVendorEligibleFor1099', $_SESSION['vendor']['IsVendorEligibleFor1099']);
        $stmt->bindParam(':Balance', $_SESSION['vendor']['Balance']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['vendor']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['vendor']['CurrencyRef_FullName']);
        $stmt->bindParam(':BillingRateRef_ListID', $_SESSION['vendor']['BillingRateRef_ListID']);
        $stmt->bindParam(':BillingRateRef_FullName', $_SESSION['vendor']['BillingRateRef_FullName']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['vendor']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['vendor']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':SalesTaxCountry', $_SESSION['vendor']['SalesTaxCountry']);
        $stmt->bindParam(':IsSalesTaxAgency', $_SESSION['vendor']['IsSalesTaxAgency']);
        $stmt->bindParam(':SalesTaxReturnRef_ListID', $_SESSION['vendor']['SalesTaxReturnRef_ListID']);
        $stmt->bindParam(':SalesTaxReturnRef_FullName', $_SESSION['vendor']['SalesTaxReturnRef_FullName']);
        $stmt->bindParam(':TaxRegistrationNumber', $_SESSION['vendor']['TaxRegistrationNumber']);
        $stmt->bindParam(':ReportingPeriod', $_SESSION['vendor']['ReportingPeriod']);
        $stmt->bindParam(':IsTaxTrackedOnPurchases', $_SESSION['vendor']['IsTaxTrackedOnPurchases']);
        $stmt->bindParam(':TaxOnPurchasesAccountRef_ListID', $_SESSION['vendor']['TaxOnPurchasesAccountRef_ListID']);
        $stmt->bindParam(':TaxOnPurchasesAccountRef_FullName', $_SESSION['vendor']['TaxOnPurchasesAccountRef_FullName']);
        $stmt->bindParam(':IsTaxTrackedOnSales', $_SESSION['vendor']['IsTaxTrackedOnSales']);
        $stmt->bindParam(':TaxOnSalesAccountRef_ListID', $_SESSION['vendor']['TaxOnSalesAccountRef_ListID']);
        $stmt->bindParam(':TaxOnSalesAccountRef_FullName', $_SESSION['vendor']['TaxOnSalesAccountRef_FullName']);
        $stmt->bindParam(':IsTaxOnTax', $_SESSION['vendor']['IsTaxOnTax']);
        $stmt->bindParam(':PrefillAccountRef_ListID', $_SESSION['vendor']['PrefillAccountRef_ListID']);
        $stmt->bindParam(':PrefillAccountRef_FullName', $_SESSION['vendor']['PrefillAccountRef_FullName']);
        $stmt->bindParam(':CustomField1', $_SESSION['vendor']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['vendor']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['vendor']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['vendor']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['vendor']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['vendor']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['vendor']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['vendor']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['vendor']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['vendor']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['vendor']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['vendor']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['vendor']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['vendor']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['vendor']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['vendor']['Status']);

        $stmt->bindParam(':clave', $_SESSION['vendor']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = $e->getTraceAsString();
    }
    return $estado;    
}
