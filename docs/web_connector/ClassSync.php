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
define('QB_PRIORITY_CLASS', 1);
$myfile = fopen("class.txt", "w") or die("Unable to open file!");
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
   QUICKBOOKS_EXPORT_CLASS => array('_quickbooks_class_export_request', '_quickbooks_class_export_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_EXPORT_CLASS)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_EXPORT_CLASS, $date);
    }
    $Queue->enqueue(QUICKBOOKS_EXPORT_CLASS, 1, QB_PRIORITY_CLASS, NULL, $user);
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

function _quickbooks_class_export_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_class_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }
    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<ClassQueryRq>
			</ClassQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';

    $myfile = fopen("class2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_class_initial_response() {
    
}

function _quickbooks_class_export_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_EXPORT_CLASS, null, QB_PRIORITY_CLASS, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("class3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    $_SESSION['lotes'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("class.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "ClassRet";
    $clases = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($clases as $uno) {
        genLimpia_bodegas();
        gentraverse_bodegas($uno);

        $existe = buscaIgual_bodegas($db);
        if ($existe == "OK") {
            quitaslashes_bodegas();
            $retorna = adiciona_bodegas($db);
            fwrite($myfile, "Produccion nueva" . $retorna . " \r\n");
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_bodegas();
            $paso = actualiza_bodegas($db);
            fwrite($myfile, "Existe produccion " . $paso . " \r\n");
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

function genLimpia_bodegas() {
    $_SESSION['bodegas']['ListID'] = ' ';
    $_SESSION['bodegas']['TimeCreated'] = ' ';
    $_SESSION['bodegas']['TimeModified'] = ' ';
    $_SESSION['bodegas']['EditSequence'] = ' ';
    $_SESSION['bodegas']['Name'] = ' ';
    $_SESSION['bodegas']['FullName'] = ' ';
    $_SESSION['bodegas']['IsActive'] = ' ';
    $_SESSION['bodegas']['ParentRef_ListID'] = ' ';
    $_SESSION['bodegas']['ParentRef_FullName'] = ' ';
    $_SESSION['bodegas']['Sublevel'] = ' ';
    $_SESSION['bodegas']['Status'] = ' ';
    $_SESSION['bodegas']['Estado'] = ' ';
}

function gentraverse_bodegas($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['bodegas']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['bodegas']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['bodegas']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['bodegas']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['bodegas']['Name'] = $nivel1->nodeValue;
                    break;
                case 'FullName':
                    $_SESSION['bodegas']['FullName'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['bodegas']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'Sublevel':
                    $_SESSION['bodegas']['Sublevel'] = $nivel1->nodeValue;
                    break;
                case 'Status':
                    $_SESSION['bodegas']['Status'] = $nivel1->nodeValue;
                    break;
                case 'Estado':
                    $_SESSION['bodegas']['Estado'] = $nivel1->nodeValue;
                    break;
                case 'ParentRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ParentRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['bodegas']['ParentRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['bodegas']['ParentRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }   
}

function adiciona_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO bodegas (  ListID, TimeCreated, TimeModified, EditSequence, Name, FullName, IsActive, ParentRef_ListID, ParentRef_FullName, Sublevel) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :FullName, :IsActive, :ParentRef_ListID, :ParentRef_FullName, :Sublevel)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['bodegas']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['bodegas']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['bodegas']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['bodegas']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['bodegas']['Name']);
        $stmt->bindParam(':FullName', $_SESSION['bodegas']['FullName']);
        $stmt->bindParam(':IsActive', $_SESSION['bodegas']['IsActive']);
        $stmt->bindParam(':ParentRef_ListID', $_SESSION['bodegas']['ParentRef_ListID']);
        $stmt->bindParam(':ParentRef_FullName', $_SESSION['bodegas']['ParentRef_FullName']);
        $stmt->bindParam(':Sublevel', $_SESSION['bodegas']['Sublevel']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function quitaslashes_bodegas() {
    $_SESSION['bodegas']['ListID'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ListID']));
    $_SESSION['bodegas']['TimeCreated'] = date('Y-m-d H:m:s', strtotime($_SESSION['bodegas']['TimeCreated']));
    $_SESSION['bodegas']['TimeModified'] = date('Y-m-d H:m:s', strtotime($_SESSION['bodegas']['TimeModified']));
    $_SESSION['bodegas']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['EditSequence']));
    $_SESSION['bodegas']['Name'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Name']));
    $_SESSION['bodegas']['FullName'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['FullName']));
    $_SESSION['bodegas']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['IsActive']));
    $_SESSION['bodegas']['ParentRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ParentRef_ListID']));
    $_SESSION['bodegas']['ParentRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ParentRef_FullName']));
    $_SESSION['bodegas']['Sublevel'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Sublevel']));
    $_SESSION['bodegas']['Status'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Status']));
    $_SESSION['bodegas']['Estado'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Estado']));
}

function buscaIgual_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM bodegas WHERE ListID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['bodegas']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ListID'] === $_SESSION['bodegas']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        echo $e->getMessage();
    }

    return $estado;
}

function actualiza_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE bodegas SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, Name=:Name, FullName=:FullName, IsActive=:IsActive, ParentRef_ListID=:ParentRef_ListID, ParentRef_FullName=:ParentRef_FullName, Sublevel=:Sublevel WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['bodegas']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['bodegas']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['bodegas']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['bodegas']['Name']);
        $stmt->bindParam(':FullName', $_SESSION['bodegas']['FullName']);
        $stmt->bindParam(':IsActive', $_SESSION['bodegas']['IsActive']);
        $stmt->bindParam(':ParentRef_ListID', $_SESSION['bodegas']['ParentRef_ListID']);
        $stmt->bindParam(':ParentRef_FullName', $_SESSION['bodegas']['ParentRef_FullName']);
        $stmt->bindParam(':Sublevel', $_SESSION['bodegas']['Sublevel']);
        $stmt->bindParam(':clave', $_SESSION['bodegas']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action === QUICKBOOKS_EXPORT_CLASS) {
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
