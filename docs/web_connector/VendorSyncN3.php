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
define('QB_QUICKBOOKS_MAX_RETURNED', 3725);
define('QB_QUICKBOOKS_MAILTO', 'jrcscarrillo@gmail.com');
define('QB_PRIORITY_VENDOR', 1);
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
define('FECHAMODIFICACION', $fecha);
$fecha = date("Y-m-d", strtotime($registro['otrosHasta']));
define("AU_HASTA", $fecha);
fwrite($myfile, 'fechas : ' . AU_DESDE . ' ' . AU_HASTA . '\r\n');
fclose($myfile);

$map = array(
   QUICKBOOKS_IMPORT_VENDOR => array('_quickbooks_vendor_import_request', '_quickbooks_vendor_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_VENDOR)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_VENDOR, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_VENDOR, 1, QB_PRIORITY_VENDOR, NULL, $user);
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

function _quickbooks_vendor_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_vendor_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<VendorQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <FromModifiedDate>' . FECHAMODIFICACION . '</FromModifiedDate>
                            <OwnerID>0</OwnerID>
			</VendorQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_vendor_initial_response() {
    
}

function _quickbooks_vendor_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_VENDOR, null, QB_PRIORITY_VENDOR, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    $_SESSION['vendor'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("vendors.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "VendorRet";
    $proveedor = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($proveedor as $uno) {
        genLimpia_vendor();
        gentraverse_vendor($uno);
        $existe = buscaIgual_vendor($db);
        if ($existe == "OK") {
            quitaslashes_vendor();
            $retorna = adiciona_vendor($db);
            fwrite($myfile, "Proveedor nuevo" . $retorna . " \r\n");
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_vendor();
            $paso = actualiza_vendor($db);
            fwrite($myfile, "Existe proveedor " . $paso . " \r\n");
        } else {
            fwrite($myfile, $existe . " \r\n");
        }

        $k++;
    }
    fwrite($myfile, "-------->  FIN DEL LOG \r\n");
    fclose($myfile);
    fclose($myfile1);

    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_VENDOR) {
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

function genLimpia_vendor() {
    
}

function genTraverse_vendor($node) {
        if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                default :
                    $_SESSION['vendor'][$nivel1->nodeName] = $nivel1->nodeValue;
                    break;
            }
        }
        }
        
}

function buscaIgual_vendor($db) {
    
}

function quitaSlashes_vendor() {
    
}

function adiciona_vendor($db) {
    
}

function actualiza_vendor($db) {
    
}

