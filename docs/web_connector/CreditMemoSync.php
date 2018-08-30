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
require_once 'funciones.php';
$user = 'jrcscarrillo';
$pass = 'f9234568';
define('QB_QUICKBOOKS_CONFIG_LAST', 'last');
define('QB_QUICKBOOKS_CONFIG_CURR', 'curr');
define('QB_QUICKBOOKS_MAX_RETURNED', 1725);
define('QB_PRIORITY_CREDITMEMO', 1);
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
$fecha = date("Y-m-d", strtotime($registro['creditMemoDesde']));
define("AU_DESDE", $fecha);
$fecha = date("Y-m-d", strtotime($registro['creditMemoHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);
$db = null;
/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_CREDITMEMO => array('_quickbooks_creditmemo_import_request', '_quickbooks_creditmemo_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CREDITMEMO)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CREDITMEMO, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_CREDITMEMO, 1, QB_PRIORITY_CREDITMEMO, NULL, $user);
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

function _quickbooks_creditmemo_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_creditmemo_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<CreditMemoQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <TxnDateRangeFilter>
                                <FromTxnDate >' . AU_DESDE . '</FromTxnDate>
                                <ToTxnDate >' . AU_HASTA . '</ToTxnDate>
                            </TxnDateRangeFilter>
                            <IncludeLineItems>true</IncludeLineItems>
                            <OwnerID>0</OwnerID>
			</CreditMemoQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_creditmemo_initial_response() {
    
}

function _quickbooks_creditmemo_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_CREDITMEMO, null, QB_PRIORITY_CREDITMEMO, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['creditmemo'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("creditmemo.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "CreditMemoRet";
    $credito = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($credito as $uno) {
        genLimpia_creditmemo();
        gentraverse_creditmemo($uno);

        if ($_SESSION['creditmemo']['TemplateRef_FullName'] === "NOTA DE CREDITO") {
            $existe = buscaIgual_creditmemo($db);
            $estado = 'INIT';
            $status = 'INIT';
            if ($existe == "OK") {
                quitaslashes_creditmemo();
                $estado = adiciona_creditmemo($db);
            } elseif ($existe == "ACTUALIZA") {
                if ($_SESSION['creditmemo']['Memo'] === 'VOID:') {
                    $estado = delete_creditmemo($db);
                } else {
                    quitaslashes_creditmemo();
                    $estado = actualiza_creditmemo($db);
                    $status = delete_creditmemodetail($db);
                }
            }
            fwrite($myfile, "INICIO " . $estado . " " . $status . " " . $existe . "\r\n");

            $detalle = $credito->item($k)->getElementsByTagName('CreditMemoLineRet');
            foreach ($detalle as $uno) {
                genLimpia_creditmemodetail();
                gentraverse_creditmemodetail($uno);
                $estado = adiciona_creditmemodetail($db);
                fwrite($myfile, " " . $estado . "\r\n");
            }
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
    if ($action == QUICKBOOKS_IMPORT_CREDITMEMO) {
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

function delete_creditmemo($db) {
    $estado = 'INIT';
    try {
        $sql = 'DELETE FROM creditmemo WHERE TxnID = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['creditmemo']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function delete_creditmemodetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'DELETE FROM creditmemolinedetail WHERE IDKEY = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['creditmemo']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function adiciona_creditmemodetail($db) {
    $estado = "INIT";
    try {
        $sql = 'INSERT INTO creditmemolinedetail (  TxnLineID, ItemRef_ListID, ItemRef_FullName, Description, '
           . 'Quantity, UnitOfMeasure, OverrideUOMSetRef_ListID, OverrideUOMSetRef_FullName, Rate, RatePercent, '
           . 'ClassRef_ListID, ClassRef_FullName, Amount, InventorySiteRef_ListID, InventorySiteRef_FullName, '
           . 'SerialNumber, LotNumber, ServiceDate, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, '
           . 'Other1, Other2, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, '
           . 'CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, '
           . 'CustomField13, CustomField14, CustomField15, IDKEY) VALUES ( :TxnLineID, :ItemRef_ListID, :ItemRef_FullName, '
           . ':Description, :Quantity, :UnitOfMeasure, :OverrideUOMSetRef_ListID, :OverrideUOMSetRef_FullName, '
           . ':Rate, :RatePercent, :ClassRef_ListID, :ClassRef_FullName, :Amount, :InventorySiteRef_ListID, '
           . ':InventorySiteRef_FullName, :SerialNumber, :LotNumber, :ServiceDate, :SalesTaxCodeRef_ListID, '
           . ':SalesTaxCodeRef_FullName, :Other1, :Other2, :CustomField1, :CustomField2, :CustomField3, '
           . ':CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, '
           . ':CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnLineID', $_SESSION['creditmemodetail']['TxnLineID']);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['creditmemodetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['creditmemodetail']['ItemRef_FullName']);
        $stmt->bindParam(':Description', $_SESSION['creditmemodetail']['Description']);
        $stmt->bindParam(':Quantity', $_SESSION['creditmemodetail']['Quantity']);
        $stmt->bindParam(':UnitOfMeasure', $_SESSION['creditmemodetail']['UnitOfMeasure']);
        $stmt->bindParam(':OverrideUOMSetRef_ListID', $_SESSION['creditmemodetail']['OverrideUOMSetRef_ListID']);
        $stmt->bindParam(':OverrideUOMSetRef_FullName', $_SESSION['creditmemodetail']['OverrideUOMSetRef_FullName']);
        $stmt->bindParam(':Rate', $_SESSION['creditmemodetail']['Rate']);
        $stmt->bindParam(':RatePercent', $_SESSION['creditmemodetail']['RatePercent']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['creditmemodetail']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['creditmemodetail']['ClassRef_FullName']);
        $stmt->bindParam(':Amount', $_SESSION['creditmemodetail']['Amount']);
        $stmt->bindParam(':InventorySiteRef_ListID', $_SESSION['creditmemodetail']['InventorySiteRef_ListID']);
        $stmt->bindParam(':InventorySiteRef_FullName', $_SESSION['creditmemodetail']['InventorySiteRef_FullName']);
        $stmt->bindParam(':SerialNumber', $_SESSION['creditmemodetail']['SerialNumber']);
        $stmt->bindParam(':LotNumber', $_SESSION['creditmemodetail']['LotNumber']);
        $stmt->bindParam(':ServiceDate', $_SESSION['creditmemodetail']['ServiceDate']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['creditmemodetail']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['creditmemodetail']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other1', $_SESSION['creditmemodetail']['Other1']);
        $stmt->bindParam(':Other2', $_SESSION['creditmemodetail']['Other2']);
        $stmt->bindParam(':CustomField1', $_SESSION['creditmemodetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['creditmemodetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['creditmemodetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['creditmemodetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['creditmemodetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['creditmemodetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['creditmemodetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['creditmemodetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['creditmemodetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['creditmemodetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['creditmemodetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['creditmemodetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['creditmemodetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['creditmemodetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['creditmemodetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['creditmemodetail']['IDKEY']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['creditmemodetail']['TxnLineID'];
    }
    return $estado;
}

function genLimpia_creditmemodetail() {
    $_SESSION['creditmemodetail']['TxnLineID'] = ' ';
    $_SESSION['creditmemodetail']['ItemRef_ListID'] = ' ';
    $_SESSION['creditmemodetail']['ItemRef_FullName'] = ' ';
    $_SESSION['creditmemodetail']['Description'] = ' ';
    $_SESSION['creditmemodetail']['Quantity'] = 0;
    $_SESSION['creditmemodetail']['UnitOfMeasure'] = ' ';
    $_SESSION['creditmemodetail']['OverrideUOMSetRef_ListID'] = ' ';
    $_SESSION['creditmemodetail']['OverrideUOMSetRef_FullName'] = ' ';
    $_SESSION['creditmemodetail']['Rate'] = 0;
    $_SESSION['creditmemodetail']['RatePercent'] = 0;
    $_SESSION['creditmemodetail']['ClassRef_ListID'] = ' ';
    $_SESSION['creditmemodetail']['ClassRef_FullName'] = ' ';
    $_SESSION['creditmemodetail']['Amount'] = 0;
    $_SESSION['creditmemodetail']['InventorySiteRef_ListID'] = ' ';
    $_SESSION['creditmemodetail']['InventorySiteRef_FullName'] = ' ';
    $_SESSION['creditmemodetail']['SerialNumber'] = ' ';
    $_SESSION['creditmemodetail']['LotNumber'] = ' ';
    $_SESSION['creditmemodetail']['ServiceDate'] = '2018-01-01 ';
    $_SESSION['creditmemodetail']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['creditmemodetail']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['creditmemodetail']['Other1'] = ' ';
    $_SESSION['creditmemodetail']['Other2'] = ' ';
    $_SESSION['creditmemodetail']['CustomField1'] = ' ';
    $_SESSION['creditmemodetail']['CustomField2'] = ' ';
    $_SESSION['creditmemodetail']['CustomField3'] = ' ';
    $_SESSION['creditmemodetail']['CustomField4'] = ' ';
    $_SESSION['creditmemodetail']['CustomField5'] = ' ';
    $_SESSION['creditmemodetail']['CustomField6'] = ' ';
    $_SESSION['creditmemodetail']['CustomField7'] = ' ';
    $_SESSION['creditmemodetail']['CustomField8'] = ' ';
    $_SESSION['creditmemodetail']['CustomField9'] = ' ';
    $_SESSION['creditmemodetail']['CustomField10'] = ' ';
    $_SESSION['creditmemodetail']['CustomField11'] = ' ';
    $_SESSION['creditmemodetail']['CustomField12'] = ' ';
    $_SESSION['creditmemodetail']['CustomField13'] = ' ';
    $_SESSION['creditmemodetail']['CustomField14'] = ' ';
    $_SESSION['creditmemodetail']['CustomField15'] = ' ';
    $_SESSION['creditmemodetail']['IDKEY'] = ' ';
    $_SESSION['creditmemodetail']['GroupIDKEY'] = ' ';
}

function gentraverse_creditmemodetail($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnLineID':
                    $_SESSION['creditmemodetail']['TxnLineID'] = $nivel1->nodeValue;
                    $_SESSION['creditmemodetail']['IDKEY'] = $_SESSION['creditmemo']['TxnID'];
                    break;
                case 'Desc':
                    $_SESSION['creditmemodetail']['Description'] = $nivel1->nodeValue;
                    break;
                case 'Quantity':
                    $_SESSION['creditmemodetail']['Quantity'] = $nivel1->nodeValue;
                    break;
                case 'UnitOfMeasure':
                    $_SESSION['creditmemodetail']['UnitOfMeasure'] = $nivel1->nodeValue;
                    break;
                case 'Rate':
                    $_SESSION['creditmemodetail']['Rate'] = $nivel1->nodeValue;
                    break;
                case 'RatePercent':
                    $_SESSION['creditmemodetail']['RatePercent'] = $nivel1->nodeValue;
                    break;
                case 'Amount':
                    $_SESSION['creditmemodetail']['Amount'] = $nivel1->nodeValue;
                    break;
                case 'SerialNumber':
                    $_SESSION['creditmemodetail']['SerialNumber'] = $nivel1->nodeValue;
                    break;
                case 'LotNumber':
                    $_SESSION['creditmemodetail']['LotNumber'] = $nivel1->nodeValue;
                    break;
                case 'ServiceDate':
                    $_SESSION['creditmemodetail']['ServiceDate'] = $nivel1->nodeValue;
                    break;

                case 'ItemRef':
                case 'ClassRef':
                case 'SalesTaxCodeRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ItemRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemodetail']['ItemRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemodetail']['ItemRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemodetail']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemodetail']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxCodeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemodetail']['SalesTaxCodeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemodetail']['SalesTaxCodeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
            }
        }
    }
}

function actualiza_creditmemo($db) {
    $estado = 'INIT';

    try {
        $sql = 'UPDATE creditmemo SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, '
           . 'TxnNumber=:TxnNumber, CustomerRef_ListID=:CustomerRef_ListID, CustomerRef_FullName=:CustomerRef_FullName, '
           . 'ClassRef_ListID=:ClassRef_ListID, ClassRef_FullName=:ClassRef_FullName, ARAccountRef_ListID=:ARAccountRef_ListID, '
           . 'ARAccountRef_FullName=:ARAccountRef_FullName, TemplateRef_ListID=:TemplateRef_ListID, '
           . 'TemplateRef_FullName=:TemplateRef_FullName, TxnDate=:TxnDate, RefNumber=:RefNumber, '
           . 'BillAddress_Addr1=:BillAddress_Addr1, BillAddress_Addr2=:BillAddress_Addr2, BillAddress_Addr3=:BillAddress_Addr3, '
           . 'BillAddress_Addr4=:BillAddress_Addr4, BillAddress_Addr5=:BillAddress_Addr5, BillAddress_City=:BillAddress_City, '
           . 'BillAddress_State=:BillAddress_State, BillAddress_PostalCode=:BillAddress_PostalCode, '
           . 'BillAddress_Country=:BillAddress_Country, BillAddress_Note=:BillAddress_Note, ShipAddress_Addr1=:ShipAddress_Addr1, '
           . 'ShipAddress_Addr2=:ShipAddress_Addr2, ShipAddress_Addr3=:ShipAddress_Addr3, ShipAddress_Addr4=:ShipAddress_Addr4, '
           . 'ShipAddress_Addr5=:ShipAddress_Addr5, ShipAddress_City=:ShipAddress_City, ShipAddress_State=:ShipAddress_State, '
           . 'ShipAddress_PostalCode=:ShipAddress_PostalCode, ShipAddress_Country=:ShipAddress_Country, '
           . 'ShipAddress_Note=:ShipAddress_Note, IsPending=:IsPending, PONumber=:PONumber, TermsRef_ListID=:TermsRef_ListID, '
           . 'TermsRef_FullName=:TermsRef_FullName, DueDate=:DueDate, SalesRepRef_ListID=:SalesRepRef_ListID, '
           . 'SalesRepRef_FullName=:SalesRepRef_FullName, FOB=:FOB, ShipDate=:ShipDate, ShipMethodRef_ListID=:ShipMethodRef_ListID, '
           . 'ShipMethodRef_FullName=:ShipMethodRef_FullName, Subtotal=:Subtotal, ItemSalesTaxRef_ListID=:ItemSalesTaxRef_ListID, '
           . 'ItemSalesTaxRef_FullName=:ItemSalesTaxRef_FullName, SalesTaxPercentage=:SalesTaxPercentage, SalesTaxTotal=:SalesTaxTotal, '
           . 'TotalAmount=:TotalAmount, CreditRemaining=:CreditRemaining, CurrencyRef_ListID=:CurrencyRef_ListID, '
           . 'CurrencyRef_FullName=:CurrencyRef_FullName, ExchangeRate=:ExchangeRate, CreditRemainingInHomeCurrency=:CreditRemainingInHomeCurrency, '
           . 'Memo=:Memo, CustomerMsgRef_ListID=:CustomerMsgRef_ListID, CustomerMsgRef_FullName=:CustomerMsgRef_FullName, '
           . 'IsToBePrinted=:IsToBePrinted, IsToBeEmailed=:IsToBeEmailed, IsTaxIncluded=:IsTaxIncluded, '
           . 'CustomerSalesTaxCodeRef_ListID=:CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName=:CustomerSalesTaxCodeRef_FullName, '
           . 'Other=:Other, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, '
           . 'CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, '
           . 'CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, '
           . 'CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['creditmemo']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['creditmemo']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['creditmemo']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['creditmemo']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['creditmemo']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['creditmemo']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['creditmemo']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['creditmemo']['ClassRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['creditmemo']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['creditmemo']['ARAccountRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['creditmemo']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['creditmemo']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['creditmemo']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['creditmemo']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['creditmemo']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['creditmemo']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['creditmemo']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['creditmemo']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['creditmemo']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['creditmemo']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['creditmemo']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['creditmemo']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['creditmemo']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['creditmemo']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['creditmemo']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['creditmemo']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['creditmemo']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['creditmemo']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['creditmemo']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['creditmemo']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['creditmemo']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['creditmemo']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['creditmemo']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['creditmemo']['ShipAddress_Note']);
        $stmt->bindParam(':IsPending', $_SESSION['creditmemo']['IsPending']);
        $stmt->bindParam(':PONumber', $_SESSION['creditmemo']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['creditmemo']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['creditmemo']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['creditmemo']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['creditmemo']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['creditmemo']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['creditmemo']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['creditmemo']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['creditmemo']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['creditmemo']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['creditmemo']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['creditmemo']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['creditmemo']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['creditmemo']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['creditmemo']['SalesTaxTotal']);
        $stmt->bindParam(':TotalAmount', $_SESSION['creditmemo']['TotalAmount']);
        $stmt->bindParam(':CreditRemaining', $_SESSION['creditmemo']['CreditRemaining']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['creditmemo']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['creditmemo']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['creditmemo']['ExchangeRate']);
        $stmt->bindParam(':CreditRemainingInHomeCurrency', $_SESSION['creditmemo']['CreditRemainingInHomeCurrency']);
        $stmt->bindParam(':Memo', $_SESSION['creditmemo']['Memo']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['creditmemo']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['creditmemo']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['creditmemo']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['creditmemo']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['creditmemo']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other', $_SESSION['creditmemo']['Other']);
        $stmt->bindParam(':CustomField1', $_SESSION['creditmemo']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['creditmemo']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['creditmemo']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['creditmemo']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['creditmemo']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['creditmemo']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['creditmemo']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['creditmemo']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['creditmemo']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['creditmemo']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['creditmemo']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['creditmemo']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['creditmemo']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['creditmemo']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['creditmemo']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['creditmemo']['Status']);
        $stmt->bindParam(':clave', $_SESSION['invoice']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['creditmemo']['TxnID'] . ' campo ' . $_SESSION['creditmemo']['IsPaid'] . '<br>';
    }
    return $estado;
}

function adiciona_creditmemo($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO creditmemo (  TxnID, TimeCreated, TimeModified, EditSequence, TxnNumber, CustomerRef_ListID, '
           . 'CustomerRef_FullName, ClassRef_ListID, ClassRef_FullName, ARAccountRef_ListID, ARAccountRef_FullName, '
           . 'TemplateRef_ListID, TemplateRef_FullName, TxnDate, RefNumber, BillAddress_Addr1, BillAddress_Addr2, '
           . 'BillAddress_Addr3, BillAddress_Addr4, BillAddress_Addr5, BillAddress_City, BillAddress_State, '
           . 'BillAddress_PostalCode, BillAddress_Country, BillAddress_Note, ShipAddress_Addr1, ShipAddress_Addr2, '
           . 'ShipAddress_Addr3, ShipAddress_Addr4, ShipAddress_Addr5, ShipAddress_City, ShipAddress_State, '
           . 'ShipAddress_PostalCode, ShipAddress_Country, ShipAddress_Note, IsPending, PONumber, TermsRef_ListID, '
           . 'TermsRef_FullName, DueDate, SalesRepRef_ListID, SalesRepRef_FullName, FOB, ShipDate, ShipMethodRef_ListID, '
           . 'ShipMethodRef_FullName, Subtotal, ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName, SalesTaxPercentage, '
           . 'SalesTaxTotal, TotalAmount, CreditRemaining, CurrencyRef_ListID, CurrencyRef_FullName, ExchangeRate, '
           . 'CreditRemainingInHomeCurrency, Memo, CustomerMsgRef_ListID, CustomerMsgRef_FullName, IsToBePrinted, '
           . 'IsToBeEmailed, IsTaxIncluded, CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName, Other, '
           . 'CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, '
           . 'CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, '
           . 'CustomField15, Status) VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnNumber, '
           . ':CustomerRef_ListID, :CustomerRef_FullName, :ClassRef_ListID, :ClassRef_FullName, :ARAccountRef_ListID, '
           . ':ARAccountRef_FullName, :TemplateRef_ListID, :TemplateRef_FullName, :TxnDate, :RefNumber, '
           . ':BillAddress_Addr1, :BillAddress_Addr2, :BillAddress_Addr3, :BillAddress_Addr4, :BillAddress_Addr5, '
           . ':BillAddress_City, :BillAddress_State, :BillAddress_PostalCode, :BillAddress_Country, :BillAddress_Note, '
           . ':ShipAddress_Addr1, :ShipAddress_Addr2, :ShipAddress_Addr3, :ShipAddress_Addr4, :ShipAddress_Addr5, '
           . ':ShipAddress_City, :ShipAddress_State, :ShipAddress_PostalCode, :ShipAddress_Country, :ShipAddress_Note, '
           . ':IsPending, :PONumber, :TermsRef_ListID, :TermsRef_FullName, :DueDate, :SalesRepRef_ListID, '
           . ':SalesRepRef_FullName, :FOB, :ShipDate, :ShipMethodRef_ListID, :ShipMethodRef_FullName, :Subtotal, '
           . ':ItemSalesTaxRef_ListID, :ItemSalesTaxRef_FullName, :SalesTaxPercentage, :SalesTaxTotal, :TotalAmount, '
           . ':CreditRemaining, :CurrencyRef_ListID, :CurrencyRef_FullName, :ExchangeRate, :CreditRemainingInHomeCurrency, '
           . ':Memo, :CustomerMsgRef_ListID, :CustomerMsgRef_FullName, :IsToBePrinted, :IsToBeEmailed, :IsTaxIncluded, '
           . ':CustomerSalesTaxCodeRef_ListID, :CustomerSalesTaxCodeRef_FullName, :Other, :CustomField1, :CustomField2, '
           . ':CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, '
           . ':CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['creditmemo']['TxnID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['creditmemo']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['creditmemo']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['creditmemo']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['creditmemo']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['creditmemo']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['creditmemo']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['creditmemo']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['creditmemo']['ClassRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['creditmemo']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['creditmemo']['ARAccountRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['creditmemo']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['creditmemo']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['creditmemo']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['creditmemo']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['creditmemo']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['creditmemo']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['creditmemo']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['creditmemo']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['creditmemo']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['creditmemo']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['creditmemo']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['creditmemo']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['creditmemo']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['creditmemo']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['creditmemo']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['creditmemo']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['creditmemo']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['creditmemo']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['creditmemo']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['creditmemo']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['creditmemo']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['creditmemo']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['creditmemo']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['creditmemo']['ShipAddress_Note']);
        $stmt->bindParam(':IsPending', $_SESSION['creditmemo']['IsPending']);
        $stmt->bindParam(':PONumber', $_SESSION['creditmemo']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['creditmemo']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['creditmemo']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['creditmemo']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['creditmemo']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['creditmemo']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['creditmemo']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['creditmemo']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['creditmemo']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['creditmemo']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['creditmemo']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['creditmemo']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['creditmemo']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['creditmemo']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['creditmemo']['SalesTaxTotal']);
        $stmt->bindParam(':TotalAmount', $_SESSION['creditmemo']['TotalAmount']);
        $stmt->bindParam(':CreditRemaining', $_SESSION['creditmemo']['CreditRemaining']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['creditmemo']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['creditmemo']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['creditmemo']['ExchangeRate']);
        $stmt->bindParam(':CreditRemainingInHomeCurrency', $_SESSION['creditmemo']['CreditRemainingInHomeCurrency']);
        $stmt->bindParam(':Memo', $_SESSION['creditmemo']['Memo']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['creditmemo']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['creditmemo']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['creditmemo']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['creditmemo']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['creditmemo']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other', $_SESSION['creditmemo']['Other']);
        $stmt->bindParam(':CustomField1', $_SESSION['creditmemo']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['creditmemo']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['creditmemo']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['creditmemo']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['creditmemo']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['creditmemo']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['creditmemo']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['creditmemo']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['creditmemo']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['creditmemo']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['creditmemo']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['creditmemo']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['creditmemo']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['creditmemo']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['creditmemo']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['creditmemo']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . $_SESSION['creditmemo']['TxnID'] . ' campo ' . $_SESSION['creditmemo']['IsPaid'] . '<br>';
    }
    return $estado;
}

function buscaIgual_creditmemo($db) {
    $estado = "ERR";
    try {
        $sql = "SELECT * FROM creditmemo WHERE TxnID = :clave ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['creditmemo']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = "OK";
            $_SESSION['creditmemo']['CustomField10'] = 'SIN IMPRIMIR';
            $_SESSION['creditmemo']['CustomField15'] = 'SIN FIRMAR';
        } else {
            if ($registro['TxnID'] === $_SESSION['creditmemo']['TxnID']) {
                $estado = "ACTUALIZA";
                $_SESSION['creditmemo']['CustomField10'] = $registro['CustomField10'];
                $_SESSION['creditmemo']['CustomField15'] = $registro['CustomField15'];
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_creditmemo($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['creditmemo']['TxnID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['creditmemo']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['creditmemo']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['creditmemo']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'TxnNumber':
                    $_SESSION['creditmemo']['TxnNumber'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['creditmemo']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['creditmemo']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'IsPending':
                    $_SESSION['creditmemo']['IsPending'] = $nivel1->nodeValue;
                    break;
                case 'PONumber':
                    $_SESSION['creditmemo']['PONumber'] = $nivel1->nodeValue;
                    break;
                case 'DueDate':
                    $_SESSION['creditmemo']['DueDate'] = $nivel1->nodeValue;
                    break;
                case 'FOB':
                    $_SESSION['creditmemo']['FOB'] = $nivel1->nodeValue;
                    break;
                case 'ShipDate':
                    $_SESSION['creditmemo']['ShipDate'] = $nivel1->nodeValue;
                    break;
                case 'Subtotal':
                    $_SESSION['creditmemo']['Subtotal'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxPercentage':
                    $_SESSION['creditmemo']['SalesTaxPercentage'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxTotal':
                    $_SESSION['creditmemo']['SalesTaxTotal'] = $nivel1->nodeValue;
                    break;
                case 'TotalAmount':
                    $_SESSION['creditmemo']['TotalAmount'] = $nivel1->nodeValue;
                    break;
                case 'CreditRemaining':
                    $_SESSION['creditmemo']['CreditRemaining'] = $nivel1->nodeValue;
                    break;
                case 'ExchangeRate':
                    $_SESSION['creditmemo']['ExchangeRate'] = $nivel1->nodeValue;
                    break;
                case 'CreditRemainingInHomeCurrency':
                    $_SESSION['creditmemo']['BalanceRemainingInHomeCurrency'] = $nivel1->nodeValue;
                    break;
                case 'Memo':
                    $_SESSION['creditmemo']['Memo'] = $nivel1->nodeValue;
                    break;
                case 'IsToBePrinted':
                    $_SESSION['creditmemo']['IsToBePrinted'] = $nivel1->nodeValue;
                    break;
                case 'IsToBeEmailed':
                    $_SESSION['creditmemo']['IsToBeEmailed'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxIncluded':
                    $_SESSION['creditmemo']['IsTaxIncluded'] = $nivel1->nodeValue;
                    break;
                case 'Other':
                    $_SESSION['creditmemo']['Other'] = $nivel1->nodeValue;
                    break;

                case 'BillAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['creditmemo']['BillAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['creditmemo']['BillAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['creditmemo']['BillAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['creditmemo']['BillAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['creditmemo']['BillAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['creditmemo']['BillAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['creditmemo']['BillAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['creditmemo']['BillAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['creditmemo']['BillAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['creditmemo']['BillAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                case 'CustomerRef':
                case 'ClassRef':
                case 'ARAccountRef':
                case 'TemplateRef':
                case 'TermsRef':
                case 'SalesRepRef':
                case 'ShipMethodRef':
                case 'ItemSalesTaxRef':
                case 'CurrencyRef':
                case 'CustomerMsgRef':
                case 'CustomerSalesTaxCodeRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'CustomerRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['CustomerRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['CustomerRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TemplateRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['TemplateRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['TemplateRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TermsRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['TermsRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['TermsRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesRepRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['SalesRepRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['SalesRepRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ItemSalesTaxRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['ItemSalesTaxRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['ItemSalesTaxRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CustomerMsgRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['creditmemo']['CustomerMsgRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['creditmemo']['CustomerMsgRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function quitaslashes_creditmemodetail() {
    $_SESSION['creditmemodetail']['TxnLineID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['TxnLineID']));
    $_SESSION['creditmemodetail']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['ItemRef_ListID']));
    $_SESSION['creditmemodetail']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['ItemRef_FullName']));
    $_SESSION['creditmemodetail']['Description'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Description']));
    $_SESSION['creditmemodetail']['Quantity'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Quantity']));
    $_SESSION['creditmemodetail']['UnitOfMeasure'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['UnitOfMeasure']));
    $_SESSION['creditmemodetail']['OverrideUOMSetRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['OverrideUOMSetRef_ListID']));
    $_SESSION['creditmemodetail']['OverrideUOMSetRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['OverrideUOMSetRef_FullName']));
    $_SESSION['creditmemodetail']['Rate'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Rate']));
    $_SESSION['creditmemodetail']['RatePercent'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['RatePercent']));
    $_SESSION['creditmemodetail']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['ClassRef_ListID']));
    $_SESSION['creditmemodetail']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['ClassRef_FullName']));
    $_SESSION['creditmemodetail']['Amount'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Amount']));
    $_SESSION['creditmemodetail']['InventorySiteRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['InventorySiteRef_ListID']));
    $_SESSION['creditmemodetail']['InventorySiteRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['InventorySiteRef_FullName']));
    $_SESSION['creditmemodetail']['SerialNumber'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['SerialNumber']));
    $_SESSION['creditmemodetail']['LotNumber'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['LotNumber']));
    $_SESSION['creditmemodetail']['ServiceDate'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['ServiceDate']));
    $_SESSION['creditmemodetail']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['SalesTaxCodeRef_ListID']));
    $_SESSION['creditmemodetail']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['SalesTaxCodeRef_FullName']));
    $_SESSION['creditmemodetail']['Other1'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Other1']));
    $_SESSION['creditmemodetail']['Other2'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['Other2']));
    $_SESSION['creditmemodetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField1']));
    $_SESSION['creditmemodetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField2']));
    $_SESSION['creditmemodetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField3']));
    $_SESSION['creditmemodetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField4']));
    $_SESSION['creditmemodetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField5']));
    $_SESSION['creditmemodetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField6']));
    $_SESSION['creditmemodetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField7']));
    $_SESSION['creditmemodetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField8']));
    $_SESSION['creditmemodetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField9']));
    $_SESSION['creditmemodetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField10']));
    $_SESSION['creditmemodetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField11']));
    $_SESSION['creditmemodetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField12']));
    $_SESSION['creditmemodetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField13']));
    $_SESSION['creditmemodetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField14']));
    $_SESSION['creditmemodetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['CustomField15']));
    $_SESSION['creditmemodetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['creditmemodetail']['IDKEY']));
}

function quitaslashes_creditmemo() {
    $_SESSION['creditmemo']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TxnID']));
    $_SESSION['creditmemo']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['creditmemo']['TimeCreated']));
    $_SESSION['creditmemo']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['creditmemo']['TimeModified']));
    $_SESSION['creditmemo']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['EditSequence']));
    $_SESSION['creditmemo']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TxnNumber']));
    $_SESSION['creditmemo']['CustomerRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerRef_ListID']));
    $_SESSION['creditmemo']['CustomerRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerRef_FullName']));
    $_SESSION['creditmemo']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ClassRef_ListID']));
    $_SESSION['creditmemo']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ClassRef_FullName']));
    $_SESSION['creditmemo']['ARAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ARAccountRef_ListID']));
    $_SESSION['creditmemo']['ARAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ARAccountRef_FullName']));
    $_SESSION['creditmemo']['TemplateRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TemplateRef_ListID']));
    $_SESSION['creditmemo']['TemplateRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TemplateRef_FullName']));
    $_SESSION['creditmemo']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TxnDate']));
    $_SESSION['creditmemo']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['RefNumber']));
    $_SESSION['creditmemo']['BillAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Addr1']));
    $_SESSION['creditmemo']['BillAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Addr2']));
    $_SESSION['creditmemo']['BillAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Addr3']));
    $_SESSION['creditmemo']['BillAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Addr4']));
    $_SESSION['creditmemo']['BillAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Addr5']));
    $_SESSION['creditmemo']['BillAddress_City'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_City']));
    $_SESSION['creditmemo']['BillAddress_State'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_State']));
    $_SESSION['creditmemo']['BillAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_PostalCode']));
    $_SESSION['creditmemo']['BillAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Country']));
    $_SESSION['creditmemo']['BillAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['BillAddress_Note']));
    $_SESSION['creditmemo']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Addr1']));
    $_SESSION['creditmemo']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Addr2']));
    $_SESSION['creditmemo']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Addr3']));
    $_SESSION['creditmemo']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Addr4']));
    $_SESSION['creditmemo']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Addr5']));
    $_SESSION['creditmemo']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_City']));
    $_SESSION['creditmemo']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_State']));
    $_SESSION['creditmemo']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_PostalCode']));
    $_SESSION['creditmemo']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Country']));
    $_SESSION['creditmemo']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipAddress_Note']));
    $_SESSION['creditmemo']['IsPending'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['IsPending']));
    $_SESSION['creditmemo']['PONumber'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['PONumber']));
    $_SESSION['creditmemo']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TermsRef_ListID']));
    $_SESSION['creditmemo']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TermsRef_FullName']));
    $_SESSION['creditmemo']['DueDate'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['DueDate']));
    $_SESSION['creditmemo']['SalesRepRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['SalesRepRef_ListID']));
    $_SESSION['creditmemo']['SalesRepRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['SalesRepRef_FullName']));
    $_SESSION['creditmemo']['FOB'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['FOB']));
    $_SESSION['creditmemo']['ShipDate'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipDate']));
    $_SESSION['creditmemo']['ShipMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipMethodRef_ListID']));
    $_SESSION['creditmemo']['ShipMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ShipMethodRef_FullName']));
    $_SESSION['creditmemo']['Subtotal'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['Subtotal']));
    $_SESSION['creditmemo']['ItemSalesTaxRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ItemSalesTaxRef_ListID']));
    $_SESSION['creditmemo']['ItemSalesTaxRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ItemSalesTaxRef_FullName']));
    $_SESSION['creditmemo']['SalesTaxPercentage'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['SalesTaxPercentage']));
    $_SESSION['creditmemo']['SalesTaxTotal'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['SalesTaxTotal']));
    $_SESSION['creditmemo']['TotalAmount'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['TotalAmount']));
    $_SESSION['creditmemo']['CreditRemaining'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CreditRemaining']));
    $_SESSION['creditmemo']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CurrencyRef_ListID']));
    $_SESSION['creditmemo']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CurrencyRef_FullName']));
    $_SESSION['creditmemo']['ExchangeRate'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['ExchangeRate']));
    $_SESSION['creditmemo']['CreditRemainingInHomeCurrency'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CreditRemainingInHomeCurrency']));
    $_SESSION['creditmemo']['Memo'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['Memo']));
    $_SESSION['creditmemo']['CustomerMsgRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerMsgRef_ListID']));
    $_SESSION['creditmemo']['CustomerMsgRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerMsgRef_FullName']));
    $_SESSION['creditmemo']['IsToBePrinted'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['IsToBePrinted']));
    $_SESSION['creditmemo']['IsToBeEmailed'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['IsToBeEmailed']));
    $_SESSION['creditmemo']['IsTaxIncluded'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['IsTaxIncluded']));
    $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerSalesTaxCodeRef_ListID']));
    $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomerSalesTaxCodeRef_FullName']));
    $_SESSION['creditmemo']['Other'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['Other']));
    $_SESSION['creditmemo']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField1']));
    $_SESSION['creditmemo']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField2']));
    $_SESSION['creditmemo']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField3']));
    $_SESSION['creditmemo']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField4']));
    $_SESSION['creditmemo']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField5']));
    $_SESSION['creditmemo']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField6']));
    $_SESSION['creditmemo']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField7']));
    $_SESSION['creditmemo']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField8']));
    $_SESSION['creditmemo']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField9']));
    $_SESSION['creditmemo']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField10']));
    $_SESSION['creditmemo']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField11']));
    $_SESSION['creditmemo']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField12']));
    $_SESSION['creditmemo']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField13']));
    $_SESSION['creditmemo']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField14']));
    $_SESSION['creditmemo']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['CustomField15']));
    $_SESSION['creditmemo']['Status'] = htmlspecialchars(strip_tags($_SESSION['creditmemo']['Status']));
}

function genLimpia_creditmemo() {
    $_SESSION['creditmemo']['TxnID'] = ' ';
    $_SESSION['creditmemo']['TimeCreated'] = ' ';
    $_SESSION['creditmemo']['TimeModified'] = ' ';
    $_SESSION['creditmemo']['EditSequence'] = ' ';
    $_SESSION['creditmemo']['TxnNumber'] = ' ';
    $_SESSION['creditmemo']['CustomerRef_ListID'] = ' ';
    $_SESSION['creditmemo']['CustomerRef_FullName'] = ' ';
    $_SESSION['creditmemo']['ClassRef_ListID'] = ' ';
    $_SESSION['creditmemo']['ClassRef_FullName'] = ' ';
    $_SESSION['creditmemo']['ARAccountRef_ListID'] = ' ';
    $_SESSION['creditmemo']['ARAccountRef_FullName'] = ' ';
    $_SESSION['creditmemo']['TemplateRef_ListID'] = ' ';
    $_SESSION['creditmemo']['TemplateRef_FullName'] = ' ';
    $_SESSION['creditmemo']['TxnDate'] = ' ';
    $_SESSION['creditmemo']['RefNumber'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Addr1'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Addr2'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Addr3'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Addr4'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Addr5'] = ' ';
    $_SESSION['creditmemo']['BillAddress_City'] = ' ';
    $_SESSION['creditmemo']['BillAddress_State'] = ' ';
    $_SESSION['creditmemo']['BillAddress_PostalCode'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Country'] = ' ';
    $_SESSION['creditmemo']['BillAddress_Note'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Addr1'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Addr2'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Addr3'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Addr4'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Addr5'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_City'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_State'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Country'] = ' ';
    $_SESSION['creditmemo']['ShipAddress_Note'] = ' ';
    $_SESSION['creditmemo']['IsPending'] = 'false';
    $_SESSION['creditmemo']['PONumber'] = ' ';
    $_SESSION['creditmemo']['TermsRef_ListID'] = ' ';
    $_SESSION['creditmemo']['TermsRef_FullName'] = ' ';
    $_SESSION['creditmemo']['DueDate'] = '2018-01-01';
    $_SESSION['creditmemo']['SalesRepRef_ListID'] = ' ';
    $_SESSION['creditmemo']['SalesRepRef_FullName'] = ' ';
    $_SESSION['creditmemo']['FOB'] = 0;
    $_SESSION['creditmemo']['ShipDate'] = ' ';
    $_SESSION['creditmemo']['ShipMethodRef_ListID'] = ' ';
    $_SESSION['creditmemo']['ShipMethodRef_FullName'] = ' ';
    $_SESSION['creditmemo']['Subtotal'] = 0;
    $_SESSION['creditmemo']['ItemSalesTaxRef_ListID'] = ' ';
    $_SESSION['creditmemo']['ItemSalesTaxRef_FullName'] = ' ';
    $_SESSION['creditmemo']['SalesTaxPercentage'] = ' ';
    $_SESSION['creditmemo']['SalesTaxTotal'] = 0;
    $_SESSION['creditmemo']['TotalAmount'] = 0;
    $_SESSION['creditmemo']['CreditRemaining'] = 0;
    $_SESSION['creditmemo']['CurrencyRef_ListID'] = ' ';
    $_SESSION['creditmemo']['CurrencyRef_FullName'] = ' ';
    $_SESSION['creditmemo']['ExchangeRate'] = 0;
    $_SESSION['creditmemo']['CreditRemainingInHomeCurrency'] = 0;
    $_SESSION['creditmemo']['Memo'] = ' ';
    $_SESSION['creditmemo']['CustomerMsgRef_ListID'] = ' ';
    $_SESSION['creditmemo']['CustomerMsgRef_FullName'] = ' ';
    $_SESSION['creditmemo']['IsToBePrinted'] = 'false';
    $_SESSION['creditmemo']['IsToBeEmailed'] = 'false';
    $_SESSION['creditmemo']['IsTaxIncluded'] = 'false';
    $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['creditmemo']['CustomerSalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['creditmemo']['Other'] = ' ';
    $_SESSION['creditmemo']['CustomField1'] = ' ';
    $_SESSION['creditmemo']['CustomField2'] = ' ';
    $_SESSION['creditmemo']['CustomField3'] = ' ';
    $_SESSION['creditmemo']['CustomField4'] = ' ';
    $_SESSION['creditmemo']['CustomField5'] = ' ';
    $_SESSION['creditmemo']['CustomField6'] = ' ';
    $_SESSION['creditmemo']['CustomField7'] = ' ';
    $_SESSION['creditmemo']['CustomField8'] = ' ';
    $_SESSION['creditmemo']['CustomField9'] = ' ';
    $_SESSION['creditmemo']['CustomField10'] = ' ';
    $_SESSION['creditmemo']['CustomField11'] = ' ';
    $_SESSION['creditmemo']['CustomField12'] = ' ';
    $_SESSION['creditmemo']['CustomField13'] = ' ';
    $_SESSION['creditmemo']['CustomField14'] = ' ';
    $_SESSION['creditmemo']['CustomField15'] = ' ';
    $_SESSION['creditmemo']['Status'] = ' ';
}
