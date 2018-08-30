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
define('QB_PRIORITY_RECEIVEPAYMENT', 1);
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
$fecha = date("Y-m-d", strtotime($registro['productionDesde']));
define("AU_DESDE", $fecha);
$fecha = date("Y-m-d", strtotime($registro['productionHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);
$db = null;
/**
 *       sigue el programa como funciona en los ejemplos
 */
$map = array(
   QUICKBOOKS_IMPORT_RECEIVEPAYMENT => array('_quickbooks_pagos_import_request', '_quickbooks_pagos_import_response'),
   QUICKBOOKS_ADD_INVOICE => array('_quickbooks_invoice_add_request', '_quickbooks_invoice_add_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_RECEIVEPAYMENT)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_RECEIVEPAYMENT, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_RECEIVEPAYMENT, 1, QB_PRIORITY_RECEIVEPAYMENT, NULL, $user);
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

function _quickbooks_pagos_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_pagos_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<ReceivePaymentQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <TxnDateRangeFilter>
                                <FromTxnDate >' . AU_DESDE . '</FromTxnDate>
                                <ToTxnDate >' . AU_HASTA . '</ToTxnDate>
                            </TxnDateRangeFilter>
                            <IncludeLineItems>true</IncludeLineItems>
                            <OwnerID>0</OwnerID>
			</ReceivePaymentQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("pagos2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_pagos_initial_response() {
    $myfile = fopen("pagos1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Se ha dejado limpia la tabla de facturas de venta");
    fclose($myfile);
}

function _quickbooks_pagos_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, null, QB_PRIORITY_INVOICE, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("pagos3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['receivepayment'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("payments.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "ReceivePaymentRet";
    $pago = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($pago as $uno) {
        $estado = "INIT";
        genLimpia_receivepayment();
        gentraverse_receivepayment($uno);
        $estado = buscaIgual_receivepayment($db);
        if ($estado === 'OK') {
            quitaslashes_receivepayment();
            fwrite($myfile, "NO!!! Existe pago " . $_SESSION['receivepayment']['TxnID'] . " \r\n");
            $estado = adiciona_receivepayment($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_receivepayment();
            fwrite($myfile, "Existe pago " . $_SESSION['receivepayment']['TxnID'] . " \r\n");
            $estado = actualiza_receivepayment($db);
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso a la cabecera del pago " . $estado . " id " . $_SESSION['receivepayment']['TxnID'] . " \r\n");
        }
    }

    $param = "AppliedToTxnRet";
    $detalle = $doc->getElementsByTagName($param);
    foreach ($detalle as $uno) {
        $estado = "INIT";
        genLimpia_paymentdetail();
        gentraverse_paymentdetail($uno);
        $existe = buscaIgual_paymentdetail($db);
        if ($existe == "OK") {
            quitaslashes_paymentdetail();
            $estado = adiciona_paymentdetail($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_paymentdetail();
            $estado = actualiza_paymentdetail($db);
        } else {
            fwrite($myfile, "Errores cuando lee detalle del pago  " . $_SESSION['paymentdetail']['TxnLineID'] . " \r\n");
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso a la tabla del detalle del pago " . $estado . " id " . $_SESSION['paymentdetail']['TxnLineID'] . " \r\n");
        }
    }

    fclose($myfile);
    fclose($myfile1);
    $sql = 'DELETE FROM receivepayment WHERE Memo = "VOID:"';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $db = null;
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

function genLimpia_receivepayment() {
    $_SESSION['receivepayment']['TxnID'] = ' ';
    $_SESSION['receivepayment']['TimeCreated'] = ' ';
    $_SESSION['receivepayment']['TimeModified'] = ' ';
    $_SESSION['receivepayment']['EditSequence'] = ' ';
    $_SESSION['receivepayment']['TxnNumber'] = ' ';
    $_SESSION['receivepayment']['CustomerRef_ListID'] = ' ';
    $_SESSION['receivepayment']['CustomerRef_FullName'] = ' ';
    $_SESSION['receivepayment']['ARAccountRef_ListID'] = ' ';
    $_SESSION['receivepayment']['ARAccountRef_FullName'] = ' ';
    $_SESSION['receivepayment']['TxnDate'] = ' ';
    $_SESSION['receivepayment']['RefNumber'] = ' ';
    $_SESSION['receivepayment']['TotalAmount'] = ' ';
    $_SESSION['receivepayment']['CurrencyRef_ListID'] = ' ';
    $_SESSION['receivepayment']['CurrencyRef_FullName'] = ' ';
    $_SESSION['receivepayment']['ExchangeRate'] = ' ';
    $_SESSION['receivepayment']['TotalAmountInHomeCurrency'] = ' ';
    $_SESSION['receivepayment']['PaymentMethodRef_ListID'] = ' ';
    $_SESSION['receivepayment']['PaymentMethodRef_FullName'] = ' ';
    $_SESSION['receivepayment']['Memo'] = ' ';
    $_SESSION['receivepayment']['DepositToAccountRef_ListID'] = ' ';
    $_SESSION['receivepayment']['DepositToAccountRef_FullName'] = ' ';
    $_SESSION['receivepayment']['UnusedPayment'] = ' ';
    $_SESSION['receivepayment']['UnusedCredits'] = ' ';
    $_SESSION['receivepayment']['CustomField1'] = ' ';
    $_SESSION['receivepayment']['CustomField2'] = ' ';
    $_SESSION['receivepayment']['CustomField3'] = ' ';
    $_SESSION['receivepayment']['CustomField4'] = ' ';
    $_SESSION['receivepayment']['CustomField5'] = ' ';
    $_SESSION['receivepayment']['CustomField6'] = ' ';
    $_SESSION['receivepayment']['CustomField7'] = ' ';
    $_SESSION['receivepayment']['CustomField8'] = ' ';
    $_SESSION['receivepayment']['CustomField9'] = ' ';
    $_SESSION['receivepayment']['CustomField10'] = ' ';
    $_SESSION['receivepayment']['CustomField11'] = ' ';
    $_SESSION['receivepayment']['CustomField12'] = ' ';
    $_SESSION['receivepayment']['CustomField13'] = ' ';
    $_SESSION['receivepayment']['CustomField14'] = ' ';
    $_SESSION['receivepayment']['CustomField15'] = ' ';
    $_SESSION['receivepayment']['Status'] = ' ';
}

function gentraverse_receivepayment($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['receivepayment']['TxnID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['receivepayment']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['receivepayment']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['receivepayment']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'TxnNumber':
                    $_SESSION['receivepayment']['TxnNumber'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['receivepayment']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['receivepayment']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'TotalAmount':
                    $_SESSION['receivepayment']['TotalAmount'] = $nivel1->nodeValue;
                    break;
                case 'Memo':
                    $_SESSION['receivepayment']['Memo'] = $nivel1->nodeValue;
                    break;
                case 'UnusedPayment':
                    $_SESSION['receivepayment']['UnusedPayment'] = $nivel1->nodeValue;
                    break;
                case 'UnusedCredits':
                    $_SESSION['receivepayment']['UnusedCredits'] = $nivel1->nodeValue;
                    break;
                case 'CustomerRef':
                case 'PaymentMethodRef':
                case 'ARAccountRef':
                case 'DepositToAccountRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'CustomerRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['receivepayment']['CustomerRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['receivepayment']['CustomerRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PaymentMethodRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['receivepayment']['PaymentMethodRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['receivepayment']['PaymentMethodRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ARAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['receivepayment']['ARAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['receivepayment']['ARAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'DepositToAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['receivepayment']['DepositToAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['receivepayment']['DepositToAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function buscaIgual_receivepayment($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM receivepayment WHERE TxnID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['receivepayment']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['TxnID'] === $_SESSION['receivepayment']['TxnID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }

    return $estado;
}

function quitaslashes_receivepayment() {
    $_SESSION['receivepayment']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['TxnID']));
    $_SESSION['receivepayment']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['receivepayment']['TimeCreated']));
    $_SESSION['receivepayment']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['receivepayment']['TimeModified']));
    $_SESSION['receivepayment']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['EditSequence']));
    $_SESSION['receivepayment']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['TxnNumber']));
    $_SESSION['receivepayment']['CustomerRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomerRef_ListID']));
    $_SESSION['receivepayment']['CustomerRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomerRef_FullName']));
    $_SESSION['receivepayment']['ARAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['ARAccountRef_ListID']));
    $_SESSION['receivepayment']['ARAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['ARAccountRef_FullName']));
    $_SESSION['receivepayment']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['TxnDate']));
    $_SESSION['receivepayment']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['RefNumber']));
    $_SESSION['receivepayment']['TotalAmount'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['TotalAmount']));
    $_SESSION['receivepayment']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CurrencyRef_ListID']));
    $_SESSION['receivepayment']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CurrencyRef_FullName']));
    $_SESSION['receivepayment']['ExchangeRate'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['ExchangeRate']));
    $_SESSION['receivepayment']['TotalAmountInHomeCurrency'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['TotalAmountInHomeCurrency']));
    $_SESSION['receivepayment']['PaymentMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['PaymentMethodRef_ListID']));
    $_SESSION['receivepayment']['PaymentMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['PaymentMethodRef_FullName']));
    $_SESSION['receivepayment']['Memo'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['Memo']));
    $_SESSION['receivepayment']['DepositToAccountRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['DepositToAccountRef_ListID']));
    $_SESSION['receivepayment']['DepositToAccountRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['DepositToAccountRef_FullName']));
    $_SESSION['receivepayment']['UnusedPayment'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['UnusedPayment']));
    $_SESSION['receivepayment']['UnusedCredits'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['UnusedCredits']));
    $_SESSION['receivepayment']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField1']));
    $_SESSION['receivepayment']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField2']));
    $_SESSION['receivepayment']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField3']));
    $_SESSION['receivepayment']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField4']));
    $_SESSION['receivepayment']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField5']));
    $_SESSION['receivepayment']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField6']));
    $_SESSION['receivepayment']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField7']));
    $_SESSION['receivepayment']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField8']));
    $_SESSION['receivepayment']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField9']));
    $_SESSION['receivepayment']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField10']));
    $_SESSION['receivepayment']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField11']));
    $_SESSION['receivepayment']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField12']));
    $_SESSION['receivepayment']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField13']));
    $_SESSION['receivepayment']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField14']));
    $_SESSION['receivepayment']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['CustomField15']));
    $_SESSION['receivepayment']['Status'] = htmlspecialchars(strip_tags($_SESSION['receivepayment']['Status']));
}

function adiciona_receivepayment($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO receivepayment (  TxnID, TimeCreated, TimeModified, EditSequence, TxnNumber, CustomerRef_ListID, CustomerRef_FullName, ARAccountRef_ListID, ARAccountRef_FullName, TxnDate, RefNumber, TotalAmount, CurrencyRef_ListID, CurrencyRef_FullName, ExchangeRate, TotalAmountInHomeCurrency, PaymentMethodRef_ListID, PaymentMethodRef_FullName, Memo, DepositToAccountRef_ListID, DepositToAccountRef_FullName, UnusedPayment, UnusedCredits, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnNumber, :CustomerRef_ListID, :CustomerRef_FullName, :ARAccountRef_ListID, :ARAccountRef_FullName, :TxnDate, :RefNumber, :TotalAmount, :CurrencyRef_ListID, :CurrencyRef_FullName, :ExchangeRate, :TotalAmountInHomeCurrency, :PaymentMethodRef_ListID, :PaymentMethodRef_FullName, :Memo, :DepositToAccountRef_ListID, :DepositToAccountRef_FullName, :UnusedPayment, :UnusedCredits, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['receivepayment']['TxnID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['receivepayment']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['receivepayment']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['receivepayment']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['receivepayment']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['receivepayment']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['receivepayment']['CustomerRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['receivepayment']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['receivepayment']['ARAccountRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['receivepayment']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['receivepayment']['RefNumber']);
        $stmt->bindParam(':TotalAmount', $_SESSION['receivepayment']['TotalAmount']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['receivepayment']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['receivepayment']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['receivepayment']['ExchangeRate']);
        $stmt->bindParam(':TotalAmountInHomeCurrency', $_SESSION['receivepayment']['TotalAmountInHomeCurrency']);
        $stmt->bindParam(':PaymentMethodRef_ListID', $_SESSION['receivepayment']['PaymentMethodRef_ListID']);
        $stmt->bindParam(':PaymentMethodRef_FullName', $_SESSION['receivepayment']['PaymentMethodRef_FullName']);
        $stmt->bindParam(':Memo', $_SESSION['receivepayment']['Memo']);
        $stmt->bindParam(':DepositToAccountRef_ListID', $_SESSION['receivepayment']['DepositToAccountRef_ListID']);
        $stmt->bindParam(':DepositToAccountRef_FullName', $_SESSION['receivepayment']['DepositToAccountRef_FullName']);
        $stmt->bindParam(':UnusedPayment', $_SESSION['receivepayment']['UnusedPayment']);
        $stmt->bindParam(':UnusedCredits', $_SESSION['receivepayment']['UnusedCredits']);
        $stmt->bindParam(':CustomField1', $_SESSION['receivepayment']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['receivepayment']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['receivepayment']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['receivepayment']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['receivepayment']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['receivepayment']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['receivepayment']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['receivepayment']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['receivepayment']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['receivepayment']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['receivepayment']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['receivepayment']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['receivepayment']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['receivepayment']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['receivepayment']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['receivepayment']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['receivepayment']['TxnID'];
    }
}

function actualiza_receivepayment($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE receivepayment SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, TxnNumber=:TxnNumber, CustomerRef_ListID=:CustomerRef_ListID, CustomerRef_FullName=:CustomerRef_FullName, ARAccountRef_ListID=:ARAccountRef_ListID, ARAccountRef_FullName=:ARAccountRef_FullName, TxnDate=:TxnDate, RefNumber=:RefNumber, TotalAmount=:TotalAmount, CurrencyRef_ListID=:CurrencyRef_ListID, CurrencyRef_FullName=:CurrencyRef_FullName, ExchangeRate=:ExchangeRate, TotalAmountInHomeCurrency=:TotalAmountInHomeCurrency, PaymentMethodRef_ListID=:PaymentMethodRef_ListID, PaymentMethodRef_FullName=:PaymentMethodRef_FullName, Memo=:Memo, DepositToAccountRef_ListID=:DepositToAccountRef_ListID, DepositToAccountRef_FullName=:DepositToAccountRef_FullName, UnusedPayment=:UnusedPayment, UnusedCredits=:UnusedCredits, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['receivepayment']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['receivepayment']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['receivepayment']['EditSequence']);
        $stmt->bindParam(':TxnNumber', $_SESSION['receivepayment']['TxnNumber']);
        $stmt->bindParam(':CustomerRef_ListID', $_SESSION['receivepayment']['CustomerRef_ListID']);
        $stmt->bindParam(':CustomerRef_FullName', $_SESSION['receivepayment']['CustomerRef_FullName']);
        $stmt->bindParam(':ARAccountRef_ListID', $_SESSION['receivepayment']['ARAccountRef_ListID']);
        $stmt->bindParam(':ARAccountRef_FullName', $_SESSION['receivepayment']['ARAccountRef_FullName']);
        $stmt->bindParam(':TxnDate', $_SESSION['receivepayment']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['receivepayment']['RefNumber']);
        $stmt->bindParam(':TotalAmount', $_SESSION['receivepayment']['TotalAmount']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['receivepayment']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['receivepayment']['CurrencyRef_FullName']);
        $stmt->bindParam(':ExchangeRate', $_SESSION['receivepayment']['ExchangeRate']);
        $stmt->bindParam(':TotalAmountInHomeCurrency', $_SESSION['receivepayment']['TotalAmountInHomeCurrency']);
        $stmt->bindParam(':PaymentMethodRef_ListID', $_SESSION['receivepayment']['PaymentMethodRef_ListID']);
        $stmt->bindParam(':PaymentMethodRef_FullName', $_SESSION['receivepayment']['PaymentMethodRef_FullName']);
        $stmt->bindParam(':Memo', $_SESSION['receivepayment']['Memo']);
        $stmt->bindParam(':DepositToAccountRef_ListID', $_SESSION['receivepayment']['DepositToAccountRef_ListID']);
        $stmt->bindParam(':DepositToAccountRef_FullName', $_SESSION['receivepayment']['DepositToAccountRef_FullName']);
        $stmt->bindParam(':UnusedPayment', $_SESSION['receivepayment']['UnusedPayment']);
        $stmt->bindParam(':UnusedCredits', $_SESSION['receivepayment']['UnusedCredits']);
        $stmt->bindParam(':CustomField1', $_SESSION['receivepayment']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['receivepayment']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['receivepayment']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['receivepayment']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['receivepayment']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['receivepayment']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['receivepayment']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['receivepayment']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['receivepayment']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['receivepayment']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['receivepayment']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['receivepayment']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['receivepayment']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['receivepayment']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['receivepayment']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['receivepayment']['Status']);
        $stmt->bindParam(':clave', $_SESSION['receivepayment']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['receivepayment']['TxnID'];
    }
}

function genLimpia_paymentdetail() {
    $_SESSION['paymentdetail']['TxnID'] = ' ';
    $_SESSION['paymentdetail']['TxnType'] = ' ';
    $_SESSION['paymentdetail']['TxnDate'] = ' ';
    $_SESSION['paymentdetail']['RefNumber'] = ' ';
    $_SESSION['paymentdetail']['TotalAmount'] = ' ';
    $_SESSION['paymentdetail']['AppliedAmount'] = ' ';
    $_SESSION['paymentdetail']['CustomField1'] = ' ';
    $_SESSION['paymentdetail']['CustomField2'] = ' ';
    $_SESSION['paymentdetail']['CustomField3'] = ' ';
    $_SESSION['paymentdetail']['CustomField4'] = ' ';
    $_SESSION['paymentdetail']['CustomField5'] = ' ';
    $_SESSION['paymentdetail']['CustomField6'] = ' ';
    $_SESSION['paymentdetail']['CustomField7'] = ' ';
    $_SESSION['paymentdetail']['CustomField8'] = ' ';
    $_SESSION['paymentdetail']['CustomField9'] = ' ';
    $_SESSION['paymentdetail']['CustomField10'] = ' ';
    $_SESSION['paymentdetail']['CustomField11'] = ' ';
    $_SESSION['paymentdetail']['CustomField12'] = ' ';
    $_SESSION['paymentdetail']['CustomField13'] = ' ';
    $_SESSION['paymentdetail']['CustomField14'] = ' ';
    $_SESSION['paymentdetail']['CustomField15'] = ' ';
    $_SESSION['paymentdetail']['IDKEY'] = ' ';
    $_SESSION['paymentdetail']['GroupIDKEY'] = ' ';
}

function gentraverse_paymentdetail($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['paymentdetail']['TxnID'] = $nivel1->nodeValue;
                    $_SESSION['paymentdetail']['IDKEY'] = $_SESSION['receivepayment']['TxnID'];
                    break;
                case 'TxnType':
                    $_SESSION['paymentdetail']['TxnType'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['paymentdetail']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['paymentdetail']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'BalanceRemaining':
                    $_SESSION['paymentdetail']['BalanceRemaining'] = $nivel1->nodeValue;
                    break;
                case 'Amount':
                    $_SESSION['paymentdetail']['Amount'] = $nivel1->nodeValue;
                    break;
            }
        }
    }
}

function quitaslashes_paymentdetail() {
    $_SESSION['paymentdetail']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['TxnID']));
    $_SESSION['paymentdetail']['TxnType'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['TxnType']));
    $_SESSION['paymentdetail']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['TxnDate']));
    $_SESSION['paymentdetail']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['RefNumber']));
    $_SESSION['paymentdetail']['TotalAmount'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['TotalAmount']));
    $_SESSION['paymentdetail']['AppliedAmount'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['AppliedAmount']));
    $_SESSION['paymentdetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField1']));
    $_SESSION['paymentdetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField2']));
    $_SESSION['paymentdetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField3']));
    $_SESSION['paymentdetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField4']));
    $_SESSION['paymentdetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField5']));
    $_SESSION['paymentdetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField6']));
    $_SESSION['paymentdetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField7']));
    $_SESSION['paymentdetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField8']));
    $_SESSION['paymentdetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField9']));
    $_SESSION['paymentdetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField10']));
    $_SESSION['paymentdetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField11']));
    $_SESSION['paymentdetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField12']));
    $_SESSION['paymentdetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField13']));
    $_SESSION['paymentdetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField14']));
    $_SESSION['paymentdetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['CustomField15']));
    $_SESSION['paymentdetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['IDKEY']));
    $_SESSION['paymentdetail']['GroupIDKEY'] = htmlspecialchars(strip_tags($_SESSION['paymentdetail']['GroupIDKEY']));
}

function adiciona_paymentdetail($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO txnpaymentlinedetail (  TxnID, TxnType, TxnDate, RefNumber, TotalAmount, AppliedAmount, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, IDKEY, GroupIDKEY) VALUES ( :TxnID, :TxnType, :TxnDate, :RefNumber, :TotalAmount, :AppliedAmount, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY, :GroupIDKEY)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['paymentdetail']['TxnID']);
        $stmt->bindParam(':TxnType', $_SESSION['paymentdetail']['TxnType']);
        $stmt->bindParam(':TxnDate', $_SESSION['paymentdetail']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['paymentdetail']['RefNumber']);
        $stmt->bindParam(':TotalAmount', $_SESSION['paymentdetail']['TotalAmount']);
        $stmt->bindParam(':AppliedAmount', $_SESSION['paymentdetail']['AppliedAmount']);
        $stmt->bindParam(':CustomField1', $_SESSION['paymentdetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['paymentdetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['paymentdetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['paymentdetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['paymentdetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['paymentdetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['paymentdetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['paymentdetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['paymentdetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['paymentdetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['paymentdetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['paymentdetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['paymentdetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['paymentdetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['paymentdetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['paymentdetail']['IDKEY']);
        $stmt->bindParam(':GroupIDKEY', $_SESSION['paymentdetail']['GroupIDKEY']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['paymentdetail']['TxnID'];
    }
}

function actualiza_paymentdetail($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE txnpaymentlinedetail SET TxnType=:TxnType, TxnDate=:TxnDate, RefNumber=:RefNumber, TotalAmount=:TotalAmount, AppliedAmount=:AppliedAmount, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15, IDKEY=:IDKEY, GroupIDKEY=:GroupIDKEY WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnType', $_SESSION['paymentdetail']['TxnType']);
        $stmt->bindParam(':TxnDate', $_SESSION['paymentdetail']['TxnDate']);
        $stmt->bindParam(':RefNumber', $_SESSION['paymentdetail']['RefNumber']);
        $stmt->bindParam(':TotalAmount', $_SESSION['paymentdetail']['TotalAmount']);
        $stmt->bindParam(':AppliedAmount', $_SESSION['paymentdetail']['AppliedAmount']);
        $stmt->bindParam(':CustomField1', $_SESSION['paymentdetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['paymentdetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['paymentdetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['paymentdetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['paymentdetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['paymentdetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['paymentdetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['paymentdetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['paymentdetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['paymentdetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['paymentdetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['paymentdetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['paymentdetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['paymentdetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['paymentdetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['paymentdetail']['IDKEY']);
        $stmt->bindParam(':GroupIDKEY', $_SESSION['paymentdetail']['GroupIDKEY']);
        $stmt->bindParam(':clave', $_SESSION['receivepayment']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = " existe un error en el programa " . $e->getMessage() . " posiblemente por aqui " . $e->getLine() . $_SESSION['paymentdetail']['TxnID'];
    }
}

function buscaIgual_paymentdetail($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM txnpaymentlinedetail WHERE TxnID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['paymentdetail']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['TxnID'] === $_SESSION['paymentdetail']['TxnID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado = 'Error accedando al detalle de pagos ' . $e->getCode() . ' Mensaje ' . $e->getMessage() . ' aproximadamente por ' . $e->getLine();
    }

    return $estado;
}
