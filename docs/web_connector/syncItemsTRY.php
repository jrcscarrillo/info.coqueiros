<?php

session_start();
error_reporting(0);
if (!empty($_GET['support'])) {
    header('Location: http://www.consolibyte.com/');
    exit;
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/New_York');
}
require_once '../../QuickBooks.php';
require_once 'conectaDB.php';
$user = 'jrcscarrillo';
$pass = 'f9234568';

define('QB_QUICKBOOKS_CONFIG_LAST', 'last');
define('QB_QUICKBOOKS_CONFIG_CURR', 'curr');
define('QB_QUICKBOOKS_MAX_RETURNED', 1000);
define('QB_PRIORITY_ITEM', 3);

$map = array(
    QUICKBOOKS_IMPORT_ITEM => array('_quickbooks_item_import_request', '_quickbooks_item_import_response'),
);
$errmap = array(
    500 => '_quickbooks_error_e500_notfound', // Catch errors caused by searching for things not present in QuickBooks
    1 => '_quickbooks_error_e500_notfound',
    '*' => '_quickbooks_error_catchall', // Catch any other errors that might occur
);
$hooks = array(
    QuickBooks_WebConnector_Handlers::HOOK_LOGINSUCCESS => '_quickbooks_hook_loginsuccess', // call this whenever a successful login occurs
);
$log_level = QUICKBOOKS_LOG_DEVELOP;
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

$dsn = 'mysql://coqueiro_qb:freedom@localhost/coqueiro_qb';
define('QB_QUICKBOOKS_DSN', $dsn);

QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);

$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);
$response = $Server->handle(true, true);

function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config) {
    // For new users, we need to set up a few things
    // Fetch the queue instance
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    $date = '2016-01-02 12:01:01';
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_ITEM)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_ITEM, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_ITEM, 1, QB_PRIORITY_ITEM, NULL, $user);
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

function _quickbooks_item_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_ITEM, null, QB_PRIORITY_ITEM, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "ha pasado la respuesta ");
    $_SESSION['item'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $Tag_adi = $doc->getElementsByTagName('ItemQueryRs')->item(0);
    if ($Tag_adi->hasChildNodes()) {
        foreach ($Tag_adi->childNodes as $tipo) {
                    if ($tipo->nodeName == "ItemInventoryAssemblyRet"){
                        traverseDocument($tipo);
                        grabaproducto();
                }
            }
        }
    return true;
}

function grabaproducto() {
    $db = conecta_godaddy();
    $sql = "INSERT INTO items (name, fullname, description, quickbooks_listid, quickbooks_editsequence, ";
    $sql .= "quickbooks_errnum, quickbooks_errmsg, is_active, parent_reference_listid, parent_reference_full_name, ";
    $sql .= "sublevel, unit_of_measure_set_ref_listid, unit_of_measure_set_ref_fullname, type, sales_tax_code_ref_listid, ";
    $sql .= "sales_tax_code_ref_fullname, sales_desc, sales_price, income_account_ref_listid, ";
    $sql .= "income_account_ref_fullname, purchase_cost, COGS_account_ref_listid, COGS_account_ref_fullname, ";
    $sql .= "assests_account_ref_listid, assests_acc, purchase_desc, QuantityOnHand, QuantityOnOrder, QuantityOnSalesOrder, AverageCost) ";
    $sql .= "VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  
                
    $myfile = fopen("newfile.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $_SESSION['item']);
    fclose($myfile);
        if ($_SESSION['item']['is_active']){
            if (!($stmt = $db->prepare($sql))) {
                fwrite($myfile, $db->error);
                } else {
                    if (!$stmt->bind_param("ssssssssssssssssssssssssssssss", $_SESSION['item']['name'], $_SESSION['item']['fullname'], $_SESSION['item']['description'], $_SESSION['item']['quickbooks_listid'], $_SESSION['item']['quickbooks_editsequence'], $_SESSION['item']['quickbooks_errnum'], $_SESSION['item']['quickbooks_errmsg'], $_SESSION['item']['is_active'], $_SESSION['item']['parent_reference_listid'], $_SESSION['item']['parent_reference_full_name'], $_SESSION['item']['sublevel'], $_SESSION['item']['unit_of_measure_set_ref_listid'], $_SESSION['item']['unit_of_measure_set_ref_fullname'], $_SESSION['item']['type'], $_SESSION['item']['sales_tax_code_ref_listid'], $_SESSION['item']['sales_tax_code_ref_fullname'], $_SESSION['item']['sales_desc'], $_SESSION['item']['sales_price'], $_SESSION['item']['income_account_ref_listid'], $_SESSION['item']['income_account_ref_fullname'], $_SESSION['item']['purchase_cost'], $_SESSION['item']['COGS_account_ref_listid'], $_SESSION['item']['COGS_account_ref_fullname'], $_SESSION['item']['assests_account_ref_listid'], $_SESSION['item']['assests_acc'], $_SESSION['item']['purchase_desc'], $_SESSION['item']['QuantityOnHand'], $_SESSION['item']['QuantityOnOrder'], $_SESSION['item']['QuantityOnSalesOrder'], $_SESSION['item']['AverageCost'] )) {
                        fwrite($myfile, "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
                        } else {
                            if (!$stmt->execute()) {
                                fwrite($myfile, "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
                                }
                        }           
        }
        }
        $stmt->close();
        $db->close();
}
function _quickbooks_item_initial_response() {
    $db = conecta_godaddy();
    $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
    $sql = "TRUNCATE items";
    if (!($stmt = $db->prepare($sql))) {
        fwrite($myfile, $db->error);
    } else {
    if (!$stmt->execute()) {
        fwrite($myfile, "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    } else {
        fwrite($myfile, "Inicializo la tabla");
    }
}
}

function _quickbooks_item_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);   // Update the last run time to NOW()
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_item_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
            <?qbxml version="' . $version . '"?>
            <QBXML>
		<QBXMLMsgsRq onError="stopOnError">
                    <ItemQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                    <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                    <OwnerID>0</OwnerID>
                    </ItemQueryRq>	
		</QBXMLMsgsRq>
            </QBXML>';
    
    $myfile = fopen("newfile.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "ha pasado xml de request con response ");
    fclose($myfile);
    return $xml;
}

function traverseDocument($node) {
            $node->getElementsByTagName('Name')->item(0) === NULL ? $_SESSION['item']['name'] = " " : $_SESSION['item']['name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
            $node->getElementsByTagName('FullName')->item(0) === NULL ? $_SESSION['item']['fullname'] = " " : $_SESSION['item']['fullname'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
            $node->getElementsByTagName('SalesDesc')->item(0) === NULL ? $_SESSION['item']['description'] = " " : $_SESSION['item']['description'] = $node->getElementsByTagName('SalesDesc')->item(0)->nodeValue; 
            $node->getElementsByTagName('ListID')->item(0) === NULL ? : $_SESSION['item']['quickbooks_listid'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
            $node->getElementsByTagName('EditSequence')->item(0) === NULL ? $_SESSION['item']['quickbooks_editsequence'] = " " : $_SESSION['item']['quickbooks_editsequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
            $_SESSION['item']['quickbooks_errnum'] = " ";
            $_SESSION['item']['quickbooks_errmsg'] = " ";
            $node->getElementsByTagName('IsActive')->item(0) === NULL ? : $_SESSION['item']['is_active'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(1) === NULL ? : $_SESSION['item']['parent_reference_listid'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
            $node->getElementsByTagName('FullName')->item(1) === NULL ? : $_SESSION['item']['parent_reference_full_name'] = $node->getElementsByTagName('FullName')->item(1)->nodeValue;
            $node->getElementsByTagName('Sublevel')->item(0) === NULL ? : $_SESSION['item']['sublevel'] = $node->getElementsByTagName('Sublevel')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(2) === NULL ? : $_SESSION['item']['unit_of_measure_set_ref_listid'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
            $node->getElementsByTagName('FullName')->item(2) === NULL ? : $_SESSION['item']['unit_of_measure_set_ref_fullname'] = $node->getElementsByTagName('FullName')->item(2)->nodeValue;
            $_SESSION['item']['type'] = "Assembly";
            $node->getElementsByTagName('ListID')->item(3) === NULL ? : $_SESSION['item']['sales_tax_code_ref_listid'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
            $node->getElementsByTagName('FullName')->item(3) === NULL ? : $_SESSION['item']['sales_tax_code_ref_fullname'] = $node->getElementsByTagName('FullName')->item(3)->nodeValue;
            $node->getElementsByTagName('SalesDesc')->item(0) === NULL ? : $_SESSION['item']['sales_desc'] = $node->getElementsByTagName('SalesDesc')->item(0)->nodeValue;
            $node->getElementsByTagName('SalesPrice')->item(0) === NULL ? : $_SESSION['item']['sales_price'] = $node->getElementsByTagName('SalesPrice')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(4) === NULL ? : $_SESSION['item']['income_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
            $node->getElementsByTagName('FullName')->item(4) === NULL ? : $_SESSION['item']['income_account_ref_fullname'] = $node->getElementsByTagName('FullName')->item(4)->nodeValue;
            $node->getElementsByTagName('PurchaseCost')->item(0) === NULL ? : $_SESSION['item']['purchase_cost'] = $node->getElementsByTagName('PurchaseCost')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(5) === NULL ? : $_SESSION['item']['COGS_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(5)->nodeValue;
            $node->getElementsByTagName('FullName')->item(5) === NULL ? : $_SESSION['item']['COGS_account_ref_fullname'] = $node->getElementsByTagName('FullName')->item(5)->nodeValue;
            $node->getElementsByTagName('ListID')->item(6) === NULL ? : $_SESSION['item']['assests_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(6)->nodeValue;
            $node->getElementsByTagName('FullName')->item(6) === NULL ? : $_SESSION['item']['assests_acc'] = $node->getElementsByTagName('FullName')->item(6)->nodeValue;
            $_SESSION['item']['purchase_desc'] = " ";
            $node->getElementsByTagName('QuantityOnHand')->item(0) === NULL ? : $_SESSION['item']['QuantityOnHand'] = $node->getElementsByTagName('QuantityOnHand')->item(0)->nodeValue;
            $node->getElementsByTagName('QuantityOnOrder')->item(0) === NULL ? : $_SESSION['item']['QuantityOnOrder'] = $node->getElementsByTagName('QuantityOnOrder')->item(0)->nodeValue;
            $node->getElementsByTagName('QuantityOnSalesOrder')->item(0) === NULL ? : $_SESSION['item']['QuantityOnSalesOrder'] = $node->getElementsByTagName('QuantityOnSalesOrder')->item(0)->nodeValue;
            $node->getElementsByTagName('AverageCost')->item(0) === NULL ? : $_SESSION['item']['AverageCost'] = $node->getElementsByTagName('AverageCost')->item(0)->nodeValue;
    }
function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();

    if ($action == QUICKBOOKS_IMPORT_ITEM) {
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

function limpiaItem() {
    $_SESSION['item']['name'] = " ";
    $_SESSION['item']['fullname'] = " ";
    $_SESSION['item']['description'] = " ";
    $_SESSION['item']['quickbooks_listid'] = " ";
    $_SESSION['item']['quickbooks_editsequence'] = " ";
    $_SESSION['item']['quickbooks_errnum'] = " ";
    $_SESSION['item']['quickbooks_errmsg'] = " ";
    $_SESSION['item']['is_active'] = " ";
    $_SESSION['item']['parent_reference_listid'] = " ";
    $_SESSION['item']['parent_reference_full_name'] = " ";
    $_SESSION['item']['sublevel'] = " ";
    $_SESSION['item']['unit_of_measure_set_ref_listid'] = " ";
    $_SESSION['item']['unit_of_measure_set_ref_fullname'] = " ";
    $_SESSION['item']['type'] = " ";
    $_SESSION['item']['sales_tax_code_ref_listid'] = " ";
    $_SESSION['item']['sales_tax_code_ref_fullname'] = " ";
    $_SESSION['item']['sales_desc'] = " ";
    $_SESSION['item']['sales_price'] = " ";
    $_SESSION['item']['income_account_ref_listid'] = " ";
    $_SESSION['item']['income_account_ref_fullname'] = " ";
    $_SESSION['item']['purchase_cost'] = " ";
    $_SESSION['item']['COGS_account_ref_listid'] = " ";
    $_SESSION['item']['COGS_account_ref_fullname'] = " ";
    $_SESSION['item']['assests_account_ref_listid'] = " ";
    $_SESSION['item']['assests_acc'] = " ";
    $_SESSION['item']['purchase_desc'] = " ";
}
