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
define('QB_PRIORITY_PAYMENTMETHOD', 1);

$map = array(
   QUICKBOOKS_IMPORT_PAYMENTMETHOD => array('_quickbooks_paymentmethod_import_request', '_quickbooks_paymentmethod_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_PAYMENTMETHOD)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_PAYMENTMETHOD, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_PAYMENTMETHOD, 1, QB_PRIORITY_PAYMENTMETHOD, NULL, $user);
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

function _quickbooks_paymentmethod_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<PaymentMethodQueryRq requestID="' . $requestID . '">
			</PaymentmethodQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("paymentmethod.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_paymentmethod_initial_response() {
    $myfile = fopen("paymentmethod1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Se ha dejado limpia la tabla de facturas de venta");
    fclose($myfile);
}

function _quickbooks_paymentmethod_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_PAYMENTMETHOD, null, QB_PRIORITY_PAYMENTMETHOD, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("pricelevel3.txt", "w") or die("Unable to open file!");
    $myfile = fopen("pricelevelpaymentmethod3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['paymentmethod'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("paymentmethod.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "PaymentMethodRet";
    $factura = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($factura as $uno) {
        $estado = "INIT";
        genLimpia_paymentmethod();
        gentraverse_paymentmethod($uno);
        $existe = buscaIgual_paymentmethod($db);
        if ($existe == "OK") {
            quitaslashes_paymentmethod();
            fwrite($myfile, "NO!!! Existe forma de pago " . $_SESSION['paymentmethod']['ListID'] . " \r\n");
            $estado = adiciona_paymentmethod($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_paymentmethod();
            fwrite($myfile, "Existe forma de pago " . $_SESSION['paymentmethod']['ListID'] . " \r\n");
            $estado = actualiza_paymentmethod($db);
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso al metodo de pago  " . $estado . " id " . $_SESSION['paymentmethod']['ListID'] . " \r\n");
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
    if ($action == QUICKBOOKS_IMPORT_PRICELEVEL) {
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

function gentraverse_paymentmethod($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['paymentmethod']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['paymentmethod']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['paymentmethod']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['paymentmethod']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['paymentmethod']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['paymentmethod']['Name'] = $nivel1->nodeValue;
                    break;
                case 'PaymentMethodType':
                    $_SESSION['paymentmethod']['PriceLevelType'] = $nivel1->nodeValue;
                    break;
            }
        }
    }
}

function genLimpia_paymentmethod() {
    $_SESSION['paymentmethod']['ListID'] = ' ';
    $_SESSION['paymentmethod']['TimeCreated'] = ' ';
    $_SESSION['paymentmethod']['TimeModified'] = ' ';
    $_SESSION['paymentmethod']['EditSequence'] = ' ';
    $_SESSION['paymentmethod']['Name'] = ' ';
    $_SESSION['paymentmethod']['IsActive'] = ' ';
    $_SESSION['paymentmethod']['PaymentMethodType'] = ' ';
    $_SESSION['paymentmethod']['Status'] = ' ';
}

function adiciona_paymentmethod($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO paymentmethod (  ListID, TimeCreated, TimeModified, EditSequence, Name, IsActive, PaymentMethodType, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :IsActive, :PaymentMethodType, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['paymentmethod']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['paymentmethod']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['paymentmethod']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['paymentmethod']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['paymentmethod']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['paymentmethod']['IsActive']);
        $stmt->bindParam(':PaymentMethodType', $_SESSION['paymentmethod']['PaymentMethodType']);
        $stmt->bindParam(':Status', $_SESSION['paymentmethod']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function quitaslashes_paymentmethod() {
    $_SESSION['paymentmethod']['ListID'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['ListID']));
    $_SESSION['paymentmethod']['TimeCreated'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['TimeCreated']));
    $_SESSION['paymentmethod']['TimeModified'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['TimeModified']));
    $_SESSION['paymentmethod']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['EditSequence']));
    $_SESSION['paymentmethod']['Name'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['Name']));
    $_SESSION['paymentmethod']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['IsActive']));
    $_SESSION['paymentmethod']['PaymentMethodType'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['PaymentMethodType']));
    $_SESSION['paymentmethod']['Status'] = htmlspecialchars(strip_tags($_SESSION['paymentmethod']['Status']));
}

function buscaIgual_paymentmethod($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM paymentmethod WHERE ListID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['paymentmethod']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ListID'] === $_SESSION['paymentmethod']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        echo $e->getMessage();
    }

    return $estado;
}

function actualiza_paymentmethod($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE paymentmethod SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, Name=:Name, IsActive=:IsActive, PaymentMethodType=:PaymentMethodType, Status=:Status, WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['paymentmethod']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['paymentmethod']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['paymentmethod']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['paymentmethod']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['paymentmethod']['IsActive']);
        $stmt->bindParam(':PaymentMethodType', $_SESSION['paymentmethod']['PaymentMethodType']);
        $stmt->bindParam(':Status', $_SESSION['paymentmethod']['Status']);
        $stmt->bindParam(':clave', $_SESSION['paymentmethod']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}
