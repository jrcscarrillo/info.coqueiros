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

$dsn = 'mysqli://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';
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
    $myfile1 = fopen("items.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "ItemInventoryAssemblyRet";
    $inventario = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($inventario as $uno) {
        genLimpia_items();
        gentraverse_items($uno);
        $existe = buscaIgual_items($db);
        $inicial = substr($_SESSION['item']['Name'], 0, 1);
        $parafila = "Procesa " . $existe . " " . $inicial . " " . $_SESSION['item']['Name'] . " " . $_SESSION['item']['ListID'] . "\r\n";
        fwrite($myfile, $parafila);
        quitaslashes_items();
        if ($existe == "OK") {
            if ($inicial === "0" or $inicial === "1" or $inicial === "2" or $inicial === "M") {
                $_SESSION['item']['tipo'] = 'Assembly';
                $_SESSION['item']['PurchaseCost'] = 0;
                $estado = adiciona_items($db);
            }
        } elseif ($existe === "ACTUALIZA") {
            $_SESSION['item']['tipo'] = 'Assembly';
            $estado = actualiza_items($db);
        }
        $parafila = "Procesado " . $estado . "\r\n";
        fwrite($myfile, $parafila);
    }
    $param = "ItemOtherChargeRet";
    $inventario = $doc->getElementsByTagName($param);
    foreach ($inventario as $uno) {
        genLimpia_items();
        gentraverse_items($uno);
        $existe = buscaIgual_items($db);
        $inicial = substr($_SESSION['item']['SalesDesc'], 0, 13);
        $parafila = "Procesa " . $existe . " " . $inicial . " " . $_SESSION['item']['Name'] . " " . $_SESSION['item']['ListID'] . "\r\n";
        fwrite($myfile, $parafila);
        quitaslashes_items();
        if ($existe == "OK") {
            if (is_numeric($inicial) AND $inicial > 0) {
                $_SESSION['item']['tipo'] = 'OtherCharge';
                $_SESSION['item']['PurchaseCost'] = 0;
                $estado = adiciona_items($db);
            }
        } elseif ($existe === "ACTUALIZA") {
            $_SESSION['item']['tipo'] = 'OtherCharge';
            $estado = actualiza_items($db);
        }
        $parafila = "Procesado " . $estado . "\r\n";
        fwrite($myfile, $parafila);
    }
    $param = "ItemInventoryRet";
    $inventario = $doc->getElementsByTagName($param);
    foreach ($inventario as $uno) {
        genLimpia_items();
        gentraverse_items($uno);
        $existe = buscaIgual_items($db);
        $inicial = substr($_SESSION['item']['SalesDesc'], 0, 13);
        $parafila = "Procesa " . $existe . " " . $inicial . " " . $_SESSION['item']['Name'] . " " . $_SESSION['item']['ListID'];
        fwrite($myfile, $parafila);
        quitaslashes_items();
        if ($existe == "OK") {
            if (is_numeric($inicial) AND $inicial > 0) {
                $_SESSION['item']['tipo'] = 'Inventory';
                $_SESSION['item']['PurchaseCost'] = 0;
                $estado = adiciona_items($db);
            }
        } elseif ($existe === "ACTUALIZA") {
            $_SESSION['item']['tipo'] = 'Inventory';
            $estado = actualiza_items($db);
        }
        $parafila = "Procesado " . $estado . "\r\n";
        fwrite($myfile, $parafila);
    }
    $param = "ItemServiceRet";
    $inventario = $doc->getElementsByTagName($param);
    foreach ($inventario as $uno) {
        genLimpia_items();
        gentraverse_items($uno);
        $existe = buscaIgual_items($db);
        $parafila = "Procesa " . $existe . " " . $inicial . " " . $_SESSION['item']['Name'] . " " . $_SESSION['item']['ListID'];
        fwrite($myfile, $parafila);
        quitaslashes_items();
        if ($existe == "OK") {
            $_SESSION['item']['tipo'] = 'Service';
            $_SESSION['item']['PurchaseCost'] = 0;
            $estado = adiciona_items($db);
        } elseif ($existe === "ACTUALIZA") {
            $_SESSION['item']['tipo'] = 'Service';
            $estado = actualiza_items($db);
        }
        $parafila = "Procesado " . $estado . "\r\n";
        fwrite($myfile, $parafila);
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
                    <ItemQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                    <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                    <OwnerID>0</OwnerID>
                    </ItemQueryRq>	
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

function buscaIgual_items($db) {
    $estado = 'INIT';
    try {
        $sql = 'SELECT * FROM items WHERE quickbooks_listid = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['item']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            $estado = 'ACTUALIZA';
        }
    } catch (PDOException $e) {
        $estado = $e->getMessage();
    }

    return $estado;
}

function adiciona_items($db) {
    $estado = 'INIT';
    try {

        $sql = 'INSERT INTO items (  name, fullname, description, quickbooks_listid, quickbooks_editsequence, '
           . 'is_active, parent_reference_listid, parent_reference_full_name, '
           . 'sublevel, unit_of_measure_set_ref_listid, unit_of_measure_set_ref_fullname, type, sales_tax_code_ref_listid, '
           . 'sales_tax_code_ref_fullname, sales_desc, sales_price, income_account_ref_listid, income_account_ref_fullname, '
           . 'purchase_cost, COGS_account_ref_listid, COGS_account_ref_fullname, assests_account_ref_listid, assests_acc, '
           . 'purchase_desc, QuantityOnHand, QuantityOnOrder, QuantityOnSalesOrder, AverageCost, placa, tipoid, numeroid, email) VALUES ( '
           . ':p1, :p2, :p3, :p4, :p5, :p6, :p7, :p8, :p9, :p10, :p11, :p12, :p13, :p14, '
           . ':p15, :p16, :p17, :p18, :p19, :p20, :p21, :p22, :p23, :p24, :p25, :p26, :p27, :p28, :p29, :p30, :p31, :p32 )';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':p1', $_SESSION['item']['Name']);
        $stmt->bindParam(':p2', $_SESSION['item']['FullName']);
        $stmt->bindParam(':p3', $_SESSION['item']['SalesDesc']);
        $stmt->bindParam(':p4', $_SESSION['item']['ListID']);
        $stmt->bindParam(':p5', $_SESSION['item']['EditSequence']);
        $stmt->bindParam(':p6', $_SESSION['item']['IsActive']);
        $stmt->bindParam(':p7', $_SESSION['item']['ParentRef_ListID']);
        $stmt->bindParam(':p8', $_SESSION['item']['ParentRef_FullName']);
        $stmt->bindParam(':p9', $_SESSION['item']['Sublevel']);
        $stmt->bindParam(':p10', $_SESSION['item']['UnitOfMeasureSetRef_ListID']);
        $stmt->bindParam(':p11', $_SESSION['item']['UnitOfMeasureSetRef_FullName']);
        $stmt->bindParam(':p12', $_SESSION['item']['tipo']);
        $stmt->bindParam(':p13', $_SESSION['item']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':p14', $_SESSION['item']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':p15', $_SESSION['item']['SalesDesc']);
        $stmt->bindParam(':p16', $_SESSION['item']['SalesPrice']);
        $stmt->bindParam(':p17', $_SESSION['item']['IncomeAccountRef_ListID']);
        $stmt->bindParam(':p18', $_SESSION['item']['IncomeAccountRef_FullName']);
        $stmt->bindParam(':p19', $_SESSION['item']['PurchaseCost']);
        $stmt->bindParam(':p20', $_SESSION['item']['COGSAccountRef_ListID']);
        $stmt->bindParam(':p21', $_SESSION['item']['COGSAccountRef_FullName']);
        $stmt->bindParam(':p22', $_SESSION['item']['AssetAccountRef_ListID']);
        $stmt->bindParam(':p23', $_SESSION['item']['AssetAccountRef_FullName']);
        $stmt->bindParam(':p24', $_SESSION['item']['PurchaseDesc']);
        $stmt->bindParam(':p25', $_SESSION['item']['QuantityOnHand']);
        $stmt->bindParam(':p26', $_SESSION['item']['AverageCost']);
        $stmt->bindParam(':p27', $_SESSION['item']['QuantityOnOrder']);
        $stmt->bindParam(':p28', $_SESSION['item']['QuantityOnSalesOrder']);
        $stmt->bindParam(':p29', $_SESSION['item']['placa']);
        $stmt->bindParam(':p30', $_SESSION['item']['tipoid']);
        $stmt->bindParam(':p31', $_SESSION['item']['numeroid']);
        $stmt->bindParam(':p32', $_SESSION['item']['email']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR =>' . $e->getMessage();
    }
    return $estado;
}

function actualiza_items($db) {
    $estado = 'INIT';
    try {

        $sql = 'UPDATE items SET name=:p1, fullname=:p2, description=:p3, quickbooks_listid=:p4, quickbooks_editsequence=:p5, '
           . 'is_active=:p6, parent_reference_listid=:p7, parent_reference_full_name=:p8, '
           . 'sublevel=:p9, unit_of_measure_set_ref_listid=:p10, unit_of_measure_set_ref_fullname=:p11, type=:p12, sales_tax_code_ref_listid=:p13, '
           . 'sales_tax_code_ref_fullname=:p14, sales_price=:p16, income_account_ref_listid=:p17, income_account_ref_fullname=:p18, '
           . 'purchase_cost=:p19, COGS_account_ref_listid=:p20, COGS_account_ref_fullname=:p21, assests_account_ref_listid=:p22, assests_acc=:p23, '
           . 'purchase_desc=:p24, QuantityOnHand=:p25, QuantityOnOrder=:p26, QuantityOnSalesOrder=:p27, AverageCost=:p28, placa=:p29, tipoid=:p30, numeroid=:p31, email=:p32'
           . ' WHERE quickbooks_listid =:clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':p1', $_SESSION['item']['Name']);
        $stmt->bindParam(':p2', $_SESSION['item']['FullName']);
        $stmt->bindParam(':p3', $_SESSION['item']['SalesDesc']);
        $stmt->bindParam(':p4', $_SESSION['item']['ListID']);
        $stmt->bindParam(':p5', $_SESSION['item']['EditSequence']);
        $stmt->bindParam(':p6', $_SESSION['item']['IsActive']);
        $stmt->bindParam(':p7', $_SESSION['item']['ParentRef_ListID']);
        $stmt->bindParam(':p8', $_SESSION['item']['ParentRef_FullName']);
        $stmt->bindParam(':p9', $_SESSION['item']['Sublevel']);
        $stmt->bindParam(':p10', $_SESSION['item']['UnitOfMeasureSetRef_ListID']);
        $stmt->bindParam(':p11', $_SESSION['item']['UnitOfMeasureSetRef_FullName']);
        $stmt->bindParam(':p12', $_SESSION['item']['tipo']);
        $stmt->bindParam(':p13', $_SESSION['item']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':p14', $_SESSION['item']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':p16', $_SESSION['item']['SalesPrice']);
        $stmt->bindParam(':p17', $_SESSION['item']['IncomeAccountRef_ListID']);
        $stmt->bindParam(':p18', $_SESSION['item']['IncomeAccountRef_FullName']);
        $stmt->bindParam(':p19', $_SESSION['item']['PurchaseCost']);
        $stmt->bindParam(':p20', $_SESSION['item']['COGSAccountRef_ListID']);
        $stmt->bindParam(':p21', $_SESSION['item']['COGSAccountRef_FullName']);
        $stmt->bindParam(':p22', $_SESSION['item']['AssetAccountRef_ListID']);
        $stmt->bindParam(':p23', $_SESSION['item']['AssetAccountRef_FullName']);
        $stmt->bindParam(':p24', $_SESSION['item']['PurchaseDesc']);
        $stmt->bindParam(':p25', $_SESSION['item']['QuantityOnHand']);
        $stmt->bindParam(':p26', $_SESSION['item']['AverageCost']);
        $stmt->bindParam(':p27', $_SESSION['item']['QuantityOnOrder']);
        $stmt->bindParam(':p28', $_SESSION['item']['QuantityOnSalesOrder']);
        $stmt->bindParam(':p29', $_SESSION['item']['placa']);
        $stmt->bindParam(':p30', $_SESSION['item']['tipoid']);
        $stmt->bindParam(':p31', $_SESSION['item']['numeroid']);
        $stmt->bindParam(':p32', $_SESSION['item']['email']);
        $stmt->bindParam(':clave', $_SESSION['item']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = "ERROR updating...." . $e->getMessage();
    }
    return $estado;
}

function gentraverse_items($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['item']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['item']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['item']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['item']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['item']['Name'] = $nivel1->nodeValue;
                    break;
                case 'FullName':
                    $_SESSION['item']['FullName'] = $nivel1->nodeValue;
                    break;
                case 'BarCodeValue':
                    $_SESSION['item']['BarCodeValue'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['item']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'ClassRef':
                case 'ParentRef':
                case 'UnitOfMeasureSetRef':
                case 'SalesTaxCodeRef':
                case 'IncomeAccountRef':
                case 'COGSAccountRef':
                case 'PrefVendorRef':
                case 'AssetAccountRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ParentRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['ParentRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['ParentRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'UnitOfMeasureSetRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['UnitOfMeasureSetRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['UnitOfMeasureSetRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxCodeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['SalesTaxCodeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['SalesTaxCodeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'IncomeAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['IncomeAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['IncomeAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'COGSAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['COGSAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['COGSAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PrefVendorRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['PrefVendorRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['PrefVendorRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'AssetAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['AssetAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['AssetAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
                case 'Sublevel':
                    $_SESSION['item']['Sublevel'] = $nivel1->nodeValue;
                    break;
                case 'ManufacturerPartNumber':
                    $_SESSION['item']['ManufacturerPartNumber'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxIncluded':
                    $_SESSION['item']['IsTaxIncluded'] = $nivel1->nodeValue;
                    break;
                case 'SalesDesc':
                    $_SESSION['item']['SalesDesc'] = $nivel1->nodeValue;
                    break;
                case 'SalesOrPurchase';
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Desc':
                                $_SESSION['item']['SalesDesc'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'SalesPrice':
                    $_SESSION['item']['SalesPrice'] = $nivel1->nodeValue;
                    break;
                case 'PurchaseDesc':
                    $_SESSION['item']['PurchaseDesc'] = $nivel1->nodeValue;
                    break;
                case 'PurchaseCost':
                    $_SESSION['item']['PurchaseCost'] = $nivel1->nodeValue;
                    break;
                case 'ReorderPoint':
                    $_SESSION['item']['ReorderPoint'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnHand':
                    $_SESSION['item']['QuantityOnHand'] = $nivel1->nodeValue;
                    break;
                case 'AverageCost':
                    $_SESSION['item']['AverageCost'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnOrder':
                    $_SESSION['item']['QuantityOnOrder'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnSalesOrder':
                    $_SESSION['item']['QuantityOnSalesOrder'] = $nivel1->nodeValue;
                    break;
                case 'DataExtRet':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'DataExtName':
                                $_SESSION['item']['paso'] = $nivel2->nodeValue;
                                break;
                            case 'DataExtValue':
                                if ($_SESSION['item']['paso'] === "Placa") {
                                    $_SESSION['item']['placa'] = $nivel2->nodeValue;
                                } elseif ($_SESSION['item']['paso'] === "Tipo ID") {
                                    $_SESSION['item']['tipoid'] = $nivel2->nodeValue;
                                } elseif ($_SESSION['item']['paso'] === "Numero ID") {
                                    $_SESSION['item']['numeroid'] = $nivel2->nodeValue;
                                } elseif ($_SESSION['item']['paso'] === "Email") {
                                    $_SESSION['item']['email'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function genLimpia_items() {
    $_SESSION['item']['ListID'] = ' ';
    $_SESSION['item']['TimeCreated'] = ' ';
    $_SESSION['item']['TimeModified'] = ' ';
    $_SESSION['item']['EditSequence'] = ' ';
    $_SESSION['item']['Name'] = ' ';
    $_SESSION['item']['FullName'] = ' ';
    $_SESSION['item']['BarCodeValue'] = ' ';
    $_SESSION['item']['IsActive'] = 1;
    $_SESSION['item']['ClassRef_ListID'] = ' ';
    $_SESSION['item']['ClassRef_FullName'] = ' ';
    $_SESSION['item']['ParentRef_ListID'] = ' ';
    $_SESSION['item']['ParentRef_FullName'] = ' ';
    $_SESSION['item']['Sublevel'] = ' ';
    $_SESSION['item']['ManufacturerPartNumber'] = ' ';
    $_SESSION['item']['UnitOfMeasureSetRef_ListID'] = ' ';
    $_SESSION['item']['UnitOfMeasureSetRef_FullName'] = ' ';
    $_SESSION['item']['IsTaxIncluded'] = 'false';
    $_SESSION['item']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['item']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['item']['SalesDesc'] = 0;
    $_SESSION['item']['SalesPrice'] = 0;
    $_SESSION['item']['IncomeAccountRef_ListID'] = ' ';
    $_SESSION['item']['IncomeAccountRef_FullName'] = ' ';
    $_SESSION['item']['PurchaseDesc'] = 0;
    $_SESSION['item']['PurchaseCost'] = 0;
    $_SESSION['item']['COGSAccountRef_ListID'] = ' ';
    $_SESSION['item']['COGSAccountRef_FullName'] = ' ';
    $_SESSION['item']['PrefVendorRef_ListID'] = ' ';
    $_SESSION['item']['PrefVendorRef_FullName'] = ' ';
    $_SESSION['item']['AssetAccountRef_ListID'] = ' ';
    $_SESSION['item']['AssetAccountRef_FullName'] = ' ';
    $_SESSION['item']['ReorderPoint'] = 0;
    $_SESSION['item']['QuantityOnHand'] = 0;
    $_SESSION['item']['AverageCost'] = 0;
    $_SESSION['item']['QuantityOnOrder'] = 0;
    $_SESSION['item']['QuantityOnSalesOrder'] = 0;
    $_SESSION['item']['placa'] = ' ';
    $_SESSION['item']['tipoid'] = ' ';
    $_SESSION['item']['numeroid'] = ' ';
    $_SESSION['item']['email'] = ' ';
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

function quitaslashes_items() {
    $_SESSION['item']['ListID'] = htmlspecialchars(strip_tags($_SESSION['item']['ListID']));
    $_SESSION['item']['TimeCreated'] = htmlspecialchars(strip_tags($_SESSION['item']['TimeCreated']));
    $_SESSION['item']['TimeModified'] = htmlspecialchars(strip_tags($_SESSION['item']['TimeModified']));
    $_SESSION['item']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['item']['EditSequence']));
    $_SESSION['item']['Name'] = htmlspecialchars(strip_tags($_SESSION['item']['Name']));
    $_SESSION['item']['FullName'] = htmlspecialchars(strip_tags($_SESSION['item']['FullName']));
    $_SESSION['item']['BarCodeValue'] = htmlspecialchars(strip_tags($_SESSION['item']['BarCodeValue']));
    $_SESSION['item']['IsActive'] = 1;
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
}
