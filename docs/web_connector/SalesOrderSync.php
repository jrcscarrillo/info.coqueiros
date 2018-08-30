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
define('QB_PRIORITY_SALESORDER', 1);
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
$fecha = date("Y-m-d", strtotime($registro['otrosDesde']));
define("AU_DESDE", $fecha);
$fecha = date("Y-m-d", strtotime($registro['otrosHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);
$db = null;
/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_SALESORDER => array('_quickbooks_salesorder_import_request', '_quickbooks_salesorder_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_SALESORDER)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_SALESORDER, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_SALESORDER, 1, QB_PRIORITY_SALESORDER, NULL, $user);
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

function _quickbooks_salesorder_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_salesorder_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<SalesOrderQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <TxnDateRangeFilter>
                                <FromTxnDate >' . AU_DESDE . '</FromTxnDate>
                                <ToTxnDate >' . AU_HASTA . '</ToTxnDate>
                            </TxnDateRangeFilter>
                            <IncludeLineItems>true</IncludeLineItems>
                            <OwnerID>0</OwnerID>
			</SalesOrderQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_salesorder_initial_response() {
    
}

function _quickbooks_salesorder_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_SALESORDER, null, QB_PRIORITY_SALESORDER, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['salesorder'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("salesorder.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "SalesOrderRet";
    $orden = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($orden as $uno) {
        $estado = "INIT";
        genLimpia_salesorder();
        gentraverse_salesorder($uno);
        $existe = buscaIgual_salesorder($db);
        $estado = 'INIT';
        $status = 'INIT';
        $borra = 'INIT';
        if ($existe == "OK") {
            quitaslashes_salesorder();
            $estado = adiciona_salesorder($db);
        } elseif ($existe == "ACTUALIZA") {
            if ($_SESSION['salesorder']['Memo'] === 'VOID:') {
                $estado = delete_salesorder($db);
                $borra = "OK";
            } else {
                quitaslashes_salesorder();
                $estado = actualiza_salesorder($db);
                $status = delete_salesorderlinedetail($db);
            }
        }
        fwrite($myfile, "INICIO " . $estado . " " . $status . " " . $existe . "\r\n");
        if ($borra === "INIT") {
            $detalle = $orden->item($k)->getElementsByTagName('SalesOrderLineRet');
            foreach ($detalle as $uno) {
                genLimpia_salesorderlinedetail();
                gentraverse_salesorderlinedetail($uno);
                $estado = adiciona_salesorderlinedetail($db);
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
    if ($action == QUICKBOOKS_IMPORT_SALESORDER) {
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

function delete_salesorder($db) {
    $estado = 'DELE';
    try {
        $sql = 'DELETE FROM salesorder WHERE TxnID = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['salesorder']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function delete_salesorderlinedetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'DELETE FROM salesorderlinedetail WHERE IDKEY = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['salesorder']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_salesorderlinedetail($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnLineID':
                    $_SESSION['salesorderlinedetail']['TxnLineID'] = $nivel1->nodeValue;
                    $_SESSION['salesorderlinedetail']['IDKEY'] = $_SESSION['salesorder']['TxnID'];
                    break;
                case 'Desc':
                    $_SESSION['salesorderlinedetail']['Description'] = $nivel1->nodeValue;
                    break;
                case 'Quantity':
                    $_SESSION['salesorderlinedetail']['Quantity'] = $nivel1->nodeValue;
                    break;
                case 'UnitOfMeasure':
                    $_SESSION['salesorderlinedetail']['UnitOfMeasure'] = $nivel1->nodeValue;
                    break;
                case 'Amount':
                    $_SESSION['salesorderlinedetail']['Amount'] = $nivel1->nodeValue;
                    break;

                case 'ItemRef':
                case 'ClassRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ItemRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorderlinedetail']['ItemRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorderlinedetail']['ItemRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorderlinedetail']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorderlinedetail']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function buscaIgual_salesorder($db) {
    $estado = "ERR";
    try {
        $sql = "SELECT * FROM salesorder WHERE TxnID = :clave ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['salesorder']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = "OK";
        } else {
            if ($registro['TxnID'] === $_SESSION['salesorder']['TxnID']) {
                $estado = "ACTUALIZA";
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_salesorder($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['salesorder']['TxnID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['salesorder']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['salesorder']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['salesorder']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'TxnNumber':
                    $_SESSION['salesorder']['TxnNumber'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['salesorder']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['salesorder']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'PONumber':
                    $_SESSION['salesorder']['PONumber'] = $nivel1->nodeValue;
                    break;
                case 'DueDate':
                    $_SESSION['salesorder']['DueDate'] = $nivel1->nodeValue;
                    break;
                case 'FOB':
                    $_SESSION['salesorder']['FOB'] = $nivel1->nodeValue;
                    break;
                case 'ShipDate':
                    $_SESSION['salesorder']['ShipDate'] = $nivel1->nodeValue;
                    break;
                case 'Subtotal':
                    $_SESSION['salesorder']['Subtotal'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxPercentage':
                    $_SESSION['salesorder']['SalesTaxPercentage'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxTotal':
                    $_SESSION['salesorder']['SalesTaxTotal'] = $nivel1->nodeValue;
                    break;
                case 'TotalAmount':
                    $_SESSION['salesorder']['AppliedAmount'] = $nivel1->nodeValue;
                    break;
                case 'Memo':
                    $_SESSION['salesorder']['Memo'] = $nivel1->nodeValue;
                    break;
                case 'Other':
                    $_SESSION['salesorder']['Other'] = $nivel1->nodeValue;
                    break;

                case 'BillAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['salesorder']['BillAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['salesorder']['BillAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['salesorder']['BillAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['salesorder']['BillAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['salesorder']['BillAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['salesorder']['BillAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['salesorder']['BillAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['salesorder']['BillAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['salesorder']['BillAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['salesorder']['BillAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'CustomerRef':
                case 'ClassRef':
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
                                    $_SESSION['salesorder']['CustomerRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['CustomerRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorder']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TermsRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorder']['TermsRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['TermsRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesRepRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorder']['SalesRepRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['SalesRepRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ItemSalesTaxRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorder']['ItemSalesTaxRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['ItemSalesTaxRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CustomerMsgRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['salesorder']['CustomerMsgRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['salesorder']['CustomerMsgRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function genLimpia_salesorderlinedetail() {
    $_SESSION['salesorderlinedetail']['TxnLineID'] = ' ';
    $_SESSION['salesorderlinedetail']['ItemRef_ListID'] = ' ';
    $_SESSION['salesorderlinedetail']['ItemRef_FullName'] = ' ';
    $_SESSION['salesorderlinedetail']['Description'] = ' ';
    $_SESSION['salesorderlinedetail']['Quantity'] = 0;
    $_SESSION['salesorderlinedetail']['UnitOfMeasure'] = ' ';
    $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_ListID'] = ' ';
    $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_FullName'] = ' ';
    $_SESSION['salesorderlinedetail']['Rate'] = 0;
    $_SESSION['salesorderlinedetail']['RatePercent'] = 0;
    $_SESSION['salesorderlinedetail']['ClassRef_ListID'] = ' ';
    $_SESSION['salesorderlinedetail']['ClassRef_FullName'] = ' ';
    $_SESSION['salesorderlinedetail']['Amount'] = 0;
    $_SESSION['salesorderlinedetail']['InventorySiteRef_ListID'] = ' ';
    $_SESSION['salesorderlinedetail']['InventorySiteRef_FullName'] = ' ';
    $_SESSION['salesorderlinedetail']['SerialNumber'] = ' ';
    $_SESSION['salesorderlinedetail']['LotNumber'] = ' ';
    $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['salesorderlinedetail']['Invoiced'] = 'false';
    $_SESSION['salesorderlinedetail']['IsManuallyClosed'] = 'false';
    $_SESSION['salesorderlinedetail']['Other1'] = ' ';
    $_SESSION['salesorderlinedetail']['Other2'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField1'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField2'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField3'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField4'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField5'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField6'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField7'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField8'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField9'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField10'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField11'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField12'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField13'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField14'] = ' ';
    $_SESSION['salesorderlinedetail']['CustomField15'] = ' ';
    $_SESSION['salesorderlinedetail']['IDKEY'] = ' ';
    $_SESSION['salesorderlinedetail']['GroupIDKEY'] = ' ';
}

function adiciona_salesorderlinedetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO salesorderlinedetail (  TxnLineID, ItemRef_ListID, ItemRef_FullName, Description, Quantity, UnitOfMeasure, OverrideUOMSetRef_ListID, OverrideUOMSetRef_FullName, Rate, RatePercent, ClassRef_ListID, ClassRef_FullName, Amount, InventorySiteRef_ListID, InventorySiteRef_FullName, SerialNumber, LotNumber, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, Invoiced, IsManuallyClosed, Other1, Other2, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, IDKEY, GroupIDKEY) VALUES ( :TxnLineID, :ItemRef_ListID, :ItemRef_FullName, :Description, :Quantity, :UnitOfMeasure, :OverrideUOMSetRef_ListID, :OverrideUOMSetRef_FullName, :Rate, :RatePercent, :ClassRef_ListID, :ClassRef_FullName, :Amount, :InventorySiteRef_ListID, :InventorySiteRef_FullName, :SerialNumber, :LotNumber, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, :Invoiced, :IsManuallyClosed, :Other1, :Other2, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY, :GroupIDKEY)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnLineID', $_SESSION['salesorderlinedetail']['TxnLineID']);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['salesorderlinedetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['salesorderlinedetail']['ItemRef_FullName']);
        $stmt->bindParam(':Description', $_SESSION['salesorderlinedetail']['Description']);
        $stmt->bindParam(':Quantity', $_SESSION['salesorderlinedetail']['Quantity']);
        $stmt->bindParam(':UnitOfMeasure', $_SESSION['salesorderlinedetail']['UnitOfMeasure']);
        $stmt->bindParam(':OverrideUOMSetRef_ListID', $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_ListID']);
        $stmt->bindParam(':OverrideUOMSetRef_FullName', $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_FullName']);
        $stmt->bindParam(':Rate', $_SESSION['salesorderlinedetail']['Rate']);
        $stmt->bindParam(':RatePercent', $_SESSION['salesorderlinedetail']['RatePercent']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['salesorderlinedetail']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['salesorderlinedetail']['ClassRef_FullName']);
        $stmt->bindParam(':Amount', $_SESSION['salesorderlinedetail']['Amount']);
        $stmt->bindParam(':InventorySiteRef_ListID', $_SESSION['salesorderlinedetail']['InventorySiteRef_ListID']);
        $stmt->bindParam(':InventorySiteRef_FullName', $_SESSION['salesorderlinedetail']['InventorySiteRef_FullName']);
        $stmt->bindParam(':SerialNumber', $_SESSION['salesorderlinedetail']['SerialNumber']);
        $stmt->bindParam(':LotNumber', $_SESSION['salesorderlinedetail']['LotNumber']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Invoiced', $_SESSION['salesorderlinedetail']['Invoiced']);
        $stmt->bindParam(':IsManuallyClosed', $_SESSION['salesorderlinedetail']['IsManuallyClosed']);
        $stmt->bindParam(':Other1', $_SESSION['salesorderlinedetail']['Other1']);
        $stmt->bindParam(':Other2', $_SESSION['salesorderlinedetail']['Other2']);
        $stmt->bindParam(':CustomField1', $_SESSION['salesorderlinedetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['salesorderlinedetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['salesorderlinedetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['salesorderlinedetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['salesorderlinedetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['salesorderlinedetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['salesorderlinedetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['salesorderlinedetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['salesorderlinedetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['salesorderlinedetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['salesorderlinedetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['salesorderlinedetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['salesorderlinedetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['salesorderlinedetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['salesorderlinedetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['salesorderlinedetail']['IDKEY']);
        $stmt->bindParam(':GroupIDKEY', $_SESSION['salesorderlinedetail']['GroupIDKEY']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['salesorderlinedetail']['TxnLineID'];
    }
    return $estado;
}

function quitaslashes_salesorderlinedetail() {
    $_SESSION['salesorderlinedetail']['TxnLineID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['TxnLineID']));
    $_SESSION['salesorderlinedetail']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['ItemRef_ListID']));
    $_SESSION['salesorderlinedetail']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['ItemRef_FullName']));
    $_SESSION['salesorderlinedetail']['Description'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Description']));
    $_SESSION['salesorderlinedetail']['Quantity'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Quantity']));
    $_SESSION['salesorderlinedetail']['UnitOfMeasure'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['UnitOfMeasure']));
    $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['OverrideUOMSetRef_ListID']));
    $_SESSION['salesorderlinedetail']['OverrideUOMSetRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['OverrideUOMSetRef_FullName']));
    $_SESSION['salesorderlinedetail']['Rate'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Rate']));
    $_SESSION['salesorderlinedetail']['RatePercent'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['RatePercent']));
    $_SESSION['salesorderlinedetail']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['ClassRef_ListID']));
    $_SESSION['salesorderlinedetail']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['ClassRef_FullName']));
    $_SESSION['salesorderlinedetail']['Amount'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Amount']));
    $_SESSION['salesorderlinedetail']['InventorySiteRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['InventorySiteRef_ListID']));
    $_SESSION['salesorderlinedetail']['InventorySiteRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['InventorySiteRef_FullName']));
    $_SESSION['salesorderlinedetail']['SerialNumber'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['SerialNumber']));
    $_SESSION['salesorderlinedetail']['LotNumber'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['LotNumber']));
    $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['SalesTaxCodeRef_ListID']));
    $_SESSION['salesorderlinedetail']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['SalesTaxCodeRef_FullName']));
    $_SESSION['salesorderlinedetail']['Invoiced'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Invoiced']));
    $_SESSION['salesorderlinedetail']['IsManuallyClosed'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['IsManuallyClosed']));
    $_SESSION['salesorderlinedetail']['Other1'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Other1']));
    $_SESSION['salesorderlinedetail']['Other2'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['Other2']));
    $_SESSION['salesorderlinedetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField1']));
    $_SESSION['salesorderlinedetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField2']));
    $_SESSION['salesorderlinedetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField3']));
    $_SESSION['salesorderlinedetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField4']));
    $_SESSION['salesorderlinedetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField5']));
    $_SESSION['salesorderlinedetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField6']));
    $_SESSION['salesorderlinedetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField7']));
    $_SESSION['salesorderlinedetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField8']));
    $_SESSION['salesorderlinedetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField9']));
    $_SESSION['salesorderlinedetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField10']));
    $_SESSION['salesorderlinedetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField11']));
    $_SESSION['salesorderlinedetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField12']));
    $_SESSION['salesorderlinedetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField13']));
    $_SESSION['salesorderlinedetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField14']));
    $_SESSION['salesorderlinedetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['CustomField15']));
    $_SESSION['salesorderlinedetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['IDKEY']));
    $_SESSION['salesorderlinedetail']['GroupIDKEY'] = htmlspecialchars(strip_tags($_SESSION['salesorderlinedetail']['GroupIDKEY']));
}

function genLimpia_salesorder() {
    $_SESSION['salesorder']['TxnID'] = ' ';
    $_SESSION['salesorder']['TimeCreated'] = ' ';
    $_SESSION['salesorder']['TimeModified'] = ' ';
    $_SESSION['salesorder']['EditSequence'] = ' ';
    $_SESSION['salesorder']['TxnNumber'] = ' ';
    $_SESSION['salesorder']['CustomerRef_ListID'] = ' ';
    $_SESSION['salesorder']['CustomerRef_FullName'] = ' ';
    $_SESSION['salesorder']['ClassRef_ListID'] = ' ';
    $_SESSION['salesorder']['ClassRef_FullName'] = ' ';
    $_SESSION['salesorder']['TemplateRef_ListID'] = ' ';
    $_SESSION['salesorder']['TemplateRef_FullName'] = ' ';
    $_SESSION['salesorder']['TxnDate'] = ' ';
    $_SESSION['salesorder']['RefNumber'] = ' ';
    $_SESSION['salesorder']['BillAddress_Addr1'] = ' ';
    $_SESSION['salesorder']['BillAddress_Addr2'] = ' ';
    $_SESSION['salesorder']['BillAddress_Addr3'] = ' ';
    $_SESSION['salesorder']['BillAddress_Addr4'] = ' ';
    $_SESSION['salesorder']['BillAddress_Addr5'] = ' ';
    $_SESSION['salesorder']['BillAddress_City'] = ' ';
    $_SESSION['salesorder']['BillAddress_State'] = ' ';
    $_SESSION['salesorder']['BillAddress_PostalCode'] = ' ';
    $_SESSION['salesorder']['BillAddress_Country'] = ' ';
    $_SESSION['salesorder']['BillAddress_Note'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Addr1'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Addr2'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Addr3'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Addr4'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Addr5'] = ' ';
    $_SESSION['salesorder']['ShipAddress_City'] = ' ';
    $_SESSION['salesorder']['ShipAddress_State'] = ' ';
    $_SESSION['salesorder']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Country'] = ' ';
    $_SESSION['salesorder']['ShipAddress_Note'] = ' ';
    $_SESSION['salesorder']['PONumber'] = ' ';
    $_SESSION['salesorder']['TermsRef_ListID'] = ' ';
    $_SESSION['salesorder']['TermsRef_FullName'] = ' ';
    $_SESSION['salesorder']['DueDate'] = '2010-01-01';
    $_SESSION['salesorder']['SalesRepRef_ListID'] = ' ';
    $_SESSION['salesorder']['SalesRepRef_FullName'] = ' ';
    $_SESSION['salesorder']['FOB'] = 0;
    $_SESSION['salesorder']['ShipDate'] = '2010-01-01';
    $_SESSION['salesorder']['ShipMethodRef_ListID'] = ' ';
    $_SESSION['salesorder']['ShipMethodRef_FullName'] = ' ';
    $_SESSION['salesorder']['Subtotal'] = 0;
    $_SESSION['salesorder']['ItemSalesTaxRef_ListID'] = ' ';
    $_SESSION['salesorder']['ItemSalesTaxRef_FullName'] = ' ';
    $_SESSION['salesorder']['SalesTaxPercentage'] = 0;
    $_SESSION['salesorder']['SalesTaxTotal'] = 0;
    $_SESSION['salesorder']['TotalAmount'] = 0;
    $_SESSION['salesorder']['CurrencyRef_ListID'] = ' ';
    $_SESSION['salesorder']['CurrencyRef_FullName'] = ' ';
    $_SESSION['salesorder']['ExchangeRate'] = 0;
    $_SESSION['salesorder']['TotalAmountInHomeCurrency'] = 0;
    $_SESSION['salesorder']['IsManuallyClosed'] = 'false';
    $_SESSION['salesorder']['IsFullyInvoiced'] = 'false';
    $_SESSION['salesorder']['Memo'] = ' ';
    $_SESSION['salesorder']['CustomerMsgRef_ListID'] = ' ';
    $_SESSION['salesorder']['CustomerMsgRef_FullName'] = ' ';
    $_SESSION['salesorder']['IsToBePrinted'] = 'false';
    $_SESSION['salesorder']['IsToBeEmailed'] = 'false';
    $_SESSION['salesorder']['IsTaxIncluded'] = 'false';
    $_SESSION['salesorder']['CustomerSalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['salesorder']['CustomerSalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['salesorder']['Other'] = ' ';
    $_SESSION['salesorder']['LinkedTxn'] = ' ';
    $_SESSION['salesorder']['CustomField1'] = 'SIN RUTA';
    $_SESSION['salesorder']['CustomField2'] = ' ';
    $_SESSION['salesorder']['CustomField3'] = ' ';
    $_SESSION['salesorder']['CustomField4'] = ' ';
    $_SESSION['salesorder']['CustomField5'] = ' ';
    $_SESSION['salesorder']['CustomField6'] = ' ';
    $_SESSION['salesorder']['CustomField7'] = ' ';
    $_SESSION['salesorder']['CustomField8'] = ' ';
    $_SESSION['salesorder']['CustomField9'] = ' ';
    $_SESSION['salesorder']['CustomField10'] = 'SIN IMPRIMIR';
    $_SESSION['salesorder']['CustomField11'] = ' ';
    $_SESSION['salesorder']['CustomField12'] = ' ';
    $_SESSION['salesorder']['CustomField13'] = ' ';
    $_SESSION['salesorder']['CustomField14'] = ' ';
    $_SESSION['salesorder']['CustomField15'] = 'SIN FIRMAR';
    $_SESSION['salesorder']['Status'] = ' ';
}

function adiciona_salesorder($db) {
    $estado = 'INIT';
    try {
        $sql = 'INSERT INTO salesorder (  TxnID, TimeCreated, TimeModified, EditSequence, TxnNumber, CustomerRef_ListID, CustomerRef_FullName, ClassRef_ListID, ClassRef_FullName, TemplateRef_ListID, TemplateRef_FullName, TxnDate, RefNumber, BillAddress_Addr1, BillAddress_Addr2, BillAddress_Addr3, BillAddress_Addr4, BillAddress_Addr5, BillAddress_City, BillAddress_State, BillAddress_PostalCode, BillAddress_Country, BillAddress_Note, ShipAddress_Addr1, ShipAddress_Addr2, ShipAddress_Addr3, ShipAddress_Addr4, ShipAddress_Addr5, ShipAddress_City, ShipAddress_State, ShipAddress_PostalCode, ShipAddress_Country, ShipAddress_Note, PONumber, TermsRef_ListID, TermsRef_FullName, DueDate, SalesRepRef_ListID, SalesRepRef_FullName, FOB, ShipDate, ShipMethodRef_ListID, ShipMethodRef_FullName, Subtotal, ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName, SalesTaxPercentage, SalesTaxTotal, TotalAmount, CurrencyRef_ListID, CurrencyRef_FullName, ExchangeRate, TotalAmountInHomeCurrency, IsManuallyClosed, IsFullyInvoiced, Memo, CustomerMsgRef_ListID, CustomerMsgRef_FullName, IsToBePrinted, IsToBeEmailed, IsTaxIncluded, CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName, Other, LinkedTxn, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnNumber, :CustomerRef_ListID, :CustomerRef_FullName, :ClassRef_ListID, :ClassRef_FullName, :TemplateRef_ListID, :TemplateRef_FullName, :TxnDate, :RefNumber, :BillAddress_Addr1, :BillAddress_Addr2, :BillAddress_Addr3, :BillAddress_Addr4, :BillAddress_Addr5, :BillAddress_City, :BillAddress_State, :BillAddress_PostalCode, :BillAddress_Country, :BillAddress_Note, :ShipAddress_Addr1, :ShipAddress_Addr2, :ShipAddress_Addr3, :ShipAddress_Addr4, :ShipAddress_Addr5, :ShipAddress_City, :ShipAddress_State, :ShipAddress_PostalCode, :ShipAddress_Country, :ShipAddress_Note, :PONumber, :TermsRef_ListID, :TermsRef_FullName, :DueDate, :SalesRepRef_ListID, :SalesRepRef_FullName, :FOB, :ShipDate, :ShipMethodRef_ListID, :ShipMethodRef_FullName, :Subtotal, :ItemSalesTaxRef_ListID, :ItemSalesTaxRef_FullName, :SalesTaxPercentage, :SalesTaxTotal, :TotalAmount, :CurrencyRef_ListID, :CurrencyRef_FullName, :ExchangeRate, :TotalAmountInHomeCurrency, :IsManuallyClosed, :IsFullyInvoiced, :Memo, :CustomerMsgRef_ListID, :CustomerMsgRef_FullName, :IsToBePrinted, :IsToBeEmailed, :IsTaxIncluded, :CustomerSalesTaxCodeRef_ListID, :CustomerSalesTaxCodeRef_FullName, :Other, :LinkedTxn, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['salesorder']['TxnID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['salesorder']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['salesorder']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['salesorder']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['salesorder']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['salesorder']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['salesorder']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['salesorder']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['salesorder']['ClassRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['salesorder']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['salesorder']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['salesorder']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['salesorder']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['salesorder']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['salesorder']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['salesorder']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['salesorder']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['salesorder']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['salesorder']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['salesorder']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['salesorder']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['salesorder']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['salesorder']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['salesorder']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['salesorder']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['salesorder']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['salesorder']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['salesorder']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['salesorder']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['salesorder']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['salesorder']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['salesorder']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['salesorder']['ShipAddress_Note']);
        $stmt->bindParam(':PONumber', $_SESSION['salesorder']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['salesorder']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['salesorder']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['salesorder']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['salesorder']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['salesorder']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['salesorder']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['salesorder']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['salesorder']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['salesorder']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['salesorder']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['salesorder']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['salesorder']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['salesorder']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['salesorder']['SalesTaxTotal']);
        $stmt->bindParam(':TotalAmount', $_SESSION['salesorder']['TotalAmount']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['salesorder']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['salesorder']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['salesorder']['ExchangeRate']);
        $stmt->bindParam(':TotalAmountInHomeCurrency', $_SESSION['salesorder']['TotalAmountInHomeCurrency']);
        $stmt->bindParam(':IsManuallyClosed', $_SESSION['salesorder']['IsManuallyClosed']);
        $stmt->bindParam(':IsFullyInvoiced', $_SESSION['salesorder']['IsFullyInvoiced']);
        $stmt->bindParam(':Memo', $_SESSION['salesorder']['Memo']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['salesorder']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['salesorder']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['salesorder']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['salesorder']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['salesorder']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['salesorder']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['salesorder']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other', $_SESSION['salesorder']['Other']);
        $stmt->bindParam(':LinkedTxn', $_SESSION['salesorder']['LinkedTxn']);
        $stmt->bindParam(':CustomField1', $_SESSION['salesorder']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['salesorder']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['salesorder']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['salesorder']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['salesorder']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['salesorder']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['salesorder']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['salesorder']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['salesorder']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['salesorder']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['salesorder']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['salesorder']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['salesorder']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['salesorder']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['salesorder']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['salesorder']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['salesorder']['TxnID'];
    }
    return $estado;
}

function quitaslashes_salesorder() {
    $_SESSION['salesorder']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TxnID']));
    $_SESSION['salesorder']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['salesorder']['TimeCreated']));
    $_SESSION['salesorder']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['salesorder']['TimeModified']));
    $_SESSION['salesorder']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['EditSequence']));
    $_SESSION['salesorder']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TxnNumber']));
    $_SESSION['salesorder']['CustomerRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerRef_ListID']));
    $_SESSION['salesorder']['CustomerRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerRef_FullName']));
    $_SESSION['salesorder']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ClassRef_ListID']));
    $_SESSION['salesorder']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ClassRef_FullName']));
    $_SESSION['salesorder']['TemplateRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TemplateRef_ListID']));
    $_SESSION['salesorder']['TemplateRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TemplateRef_FullName']));
    $_SESSION['salesorder']['TxnDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['salesorder']['TxnDate']));
    $_SESSION['salesorder']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['RefNumber']));
    $_SESSION['salesorder']['BillAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Addr1']));
    $_SESSION['salesorder']['BillAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Addr2']));
    $_SESSION['salesorder']['BillAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Addr3']));
    $_SESSION['salesorder']['BillAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Addr4']));
    $_SESSION['salesorder']['BillAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Addr5']));
    $_SESSION['salesorder']['BillAddress_City'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_City']));
    $_SESSION['salesorder']['BillAddress_State'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_State']));
    $_SESSION['salesorder']['BillAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_PostalCode']));
    $_SESSION['salesorder']['BillAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Country']));
    $_SESSION['salesorder']['BillAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['BillAddress_Note']));
    $_SESSION['salesorder']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Addr1']));
    $_SESSION['salesorder']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Addr2']));
    $_SESSION['salesorder']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Addr3']));
    $_SESSION['salesorder']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Addr4']));
    $_SESSION['salesorder']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Addr5']));
    $_SESSION['salesorder']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_City']));
    $_SESSION['salesorder']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_State']));
    $_SESSION['salesorder']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_PostalCode']));
    $_SESSION['salesorder']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Country']));
    $_SESSION['salesorder']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipAddress_Note']));
    $_SESSION['salesorder']['PONumber'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['PONumber']));
    $_SESSION['salesorder']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TermsRef_ListID']));
    $_SESSION['salesorder']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TermsRef_FullName']));
    $_SESSION['salesorder']['DueDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['salesorder']['DueDate']));
    $_SESSION['salesorder']['SalesRepRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['SalesRepRef_ListID']));
    $_SESSION['salesorder']['SalesRepRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['SalesRepRef_FullName']));
    $_SESSION['salesorder']['FOB'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['FOB']));
    $_SESSION['salesorder']['ShipDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['salesorder']['ShipDate']));
    $_SESSION['salesorder']['ShipMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipMethodRef_ListID']));
    $_SESSION['salesorder']['ShipMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ShipMethodRef_FullName']));
    $_SESSION['salesorder']['Subtotal'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['Subtotal']));
    $_SESSION['salesorder']['ItemSalesTaxRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ItemSalesTaxRef_ListID']));
    $_SESSION['salesorder']['ItemSalesTaxRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ItemSalesTaxRef_FullName']));
    $_SESSION['salesorder']['SalesTaxPercentage'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['SalesTaxPercentage']));
    $_SESSION['salesorder']['SalesTaxTotal'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['SalesTaxTotal']));
    $_SESSION['salesorder']['TotalAmount'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TotalAmount']));
    $_SESSION['salesorder']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CurrencyRef_ListID']));
    $_SESSION['salesorder']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CurrencyRef_FullName']));
    $_SESSION['salesorder']['ExchangeRate'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['ExchangeRate']));
    $_SESSION['salesorder']['TotalAmountInHomeCurrency'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['TotalAmountInHomeCurrency']));
    $_SESSION['salesorder']['IsManuallyClosed'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['IsManuallyClosed']));
    $_SESSION['salesorder']['IsFullyInvoiced'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['IsFullyInvoiced']));
    $_SESSION['salesorder']['Memo'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['Memo']));
    $_SESSION['salesorder']['CustomerMsgRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerMsgRef_ListID']));
    $_SESSION['salesorder']['CustomerMsgRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerMsgRef_FullName']));
    $_SESSION['salesorder']['IsToBePrinted'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['IsToBePrinted']));
    $_SESSION['salesorder']['IsToBeEmailed'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['IsToBeEmailed']));
    $_SESSION['salesorder']['IsTaxIncluded'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['IsTaxIncluded']));
    $_SESSION['salesorder']['CustomerSalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerSalesTaxCodeRef_ListID']));
    $_SESSION['salesorder']['CustomerSalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomerSalesTaxCodeRef_FullName']));
    $_SESSION['salesorder']['Other'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['Other']));
    $_SESSION['salesorder']['LinkedTxn'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['LinkedTxn']));
    $_SESSION['salesorder']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField1']));
    $_SESSION['salesorder']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField2']));
    $_SESSION['salesorder']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField3']));
    $_SESSION['salesorder']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField4']));
    $_SESSION['salesorder']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField5']));
    $_SESSION['salesorder']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField6']));
    $_SESSION['salesorder']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField7']));
    $_SESSION['salesorder']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField8']));
    $_SESSION['salesorder']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField9']));
    $_SESSION['salesorder']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField10']));
    $_SESSION['salesorder']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField11']));
    $_SESSION['salesorder']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField12']));
    $_SESSION['salesorder']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField13']));
    $_SESSION['salesorder']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField14']));
    $_SESSION['salesorder']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['CustomField15']));
    $_SESSION['salesorder']['Status'] = htmlspecialchars(strip_tags($_SESSION['salesorder']['Status']));
}

function actualiza_salesorder($db) {
    $estado = 'INIT';
    try {
        $sql = 'UPDATE salesorder SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, TxnNumber=:TxnNumber, CustomerRef_ListID=:CustomerRef_ListID, CustomerRef_FullName=:CustomerRef_FullName, ClassRef_ListID=:ClassRef_ListID, ClassRef_FullName=:ClassRef_FullName, TemplateRef_ListID=:TemplateRef_ListID, TemplateRef_FullName=:TemplateRef_FullName, TxnDate=:TxnDate, RefNumber=:RefNumber, BillAddress_Addr1=:BillAddress_Addr1, BillAddress_Addr2=:BillAddress_Addr2, BillAddress_Addr3=:BillAddress_Addr3, BillAddress_Addr4=:BillAddress_Addr4, BillAddress_Addr5=:BillAddress_Addr5, BillAddress_City=:BillAddress_City, BillAddress_State=:BillAddress_State, BillAddress_PostalCode=:BillAddress_PostalCode, BillAddress_Country=:BillAddress_Country, BillAddress_Note=:BillAddress_Note, ShipAddress_Addr1=:ShipAddress_Addr1, ShipAddress_Addr2=:ShipAddress_Addr2, ShipAddress_Addr3=:ShipAddress_Addr3, ShipAddress_Addr4=:ShipAddress_Addr4, ShipAddress_Addr5=:ShipAddress_Addr5, ShipAddress_City=:ShipAddress_City, ShipAddress_State=:ShipAddress_State, ShipAddress_PostalCode=:ShipAddress_PostalCode, ShipAddress_Country=:ShipAddress_Country, ShipAddress_Note=:ShipAddress_Note, PONumber=:PONumber, TermsRef_ListID=:TermsRef_ListID, TermsRef_FullName=:TermsRef_FullName, DueDate=:DueDate, SalesRepRef_ListID=:SalesRepRef_ListID, SalesRepRef_FullName=:SalesRepRef_FullName, FOB=:FOB, ShipDate=:ShipDate, ShipMethodRef_ListID=:ShipMethodRef_ListID, ShipMethodRef_FullName=:ShipMethodRef_FullName, Subtotal=:Subtotal, ItemSalesTaxRef_ListID=:ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName=:ItemSalesTaxRef_FullName, SalesTaxPercentage=:SalesTaxPercentage, SalesTaxTotal=:SalesTaxTotal, TotalAmount=:TotalAmount, CurrencyRef_ListID=:CurrencyRef_ListID, CurrencyRef_FullName=:CurrencyRef_FullName, ExchangeRate=:ExchangeRate, TotalAmountInHomeCurrency=:TotalAmountInHomeCurrency, IsManuallyClosed=:IsManuallyClosed, IsFullyInvoiced=:IsFullyInvoiced, Memo=:Memo, CustomerMsgRef_ListID=:CustomerMsgRef_ListID, CustomerMsgRef_FullName=:CustomerMsgRef_FullName, IsToBePrinted=:IsToBePrinted, IsToBeEmailed=:IsToBeEmailed, IsTaxIncluded=:IsTaxIncluded, CustomerSalesTaxCodeRef_ListID=:CustomerSalesTaxCodeRef_ListID, CustomerSalesTaxCodeRef_FullName=:CustomerSalesTaxCodeRef_FullName, Other=:Other, LinkedTxn=:LinkedTxn, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['salesorder']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['salesorder']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['salesorder']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['salesorder']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['salesorder']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['salesorder']['CustomerRef_FullName']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['salesorder']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['salesorder']['ClassRef_FullName']);
        $stmt->bindParam(':TemplateRef_ListID', $_SESSION['salesorder']['TemplateRef_ListID']);
        $stmt->bindParam(':TemplateRef_FullName', $_SESSION['salesorder']['TemplateRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['salesorder']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['salesorder']['RefNumber']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['salesorder']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['salesorder']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['salesorder']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['salesorder']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['salesorder']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['salesorder']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['salesorder']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['salesorder']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['salesorder']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['salesorder']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['salesorder']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['salesorder']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['salesorder']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['salesorder']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['salesorder']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['salesorder']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['salesorder']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['salesorder']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['salesorder']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['salesorder']['ShipAddress_Note']);
        $stmt->bindParam(':PONumber', $_SESSION['salesorder']['PONumber']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['salesorder']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['salesorder']['TermsRef_FullName']);
        $stmt->bindParam(':DueDate', $_SESSION['salesorder']['DueDate']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['salesorder']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['salesorder']['SalesRepRef_FullName']);
        $stmt->bindParam(':FOB', $_SESSION['salesorder']['FOB']);
        $stmt->bindParam(':ShipDate', $_SESSION['salesorder']['ShipDate']);
        $stmt->bindParam(':ShipMethodRef_ListID', $_SESSION['salesorder']['ShipMethodRef_ListID']);
        $stmt->bindParam(':ShipMethodRef_FullName', $_SESSION['salesorder']['ShipMethodRef_FullName']);
        $stmt->bindParam(':Subtotal', $_SESSION['salesorder']['Subtotal']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['salesorder']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['salesorder']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxPercentage', $_SESSION['salesorder']['SalesTaxPercentage']);
        $stmt->bindParam(':SalesTaxTotal', $_SESSION['salesorder']['SalesTaxTotal']);
        $stmt->bindParam(':TotalAmount', $_SESSION['salesorder']['TotalAmount']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['salesorder']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['salesorder']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['salesorder']['ExchangeRate']);
        $stmt->bindParam(':TotalAmountInHomeCurrency', $_SESSION['salesorder']['TotalAmountInHomeCurrency']);
        $stmt->bindParam(':IsManuallyClosed', $_SESSION['salesorder']['IsManuallyClosed']);
        $stmt->bindParam(':IsFullyInvoiced', $_SESSION['salesorder']['IsFullyInvoiced']);
        $stmt->bindParam(':Memo', $_SESSION['salesorder']['Memo']);
        $stmt->bindParam(':CustomerMsgRef_ListID', $_SESSION['salesorder']['CustomerMsgRef_ListID']);
        $stmt->bindParam(':CustomerMsgRef_FullName', $_SESSION['salesorder']['CustomerMsgRef_FullName']);
        $stmt->bindParam(':IsToBePrinted', $_SESSION['salesorder']['IsToBePrinted']);
        $stmt->bindParam(':IsToBeEmailed', $_SESSION['salesorder']['IsToBeEmailed']);
        $stmt->bindParam(':IsTaxIncluded', $_SESSION['salesorder']['IsTaxIncluded']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_ListID', $_SESSION['salesorder']['CustomerSalesTaxCodeRef_ListID']);
        $stmt->bindParam(':CustomerSalesTaxCodeRef_FullName', $_SESSION['salesorder']['CustomerSalesTaxCodeRef_FullName']);
        $stmt->bindParam(':Other', $_SESSION['salesorder']['Other']);
        $stmt->bindParam(':LinkedTxn', $_SESSION['salesorder']['LinkedTxn']);
        $stmt->bindParam(':CustomField1', $_SESSION['salesorder']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['salesorder']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['salesorder']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['salesorder']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['salesorder']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['salesorder']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['salesorder']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['salesorder']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['salesorder']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['salesorder']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['salesorder']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['salesorder']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['salesorder']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['salesorder']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['salesorder']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['salesorder']['Status']);
        $stmt->bindParam(':clave', $_SESSION['salesorder']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['salesorder']['TxnID'];
    }
    return $estado;
}
