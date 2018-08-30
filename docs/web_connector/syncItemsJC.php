<?php

session_start();
error_reporting(1);
if (!empty($_GET['support'])) {
    header('Location: http://www.consolibyte.com/');
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
define('QB_QUICKBOOKS_MAX_RETURNED', 1000);
define('QB_PRIORITY_ITEM', 3);
define('QB_QUICKBOOKS_MAILTO', 'jrcscarrillo@gmail.com');

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

$dsn = 'mysql://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';
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
   $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['item'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $param = "ItemInventoryRet";
    $inventario = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($inventario as $uno) {
        if ($existe == "OK") {
        } elseif ($existe == "ACTUALIZA") {
        }
    }

    return true;
}

function _quickbooks_item_initial_response() {
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
                    <ItemInventoryQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                    <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                    <OwnerID>0</OwnerID>
                    </ItemInventoryQueryRq>	
		</QBXMLMsgsRq>
            </QBXML>';
    
    $myfile = fopen("newfile.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Paso 2 se ha enviado xml de request / ");
    fclose($myfile);
    return $xml;
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

    function buscaIgual_item($db) {
        $estado = 'ERR';
        try {
            $sql =  'SELECT * FROM items WHERE quickbooks_listid = :clave ';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':clave', $_SESSION['item']['quickbooks_listid']);
            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            if ( ! $registro){
                $estado = 'OK';
            } else {
                if ($registro['quickbooks_listid'] === $_SESSION['item']['quickbooks_listid']) {
                $estado = 'ACTUALIZA';
                }
            }
            
        } catch(PDOException $e) {
            echo $e->getMessage();
        } 
    
    return $estado;
    }
    
    function actualiza_item()
{
    $estado = 'ERR';
            try {
	$sql = 'UPDATE items SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, Name=:Name, FullName=:FullName, BarCodeValue=:BarCodeValue, IsActive=:IsActive, ClassRef_ListID=:ClassRef_ListID, ClassRef_FullName=:ClassRef_FullName, ParentRef_ListID=:ParentRef_ListID, ParentRef_FullName=:ParentRef_FullName, Sublevel=:Sublevel, ManufacturerPartNumber=:ManufacturerPartNumber, UnitOfMeasureSetRef_ListID=:UnitOfMeasureSetRef_ListID, UnitOfMeasureSetRef_FullName=:UnitOfMeasureSetRef_FullName, IsTaxIncluded=:IsTaxIncluded, SalesTaxCodeRef_ListID=:SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName=:SalesTaxCodeRef_FullName, SalesDesc=:SalesDesc, SalesPrice=:SalesPrice, IncomeAccountRef_ListID=:IncomeAccountRef_ListID, IncomeAccountRef_FullName=:IncomeAccountRef_FullName, PurchaseDesc=:PurchaseDesc, PurchaseCost=:PurchaseCost, COGSAccountRef_ListID=:COGSAccountRef_ListID, COGSAccountRef_FullName=:COGSAccountRef_FullName, PrefVendorRef_ListID=:PrefVendorRef_ListID, PrefVendorRef_FullName=:PrefVendorRef_FullName, AssetAccountRef_ListID=:AssetAccountRef_ListID, AssetAccountRef_FullName=:AssetAccountRef_FullName, ReorderPoint=:ReorderPoint, QuantityOnHand=:QuantityOnHand, AverageCost=:AverageCost, QuantityOnOrder=:QuantityOnOrder, QuantityOnSalesOrder=:QuantityOnSalesOrder, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status, WHERE ListID = :clave;'; 
	$stmt = $this->db->prepare($sql);
	 $stmt->bindParam(':ListID', $_SESSION['item']['ListID'] );
	$stmt->bindParam(':TimeCreated', $_SESSION['item']['TimeCreated'] );
	$stmt->bindParam(':TimeModified', $_SESSION['item']['TimeModified'] );
	$stmt->bindParam(':EditSequence', $_SESSION['item']['EditSequence'] );
	$stmt->bindParam(':Name', $_SESSION['item']['Name'] );
	$stmt->bindParam(':FullName', $_SESSION['item']['FullName'] );
	$stmt->bindParam(':BarCodeValue', $_SESSION['item']['BarCodeValue'] );
	$stmt->bindParam(':IsActive', $_SESSION['item']['IsActive'] );
	$stmt->bindParam(':ClassRef_ListID', $_SESSION['item']['ClassRef_ListID'] );
	$stmt->bindParam(':ClassRef_FullName', $_SESSION['item']['ClassRef_FullName'] );
	$stmt->bindParam(':ParentRef_ListID', $_SESSION['item']['ParentRef_ListID'] );
	$stmt->bindParam(':ParentRef_FullName', $_SESSION['item']['ParentRef_FullName'] );
	$stmt->bindParam(':Sublevel', $_SESSION['item']['Sublevel'] );
	$stmt->bindParam(':ManufacturerPartNumber', $_SESSION['item']['ManufacturerPartNumber'] );
	$stmt->bindParam(':UnitOfMeasureSetRef_ListID', $_SESSION['item']['UnitOfMeasureSetRef_ListID'] );
	$stmt->bindParam(':UnitOfMeasureSetRef_FullName', $_SESSION['item']['UnitOfMeasureSetRef_FullName'] );
	$stmt->bindParam(':IsTaxIncluded', $_SESSION['item']['IsTaxIncluded'] );
	$stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['item']['SalesTaxCodeRef_ListID'] );
	$stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['item']['SalesTaxCodeRef_FullName'] );
	$stmt->bindParam(':SalesDesc', $_SESSION['item']['SalesDesc'] );
	$stmt->bindParam(':SalesPrice', $_SESSION['item']['SalesPrice'] );
	$stmt->bindParam(':IncomeAccountRef_ListID', $_SESSION['item']['IncomeAccountRef_ListID'] );
	$stmt->bindParam(':IncomeAccountRef_FullName', $_SESSION['item']['IncomeAccountRef_FullName'] );
	$stmt->bindParam(':PurchaseDesc', $_SESSION['item']['PurchaseDesc'] );
	$stmt->bindParam(':PurchaseCost', $_SESSION['item']['PurchaseCost'] );
	$stmt->bindParam(':COGSAccountRef_ListID', $_SESSION['item']['COGSAccountRef_ListID'] );
	$stmt->bindParam(':COGSAccountRef_FullName', $_SESSION['item']['COGSAccountRef_FullName'] );
	$stmt->bindParam(':PrefVendorRef_ListID', $_SESSION['item']['PrefVendorRef_ListID'] );
	$stmt->bindParam(':PrefVendorRef_FullName', $_SESSION['item']['PrefVendorRef_FullName'] );
	$stmt->bindParam(':AssetAccountRef_ListID', $_SESSION['item']['AssetAccountRef_ListID'] );
	$stmt->bindParam(':AssetAccountRef_FullName', $_SESSION['item']['AssetAccountRef_FullName'] );
	$stmt->bindParam(':ReorderPoint', $_SESSION['item']['ReorderPoint'] );
	$stmt->bindParam(':QuantityOnHand', $_SESSION['item']['QuantityOnHand'] );
	$stmt->bindParam(':AverageCost', $_SESSION['item']['AverageCost'] );
	$stmt->bindParam(':QuantityOnOrder', $_SESSION['item']['QuantityOnOrder'] );
	$stmt->bindParam(':QuantityOnSalesOrder', $_SESSION['item']['QuantityOnSalesOrder'] );
	$stmt->bindParam(':CustomField1', $_SESSION['item']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['item']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['item']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['item']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['item']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['item']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['item']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['item']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['item']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['item']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['item']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['item']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['item']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['item']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['item']['CustomField15'] );
	$stmt->bindParam(':Status', $_SESSION['item']['Status'] );
	$stmt->bindParam(':clave', $_SESSION['invoice']['TxnID']);
	$stmt->execute();
     } catch(PDOException $e) {
}
}

function genLimpia_item()
{
$_SESSION['item']['ListID'] = ' ';
$_SESSION['item']['TimeCreated'] = ' ';
$_SESSION['item']['TimeModified'] = ' ';
$_SESSION['item']['EditSequence'] = ' ';
$_SESSION['item']['Name'] = ' ';
$_SESSION['item']['FullName'] = ' ';
$_SESSION['item']['BarCodeValue'] = ' ';
$_SESSION['item']['IsActive'] = ' ';
$_SESSION['item']['ClassRef_ListID'] = ' ';
$_SESSION['item']['ClassRef_FullName'] = ' ';
$_SESSION['item']['ParentRef_ListID'] = ' ';
$_SESSION['item']['ParentRef_FullName'] = ' ';
$_SESSION['item']['Sublevel'] = ' ';
$_SESSION['item']['ManufacturerPartNumber'] = ' ';
$_SESSION['item']['UnitOfMeasureSetRef_ListID'] = ' ';
$_SESSION['item']['UnitOfMeasureSetRef_FullName'] = ' ';
$_SESSION['item']['IsTaxIncluded'] = ' ';
$_SESSION['item']['SalesTaxCodeRef_ListID'] = ' ';
$_SESSION['item']['SalesTaxCodeRef_FullName'] = ' ';
$_SESSION['item']['SalesDesc'] = ' ';
$_SESSION['item']['SalesPrice'] = ' ';
$_SESSION['item']['IncomeAccountRef_ListID'] = ' ';
$_SESSION['item']['IncomeAccountRef_FullName'] = ' ';
$_SESSION['item']['PurchaseDesc'] = ' ';
$_SESSION['item']['PurchaseCost'] = ' ';
$_SESSION['item']['COGSAccountRef_ListID'] = ' ';
$_SESSION['item']['COGSAccountRef_FullName'] = ' ';
$_SESSION['item']['PrefVendorRef_ListID'] = ' ';
$_SESSION['item']['PrefVendorRef_FullName'] = ' ';
$_SESSION['item']['AssetAccountRef_ListID'] = ' ';
$_SESSION['item']['AssetAccountRef_FullName'] = ' ';
$_SESSION['item']['ReorderPoint'] = ' ';
$_SESSION['item']['QuantityOnHand'] = ' ';
$_SESSION['item']['AverageCost'] = ' ';
$_SESSION['item']['QuantityOnOrder'] = ' ';
$_SESSION['item']['QuantityOnSalesOrder'] = ' ';
$_SESSION['item']['CustomField1'] = ' ';
$_SESSION['item']['CustomField2'] = ' ';
$_SESSION['item']['CustomField3'] = ' ';
$_SESSION['item']['CustomField4'] = ' ';
$_SESSION['item']['CustomField5'] = ' ';
$_SESSION['item']['CustomField6'] = ' ';
$_SESSION['item']['CustomField7'] = ' ';
$_SESSION['item']['CustomField8'] = ' ';
$_SESSION['item']['CustomField9'] = ' ';
$_SESSION['item']['CustomField10'] = ' ';
$_SESSION['item']['CustomField11'] = ' ';
$_SESSION['item']['CustomField12'] = ' ';
$_SESSION['item']['CustomField13'] = ' ';
$_SESSION['item']['CustomField14'] = ' ';
$_SESSION['item']['CustomField15'] = ' ';
$_SESSION['item']['Status'] = ' ';

}

function gentraverse_item($node)
{
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['ListID'] = ' ' : $_SESSION['item']['ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('TimeCreated')->item(0) == NULL ? $_SESSION['item']['TimeCreated'] = '2010-08-10' : $_SESSION['item']['TimeCreated'] = $node->getElementsByTagName('TimeCreated')->item(0)->nodeValue;
$node->getElementsByTagName('TimeModified')->item(0) == NULL ? $_SESSION['item']['TimeModified'] = '2010-08-10' : $_SESSION['item']['TimeModified'] = $node->getElementsByTagName('TimeModified')->item(0)->nodeValue;
$node->getElementsByTagName('EditSequence')->item(0) == NULL ? $_SESSION['item']['EditSequence'] = 0 : $_SESSION['item']['EditSequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
$node->getElementsByTagName('Name')->item(0) == NULL ? $_SESSION['item']['Name'] = ' ' : $_SESSION['item']['Name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['FullName'] = ' ' : $_SESSION['item']['FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('BarCodeValue')->item(0) == NULL ? $_SESSION['item']['BarCodeValue'] = ' ' : $_SESSION['item']['BarCodeValue'] = $node->getElementsByTagName('BarCodeValue')->item(0)->nodeValue;
$node->getElementsByTagName('IsActive')->item(0) == NULL ? $_SESSION['item']['IsActive'] = ' ' : $_SESSION['item']['IsActive'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['ClassRef_ListID'] = ' ' : $_SESSION['item']['ClassRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['ClassRef_FullName'] = ' ' : $_SESSION['item']['ClassRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['ParentRef_ListID'] = ' ' : $_SESSION['item']['ParentRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['ParentRef_FullName'] = ' ' : $_SESSION['item']['ParentRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('Sublevel')->item(0) == NULL ? $_SESSION['item']['Sublevel'] = 0 : $_SESSION['item']['Sublevel'] = $node->getElementsByTagName('Sublevel')->item(0)->nodeValue;
$node->getElementsByTagName('ManufacturerPartNumber')->item(0) == NULL ? $_SESSION['item']['ManufacturerPartNumber'] = ' ' : $_SESSION['item']['ManufacturerPartNumber'] = $node->getElementsByTagName('ManufacturerPartNumber')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['UnitOfMeasureSetRef_ListID'] = ' ' : $_SESSION['item']['UnitOfMeasureSetRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['UnitOfMeasureSetRef_FullName'] = ' ' : $_SESSION['item']['UnitOfMeasureSetRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('IsTaxIncluded')->item(0) == NULL ? $_SESSION['item']['IsTaxIncluded'] = ' ' : $_SESSION['item']['IsTaxIncluded'] = $node->getElementsByTagName('IsTaxIncluded')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['SalesTaxCodeRef_ListID'] = ' ' : $_SESSION['item']['SalesTaxCodeRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['SalesTaxCodeRef_FullName'] = ' ' : $_SESSION['item']['SalesTaxCodeRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('SalesDesc')->item(0) == NULL ? $_SESSION['item']['SalesDesc'] = ' ' : $_SESSION['item']['SalesDesc'] = $node->getElementsByTagName('SalesDesc')->item(0)->nodeValue;
$node->getElementsByTagName('SalesPrice')->item(0) == NULL ? $_SESSION['item']['SalesPrice'] = ' ' : $_SESSION['item']['SalesPrice'] = $node->getElementsByTagName('SalesPrice')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['IncomeAccountRef_ListID'] = ' ' : $_SESSION['item']['IncomeAccountRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['IncomeAccountRef_FullName'] = ' ' : $_SESSION['item']['IncomeAccountRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('PurchaseDesc')->item(0) == NULL ? $_SESSION['item']['PurchaseDesc'] = ' ' : $_SESSION['item']['PurchaseDesc'] = $node->getElementsByTagName('PurchaseDesc')->item(0)->nodeValue;
$node->getElementsByTagName('PurchaseCost')->item(0) == NULL ? $_SESSION['item']['PurchaseCost'] = ' ' : $_SESSION['item']['PurchaseCost'] = $node->getElementsByTagName('PurchaseCost')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['COGSAccountRef_ListID'] = ' ' : $_SESSION['item']['COGSAccountRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['COGSAccountRef_FullName'] = ' ' : $_SESSION['item']['COGSAccountRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['PrefVendorRef_ListID'] = ' ' : $_SESSION['item']['PrefVendorRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['PrefVendorRef_FullName'] = ' ' : $_SESSION['item']['PrefVendorRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['item']['AssetAccountRef_ListID'] = ' ' : $_SESSION['item']['AssetAccountRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
$node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['item']['AssetAccountRef_FullName'] = ' ' : $_SESSION['item']['AssetAccountRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
$node->getElementsByTagName('ReorderPoint')->item(0) == NULL ? $_SESSION['item']['ReorderPoint'] = ' ' : $_SESSION['item']['ReorderPoint'] = $node->getElementsByTagName('ReorderPoint')->item(0)->nodeValue;
$node->getElementsByTagName('QuantityOnHand')->item(0) == NULL ? $_SESSION['item']['QuantityOnHand'] = ' ' : $_SESSION['item']['QuantityOnHand'] = $node->getElementsByTagName('QuantityOnHand')->item(0)->nodeValue;
$node->getElementsByTagName('AverageCost')->item(0) == NULL ? $_SESSION['item']['AverageCost'] = ' ' : $_SESSION['item']['AverageCost'] = $node->getElementsByTagName('AverageCost')->item(0)->nodeValue;
$node->getElementsByTagName('QuantityOnOrder')->item(0) == NULL ? $_SESSION['item']['QuantityOnOrder'] = ' ' : $_SESSION['item']['QuantityOnOrder'] = $node->getElementsByTagName('QuantityOnOrder')->item(0)->nodeValue;
$node->getElementsByTagName('QuantityOnSalesOrder')->item(0) == NULL ? $_SESSION['item']['QuantityOnSalesOrder'] = ' ' : $_SESSION['item']['QuantityOnSalesOrder'] = $node->getElementsByTagName('QuantityOnSalesOrder')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField1')->item(0) == NULL ? $_SESSION['item']['CustomField1'] = ' ' : $_SESSION['item']['CustomField1'] = $node->getElementsByTagName('CustomField1')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField2')->item(0) == NULL ? $_SESSION['item']['CustomField2'] = ' ' : $_SESSION['item']['CustomField2'] = $node->getElementsByTagName('CustomField2')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField3')->item(0) == NULL ? $_SESSION['item']['CustomField3'] = ' ' : $_SESSION['item']['CustomField3'] = $node->getElementsByTagName('CustomField3')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField4')->item(0) == NULL ? $_SESSION['item']['CustomField4'] = ' ' : $_SESSION['item']['CustomField4'] = $node->getElementsByTagName('CustomField4')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField5')->item(0) == NULL ? $_SESSION['item']['CustomField5'] = ' ' : $_SESSION['item']['CustomField5'] = $node->getElementsByTagName('CustomField5')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField6')->item(0) == NULL ? $_SESSION['item']['CustomField6'] = ' ' : $_SESSION['item']['CustomField6'] = $node->getElementsByTagName('CustomField6')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField7')->item(0) == NULL ? $_SESSION['item']['CustomField7'] = ' ' : $_SESSION['item']['CustomField7'] = $node->getElementsByTagName('CustomField7')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField8')->item(0) == NULL ? $_SESSION['item']['CustomField8'] = ' ' : $_SESSION['item']['CustomField8'] = $node->getElementsByTagName('CustomField8')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField9')->item(0) == NULL ? $_SESSION['item']['CustomField9'] = ' ' : $_SESSION['item']['CustomField9'] = $node->getElementsByTagName('CustomField9')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField10')->item(0) == NULL ? $_SESSION['item']['CustomField10'] = ' ' : $_SESSION['item']['CustomField10'] = $node->getElementsByTagName('CustomField10')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField11')->item(0) == NULL ? $_SESSION['item']['CustomField11'] = ' ' : $_SESSION['item']['CustomField11'] = $node->getElementsByTagName('CustomField11')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField12')->item(0) == NULL ? $_SESSION['item']['CustomField12'] = ' ' : $_SESSION['item']['CustomField12'] = $node->getElementsByTagName('CustomField12')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField13')->item(0) == NULL ? $_SESSION['item']['CustomField13'] = ' ' : $_SESSION['item']['CustomField13'] = $node->getElementsByTagName('CustomField13')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField14')->item(0) == NULL ? $_SESSION['item']['CustomField14'] = ' ' : $_SESSION['item']['CustomField14'] = $node->getElementsByTagName('CustomField14')->item(0)->nodeValue;
$node->getElementsByTagName('CustomField15')->item(0) == NULL ? $_SESSION['item']['CustomField15'] = ' ' : $_SESSION['item']['CustomField15'] = $node->getElementsByTagName('CustomField15')->item(0)->nodeValue;
$node->getElementsByTagName('Status')->item(0) == NULL ? $_SESSION['item']['Status'] = ' ' : $_SESSION['item']['Status'] = $node->getElementsByTagName('Status')->item(0)->nodeValue;

}

function adiciona_item()
{
        $this->estado = 'ERR';
	$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
        $sql = 'INSERT INTO iteminventory (  ListID, TimeCreated, TimeModified, EditSequence, Name, FullName, BarCodeValue, IsActive, ClassRef_ListID, ClassRef_FullName, ParentRef_ListID, ParentRef_FullName, Sublevel, ManufacturerPartNumber, UnitOfMeasureSetRef_ListID, UnitOfMeasureSetRef_FullName, IsTaxIncluded, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, SalesDesc, SalesPrice, IncomeAccountRef_ListID, IncomeAccountRef_FullName, PurchaseDesc, PurchaseCost, COGSAccountRef_ListID, COGSAccountRef_FullName, PrefVendorRef_ListID, PrefVendorRef_FullName, AssetAccountRef_ListID, AssetAccountRef_FullName, ReorderPoint, QuantityOnHand, AverageCost, QuantityOnOrder, QuantityOnSalesOrder, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :FullName, :BarCodeValue, :IsActive, :ClassRef_ListID, :ClassRef_FullName, :ParentRef_ListID, :ParentRef_FullName, :Sublevel, :ManufacturerPartNumber, :UnitOfMeasureSetRef_ListID, :UnitOfMeasureSetRef_FullName, :IsTaxIncluded, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, :SalesDesc, :SalesPrice, :IncomeAccountRef_ListID, :IncomeAccountRef_FullName, :PurchaseDesc, :PurchaseCost, :COGSAccountRef_ListID, :COGSAccountRef_FullName, :PrefVendorRef_ListID, :PrefVendorRef_FullName, :AssetAccountRef_ListID, :AssetAccountRef_FullName, :ReorderPoint, :QuantityOnHand, :AverageCost, :QuantityOnOrder, :QuantityOnSalesOrder, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
	$stmt = $this->db->prepare($sql);
	 $stmt->bindParam(':ListID', $_SESSION['item']['ListID'] );
	$stmt->bindParam(':TimeCreated', $_SESSION['item']['TimeCreated'] );
	$stmt->bindParam(':TimeModified', $_SESSION['item']['TimeModified'] );
	$stmt->bindParam(':EditSequence', $_SESSION['item']['EditSequence'] );
	$stmt->bindParam(':Name', $_SESSION['item']['Name'] );
	$stmt->bindParam(':FullName', $_SESSION['item']['FullName'] );
	$stmt->bindParam(':BarCodeValue', $_SESSION['item']['BarCodeValue'] );
	$stmt->bindParam(':IsActive', $_SESSION['item']['IsActive'] );
	$stmt->bindParam(':ClassRef_ListID', $_SESSION['item']['ClassRef_ListID'] );
	$stmt->bindParam(':ClassRef_FullName', $_SESSION['item']['ClassRef_FullName'] );
	$stmt->bindParam(':ParentRef_ListID', $_SESSION['item']['ParentRef_ListID'] );
	$stmt->bindParam(':ParentRef_FullName', $_SESSION['item']['ParentRef_FullName'] );
	$stmt->bindParam(':Sublevel', $_SESSION['item']['Sublevel'] );
	$stmt->bindParam(':ManufacturerPartNumber', $_SESSION['item']['ManufacturerPartNumber'] );
	$stmt->bindParam(':UnitOfMeasureSetRef_ListID', $_SESSION['item']['UnitOfMeasureSetRef_ListID'] );
	$stmt->bindParam(':UnitOfMeasureSetRef_FullName', $_SESSION['item']['UnitOfMeasureSetRef_FullName'] );
	$stmt->bindParam(':IsTaxIncluded', $_SESSION['item']['IsTaxIncluded'] );
	$stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['item']['SalesTaxCodeRef_ListID'] );
	$stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['item']['SalesTaxCodeRef_FullName'] );
	$stmt->bindParam(':SalesDesc', $_SESSION['item']['SalesDesc'] );
	$stmt->bindParam(':SalesPrice', $_SESSION['item']['SalesPrice'] );
	$stmt->bindParam(':IncomeAccountRef_ListID', $_SESSION['item']['IncomeAccountRef_ListID'] );
	$stmt->bindParam(':IncomeAccountRef_FullName', $_SESSION['item']['IncomeAccountRef_FullName'] );
	$stmt->bindParam(':PurchaseDesc', $_SESSION['item']['PurchaseDesc'] );
	$stmt->bindParam(':PurchaseCost', $_SESSION['item']['PurchaseCost'] );
	$stmt->bindParam(':COGSAccountRef_ListID', $_SESSION['item']['COGSAccountRef_ListID'] );
	$stmt->bindParam(':COGSAccountRef_FullName', $_SESSION['item']['COGSAccountRef_FullName'] );
	$stmt->bindParam(':PrefVendorRef_ListID', $_SESSION['item']['PrefVendorRef_ListID'] );
	$stmt->bindParam(':PrefVendorRef_FullName', $_SESSION['item']['PrefVendorRef_FullName'] );
	$stmt->bindParam(':AssetAccountRef_ListID', $_SESSION['item']['AssetAccountRef_ListID'] );
	$stmt->bindParam(':AssetAccountRef_FullName', $_SESSION['item']['AssetAccountRef_FullName'] );
	$stmt->bindParam(':ReorderPoint', $_SESSION['item']['ReorderPoint'] );
	$stmt->bindParam(':QuantityOnHand', $_SESSION['item']['QuantityOnHand'] );
	$stmt->bindParam(':AverageCost', $_SESSION['item']['AverageCost'] );
	$stmt->bindParam(':QuantityOnOrder', $_SESSION['item']['QuantityOnOrder'] );
	$stmt->bindParam(':QuantityOnSalesOrder', $_SESSION['item']['QuantityOnSalesOrder'] );
	$stmt->bindParam(':CustomField1', $_SESSION['item']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['item']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['item']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['item']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['item']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['item']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['item']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['item']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['item']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['item']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['item']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['item']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['item']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['item']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['item']['CustomField15'] );
	$stmt->bindParam(':Status', $_SESSION['item']['Status'] );
	$stmt->execute();
        } catch(PDOException $e) {
            echo $e->getMessage();
        }    
}

function quitaslashes_item()
{
         $_SESSION['item']['ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['ListID']));
$_SESSION['item']['TimeCreated'] = htmlspecialchars(strip_tags($_SESSION['item']['TimeCreated']));
$_SESSION['item']['TimeModified'] = htmlspecialchars(strip_tags($_SESSION['item']['TimeModified']));
$_SESSION['item']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['item']['EditSequence']));
$_SESSION['item']['Name'] = htmlspecialchars(strip_tags($_SESSION['item']['Name']));
$_SESSION['item']['FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['FullName']));
$_SESSION['item']['BarCodeValue'] = htmlspecialchars(strip_tags($_SESSION['item']['BarCodeValue']));
$_SESSION['item']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['item']['IsActive']));
$_SESSION['item']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['ClassRef_ListID']));
$_SESSION['item']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['ClassRef_FullName']));
$_SESSION['item']['ParentRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['ParentRef_ListID']));
$_SESSION['item']['ParentRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['ParentRef_FullName']));
$_SESSION['item']['Sublevel'] = htmlspecialchars(strip_tags($_SESSION['item']['Sublevel']));
$_SESSION['item']['ManufacturerPartNumber'] = htmlspecialchars(strip_tags($_SESSION['item']['ManufacturerPartNumber']));
$_SESSION['item']['UnitOfMeasureSetRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['UnitOfMeasureSetRef_ListID']));
$_SESSION['item']['UnitOfMeasureSetRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['UnitOfMeasureSetRef_FullName']));
$_SESSION['item']['IsTaxIncluded'] = htmlspecialchars(strip_tags($_SESSION['item']['IsTaxIncluded']));
$_SESSION['item']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['SalesTaxCodeRef_ListID']));
$_SESSION['item']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['SalesTaxCodeRef_FullName']));
$_SESSION['item']['SalesDesc'] = htmlspecialchars(strip_tags($_SESSION['item']['SalesDesc']));
$_SESSION['item']['SalesPrice'] = htmlspecialchars(strip_tags($_SESSION['item']['SalesPrice']));
$_SESSION['item']['IncomeAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['IncomeAccountRef_ListID']));
$_SESSION['item']['IncomeAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['IncomeAccountRef_FullName']));
$_SESSION['item']['PurchaseDesc'] = htmlspecialchars(strip_tags($_SESSION['item']['PurchaseDesc']));
$_SESSION['item']['PurchaseCost'] = htmlspecialchars(strip_tags($_SESSION['item']['PurchaseCost']));
$_SESSION['item']['COGSAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['COGSAccountRef_ListID']));
$_SESSION['item']['COGSAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['COGSAccountRef_FullName']));
$_SESSION['item']['PrefVendorRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['PrefVendorRef_ListID']));
$_SESSION['item']['PrefVendorRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['PrefVendorRef_FullName']));
$_SESSION['item']['AssetAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['AssetAccountRef_ListID']));
$_SESSION['item']['AssetAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['AssetAccountRef_FullName']));
$_SESSION['item']['ReorderPoint'] = htmlspecialchars(strip_tags($_SESSION['item']['ReorderPoint']));
$_SESSION['item']['QuantityOnHand'] = htmlspecialchars(strip_tags($_SESSION['item']['QuantityOnHand']));
$_SESSION['item']['AverageCost'] = htmlspecialchars(strip_tags($_SESSION['item']['AverageCost']));
$_SESSION['item']['QuantityOnOrder'] = htmlspecialchars(strip_tags($_SESSION['item']['QuantityOnOrder']));
$_SESSION['item']['QuantityOnSalesOrder'] = htmlspecialchars(strip_tags($_SESSION['item']['QuantityOnSalesOrder']));
$_SESSION['item']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField1']));
$_SESSION['item']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField2']));
$_SESSION['item']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField3']));
$_SESSION['item']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField4']));
$_SESSION['item']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField5']));
$_SESSION['item']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField6']));
$_SESSION['item']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField7']));
$_SESSION['item']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField8']));
$_SESSION['item']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField9']));
$_SESSION['item']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField10']));
$_SESSION['item']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField11']));
$_SESSION['item']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField12']));
$_SESSION['item']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField13']));
$_SESSION['item']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField14']));
$_SESSION['item']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['item']['CustomField15']));
$_SESSION['item']['Status'] = htmlspecialchars(strip_tags($_SESSION['item']['Status']));

 
    }
    

