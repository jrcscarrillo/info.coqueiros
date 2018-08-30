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
define('QB_PRIORITY_INVOICE', 1);
/** Adicion para recuperar las fechas de sincronizacion
 *
 */
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
$fecha = date("Y-m-d", strtotime($registro['invoiceDesde']));
define("AU_DESDE", $fecha);
$fecha = date("Y-m-d", strtotime($registro['invoiceHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);

/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_INVOICE => array('_quickbooks_invoice_import_request', '_quickbooks_invoice_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_INVOICE)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_INVOICE, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, 1, QB_PRIORITY_INVOICE, NULL, $user);
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

function _quickbooks_invoice_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_invoice_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<InvoiceQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <TxnDateRangeFilter>
                                <FromTxnDate >' . AU_DESDE . '</FromTxnDate>
                                <ToTxnDate >' . AU_HASTA . '</ToTxnDate>
                            </TxnDateRangeFilter>
                            <IncludeLineItems>true</IncludeLineItems>
                            <OwnerID>0</OwnerID>
			</InvoiceQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_invoice_initial_response() {
    $myfile = fopen("newfile1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Se ha dejado limpia la tabla de facturas de venta");
    fclose($myfile);
}

function _quickbooks_invoice_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, null, QB_PRIORITY_INVOICE, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['invoice'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("invoice.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "InvoiceRet";
    $factura = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($factura as $uno) {
        genLimpia_invoice();
        gentraverse_invoice($uno);
        $params2 = $factura->item($k)->getElementsByTagName('DataExtRet'); //digg categories with in Section
        $i = 0; // values is used to iterate categories  
        foreach ($params2 as $p) {
            $params3 = $params2->item($i)->getElementsByTagName('DataExtName'); //dig Arti into Categories
            $params4 = $params2->item($i)->getElementsByTagName('DataExtValue'); //dig Arti into Categories
            $j = 0; //values used to interate Arti
            foreach ($params3 as $p2) {
                if ($params3->item($j)->nodeValue == "Ruta") {
                    $_SESSION['invoice']['CustomField1'] = $params4->item($j)->nodeValue;
                }
                $j++;
            }
            $i++;
        }
        $k++;
        $existe = buscaIgual_invoice($db);
        if ($existe == "OK") {
            quitaslashes_invoice();
            fwrite($myfile, "NO!!! Existe factura " . $_SESSION['invoice']['TxnID'] . " \r\n");
            adiciona_invoice($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_invoice();
            fwrite($myfile, "Existe factura " . $_SESSION['invoice']['TxnID'] . " \r\n");
            actualiza_invoice($db);
        }
    }

    $param = "InvoiceLineRet";
    $detalle = $doc->getElementsByTagName($param);
    foreach ($detalle as $uno) {
        $estado = "INIT";
        genLimpia_invoicedetail();
        gentraverse_invoicedetail($uno);
        $existe = buscaIgual_invoicedetail($db);
        if ($existe == "OK") {
            quitaslashes_invoicedetail();
            $estado = adiciona_invoicedetail($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_invoicedetail();
            $estado = actualiza_invoicedetail($db);
        } else {
            fwrite($myfile, "Errores cuando lee detalle de la factura " . $_SESSION['invoicedetail']['TxnLineID'] . " \r\n");
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso a la tabla del detalle de facturas  " . $estado . " id " . $_SESSION['invoicedetail']['TxnLineID'] . " \r\n");
        }
    }

    fclose($myfile);
    fclose($myfile1);
    $sql = 'DELETE FROM invoice WHERE Memo = "VOID:"';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_INVOICE) {
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

function adiciona_invoicedetail($db) {
    $estado = "INIT";
    try {
        $sql = 'INSERT INTO invoicelinedetail (  TxnLineID, ItemRef_ListID, ItemRef_FullName, Description, Quantity, UnitOfMeasure, OverrideUOMSetRef_ListID, OverrideUOMSetRef_FullName, Rate, RatePercent, ClassRef_ListID, ClassRef_FullName, Amount, InventorySiteRef_ListID, InventorySiteRef_FullName, InventorySiteLocationRef_ListID, InventorySiteLocationRef_FullName, SerialNumber, LotNumber, ServiceDate, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, Other1, Other2, LinkedTxnID, LinkedTxnLineID, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, IDKEY ) VALUES ( :TxnLineID, :ItemRef_ListID, :ItemRef_FullName, :Description, :Quantity, :UnitOfMeasure, :OverrideUOMSetRef_ListID, :OverrideUOMSetRef_FullName, :Rate, :RatePercent, :ClassRef_ListID, :ClassRef_FullName, :Amount, :InventorySiteRef_ListID, :InventorySiteRef_FullName, :InventorySiteLocationRef_ListID, :InventorySiteLocationRef_FullName, :SerialNumber, :LotNumber, :ServiceDate, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, :Other1, :Other2, :LinkedTxnID, :LinkedTxnLineID, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY )';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnLineID', $_SESSION['invoicedetail']['TxnLineID']);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['invoicedetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['invoicedetail']['ItemRef_FullName']);
        $stmt->bindParam(':Description', $_SESSION['invoicedetail']['Description']);
        $stmt->bindParam(':Quantity', $_SESSION['invoicedetail']['Quantity']);
        $stmt->bindParam(':UnitOfMeasure', $_SESSION['invoicedetail']['UnitOfMeasure']);
        $stmt->bindParam(':OverrideUOMSetRef_ListID', $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID']);
        $stmt->bindParam(':OverrideUOMSetRef_FullName', $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName']);
        $stmt->bindParam(':Rate', $_SESSION['invoicedetail']['Rate']);
        $stmt->bindParam(':RatePercent', $_SESSION['invoicedetail']['RatePercent']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['invoicedetail']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['invoicedetail']['ClassRef_FullName']);
        $stmt->bindParam(':Amount', $_SESSION['invoicedetail']['Amount']);
        $stmt->bindParam(':InventorySiteRef_ListID', $_SESSION['invoicedetail']['InventorySiteRef_ListID']);
        $stmt->bindParam(':InventorySiteRef_FullName', $_SESSION['invoicedetail']['InventorySiteRef_FullName']);
        $stmt->bindParam(':InventorySiteLocationRef_ListID', $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID']);
        $stmt->bindParam(':InventorySiteLocationRef_FullName', $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName']);
        $stmt->bindParam(':SerialNumber', $_SESSION['invoicedetail']['SerialNumber']);
        $stmt->bindParam(':LotNumber', $_SESSION['invoicedetail']['LotNumber']);
        $stmt->bindParam(':ServiceDate', $_SESSION['invoicedetail']['ServiceDate']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other1', $_SESSION['invoicedetail']['Other1']);
        $stmt->bindParam(':Other2', $_SESSION['invoicedetail']['Other2']);
        $stmt->bindParam(':LinkedTxnID', $_SESSION['invoicedetail']['LinkedTxnID']);
        $stmt->bindParam(':LinkedTxnLineID', $_SESSION['invoicedetail']['LinkedTxnLineID']);
        $stmt->bindParam(':CustomField1', $_SESSION['invoicedetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['invoicedetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['invoicedetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['invoicedetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['invoicedetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['invoicedetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['invoicedetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['invoicedetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['invoicedetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['invoicedetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['invoicedetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['invoicedetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['invoicedetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['invoicedetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['invoicedetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['invoicedetail']['IDKEY']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['invoicedetail']['TxnLineID'];
    }
    return $estado;
}

function genLimpia_invoicedetail() {
    $_SESSION['invoicedetail']['TxnLineID'] = ' ';
    $_SESSION['invoicedetail']['ItemRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['ItemRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['Description'] = ' ';
    $_SESSION['invoicedetail']['Quantity'] = ' ';
    $_SESSION['invoicedetail']['UnitOfMeasure'] = ' ';
    $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['Rate'] = ' ';
    $_SESSION['invoicedetail']['RatePercent'] = ' ';
    $_SESSION['invoicedetail']['ClassRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['ClassRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['Amount'] = ' ';
    $_SESSION['invoicedetail']['InventorySiteRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['InventorySiteRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['SerialNumber'] = ' ';
    $_SESSION['invoicedetail']['LotNumber'] = ' ';
    $_SESSION['invoicedetail']['ServiceDate'] = ' ';
    $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['invoicedetail']['Other1'] = ' ';
    $_SESSION['invoicedetail']['Other2'] = ' ';
    $_SESSION['invoicedetail']['LinkedTxnID'] = ' ';
    $_SESSION['invoicedetail']['LinkedTxnLineID'] = ' ';
    $_SESSION['invoicedetail']['CustomField1'] = ' ';
    $_SESSION['invoicedetail']['CustomField2'] = ' ';
    $_SESSION['invoicedetail']['CustomField3'] = ' ';
    $_SESSION['invoicedetail']['CustomField4'] = ' ';
    $_SESSION['invoicedetail']['CustomField5'] = ' ';
    $_SESSION['invoicedetail']['CustomField6'] = ' ';
    $_SESSION['invoicedetail']['CustomField7'] = ' ';
    $_SESSION['invoicedetail']['CustomField8'] = ' ';
    $_SESSION['invoicedetail']['CustomField9'] = ' ';
    $_SESSION['invoicedetail']['CustomField10'] = ' ';
    $_SESSION['invoicedetail']['CustomField11'] = ' ';
    $_SESSION['invoicedetail']['CustomField12'] = ' ';
    $_SESSION['invoicedetail']['CustomField13'] = ' ';
    $_SESSION['invoicedetail']['CustomField14'] = ' ';
    $_SESSION['invoicedetail']['CustomField15'] = ' ';
    $_SESSION['invoicedetail']['IDKEY'] = ' ';
    $_SESSION['invoicedetail']['GroupIDKEY'] = ' ';
}

function gentraverse_invoicedetail($node) {
    $node->getElementsByTagName('TxnLineID')->item(0) === NULL ? $_SESSION['invoicedetail']['TxnLineID'] = ' ' : $_SESSION['invoicedetail']['TxnLineID'] = $node->getElementsByTagName('TxnLineID')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(0) === NULL ? $_SESSION['invoicedetail']['ItemRef_ListID'] = ' ' : $_SESSION['invoicedetail']['ItemRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) === NULL ? $_SESSION['invoicedetail']['ItemRef_FullName'] = ' ' : $_SESSION['invoicedetail']['ItemRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('Description')->item(0) === NULL ? $_SESSION['invoicedetail']['Description'] = ' ' : $_SESSION['invoicedetail']['Description'] = $node->getElementsByTagName('Description')->item(0)->nodeValue;
    $node->getElementsByTagName('Quantity')->item(0) === NULL ? $_SESSION['invoicedetail']['Quantity'] = ' ' : $_SESSION['invoicedetail']['Quantity'] = $node->getElementsByTagName('Quantity')->item(0)->nodeValue;
    $node->getElementsByTagName('UnitOfMeasure')->item(0) === NULL ? $_SESSION['invoicedetail']['UnitOfMeasure'] = ' ' : $_SESSION['invoicedetail']['UnitOfMeasure'] = $node->getElementsByTagName('UnitOfMeasure')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(1) === NULL ? $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID'] = ' ' : $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
    $node->getElementsByTagName('FullName')->item(1) === NULL ? $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName'] = ' ' : $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName'] = $node->getElementsByTagName('FullName')->item(1)->nodeValue;
    $node->getElementsByTagName('Rate')->item(0) === NULL ? $_SESSION['invoicedetail']['Rate'] = ' ' : $_SESSION['invoicedetail']['Rate'] = $node->getElementsByTagName('Rate')->item(0)->nodeValue;
    $node->getElementsByTagName('RatePercent')->item(0) === NULL ? $_SESSION['invoicedetail']['RatePercent'] = ' ' : $_SESSION['invoicedetail']['RatePercent'] = $node->getElementsByTagName('RatePercent')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(2) === NULL ? $_SESSION['invoicedetail']['ClassRef_ListID'] = ' ' : $_SESSION['invoicedetail']['ClassRef_ListID'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
    $node->getElementsByTagName('FullName')->item(2) === NULL ? $_SESSION['invoicedetail']['ClassRef_FullName'] = ' ' : $_SESSION['invoicedetail']['ClassRef_FullName'] = $node->getElementsByTagName('FullName')->item(2)->nodeValue;
    $node->getElementsByTagName('Amount')->item(0) === NULL ? $_SESSION['invoicedetail']['Amount'] = 0 : $_SESSION['invoicedetail']['Amount'] = $node->getElementsByTagName('Amount')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(3) === NULL ? $_SESSION['invoicedetail']['InventorySiteRef_ListID'] = ' ' : $_SESSION['invoicedetail']['InventorySiteRef_ListID'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
    $node->getElementsByTagName('FullName')->item(3) === NULL ? $_SESSION['invoicedetail']['InventorySiteRef_FullName'] = ' ' : $_SESSION['invoicedetail']['InventorySiteRef_FullName'] = $node->getElementsByTagName('FullName')->item(3)->nodeValue;
    $node->getElementsByTagName('ListID')->item(4) === NULL ? $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID'] = ' ' : $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
    $node->getElementsByTagName('FullName')->item(4) === NULL ? $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName'] = ' ' : $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName'] = $node->getElementsByTagName('FullName')->item(4)->nodeValue;
    $node->getElementsByTagName('SerialNumber')->item(0) === NULL ? $_SESSION['invoicedetail']['SerialNumber'] = ' ' : $_SESSION['invoicedetail']['SerialNumber'] = $node->getElementsByTagName('SerialNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('LotNumber')->item(0) === NULL ? $_SESSION['invoicedetail']['LotNumber'] = ' ' : $_SESSION['invoicedetail']['LotNumber'] = $node->getElementsByTagName('LotNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('ServiceDate')->item(0) === NULL ? $_SESSION['invoicedetail']['ServiceDate'] = '2010-08-10' : $_SESSION['invoicedetail']['ServiceDate'] = $node->getElementsByTagName('ServiceDate')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(5) === NULL ? $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID'] = ' ' : $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID'] = $node->getElementsByTagName('ListID')->item(5)->nodeValue;
    $node->getElementsByTagName('FullName')->item(5) === NULL ? $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName'] = ' ' : $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName'] = $node->getElementsByTagName('FullName')->item(5)->nodeValue;
    $node->getElementsByTagName('Other1')->item(0) === NULL ? $_SESSION['invoicedetail']['Other1'] = ' ' : $_SESSION['invoicedetail']['Other1'] = $node->getElementsByTagName('Other1')->item(0)->nodeValue;
    $node->getElementsByTagName('Other2')->item(0) === NULL ? $_SESSION['invoicedetail']['Other2'] = ' ' : $_SESSION['invoicedetail']['Other2'] = $node->getElementsByTagName('Other2')->item(0)->nodeValue;
    $node->getElementsByTagName('LinkedTxnID')->item(0) === NULL ? $_SESSION['invoicedetail']['LinkedTxnID'] = ' ' : $_SESSION['invoicedetail']['LinkedTxnID'] = $node->getElementsByTagName('LinkedTxnID')->item(0)->nodeValue;
    $node->getElementsByTagName('LinkedTxnLineID')->item(0) === NULL ? $_SESSION['invoicedetail']['LinkedTxnLineID'] = ' ' : $_SESSION['invoicedetail']['LinkedTxnLineID'] = $node->getElementsByTagName('LinkedTxnLineID')->item(0)->nodeValue;

    $node->parentNode->tagName === NULL ? $_SESSION['invoicedetail']['IDKEY'] = ' ' : $_SESSION['invoicedetail']['IDKEY'] = substr(ltrim($node->parentNode->nodeValue), 0, 17);
}

function buscaIgual_invoicedetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'SELECT * FROM invoicelinedetail WHERE TxnLineID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['invoicedetail']['TxnLineID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['TxnLineID'] === $_SESSION['invoicedetail']['TxnLineID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }

    return $estado;
}

function actualiza_invoicedetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'UPDATE invoicelinedetail SET ItemRef_ListID=:ItemRef_ListID, ItemRef_FullName=:ItemRef_FullName, Description=:Description, Quantity=:Quantity, UnitOfMeasure=:UnitOfMeasure, OverrideUOMSetRef_ListID=:OverrideUOMSetRef_ListID, OverrideUOMSetRef_FullName=:OverrideUOMSetRef_FullName, Rate=:Rate, RatePercent=:RatePercent, ClassRef_ListID=:ClassRef_ListID, ClassRef_FullName=:ClassRef_FullName, Amount=:Amount, InventorySiteRef_ListID=:InventorySiteRef_ListID, InventorySiteRef_FullName=:InventorySiteRef_FullName, InventorySiteLocationRef_ListID=:InventorySiteLocationRef_ListID, InventorySiteLocationRef_FullName=:InventorySiteLocationRef_FullName, SerialNumber=:SerialNumber, LotNumber=:LotNumber, ServiceDate=:ServiceDate, SalesTaxCodeRef_ListID=:SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName=:SalesTaxCodeRef_FullName, Other1=:Other1, Other2=:Other2, LinkedTxnID=:LinkedTxnID, LinkedTxnLineID=:LinkedTxnLineID, IDKEY=:IDKEY WHERE TxnLineID = :clave;';
        $stmt = $db->prepare($sql);

        $stmt->bindParam(':ItemRef_ListID', $_SESSION['invoicedetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['invoicedetail']['ItemRef_FullName']);
        $stmt->bindParam(':Description', $_SESSION['invoicedetail']['Description']);
        $stmt->bindParam(':Quantity', $_SESSION['invoicedetail']['Quantity']);
        $stmt->bindParam(':UnitOfMeasure', $_SESSION['invoicedetail']['UnitOfMeasure']);
        $stmt->bindParam(':OverrideUOMSetRef_ListID', $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID']);
        $stmt->bindParam(':OverrideUOMSetRef_FullName', $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName']);
        $stmt->bindParam(':Rate', $_SESSION['invoicedetail']['Rate']);
        $stmt->bindParam(':RatePercent', $_SESSION['invoicedetail']['RatePercent']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['invoicedetail']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['invoicedetail']['ClassRef_FullName']);
        $stmt->bindParam(':Amount', $_SESSION['invoicedetail']['Amount']);
        $stmt->bindParam(':InventorySiteRef_ListID', $_SESSION['invoicedetail']['InventorySiteRef_ListID']);
        $stmt->bindParam(':InventorySiteRef_FullName', $_SESSION['invoicedetail']['InventorySiteRef_FullName']);
        $stmt->bindParam(':InventorySiteLocationRef_ListID', $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID']);
        $stmt->bindParam(':InventorySiteLocationRef_FullName', $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName']);
        $stmt->bindParam(':SerialNumber', $_SESSION['invoicedetail']['SerialNumber']);
        $stmt->bindParam(':LotNumber', $_SESSION['invoicedetail']['LotNumber']);
        $stmt->bindParam(':ServiceDate', $_SESSION['invoicedetail']['ServiceDate']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other1', $_SESSION['invoicedetail']['Other1']);
        $stmt->bindParam(':Other2', $_SESSION['invoicedetail']['Other2']);
        $stmt->bindParam(':LinkedTxnID', $_SESSION['invoicedetail']['LinkedTxnID']);
        $stmt->bindParam(':LinkedTxnLineID', $_SESSION['invoicedetail']['LinkedTxnLineID']);
        $stmt->bindParam(':IDKEY', $_SESSION['invoicedetail']['IDKEY']);
        $stmt->bindParam(':clave', $_SESSION['invoicedetail']['TxnLineID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = "Tiene errores la base de datos " . $e->getMessage() . " posiblemente por aqui " . $e->getLine();
    }
    return $estado;
}

function actualiza_invoice($db) {
    $estado = 'ERR';

    try {
        $sql = 'UPDATE invoice SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, TxnNumber=:TxnNumber, CustomerRef_ListID=:CustomerRef_ListID, CustomerRef_FullName=:CustomerRef_FullName, ClassRef_ListID=:ClassRef_ListID, ClassRef_FullName=:ClassRef_FullName, ARAccountRef_ListID=:ARAccountRef_ListID, ARAccountRef_FullName=:ARAccountRef_FullName, TemplateRef_ListID=:TemplateRef_ListID, TemplateRef_FullName=:TemplateRef_FullName, TxnDate=:TxnDate, RefNumber=:RefNumber, BillAddress_Addr1=:BillAddress_Addr1, BillAddress_Addr2=:BillAddress_Addr2, BillAddress_Addr3=:BillAddress_Addr3, BillAddress_Addr4=:BillAddress_Addr4, BillAddress_Addr5=:BillAddress_Addr5, BillAddress_City=:BillAddress_City, BillAddress_State=:BillAddress_State, BillAddress_PostalCode=:BillAddress_PostalCode, BillAddress_Country=:BillAddress_Country, BillAddress_Note=:BillAddress_Note, ShipAddress_Addr1=:ShipAddress_Addr1, ShipAddress_Addr2=:ShipAddress_Addr2, ShipAddress_Addr3=:ShipAddress_Addr3, ShipAddress_Addr4=:ShipAddress_Addr4, ShipAddress_Addr5=:ShipAddress_Addr5, ShipAddress_City=:ShipAddress_City, ShipAddress_State=:ShipAddress_State, ShipAddress_PostalCode=:ShipAddress_PostalCode, ShipAddress_Country=:ShipAddress_Country, ShipAddress_Note=:ShipAddress_Note, IsPending=:IsPending, IsFinanceCharge=:IsFinanceCharge, PONumber=:PONumber, TermsRef_ListID=:TermsRef_ListID, TermsRef_FullName=:TermsRef_FullName, DueDate=:DueDate, SalesRepRef_ListID=:SalesRepRef_ListID, SalesRepRef_FullName=:SalesRepRef_FullName, FOB=:FOB, ShipDate=:ShipDate, ShipMethodRef_ListID=:ShipMethodRef_ListID, ShipMethodRef_FullName=:ShipMethodRef_FullName, Subtotal=:Subtotal, ItemSalesTaxRef_ListID=:ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName=:ItemSalesTaxRef_FullName, SalesTaxPercentage=:SalesTaxPercentage, SalesTaxTotal=:SalesTaxTotal, AppliedAmount=:AppliedAmount, BalanceRemaining=:BalanceRemaining, CurrencyRef_ListID=:CurrencyRef_ListID, CurrencyRef_FullName=:CurrencyRef_FullName, ExchangeRate=:ExchangeRate, BalanceRemainingInHomeCurrency=:BalanceRemainingInHomeCurrency, Memo=:Memo, IsPaID=:IsPaID, CustomerMsgRef_ListID=:CustomerMsgRef_ListID, CustomerMsgRef_FullName=:CustomerMsgRef_FullName, IsToBePrinted=:IsToBePrinted, IsToBeEmailed=:IsToBeEmailed, IsTaxIncluded=:IsTaxIncluded, CustomerSalesTaxCodeRef_ListID=:CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName=:CustomerSalesTaxCodeRef_FullName, SuggestedDiscountAmount=:SuggestedDiscountAmount, SuggestedDiscountDate=:SuggestedDiscountDate, Other=:Other, Status=:Status' . ' WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['invoice']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['invoice']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['invoice']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['invoice']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['invoice']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['invoice']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['invoice']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['invoice']['ClassRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['invoice']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['invoice']['ARAccountRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['invoice']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['invoice']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['invoice']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['invoice']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['invoice']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['invoice']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['invoice']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['invoice']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['invoice']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['invoice']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['invoice']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['invoice']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['invoice']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['invoice']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['invoice']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['invoice']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['invoice']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['invoice']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['invoice']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['invoice']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['invoice']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['invoice']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['invoice']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['invoice']['ShipAddress_Note']);
        $stmt->bindParam(':IsPending', $_SESSION['invoice']['IsPending']);
        $stmt->bindParam(':IsFinanceCharge', $_SESSION['invoice']['IsFinanceCharge']);
        $stmt->bindParam(':PONumber', $_SESSION['invoice']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['invoice']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['invoice']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['invoice']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['invoice']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['invoice']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['invoice']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['invoice']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['invoice']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['invoice']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['invoice']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['invoice']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['invoice']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['invoice']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['invoice']['SalesTaxTotal']);
        $stmt->bindParam(':AppliedAmount', $_SESSION['invoice']['AppliedAmount']);
        $stmt->bindParam(':BalanceRemaining', $_SESSION['invoice']['BalanceRemaining']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['invoice']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['invoice']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['invoice']['ExchangeRate']);
        $stmt->bindParam(':BalanceRemainingInHomeCurrency', $_SESSION['invoice']['BalanceRemainingInHomeCurrency']);
        $stmt->bindParam(':Memo', $_SESSION['invoice']['Memo']);
        $stmt->bindParam(':IsPaID', $_SESSION['invoice']['IsPaID']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['invoice']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['invoice']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['invoice']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['invoice']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['invoice']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':SuggestedDiscountAmount', $_SESSION['invoice']['SuggestedDiscountAmount']);
        $stmt->bindParam(':SuggestedDiscountDate', $_SESSION['invoice']['SuggestedDiscountDate']);
        $stmt->bindParam(':Other', $_SESSION['invoice']['Other']);
        $stmt->bindParam(':Status', $_SESSION['invoice']['Status']);
        $stmt->bindParam(':clave', $_SESSION['invoice']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['invoice']['TxnID'] . ' campo ' . $_SESSION['invoice']['IsPaid'] . '<br>';
    }
    return $estado;
}

function adiciona_invoice($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO invoice (  TxnID, TimeCreated, TimeModified, EditSequence, TxnNumber, CustomerRef_ListID, CustomerRef_FullName, ClassRef_ListID, ClassRef_FullName, ARAccountRef_ListID, ARAccountRef_FullName, TemplateRef_ListID, TemplateRef_FullName, TxnDate, RefNumber, BillAddress_Addr1, BillAddress_Addr2, BillAddress_Addr3, BillAddress_Addr4, BillAddress_Addr5, BillAddress_City, BillAddress_State, BillAddress_PostalCode, BillAddress_Country, BillAddress_Note, ShipAddress_Addr1, ShipAddress_Addr2, ShipAddress_Addr3, ShipAddress_Addr4, ShipAddress_Addr5, ShipAddress_City, ShipAddress_State, ShipAddress_PostalCode, ShipAddress_Country, ShipAddress_Note, IsPending, IsFinanceCharge, PONumber, TermsRef_ListID, TermsRef_FullName, DueDate, SalesRepRef_ListID, SalesRepRef_FullName, FOB, ShipDate, ShipMethodRef_ListID, ShipMethodRef_FullName, Subtotal, ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName, SalesTaxPercentage, SalesTaxTotal, AppliedAmount, BalanceRemaining, CurrencyRef_ListID, CurrencyRef_FullName, ExchangeRate, BalanceRemainingInHomeCurrency, Memo, IsPaid, CustomerMsgRef_ListID, CustomerMsgRef_FullName, IsToBePrinted, IsToBeEmailed, IsTaxIncluded, CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName, SuggestedDiscountAmount, SuggestedDiscountDate, Other, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status ) VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnNumber, :CustomerRef_ListID, :CustomerRef_FullName, :ClassRef_ListID, :ClassRef_FullName, :ARAccountRef_ListID, :ARAccountRef_FullName, :TemplateRef_ListID, :TemplateRef_FullName, :TxnDate, :RefNumber, :BillAddress_Addr1, :BillAddress_Addr2, :BillAddress_Addr3, :BillAddress_Addr4, :BillAddress_Addr5, :BillAddress_City, :BillAddress_State, :BillAddress_PostalCode, :BillAddress_Country, :BillAddress_Note, :ShipAddress_Addr1, :ShipAddress_Addr2, :ShipAddress_Addr3, :ShipAddress_Addr4, :ShipAddress_Addr5, :ShipAddress_City, :ShipAddress_State, :ShipAddress_PostalCode, :ShipAddress_Country, :ShipAddress_Note, :IsPending, :IsFinanceCharge, :PONumber, :TermsRef_ListID, :TermsRef_FullName, :DueDate, :SalesRepRef_ListID, :SalesRepRef_FullName, :FOB, :ShipDate, :ShipMethodRef_ListID, :ShipMethodRef_FullName, :Subtotal, :ItemSalesTaxRef_ListID, :ItemSalesTaxRef_FullName, :SalesTaxPercentage, :SalesTaxTotal, :AppliedAmount, :BalanceRemaining, :CurrencyRef_ListID, :CurrencyRef_FullName, :ExchangeRate, :BalanceRemainingInHomeCurrency, :Memo, :IsPaid, :CustomerMsgRef_ListID, :CustomerMsgRef_FullName, :IsToBePrinted, :IsToBeEmailed, :IsTaxIncluded, :CustomerSalesTaxCodeRef_ListID, :CustomerSalesTaxCodeRef_FullName, :SuggestedDiscountAmount, :SuggestedDiscountDate, :Other, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status )';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['invoice']['TxnID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['invoice']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['invoice']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['invoice']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['invoice']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['invoice']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['invoice']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['invoice']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['invoice']['ClassRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['invoice']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['invoice']['ARAccountRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['invoice']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['invoice']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['invoice']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['invoice']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['invoice']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['invoice']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['invoice']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['invoice']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['invoice']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['invoice']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['invoice']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['invoice']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['invoice']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['invoice']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['invoice']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['invoice']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['invoice']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['invoice']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['invoice']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['invoice']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['invoice']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['invoice']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['invoice']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['invoice']['ShipAddress_Note']);
        $stmt->bindParam(':IsPending', $_SESSION['invoice']['IsPending']);
        $stmt->bindParam(':IsFinanceCharge', $_SESSION['invoice']['IsFinanceCharge']);
        $stmt->bindParam(':PONumber', $_SESSION['invoice']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['invoice']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['invoice']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['invoice']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['invoice']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['invoice']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['invoice']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['invoice']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['invoice']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['invoice']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['invoice']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['invoice']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['invoice']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['invoice']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['invoice']['SalesTaxTotal']);
        $stmt->bindParam(':AppliedAmount', $_SESSION['invoice']['AppliedAmount']);
        $stmt->bindParam(':BalanceRemaining', $_SESSION['invoice']['BalanceRemaining']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['invoice']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['invoice']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['invoice']['ExchangeRate']);
        $stmt->bindParam(':BalanceRemainingInHomeCurrency', $_SESSION['invoice']['BalanceRemainingInHomeCurrency']);
        $stmt->bindParam(':Memo', $_SESSION['invoice']['Memo']);
        $stmt->bindParam(':IsPaid', $_SESSION['invoice']['IsPaid']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['invoice']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['invoice']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['invoice']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['invoice']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['invoice']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':SuggestedDiscountAmount', $_SESSION['invoice']['SuggestedDiscountAmount']);
        $stmt->bindParam(':SuggestedDiscountDate', $_SESSION['invoice']['SuggestedDiscountDate']);
        $stmt->bindParam(':Other', $_SESSION['invoice']['Other']);
        $stmt->bindParam(':CustomField1', $_SESSION['invoice']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['invoice']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['invoice']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['invoice']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['invoice']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['invoice']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['invoice']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['invoice']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['invoice']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['invoice']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['invoice']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['invoice']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['invoice']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['invoice']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['invoice']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['invoice']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['invoice']['TxnID'] . ' campo ' . $_SESSION['invoice']['IsPaid'] . '<br>';
    }
    return $estado;
}

function buscaIgual_invoice($db) {
    $estado = "ERR";
    try {
        $sql = "SELECT * FROM invoice WHERE TxnID = :clave ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['invoice']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = "OK";
            $_SESSION['invoice']['CustomField10'] = "SIN IMPRIMIR";
            $_SESSION['invoice']['CustomField15'] = "SIN FIRMAR";
        } else {
            if ($registro['TxnID'] === $_SESSION['invoice']['TxnID']) {
                $estado = "ACTUALIZA";
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_invoice($node) {
    $node->getElementsByTagName('TxnID')->item(0) === NULL ? $_SESSION['invoice']['TxnID'] = ' ' : $_SESSION['invoice']['TxnID'] = $node->getElementsByTagName('TxnID')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeCreated')->item(0) === NULL ? $_SESSION['invoice']['TimeCreated'] = ' ' : $_SESSION['invoice']['TimeCreated'] = $node->getElementsByTagName('TimeCreated')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeModified')->item(0) === NULL ? $_SESSION['invoice']['TimeModified'] = ' ' : $_SESSION['invoice']['TimeModified'] = $node->getElementsByTagName('TimeModified')->item(0)->nodeValue;
    $node->getElementsByTagName('EditSequence')->item(0) === NULL ? $_SESSION['invoice']['EditSequence'] = ' ' : $_SESSION['invoice']['EditSequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
    $node->getElementsByTagName('TxnNumber')->item(0) === NULL ? $_SESSION['invoice']['TxnNumber'] = ' ' : $_SESSION['invoice']['TxnNumber'] = $node->getElementsByTagName('TxnNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(0) === NULL ? $_SESSION['invoice']['CustomerRef_ListID'] = ' ' : $_SESSION['invoice']['CustomerRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) === NULL ? $_SESSION['invoice']['CustomerRef_FullName'] = ' ' : $_SESSION['invoice']['CustomerRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(1) === NULL ? $_SESSION['invoice']['ClassRef_ListID'] = ' ' : $_SESSION['invoice']['ClassRef_ListID'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
    $node->getElementsByTagName('FullName')->item(1) === NULL ? $_SESSION['invoice']['ClassRef_FullName'] = ' ' : $_SESSION['invoice']['ClassRef_FullName'] = $node->getElementsByTagName('FullName')->item(1)->nodeValue;
    $node->getElementsByTagName('ListID')->item(2) === NULL ? $_SESSION['invoice']['ARAccountRef_ListID'] = ' ' : $_SESSION['invoice']['ARAccountRef_ListID'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
    $node->getElementsByTagName('FullName')->item(2) === NULL ? $_SESSION['invoice']['ARAccountRef_FullName'] = ' ' : $_SESSION['invoice']['ARAccountRef_FullName'] = $node->getElementsByTagName('FullName')->item(2)->nodeValue;
    $node->getElementsByTagName('ListID')->item(3) === NULL ? $_SESSION['invoice']['TemplateRef_ListID'] = ' ' : $_SESSION['invoice']['TemplateRef_ListID'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
    $node->getElementsByTagName('FullName')->item(3) === NULL ? $_SESSION['invoice']['TemplateRef_FullName'] = ' ' : $_SESSION['invoice']['TemplateRef_FullName'] = $node->getElementsByTagName('FullName')->item(3)->nodeValue;
    $node->getElementsByTagName('TxnDate')->item(0) === NULL ? $_SESSION['invoice']['TxnDate'] = '2010-08-10' : $_SESSION['invoice']['TxnDate'] = $node->getElementsByTagName('TxnDate')->item(0)->nodeValue;
    $node->getElementsByTagName('RefNumber')->item(0) === NULL ? $_SESSION['invoice']['RefNumber'] = ' ' : $_SESSION['invoice']['RefNumber'] = $node->getElementsByTagName('RefNumber')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr1')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Addr1'] = ' ' : $_SESSION['invoice']['BillAddress_Addr1'] = $node->getElementsByTagName('Addr1')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr2')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Addr2'] = ' ' : $_SESSION['invoice']['BillAddress_Addr2'] = $node->getElementsByTagName('Addr2')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr3')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Addr3'] = ' ' : $_SESSION['invoice']['BillAddress_Addr3'] = $node->getElementsByTagName('Addr3')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr4')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Addr4'] = ' ' : $_SESSION['invoice']['BillAddress_Addr4'] = $node->getElementsByTagName('Addr4')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr5')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Addr5'] = ' ' : $_SESSION['invoice']['BillAddress_Addr5'] = $node->getElementsByTagName('Addr5')->item(0)->nodeValue;
    $node->getElementsByTagName('City')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_City'] = ' ' : $_SESSION['invoice']['BillAddress_City'] = $node->getElementsByTagName('City')->item(0)->nodeValue;
    $node->getElementsByTagName('State')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_State'] = ' ' : $_SESSION['invoice']['BillAddress_State'] = $node->getElementsByTagName('State')->item(0)->nodeValue;
    $node->getElementsByTagName('PostalCode')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_PostalCode'] = ' ' : $_SESSION['invoice']['BillAddress_PostalCode'] = $node->getElementsByTagName('PostalCode')->item(0)->nodeValue;
    $node->getElementsByTagName('Country')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Country'] = ' ' : $_SESSION['invoice']['BillAddress_Country'] = $node->getElementsByTagName('Country')->item(0)->nodeValue;
    $node->getElementsByTagName('Note')->item(0) === NULL ? $_SESSION['invoice']['BillAddress_Note'] = ' ' : $_SESSION['invoice']['BillAddress_Note'] = $node->getElementsByTagName('Note')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr1')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Addr1'] = ' ' : $_SESSION['invoice']['ShipAddress_Addr1'] = $node->getElementsByTagName('Addr1')->item(1)->nodeValue;
    $node->getElementsByTagName('Addr2')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Addr2'] = ' ' : $_SESSION['invoice']['ShipAddress_Addr2'] = $node->getElementsByTagName('Addr2')->item(1)->nodeValue;
    $node->getElementsByTagName('Addr3')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Addr3'] = ' ' : $_SESSION['invoice']['ShipAddress_Addr3'] = $node->getElementsByTagName('Addr3')->item(1)->nodeValue;
    $node->getElementsByTagName('Addr4')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Addr4'] = ' ' : $_SESSION['invoice']['ShipAddress_Addr4'] = $node->getElementsByTagName('Addr4')->item(1)->nodeValue;
    $node->getElementsByTagName('Addr5')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Addr5'] = ' ' : $_SESSION['invoice']['ShipAddress_Addr5'] = $node->getElementsByTagName('Addr5')->item(1)->nodeValue;
    $node->getElementsByTagName('City')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_City'] = ' ' : $_SESSION['invoice']['ShipAddress_City'] = $node->getElementsByTagName('City')->item(1)->nodeValue;
    $node->getElementsByTagName('State')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_State'] = ' ' : $_SESSION['invoice']['ShipAddress_State'] = $node->getElementsByTagName('State')->item(1)->nodeValue;
    $node->getElementsByTagName('PostalCode')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_PostalCode'] = ' ' : $_SESSION['invoice']['ShipAddress_PostalCode'] = $node->getElementsByTagName('PostalCode')->item(1)->nodeValue;
    $node->getElementsByTagName('Country')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Country'] = ' ' : $_SESSION['invoice']['ShipAddress_Country'] = $node->getElementsByTagName('Country')->item(1)->nodeValue;
    $node->getElementsByTagName('Note')->item(1) === NULL ? $_SESSION['invoice']['ShipAddress_Note'] = ' ' : $_SESSION['invoice']['ShipAddress_Note'] = $node->getElementsByTagName('Note')->item(1)->nodeValue;
    if ($node->getElementsByTagName('IsPending')->item(0)->nodeValue) {
        $_SESSION['invoice']['IsPending'] = 'true';
    } else {
        $_SESSION['invoice']['IsPending'] = 'false';
    }
    if ($node->getElementsByTagName('IsFinanceCharge')->item(0)->nodeValue) {
        $_SESSION['invoice']['IsFinanceCharge'] = 'true';
    } else {
        $_SESSION['invoice']['IsFinanceCharge'] = 'false';
    }
    $node->getElementsByTagName('PONumber')->item(0) === NULL ? $_SESSION['invoice']['PONumber'] = ' ' : $_SESSION['invoice']['PONumber'] = $node->getElementsByTagName('PONumber')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(4) === NULL ? $_SESSION['invoice']['TermsRef_ListID'] = ' ' : $_SESSION['invoice']['TermsRef_ListID'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
    $node->getElementsByTagName('FullName')->item(4) === NULL ? $_SESSION['invoice']['TermsRef_FullName'] = ' ' : $_SESSION['invoice']['TermsRef_FullName'] = $node->getElementsByTagName('FullName')->item(4)->nodeValue;
    $node->getElementsByTagName('DueDate')->item(0) === NULL ? $_SESSION['invoice']['DueDate'] = '2010-08-10' : $_SESSION['invoice']['DueDate'] = $node->getElementsByTagName('DueDate')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(5) === NULL ? $_SESSION['invoice']['SalesRepRef_ListID'] = ' ' : $_SESSION['invoice']['SalesRepRef_ListID'] = $node->getElementsByTagName('ListID')->item(5)->nodeValue;
    $node->getElementsByTagName('FullName')->item(5) === NULL ? $_SESSION['invoice']['SalesRepRef_FullName'] = ' ' : $_SESSION['invoice']['SalesRepRef_FullName'] = $node->getElementsByTagName('FullName')->item(5)->nodeValue;
    $node->getElementsByTagName('FOB')->item(0) === NULL ? $_SESSION['invoice']['FOB'] = ' ' : $_SESSION['invoice']['FOB'] = $node->getElementsByTagName('FOB')->item(0)->nodeValue;
    $node->getElementsByTagName('ShipDate')->item(0) === NULL ? $_SESSION['invoice']['ShipDate'] = '2010-08-10' : $_SESSION['invoice']['ShipDate'] = $node->getElementsByTagName('ShipDate')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(6) === NULL ? $_SESSION['invoice']['ShipMethodRef_ListID'] = ' ' : $_SESSION['invoice']['ShipMethodRef_ListID'] = $node->getElementsByTagName('ListID')->item(6)->nodeValue;
    $node->getElementsByTagName('FullName')->item(6) === NULL ? $_SESSION['invoice']['ShipMethodRef_FullName'] = ' ' : $_SESSION['invoice']['ShipMethodRef_FullName'] = $node->getElementsByTagName('FullName')->item(6)->nodeValue;
    $node->getElementsByTagName('Subtotal')->item(0) === NULL ? $_SESSION['invoice']['Subtotal'] = 0 : $_SESSION['invoice']['Subtotal'] = $node->getElementsByTagName('Subtotal')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(7) === NULL ? $_SESSION['invoice']['ItemSalesTaxRef_ListID'] = ' ' : $_SESSION['invoice']['ItemSalesTaxRef_ListID'] = $node->getElementsByTagName('ListID')->item(7)->nodeValue;
    $node->getElementsByTagName('FullName')->item(7) === NULL ? $_SESSION['invoice']['ItemSalesTaxRef_FullName'] = ' ' : $_SESSION['invoice']['ItemSalesTaxRef_FullName'] = $node->getElementsByTagName('FullName')->item(7)->nodeValue;
    $node->getElementsByTagName('SalesTaxPercentage')->item(0) === NULL ? $_SESSION['invoice']['SalesTaxPercentage'] = ' ' : $_SESSION['invoice']['SalesTaxPercentage'] = $node->getElementsByTagName('SalesTaxPercentage')->item(0)->nodeValue;
    $node->getElementsByTagName('SalesTaxTotal')->item(0) === NULL ? $_SESSION['invoice']['SalesTaxTotal'] = 0 : $_SESSION['invoice']['SalesTaxTotal'] = $node->getElementsByTagName('SalesTaxTotal')->item(0)->nodeValue;
    $node->getElementsByTagName('AppliedAmount')->item(0) === NULL ? $_SESSION['invoice']['AppliedAmount'] = 0 : $_SESSION['invoice']['AppliedAmount'] = $node->getElementsByTagName('AppliedAmount')->item(0)->nodeValue;
    $node->getElementsByTagName('BalanceRemaining')->item(0) === NULL ? $_SESSION['invoice']['BalanceRemaining'] = 0 : $_SESSION['invoice']['BalanceRemaining'] = $node->getElementsByTagName('BalanceRemaining')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(8) === NULL ? $_SESSION['invoice']['CurrencyRef_ListID'] = ' ' : $_SESSION['invoice']['CurrencyRef_ListID'] = $node->getElementsByTagName('ListID')->item(8)->nodeValue;
    $node->getElementsByTagName('FullName')->item(8) === NULL ? $_SESSION['invoice']['CurrencyRef_FullName'] = ' ' : $_SESSION['invoice']['CurrencyRef_FullName'] = $node->getElementsByTagName('FullName')->item(8)->nodeValue;
    $node->getElementsByTagName('ExchangeRate')->item(0) === NULL ? $_SESSION['invoice']['ExchangeRate'] = 0 : $_SESSION['invoice']['ExchangeRate'] = $node->getElementsByTagName('ExchangeRate')->item(0)->nodeValue;
    $node->getElementsByTagName('BalanceRemainingInHomeCurrency')->item(0) === NULL ? $_SESSION['invoice']['BalanceRemainingInHomeCurrency'] = 0 : $_SESSION['invoice']['BalanceRemainingInHomeCurrency'] = $node->getElementsByTagName('BalanceRemainingInHomeCurrency')->item(0)->nodeValue;
    $node->getElementsByTagName('Memo')->item(0) === NULL ? $_SESSION['invoice']['Memo'] = ' ' : $_SESSION['invoice']['Memo'] = $node->getElementsByTagName('Memo')->item(0)->nodeValue;
    if ($node->getElementsByTagName('IsPaid')->item(0)->nodeValue) {
        $_SESSION['invoice']['IsPaid'] = 'true';
    } else {
        $_SESSION['invoice']['IsPaid'] = 'false';
    }
    $node->getElementsByTagName('ListID')->item(9) === NULL ? $_SESSION['invoice']['CustomerMsgRef_ListID'] = ' ' : $_SESSION['invoice']['CustomerMsgRef_ListID'] = $node->getElementsByTagName('ListID')->item(9)->nodeValue;
    $node->getElementsByTagName('FullName')->item(9) === NULL ? $_SESSION['invoice']['CustomerMsgRef_FullName'] = ' ' : $_SESSION['invoice']['CustomerMsgRef_FullName'] = $node->getElementsByTagName('FullName')->item(9)->nodeValue;
    $node->getElementsByTagName('IsToBePrinted')->item(0) === NULL ? $_SESSION['invoice']['IsToBePrinted'] = ' ' : $_SESSION['invoice']['IsToBePrinted'] = $node->getElementsByTagName('IsToBePrinted')->item(0)->nodeValue;
    $node->getElementsByTagName('IsToBeEmailed')->item(0) === NULL ? $_SESSION['invoice']['IsToBeEmailed'] = ' ' : $_SESSION['invoice']['IsToBeEmailed'] = $node->getElementsByTagName('IsToBeEmailed')->item(0)->nodeValue;
    $node->getElementsByTagName('IsTaxIncluded')->item(0) === NULL ? $_SESSION['invoice']['IsTaxIncluded'] = ' ' : $_SESSION['invoice']['IsTaxIncluded'] = $node->getElementsByTagName('IsTaxIncluded')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(10) === NULL ? $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID'] = ' ' : $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID'] = $node->getElementsByTagName('ListID')->item(10)->nodeValue;
    $node->getElementsByTagName('FullName')->item(10) === NULL ? $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName'] = ' ' : $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName'] = $node->getElementsByTagName('FullName')->item(10)->nodeValue;
    $node->getElementsByTagName('SuggestedDiscountAmount')->item(0) === NULL ? $_SESSION['invoice']['SuggestedDiscountAmount'] = 0 : $_SESSION['invoice']['SuggestedDiscountAmount'] = $node->getElementsByTagName('SuggestedDiscountAmount')->item(0)->nodeValue;
    $node->getElementsByTagName('SuggestedDiscountDate')->item(0) === NULL ? $_SESSION['invoice']['SuggestedDiscountDate'] = '2010-08-10' : $_SESSION['invoice']['SuggestedDiscountDate'] = $node->getElementsByTagName('SuggestedDiscountDate')->item(0)->nodeValue;
    $node->getElementsByTagName('Other')->item(0) === NULL ? $_SESSION['invoice']['Other'] = ' ' : $_SESSION['invoice']['Other'] = $node->getElementsByTagName('Other')->item(0)->nodeValue;
    for ($k = 0; $k < 15; $k++) {
        if ($node->getElementsByTagName('DataExtName')->item($k) === NULL) {
            $ruta = $node->getElementsByTagName('DataExtName')->item($k)->nodeValue;
            if ($ruta === "Ruta") {
                $_SESSION['invoice']['CustomField1'] = $node->getElementsByTagName('DataExtValue')->item($k)->nodeValue;
            }
        }
    }
    $node->getElementsByTagName('Status')->item(0) === NULL ? $_SESSION['invoice']['Status'] = ' ' : $_SESSION['invoice']['Status'] = $node->getElementsByTagName('Status')->item(0)->nodeValue;
}

function quitaslashes_invoicedetail() {
    $_SESSION['invoicedetail']['TxnLineID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['TxnLineID']));
    $_SESSION['invoicedetail']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['ItemRef_ListID']));
    $_SESSION['invoicedetail']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['ItemRef_FullName']));
    $_SESSION['invoicedetail']['Description'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Description']));
    $_SESSION['invoicedetail']['Quantity'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Quantity']));
    $_SESSION['invoicedetail']['UnitOfMeasure'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['UnitOfMeasure']));
    $_SESSION['invoicedetail']['OverrideUOMSetRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['OverrideUOMSetRef_ListID']));
    $_SESSION['invoicedetail']['OverrideUOMSetRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['OverrideUOMSetRef_FullName']));
    $_SESSION['invoicedetail']['Rate'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Rate']));
    $_SESSION['invoicedetail']['RatePercent'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['RatePercent']));
    $_SESSION['invoicedetail']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['ClassRef_ListID']));
    $_SESSION['invoicedetail']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['ClassRef_FullName']));
    $_SESSION['invoicedetail']['Amount'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Amount']));
    $_SESSION['invoicedetail']['InventorySiteRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['InventorySiteRef_ListID']));
    $_SESSION['invoicedetail']['InventorySiteRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['InventorySiteRef_FullName']));
    $_SESSION['invoicedetail']['InventorySiteLocationRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['InventorySiteLocationRef_ListID']));
    $_SESSION['invoicedetail']['InventorySiteLocationRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['InventorySiteLocationRef_FullName']));
    $_SESSION['invoicedetail']['SerialNumber'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['SerialNumber']));
    $_SESSION['invoicedetail']['LotNumber'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['LotNumber']));
    $_SESSION['invoicedetail']['ServiceDate'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['ServiceDate']));
    $_SESSION['invoicedetail']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['SalesTaxCodeRef_ListID']));
    $_SESSION['invoicedetail']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['SalesTaxCodeRef_FullName']));
    $_SESSION['invoicedetail']['Other1'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Other1']));
    $_SESSION['invoicedetail']['Other2'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['Other2']));
    $_SESSION['invoicedetail']['LinkedTxnID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['LinkedTxnID']));
    $_SESSION['invoicedetail']['LinkedTxnLineID'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['LinkedTxnLineID']));
    $_SESSION['invoicedetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField1']));
    $_SESSION['invoicedetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField2']));
    $_SESSION['invoicedetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField3']));
    $_SESSION['invoicedetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField4']));
    $_SESSION['invoicedetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField5']));
    $_SESSION['invoicedetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField6']));
    $_SESSION['invoicedetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField7']));
    $_SESSION['invoicedetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField8']));
    $_SESSION['invoicedetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField9']));
    $_SESSION['invoicedetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField10']));
    $_SESSION['invoicedetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField11']));
    $_SESSION['invoicedetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField12']));
    $_SESSION['invoicedetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField13']));
    $_SESSION['invoicedetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField14']));
    $_SESSION['invoicedetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['CustomField15']));
//$_SESSION['invoicedetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['IDKEY']));
//$_SESSION['invoicedetail']['GroupIDKEY'] = htmlspecialchars(strip_tags($_SESSION['invoicedetail']['GroupIDKEY']));
}

function quitaslashes_invoice() {
    $_SESSION['invoice']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TxnID']));
    $_SESSION['invoice']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['invoice']['TimeCreated']));
    $_SESSION['invoice']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['invoice']['TimeModified']));
    $_SESSION['invoice']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['invoice']['EditSequence']));
    $_SESSION['invoice']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TxnNumber']));
    $_SESSION['invoice']['CustomerRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerRef_ListID']));
    $_SESSION['invoice']['CustomerRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerRef_FullName']));
    $_SESSION['invoice']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ClassRef_ListID']));
    $_SESSION['invoice']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ClassRef_FullName']));
    $_SESSION['invoice']['ARAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ARAccountRef_ListID']));
    $_SESSION['invoice']['ARAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ARAccountRef_FullName']));
    $_SESSION['invoice']['TemplateRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TemplateRef_ListID']));
    $_SESSION['invoice']['TemplateRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TemplateRef_FullName']));
    $_SESSION['invoice']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TxnDate']));
    $_SESSION['invoice']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['invoice']['RefNumber']));
    $_SESSION['invoice']['BillAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Addr1']));
    $_SESSION['invoice']['BillAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Addr2']));
    $_SESSION['invoice']['BillAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Addr3']));
    $_SESSION['invoice']['BillAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Addr4']));
    $_SESSION['invoice']['BillAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Addr5']));
    $_SESSION['invoice']['BillAddress_City'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_City']));
    $_SESSION['invoice']['BillAddress_State'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_State']));
    $_SESSION['invoice']['BillAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_PostalCode']));
    $_SESSION['invoice']['BillAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Country']));
    $_SESSION['invoice']['BillAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BillAddress_Note']));
    $_SESSION['invoice']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Addr1']));
    $_SESSION['invoice']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Addr2']));
    $_SESSION['invoice']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Addr3']));
    $_SESSION['invoice']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Addr4']));
    $_SESSION['invoice']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Addr5']));
    $_SESSION['invoice']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_City']));
    $_SESSION['invoice']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_State']));
    $_SESSION['invoice']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_PostalCode']));
    $_SESSION['invoice']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Country']));
    $_SESSION['invoice']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipAddress_Note']));
    $_SESSION['invoice']['IsPending'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsPending']));
    $_SESSION['invoice']['IsFinanceCharge'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsFinanceCharge']));
    $_SESSION['invoice']['PONumber'] = htmlspecialchars(strip_tags($_SESSION['invoice']['PONumber']));
    $_SESSION['invoice']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TermsRef_ListID']));
    $_SESSION['invoice']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['TermsRef_FullName']));
    $_SESSION['invoice']['DueDate'] = htmlspecialchars(strip_tags($_SESSION['invoice']['DueDate']));
    $_SESSION['invoice']['SalesRepRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SalesRepRef_ListID']));
    $_SESSION['invoice']['SalesRepRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SalesRepRef_FullName']));
    $_SESSION['invoice']['FOB'] = htmlspecialchars(strip_tags($_SESSION['invoice']['FOB']));
    $_SESSION['invoice']['ShipDate'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipDate']));
    $_SESSION['invoice']['ShipMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipMethodRef_ListID']));
    $_SESSION['invoice']['ShipMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ShipMethodRef_FullName']));
    $_SESSION['invoice']['Subtotal'] = htmlspecialchars(strip_tags($_SESSION['invoice']['Subtotal']));
    $_SESSION['invoice']['ItemSalesTaxRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ItemSalesTaxRef_ListID']));
    $_SESSION['invoice']['ItemSalesTaxRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ItemSalesTaxRef_FullName']));
    $_SESSION['invoice']['SalesTaxPercentage'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SalesTaxPercentage']));
    $_SESSION['invoice']['SalesTaxTotal'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SalesTaxTotal']));
    $_SESSION['invoice']['AppliedAmount'] = htmlspecialchars(strip_tags($_SESSION['invoice']['AppliedAmount']));
    $_SESSION['invoice']['BalanceRemaining'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BalanceRemaining']));
    $_SESSION['invoice']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CurrencyRef_ListID']));
    $_SESSION['invoice']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CurrencyRef_FullName']));
    $_SESSION['invoice']['ExchangeRate'] = htmlspecialchars(strip_tags($_SESSION['invoice']['ExchangeRate']));
    $_SESSION['invoice']['BalanceRemainingInHomeCurrency'] = htmlspecialchars(strip_tags($_SESSION['invoice']['BalanceRemainingInHomeCurrency']));
    $_SESSION['invoice']['Memo'] = htmlspecialchars(strip_tags($_SESSION['invoice']['Memo']));
    $_SESSION['invoice']['IsPaid'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsPaid']));
    $_SESSION['invoice']['CustomerMsgRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerMsgRef_ListID']));
    $_SESSION['invoice']['CustomerMsgRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerMsgRef_FullName']));
    $_SESSION['invoice']['IsToBePrinted'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsToBePrinted']));
    $_SESSION['invoice']['IsToBeEmailed'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsToBeEmailed']));
    $_SESSION['invoice']['IsTaxIncluded'] = htmlspecialchars(strip_tags($_SESSION['invoice']['IsTaxIncluded']));
    $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID']));
    $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName']));
    $_SESSION['invoice']['SuggestedDiscountAmount'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SuggestedDiscountAmount']));
    $_SESSION['invoice']['SuggestedDiscountDate'] = htmlspecialchars(strip_tags($_SESSION['invoice']['SuggestedDiscountDate']));
    $_SESSION['invoice']['Other'] = htmlspecialchars(strip_tags($_SESSION['invoice']['Other']));
    $_SESSION['invoice']['Status'] = htmlspecialchars(strip_tags($_SESSION['invoice']['Status']));
}

function genLimpia_invoice() {
    $_SESSION['invoice']['TxnID'] = ' ';
    $_SESSION['invoice']['TimeCreated'] = ' ';
    $_SESSION['invoice']['TimeModified'] = ' ';
    $_SESSION['invoice']['EditSequence'] = ' ';
    $_SESSION['invoice']['TxnNumber'] = ' ';
    $_SESSION['invoice']['CustomerRef_ListID'] = ' ';
    $_SESSION['invoice']['CustomerRef_FullName'] = ' ';
    $_SESSION['invoice']['ClassRef_ListID'] = ' ';
    $_SESSION['invoice']['ClassRef_FullName'] = ' ';
    $_SESSION['invoice']['ARAccountRef_ListID'] = ' ';
    $_SESSION['invoice']['ARAccountRef_FullName'] = ' ';
    $_SESSION['invoice']['TemplateRef_ListID'] = ' ';
    $_SESSION['invoice']['TemplateRef_FullName'] = ' ';
    $_SESSION['invoice']['TxnDate'] = ' ';
    $_SESSION['invoice']['RefNumber'] = ' ';
    $_SESSION['invoice']['BillAddress_Addr1'] = ' ';
    $_SESSION['invoice']['BillAddress_Addr2'] = ' ';
    $_SESSION['invoice']['BillAddress_Addr3'] = ' ';
    $_SESSION['invoice']['BillAddress_Addr4'] = ' ';
    $_SESSION['invoice']['BillAddress_Addr5'] = ' ';
    $_SESSION['invoice']['BillAddress_City'] = ' ';
    $_SESSION['invoice']['BillAddress_State'] = ' ';
    $_SESSION['invoice']['BillAddress_PostalCode'] = ' ';
    $_SESSION['invoice']['BillAddress_Country'] = ' ';
    $_SESSION['invoice']['BillAddress_Note'] = ' ';
    $_SESSION['invoice']['ShipAddress_Addr1'] = ' ';
    $_SESSION['invoice']['ShipAddress_Addr2'] = ' ';
    $_SESSION['invoice']['ShipAddress_Addr3'] = ' ';
    $_SESSION['invoice']['ShipAddress_Addr4'] = ' ';
    $_SESSION['invoice']['ShipAddress_Addr5'] = ' ';
    $_SESSION['invoice']['ShipAddress_City'] = ' ';
    $_SESSION['invoice']['ShipAddress_State'] = ' ';
    $_SESSION['invoice']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['invoice']['ShipAddress_Country'] = ' ';
    $_SESSION['invoice']['ShipAddress_Note'] = ' ';
    $_SESSION['invoice']['IsPending'] = ' ';
    $_SESSION['invoice']['IsFinanceCharge'] = ' ';
    $_SESSION['invoice']['PONumber'] = ' ';
    $_SESSION['invoice']['TermsRef_ListID'] = ' ';
    $_SESSION['invoice']['TermsRef_FullName'] = ' ';
    $_SESSION['invoice']['DueDate'] = ' ';
    $_SESSION['invoice']['SalesRepRef_ListID'] = ' ';
    $_SESSION['invoice']['SalesRepRef_FullName'] = ' ';
    $_SESSION['invoice']['FOB'] = ' ';
    $_SESSION['invoice']['ShipDate'] = ' ';
    $_SESSION['invoice']['ShipMethodRef_ListID'] = ' ';
    $_SESSION['invoice']['ShipMethodRef_FullName'] = ' ';
    $_SESSION['invoice']['Subtotal'] = ' ';
    $_SESSION['invoice']['ItemSalesTaxRef_ListID'] = ' ';
    $_SESSION['invoice']['ItemSalesTaxRef_FullName'] = ' ';
    $_SESSION['invoice']['SalesTaxPercentage'] = ' ';
    $_SESSION['invoice']['SalesTaxTotal'] = ' ';
    $_SESSION['invoice']['AppliedAmount'] = ' ';
    $_SESSION['invoice']['BalanceRemaining'] = ' ';
    $_SESSION['invoice']['CurrencyRef_ListID'] = ' ';
    $_SESSION['invoice']['CurrencyRef_FullName'] = ' ';
    $_SESSION['invoice']['ExchangeRate'] = ' ';
    $_SESSION['invoice']['BalanceRemainingInHomeCurrency'] = ' ';
    $_SESSION['invoice']['Memo'] = ' ';
    $_SESSION['invoice']['IsPaid'] = ' ';
    $_SESSION['invoice']['CustomerMsgRef_ListID'] = ' ';
    $_SESSION['invoice']['CustomerMsgRef_FullName'] = ' ';
    $_SESSION['invoice']['IsToBePrinted'] = ' ';
    $_SESSION['invoice']['IsToBeEmailed'] = ' ';
    $_SESSION['invoice']['IsTaxIncluded'] = ' ';
    $_SESSION['invoice']['CustomerSalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['invoice']['CustomerSalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['invoice']['SuggestedDiscountAmount'] = ' ';
    $_SESSION['invoice']['SuggestedDiscountDate'] = ' ';
    $_SESSION['invoice']['Other'] = ' ';
    $_SESSION['invoice']['CustomField1'] = ' ';
    $_SESSION['invoice']['CustomField2'] = ' ';
    $_SESSION['invoice']['CustomField3'] = ' ';
    $_SESSION['invoice']['CustomField4'] = ' ';
    $_SESSION['invoice']['CustomField5'] = ' ';
    $_SESSION['invoice']['CustomField6'] = ' ';
    $_SESSION['invoice']['CustomField7'] = ' ';
    $_SESSION['invoice']['CustomField8'] = ' ';
    $_SESSION['invoice']['CustomField9'] = ' ';
    $_SESSION['invoice']['CustomField10'] = ' ';
    $_SESSION['invoice']['CustomField11'] = ' ';
    $_SESSION['invoice']['CustomField12'] = ' ';
    $_SESSION['invoice']['CustomField13'] = ' ';
    $_SESSION['invoice']['CustomField14'] = ' ';
    $_SESSION['invoice']['CustomField15'] = ' ';
    $_SESSION['invoice']['Status'] = ' ';
}
