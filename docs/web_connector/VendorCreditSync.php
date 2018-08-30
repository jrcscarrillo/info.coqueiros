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
define('QB_PRIORITY_VENDORCREDIT', 1);
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
$fecha = date("Y-m-d", strtotime($registro['billCreditDesde']));
define("AU_DESDE", $fecha);
$fecha = date("Y-m-d", strtotime($registro['billCreditHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);
$db = null;
/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_VENDORCREDIT => array('_quickbooks_vendorcredit_import_request', '_quickbooks_vendorcredit_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_VENDORCREDIT)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_VENDORCREDIT, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_VENDORCREDIT, 1, QB_PRIORITY_VENDORCREDIT, NULL, $user);
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

function _quickbooks_vendorcredit_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_vendorcredit_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<VendorCreditQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <TxnDateRangeFilter>
                                <FromTxnDate >' . AU_DESDE . '</FromTxnDate>
                                <ToTxnDate >' . AU_HASTA . '</ToTxnDate>
                            </TxnDateRangeFilter>
                            <IncludeLineItems>true</IncludeLineItems>
                            <OwnerID>0</OwnerID>
			</VendorCreditQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_vendorcredit_initial_response() {
    
}

function _quickbooks_vendorcredit_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_VENDORCREDIT, null, QB_PRIORITY_VENDORCREDIT, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['vendorcredit'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("vendorcredit.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "VendorCreditRet";
    $credito = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($credito as $uno) {
        genLimpia_vendorcredit();
        gentraverse_vendorcredit($uno);
            $existe = buscaIgual_vendorcredit($db);
            $estado = 'INIT';
            $status = 'INIT';
            if ($existe == "OK") {
                quitaslashes_vendorcredit();
                $estado = adiciona_vendorcredit($db);
            } elseif ($existe == "ACTUALIZA") {
                if ($_SESSION['vendorcredit']['Memo'] === 'VOID:') {
                    $estado = delete_vendorcredit($db);
                } else {
                    quitaslashes_vendorcredit();
                    $estado = actualiza_vendorcredit($db);
                    $status = delete_vendorcreditdetail($db);
                }
            }
            fwrite($myfile, "INICIO " . $estado . " " . $status . " " . $existe . "\r\n");

            $detalle = $credito->item($k)->getElementsByTagName('ItemLineRet');
            foreach ($detalle as $uno) {
                genLimpia_vendorcreditdetail();
                gentraverse_vendorcreditdetail($uno);
                $estado = adiciona_vendorcreditdetail($db);
                fwrite($myfile, " " . $estado . "\r\n");
            }
        $k++;
    }
    fclose($myfile);
    fclose($myfile1);
    $db = null;
    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_VENDORCREDIT) {
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

function delete_vendorcredit($db) {
    $estado = 'INIT';
    try {
        $sql = 'DELETE FROM vendorcredit WHERE TxnID = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['vendorcredit']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function delete_vendorcreditdetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'DELETE FROM txnitemlinedetail WHERE IDKEY = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['vendorcredit']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function adiciona_vendorcreditdetail($db) {
    $estado = "INIT";
    try {
        $sql = 'INSERT INTO txnitemlinedetail (  TxnLineID, ItemRef_ListID, ItemRef_FullName, '
           . 'InventorySiteRef_ListID, InventorySiteRef_FullName, InventorySiteLocationRef_ListID, '
           . 'InventorySiteLocationRef_FullName, SerialNumber, LotNumber, Description, Quantity, '
           . 'UnitOfMeasure, OverrideUOMSetRef_ListID, OverrideUOMSetRef_FullName, Cost, Amount, '
           . 'CustomerRef_ListID, CustomerRef_FullName, ClassRef_ListID, ClassRef_FullName, '
           . 'SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, BillableStatus, LinkedTxnID, '
           . 'LinkedTxnLineID, CustomField1, CustomField2, CustomField3, CustomField4, '
           . 'CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, '
           . 'CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, IDKEY, GroupIDKEY) '
           . 'VALUES ( :TxnLineID, :ItemRef_ListID, :ItemRef_FullName, :InventorySiteRef_ListID, '
           . ':InventorySiteRef_FullName, :InventorySiteLocationRef_ListID, :InventorySiteLocationRef_FullName, '
           . ':SerialNumber, :LotNumber, :Description, :Quantity, :UnitOfMeasure, :OverrideUOMSetRef_ListID, '
           . ':OverrideUOMSetRef_FullName, :Cost, :Amount, :CustomerRef_ListID, :CustomerRef_FullName, '
           . ':ClassRef_ListID, :ClassRef_FullName, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, '
           . ':BillableStatus, :LinkedTxnID, :LinkedTxnLineID, :CustomField1, :CustomField2, :CustomField3, '
           . ':CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, '
           . ':CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY, :GroupIDKEY)';
        $stmt = $db->prepare($sql);
	 $stmt->bindParam(':TxnLineID', $_SESSION['vendorcreditdetail']['TxnLineID'] );
	$stmt->bindParam(':ItemRef_ListID', $_SESSION['vendorcreditdetail']['ItemRef_ListID'] );
	$stmt->bindParam(':ItemRef_FullName', $_SESSION['vendorcreditdetail']['ItemRef_FullName'] );
	$stmt->bindParam(':InventorySiteRef_ListID', $_SESSION['vendorcreditdetail']['InventorySiteRef_ListID'] );
	$stmt->bindParam(':InventorySiteRef_FullName', $_SESSION['vendorcreditdetail']['InventorySiteRef_FullName'] );
	$stmt->bindParam(':InventorySiteLocationRef_ListID', $_SESSION['vendorcreditdetail']['InventorySiteLocationRef_ListID'] );
	$stmt->bindParam(':InventorySiteLocationRef_FullName', $_SESSION['vendorcreditdetail']['InventorySiteLocationRef_FullName'] );
	$stmt->bindParam(':SerialNumber', $_SESSION['vendorcreditdetail']['SerialNumber'] );
	$stmt->bindParam(':LotNumber', $_SESSION['vendorcreditdetail']['LotNumber'] );
	$stmt->bindParam(':Description', $_SESSION['vendorcreditdetail']['Description'] );
	$stmt->bindParam(':Quantity', $_SESSION['vendorcreditdetail']['Quantity'] );
	$stmt->bindParam(':UnitOfMeasure', $_SESSION['vendorcreditdetail']['UnitOfMeasure'] );
	$stmt->bindParam(':OverrideUOMSetRef_ListID', $_SESSION['vendorcreditdetail']['OverrideUOMSetRef_ListID'] );
	$stmt->bindParam(':OverrideUOMSetRef_FullName', $_SESSION['vendorcreditdetail']['OverrideUOMSetRef_FullName'] );
	$stmt->bindParam(':Cost', $_SESSION['vendorcreditdetail']['Cost'] );
	$stmt->bindParam(':Amount', $_SESSION['vendorcreditdetail']['Amount'] );
	$stmt->bindParam(':CustomerRef_ListID', $_SESSION['vendorcreditdetail']['CustomerRef_ListID'] );
	$stmt->bindParam(':CustomerRef_FullName', $_SESSION['vendorcreditdetail']['CustomerRef_FullName'] );
	$stmt->bindParam(':ClassRef_ListID', $_SESSION['vendorcreditdetail']['ClassRef_ListID'] );
	$stmt->bindParam(':ClassRef_FullName', $_SESSION['vendorcreditdetail']['ClassRef_FullName'] );
	$stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['vendorcreditdetail']['SalesTaxCodeRef_ListID'] );
	$stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['vendorcreditdetail']['SalesTaxCodeRef_FullName'] );
	$stmt->bindParam(':BillableStatus', $_SESSION['vendorcreditdetail']['BillableStatus'] );
	$stmt->bindParam(':LinkedTxnID', $_SESSION['vendorcreditdetail']['LinkedTxnID'] );
	$stmt->bindParam(':LinkedTxnLineID', $_SESSION['vendorcreditdetail']['LinkedTxnLineID'] );
	$stmt->bindParam(':CustomField1', $_SESSION['vendorcreditdetail']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['vendorcreditdetail']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['vendorcreditdetail']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['vendorcreditdetail']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['vendorcreditdetail']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['vendorcreditdetail']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['vendorcreditdetail']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['vendorcreditdetail']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['vendorcreditdetail']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['vendorcreditdetail']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['vendorcreditdetail']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['vendorcreditdetail']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['vendorcreditdetail']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['vendorcreditdetail']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['vendorcreditdetail']['CustomField15'] );
	$stmt->bindParam(':IDKEY', $_SESSION['vendorcreditdetail']['IDKEY'] );
	$stmt->bindParam(':GroupIDKEY', $_SESSION['vendorcreditdetail']['GroupIDKEY'] );
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['vendorcreditdetail']['TxnLineID'];
    }
    return $estado;
}

function genLimpia_vendorcreditdetail() {
$_SESSION['vendorcreditdetail']['TxnLineID'] = ' ';
$_SESSION['vendorcreditdetail']['ItemRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['ItemRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['InventorySiteRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['InventorySiteRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['InventorySiteLocationRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['InventorySiteLocationRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['SerialNumber'] = ' ';
$_SESSION['vendorcreditdetail']['LotNumber'] = ' ';
$_SESSION['vendorcreditdetail']['Description'] = ' ';
$_SESSION['vendorcreditdetail']['Quantity'] = 0;
$_SESSION['vendorcreditdetail']['UnitOfMeasure'] = ' ';
$_SESSION['vendorcreditdetail']['OverrideUOMSetRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['OverrideUOMSetRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['Cost'] = 0;
$_SESSION['vendorcreditdetail']['Amount'] = 0;
$_SESSION['vendorcreditdetail']['CustomerRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['CustomerRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['ClassRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['ClassRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['SalesTaxCodeRef_ListID'] = ' ';
$_SESSION['vendorcreditdetail']['SalesTaxCodeRef_FullName'] = ' ';
$_SESSION['vendorcreditdetail']['BillableStatus'] = ' ';
$_SESSION['vendorcreditdetail']['LinkedTxnID'] = ' ';
$_SESSION['vendorcreditdetail']['LinkedTxnLineID'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField1'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField2'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField3'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField4'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField5'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField6'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField7'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField8'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField9'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField10'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField11'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField12'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField13'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField14'] = ' ';
$_SESSION['vendorcreditdetail']['CustomField15'] = ' ';
$_SESSION['vendorcreditdetail']['IDKEY'] = ' ';
$_SESSION['vendorcreditdetail']['GroupIDKEY'] = ' ';
}

function gentraverse_vendorcreditdetail($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnLineID':
                    $_SESSION['vendorcreditdetail']['TxnLineID'] = $nivel1->nodeValue;
                    $_SESSION['vendorcreditdetail']['IDKEY'] = $_SESSION['vendorcredit']['TxnID'];
                    break;
                case 'Desc':
                    $_SESSION['vendorcreditdetail']['Description'] = $nivel1->nodeValue;
                    break;
                case 'Quantity':
                    $_SESSION['vendorcreditdetail']['Quantity'] = $nivel1->nodeValue;
                    break;
                case 'UnitOfMeasure':
                    $_SESSION['vendorcreditdetail']['UnitOfMeasure'] = $nivel1->nodeValue;
                    break;
                case 'Cost':
                    $_SESSION['vendorcreditdetail']['Cost'] = $nivel1->nodeValue;
                    break;
                case 'Amount':
                    $_SESSION['vendorcreditdetail']['Amount'] = $nivel1->nodeValue;
                    break;
                case 'SerialNumber':
                    $_SESSION['vendorcreditdetail']['SerialNumber'] = $nivel1->nodeValue;
                    break;
                case 'LotNumber':
                    $_SESSION['vendorcreditdetail']['LotNumber'] = $nivel1->nodeValue;
                    break;
                case 'ServiceDate':
                    $_SESSION['vendorcreditdetail']['ServiceDate'] = $nivel1->nodeValue;
                    break;

                case 'ItemRef':
                case 'ClassRef':
                case 'CustomerRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ItemRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendorcreditdetail']['ItemRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendorcreditdetail']['ItemRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendorcreditdetail']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendorcreditdetail']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CustomerRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendorcreditdetail']['CustomerRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendorcreditdetail']['CustomerRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
            }
        }
    }
}

function actualiza_vendorcredit($db) {
    $estado = 'INIT';

    try {
	$sql = 'UPDATE vendorcredit SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, '
           . 'EditSequence=:EditSequence, TxnNumber=:TxnNumber, VendorRef_ListID=:VendorRef_ListID, '
           . 'VendorRef_FullName=:VendorRef_FullName, APAccountRef_ListID=:APAccountRef_ListID, '
           . 'APAccountRef_FullName=:APAccountRef_FullName, TxnDate=:TxnDate, CreditAmount=:CreditAmount, '
           . 'CurrencyRef_ListID=:CurrencyRef_ListID, CurrencyRef_FullName=:CurrencyRef_FullName, '
           . 'ExchangeRate=:ExchangeRate, CreditAmountInHomeCurrency=:CreditAmountInHomeCurrency, '
           . 'RefNumber=:RefNumber, Memo=:Memo, OpenAmount=:OpenAmount, CustomField1=:CustomField1, '
           . 'CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, '
           . 'CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, '
           . 'CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, '
           . 'CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, '
           . 'CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status, WHERE TxnID = :clave;'; 
        $stmt = $db->prepare($sql);
	 $stmt->bindParam(':TxnID', $_SESSION['vendorcredit']['TxnID'] );
	$stmt->bindParam(':TimeCreated', $_SESSION['vendorcredit']['TimeCreated'] );
	$stmt->bindParam(':TimeModified', $_SESSION['vendorcredit']['TimeModified'] );
	$stmt->bindParam(':EditSequence', $_SESSION['vendorcredit']['EditSequence'] );
	$stmt->bindParam(':TxnNumber', $_SESSION['vendorcredit']['TxnNumber'] );
	$stmt->bindParam(':VendorRef_ListID', $_SESSION['vendorcredit']['VendorRef_ListID'] );
	$stmt->bindParam(':VendorRef_FullName', $_SESSION['vendorcredit']['VendorRef_FullName'] );
	$stmt->bindParam(':APAccountRef_ListID', $_SESSION['vendorcredit']['APAccountRef_ListID'] );
	$stmt->bindParam(':APAccountRef_FullName', $_SESSION['vendorcredit']['APAccountRef_FullName'] );
	$stmt->bindParam(':TxnDate', $_SESSION['vendorcredit']['TxnDate'] );
	$stmt->bindParam(':CreditAmount', $_SESSION['vendorcredit']['CreditAmount'] );
	$stmt->bindParam(':CurrencyRef_ListID', $_SESSION['vendorcredit']['CurrencyRef_ListID'] );
	$stmt->bindParam(':CurrencyRef_FullName', $_SESSION['vendorcredit']['CurrencyRef_FullName'] );
	$stmt->bindParam(':ExchangeRate', $_SESSION['vendorcredit']['ExchangeRate'] );
	$stmt->bindParam(':CreditAmountInHomeCurrency', $_SESSION['vendorcredit']['CreditAmountInHomeCurrency'] );
	$stmt->bindParam(':RefNumber', $_SESSION['vendorcredit']['RefNumber'] );
	$stmt->bindParam(':Memo', $_SESSION['vendorcredit']['Memo'] );
	$stmt->bindParam(':OpenAmount', $_SESSION['vendorcredit']['OpenAmount'] );
	$stmt->bindParam(':CustomField1', $_SESSION['vendorcredit']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['vendorcredit']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['vendorcredit']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['vendorcredit']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['vendorcredit']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['vendorcredit']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['vendorcredit']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['vendorcredit']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['vendorcredit']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['vendorcredit']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['vendorcredit']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['vendorcredit']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['vendorcredit']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['vendorcredit']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['vendorcredit']['CustomField15'] );
	$stmt->bindParam(':Status', $_SESSION['vendorcredit']['Status'] );
	$stmt->bindParam(':clave', $_SESSION['invoice']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['vendorcredit']['TxnID'] . ' campo ' . $_SESSION['vendorcredit']['IsPaid'] . '<br>';
    }
    return $estado;
}

function adiciona_vendorcredit($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO vendorcredit (  TxnID, TimeCreated, TimeModified, EditSequence, TxnNumber, '
           . 'VendorRef_ListID, VendorRef_FullName, APAccountRef_ListID, APAccountRef_FullName, TxnDate, '
           . 'CreditAmount, CurrencyRef_ListID, CurrencyRef_FullName, ExchangeRate, CreditAmountInHomeCurrency, '
           . 'RefNumber, Memo, OpenAmount, CustomField1, CustomField2, CustomField3, CustomField4, '
           . 'CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, '
           . 'CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) '
           . 'VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnNumber, :VendorRef_ListID, '
           . ':VendorRef_FullName, :APAccountRef_ListID, :APAccountRef_FullName, :TxnDate, :CreditAmount, '
           . ':CurrencyRef_ListID, :CurrencyRef_FullName, :ExchangeRate, :CreditAmountInHomeCurrency, '
           . ':RefNumber, :Memo, :OpenAmount, :CustomField1, :CustomField2, :CustomField3, :CustomField4, '
           . ':CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, '
           . ':CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
        $stmt = $db->prepare($sql);
	 $stmt->bindParam(':TxnID', $_SESSION['vendorcredit']['TxnID'] );
	$stmt->bindParam(':TimeCreated', $_SESSION['vendorcredit']['TimeCreated'] );
	$stmt->bindParam(':TimeModified', $_SESSION['vendorcredit']['TimeModified'] );
	$stmt->bindParam(':EditSequence', $_SESSION['vendorcredit']['EditSequence'] );
	$stmt->bindParam(':TxnNumber', $_SESSION['vendorcredit']['TxnNumber'] );
	$stmt->bindParam(':VendorRef_ListID', $_SESSION['vendorcredit']['VendorRef_ListID'] );
	$stmt->bindParam(':VendorRef_FullName', $_SESSION['vendorcredit']['VendorRef_FullName'] );
	$stmt->bindParam(':APAccountRef_ListID', $_SESSION['vendorcredit']['APAccountRef_ListID'] );
	$stmt->bindParam(':APAccountRef_FullName', $_SESSION['vendorcredit']['APAccountRef_FullName'] );
	$stmt->bindParam(':TxnDate', $_SESSION['vendorcredit']['TxnDate'] );
	$stmt->bindParam(':CreditAmount', $_SESSION['vendorcredit']['CreditAmount'] );
	$stmt->bindParam(':CurrencyRef_ListID', $_SESSION['vendorcredit']['CurrencyRef_ListID'] );
	$stmt->bindParam(':CurrencyRef_FullName', $_SESSION['vendorcredit']['CurrencyRef_FullName'] );
	$stmt->bindParam(':ExchangeRate', $_SESSION['vendorcredit']['ExchangeRate'] );
	$stmt->bindParam(':CreditAmountInHomeCurrency', $_SESSION['vendorcredit']['CreditAmountInHomeCurrency'] );
	$stmt->bindParam(':RefNumber', $_SESSION['vendorcredit']['RefNumber'] );
	$stmt->bindParam(':Memo', $_SESSION['vendorcredit']['Memo'] );
	$stmt->bindParam(':OpenAmount', $_SESSION['vendorcredit']['OpenAmount'] );
	$stmt->bindParam(':CustomField1', $_SESSION['vendorcredit']['CustomField1'] );
	$stmt->bindParam(':CustomField2', $_SESSION['vendorcredit']['CustomField2'] );
	$stmt->bindParam(':CustomField3', $_SESSION['vendorcredit']['CustomField3'] );
	$stmt->bindParam(':CustomField4', $_SESSION['vendorcredit']['CustomField4'] );
	$stmt->bindParam(':CustomField5', $_SESSION['vendorcredit']['CustomField5'] );
	$stmt->bindParam(':CustomField6', $_SESSION['vendorcredit']['CustomField6'] );
	$stmt->bindParam(':CustomField7', $_SESSION['vendorcredit']['CustomField7'] );
	$stmt->bindParam(':CustomField8', $_SESSION['vendorcredit']['CustomField8'] );
	$stmt->bindParam(':CustomField9', $_SESSION['vendorcredit']['CustomField9'] );
	$stmt->bindParam(':CustomField10', $_SESSION['vendorcredit']['CustomField10'] );
	$stmt->bindParam(':CustomField11', $_SESSION['vendorcredit']['CustomField11'] );
	$stmt->bindParam(':CustomField12', $_SESSION['vendorcredit']['CustomField12'] );
	$stmt->bindParam(':CustomField13', $_SESSION['vendorcredit']['CustomField13'] );
	$stmt->bindParam(':CustomField14', $_SESSION['vendorcredit']['CustomField14'] );
	$stmt->bindParam(':CustomField15', $_SESSION['vendorcredit']['CustomField15'] );
	$stmt->bindParam(':Status', $_SESSION['vendorcredit']['Status'] );
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['vendorcredit']['TxnID'] . ' campo ' . $_SESSION['vendorcredit']['IsPaid'] . '<br>';
    }
    return $estado;
}

function buscaIgual_vendorcredit($db) {
    $estado = "ERR";
    try {
        $sql = "SELECT * FROM vendorcredit WHERE TxnID = :clave ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['vendorcredit']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = "OK";
        } else {
            if ($registro['TxnID'] === $_SESSION['vendorcredit']['TxnID']) {
                $estado = "ACTUALIZA";
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_vendorcredit($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['vendorcredit']['TxnID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['vendorcredit']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['vendorcredit']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['vendorcredit']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'TxnNumber':
                    $_SESSION['vendorcredit']['TxnNumber'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['vendorcredit']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['vendorcredit']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'CreditAmount':
                    $_SESSION['vendorcredit']['CreditAmount'] = $nivel1->nodeValue;
                    break;
                case 'ExchangeRate':
                    $_SESSION['vendorcredit']['ExchangeRate'] = $nivel1->nodeValue;
                    break;
                case 'CreditRemainingInHomeCurrency':
                    $_SESSION['vendorcredit']['BalanceRemainingInHomeCurrency'] = $nivel1->nodeValue;
                    break;
                case 'Memo':
                    $_SESSION['vendorcredit']['Memo'] = $nivel1->nodeValue;
                    break;
                case 'Other':
                    $_SESSION['vendorcredit']['Other'] = $nivel1->nodeValue;
                    break;
                case 'VendorRef':
                case 'CurrencyRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'VendorRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendorcredit']['VendorRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendorcredit']['VendorRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CurrencyRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['vendorcredit']['CurrencyRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['vendorcredit']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function quitaslashes_vendorcreditdetail() {
    $_SESSION['vendorcreditdetail']['TxnLineID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['TxnLineID']));
    $_SESSION['vendorcreditdetail']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['ItemRef_ListID']));
    $_SESSION['vendorcreditdetail']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['ItemRef_FullName']));
    $_SESSION['vendorcreditdetail']['Description'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Description']));
    $_SESSION['vendorcreditdetail']['Quantity'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Quantity']));
    $_SESSION['vendorcreditdetail']['UnitOfMeasure'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['UnitOfMeasure']));
    $_SESSION['vendorcreditdetail']['OverrideUOMSetRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['OverrideUOMSetRef_ListID']));
    $_SESSION['vendorcreditdetail']['OverrideUOMSetRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['OverrideUOMSetRef_FullName']));
    $_SESSION['vendorcreditdetail']['Rate'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Rate']));
    $_SESSION['vendorcreditdetail']['RatePercent'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['RatePercent']));
    $_SESSION['vendorcreditdetail']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['ClassRef_ListID']));
    $_SESSION['vendorcreditdetail']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['ClassRef_FullName']));
    $_SESSION['vendorcreditdetail']['Amount'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Amount']));
    $_SESSION['vendorcreditdetail']['InventorySiteRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['InventorySiteRef_ListID']));
    $_SESSION['vendorcreditdetail']['InventorySiteRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['InventorySiteRef_FullName']));
    $_SESSION['vendorcreditdetail']['SerialNumber'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['SerialNumber']));
    $_SESSION['vendorcreditdetail']['LotNumber'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['LotNumber']));
    $_SESSION['vendorcreditdetail']['ServiceDate'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['ServiceDate']));
    $_SESSION['vendorcreditdetail']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['SalesTaxCodeRef_ListID']));
    $_SESSION['vendorcreditdetail']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['SalesTaxCodeRef_FullName']));
    $_SESSION['vendorcreditdetail']['Other1'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Other1']));
    $_SESSION['vendorcreditdetail']['Other2'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['Other2']));
    $_SESSION['vendorcreditdetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField1']));
    $_SESSION['vendorcreditdetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField2']));
    $_SESSION['vendorcreditdetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField3']));
    $_SESSION['vendorcreditdetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField4']));
    $_SESSION['vendorcreditdetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField5']));
    $_SESSION['vendorcreditdetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField6']));
    $_SESSION['vendorcreditdetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField7']));
    $_SESSION['vendorcreditdetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField8']));
    $_SESSION['vendorcreditdetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField9']));
    $_SESSION['vendorcreditdetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField10']));
    $_SESSION['vendorcreditdetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField11']));
    $_SESSION['vendorcreditdetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField12']));
    $_SESSION['vendorcreditdetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField13']));
    $_SESSION['vendorcreditdetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField14']));
    $_SESSION['vendorcreditdetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['CustomField15']));
    $_SESSION['vendorcreditdetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['vendorcreditdetail']['IDKEY']));
}

function quitaslashes_vendorcredit() {
    $_SESSION['vendorcredit']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['TxnID']));
    $_SESSION['vendorcredit']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['vendorcredit']['TimeCreated']));
    $_SESSION['vendorcredit']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['vendorcredit']['TimeModified']));
$_SESSION['vendorcredit']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['EditSequence']));
$_SESSION['vendorcredit']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['TxnNumber']));
$_SESSION['vendorcredit']['VendorRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['VendorRef_ListID']));
$_SESSION['vendorcredit']['VendorRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['VendorRef_FullName']));
$_SESSION['vendorcredit']['APAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['APAccountRef_ListID']));
$_SESSION['vendorcredit']['APAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['APAccountRef_FullName']));
$_SESSION['vendorcredit']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['TxnDate']));
$_SESSION['vendorcredit']['CreditAmount'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CreditAmount']));
$_SESSION['vendorcredit']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CurrencyRef_ListID']));
$_SESSION['vendorcredit']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CurrencyRef_FullName']));
$_SESSION['vendorcredit']['ExchangeRate'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['ExchangeRate']));
$_SESSION['vendorcredit']['CreditAmountInHomeCurrency'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CreditAmountInHomeCurrency']));
$_SESSION['vendorcredit']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['RefNumber']));
$_SESSION['vendorcredit']['Memo'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['Memo']));
$_SESSION['vendorcredit']['OpenAmount'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['OpenAmount']));
$_SESSION['vendorcredit']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField1']));
$_SESSION['vendorcredit']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField2']));
$_SESSION['vendorcredit']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField3']));
$_SESSION['vendorcredit']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField4']));
$_SESSION['vendorcredit']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField5']));
$_SESSION['vendorcredit']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField6']));
$_SESSION['vendorcredit']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField7']));
$_SESSION['vendorcredit']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField8']));
$_SESSION['vendorcredit']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField9']));
$_SESSION['vendorcredit']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField10']));
$_SESSION['vendorcredit']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField11']));
$_SESSION['vendorcredit']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField12']));
$_SESSION['vendorcredit']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField13']));
$_SESSION['vendorcredit']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField14']));
$_SESSION['vendorcredit']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['CustomField15']));
$_SESSION['vendorcredit']['Status'] = htmlspecialchars(strip_tags($_SESSION['vendorcredit']['Status']));
}

function genLimpia_vendorcredit() {
$_SESSION['vendorcredit']['TxnID'] = ' ';
$_SESSION['vendorcredit']['TimeCreated'] = ' ';
$_SESSION['vendorcredit']['TimeModified'] = ' ';
$_SESSION['vendorcredit']['EditSequence'] = ' ';
$_SESSION['vendorcredit']['TxnNumber'] = ' ';
$_SESSION['vendorcredit']['VendorRef_ListID'] = ' ';
$_SESSION['vendorcredit']['VendorRef_FullName'] = ' ';
$_SESSION['vendorcredit']['APAccountRef_ListID'] = ' ';
$_SESSION['vendorcredit']['APAccountRef_FullName'] = ' ';
$_SESSION['vendorcredit']['TxnDate'] = ' ';
$_SESSION['vendorcredit']['CreditAmount'] = 0;
$_SESSION['vendorcredit']['CurrencyRef_ListID'] = ' ';
$_SESSION['vendorcredit']['CurrencyRef_FullName'] = ' ';
$_SESSION['vendorcredit']['ExchangeRate'] = 0;
$_SESSION['vendorcredit']['CreditAmountInHomeCurrency'] = 0;
$_SESSION['vendorcredit']['RefNumber'] = ' ';
$_SESSION['vendorcredit']['Memo'] = ' ';
$_SESSION['vendorcredit']['OpenAmount'] = 0;
$_SESSION['vendorcredit']['CustomField1'] = ' ';
$_SESSION['vendorcredit']['CustomField2'] = ' ';
$_SESSION['vendorcredit']['CustomField3'] = ' ';
$_SESSION['vendorcredit']['CustomField4'] = ' ';
$_SESSION['vendorcredit']['CustomField5'] = ' ';
$_SESSION['vendorcredit']['CustomField6'] = ' ';
$_SESSION['vendorcredit']['CustomField7'] = ' ';
$_SESSION['vendorcredit']['CustomField8'] = ' ';
$_SESSION['vendorcredit']['CustomField9'] = ' ';
$_SESSION['vendorcredit']['CustomField10'] = ' ';
$_SESSION['vendorcredit']['CustomField11'] = ' ';
$_SESSION['vendorcredit']['CustomField12'] = ' ';
$_SESSION['vendorcredit']['CustomField13'] = ' ';
$_SESSION['vendorcredit']['CustomField14'] = ' ';
$_SESSION['vendorcredit']['CustomField15'] = ' ';
$_SESSION['vendorcredit']['Status'] = ' ';
}
