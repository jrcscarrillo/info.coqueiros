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
define('QB_PRIORITY_ITEMSALESTAX', 1);
/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_ITEMSALESTAX => array('_quickbooks_itemsalestax_import_request', '_quickbooks_itemsalestax_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_ITEMSALESTAX)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_ITEMSALESTAX, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_ITEMSALESTAX, 1, QB_PRIORITY_ITEMSALESTAX, NULL, $user);
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

function _quickbooks_itemsalestax_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_itemsalestax_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<ItemSalesTaxQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <OwnerID>0</OwnerID>
			</ItemSalesTaxQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_itemsalestax_initial_response() {
    $db = conecta_SYNC();
    $sql = 'DELETE * FROM itemsalestax';
    $stmt = $db->prepare($sql);
    $stmt->execute();
}

function _quickbooks_itemsalestax_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_ITEMSALESTAX, null, QB_PRIORITY_ITEMSALESTAX, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['itemsalestax'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("itemsalestax.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "ItemSalesTaxRet";
    $iva = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($iva as $uno) {
        $estado = "INIT";
        genLimpia_itemsalestax();
        gentraverse_itemsalestax($uno);
        quitaslashes_itemsalestax();
        adiciona_itemsalestax($db);
        $k++;
    }

    fclose($myfile);
    fclose($myfile1);

    $db = null;
    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_ITEMSALESTAX) {
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

function adiciona_itemsalestax($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO itemsalestax (  ListID, TimeCreated, TimeModified, EditSequence, Name, BarCodeValue, IsActive, ClassRef_ListID, ClassRef_FullName, ItemDesc, IsUsedOnPurchaseTransaction, TaxRate, TaxVendorRef_ListID, TaxVendorRef_FullName, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :BarCodeValue, :IsActive, :ClassRef_ListID, :ClassRef_FullName, :ItemDesc, :IsUsedOnPurchaseTransaction, :TaxRate, :TaxVendorRef_ListID, :TaxVendorRef_FullName, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['itemsalestax']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['itemsalestax']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['itemsalestax']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['itemsalestax']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['itemsalestax']['Name']);
        $stmt->bindParam(':BarCodeValue', $_SESSION['itemsalestax']['BarCodeValue']);
        $stmt->bindParam(':IsActive', $_SESSION['itemsalestax']['IsActive']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['itemsalestax']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['itemsalestax']['ClassRef_FullName']);
        $stmt->bindParam(':ItemDesc', $_SESSION['itemsalestax']['ItemDesc']);
        $stmt->bindParam(':IsUsedOnPurchaseTransaction', $_SESSION['itemsalestax']['IsUsedOnPurchaseTransaction']);
        $stmt->bindParam(':TaxRate', $_SESSION['itemsalestax']['TaxRate']);
        $stmt->bindParam(':TaxVendorRef_ListID', $_SESSION['itemsalestax']['TaxVendorRef_ListID']);
        $stmt->bindParam(':TaxVendorRef_FullName', $_SESSION['itemsalestax']['TaxVendorRef_FullName']);
        $stmt->bindParam(':Status', $_SESSION['itemsalestax']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['itemsalestax']['ListID'];
    }
    return $estado;
}

function gentraverse_itemsalestax($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['itemsalestax']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['itemsalestax']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['itemsalestax']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['itemsalestax']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['itemsalestax']['Name'] = $nivel1->nodeValue;
                    break;
                case 'BarCodeValue':
                    $_SESSION['itemsalestax']['BarCodeValue'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['itemsalestax']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'ItemDesc':
                    $_SESSION['itemsalestax']['ItemDesc'] = $nivel1->nodeValue;
                    break;
                case 'IsUsedOnPurchaseTransaction':
                    $_SESSION['itemsalestax']['IsUsedOnPurchaseTransaction'] = $nivel1->nodeValue;
                    break;
                case 'TaxRate':
                    $_SESSION['itemsalestax']['TaxRate'] = $nivel1->nodeValue;
                    break;
                case 'ClassRef':
                case 'TaxVendorRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['itemsalestax']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['itemsalestax']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TaxVendorRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['itemsalestax']['TaxVendorRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['itemsalestax']['TaxVendorRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function quitaslashes_itemsalestax() {
    $_SESSION['itemsalestax']['ListID'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['ListID']));
    $_SESSION['itemsalestax']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['itemsalestax']['TimeCreated']));
    $_SESSION['itemsalestax']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['itemsalestax']['TimeModified']));
    $_SESSION['itemsalestax']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['EditSequence']));
    $_SESSION['itemsalestax']['Name'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['Name']));
    $_SESSION['itemsalestax']['BarCodeValue'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['BarCodeValue']));
    $_SESSION['itemsalestax']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['IsActive']));
    $_SESSION['itemsalestax']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['ClassRef_ListID']));
    $_SESSION['itemsalestax']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['ClassRef_FullName']));
    $_SESSION['itemsalestax']['ItemDesc'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['ItemDesc']));
    $_SESSION['itemsalestax']['IsUsedOnPurchaseTransaction'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['IsUsedOnPurchaseTransaction']));
    $_SESSION['itemsalestax']['TaxRate'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['TaxRate']));
    $_SESSION['itemsalestax']['TaxVendorRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['TaxVendorRef_ListID']));
    $_SESSION['itemsalestax']['TaxVendorRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['TaxVendorRef_FullName']));
    $_SESSION['itemsalestax']['Status'] = htmlspecialchars(strip_tags($_SESSION['itemsalestax']['Status']));
}

function genLimpia_itemsalestax() {
    $_SESSION['itemsalestax']['ListID'] = ' ';
    $_SESSION['itemsalestax']['TimeCreated'] = ' ';
    $_SESSION['itemsalestax']['TimeModified'] = ' ';
    $_SESSION['itemsalestax']['EditSequence'] = ' ';
    $_SESSION['itemsalestax']['Name'] = ' ';
    $_SESSION['itemsalestax']['BarCodeValue'] = ' ';
    $_SESSION['itemsalestax']['IsActive'] = ' ';
    $_SESSION['itemsalestax']['ClassRef_ListID'] = ' ';
    $_SESSION['itemsalestax']['ClassRef_FullName'] = ' ';
    $_SESSION['itemsalestax']['ItemDesc'] = ' ';
    $_SESSION['itemsalestax']['IsUsedOnPurchaseTransaction'] = ' ';
    $_SESSION['itemsalestax']['TaxRate'] = ' ';
    $_SESSION['itemsalestax']['TaxVendorRef_ListID'] = ' ';
    $_SESSION['itemsalestax']['TaxVendorRef_FullName'] = ' ';
    $_SESSION['itemsalestax']['Status'] = ' ';
}
