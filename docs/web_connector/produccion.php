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
define('QB_QUICKBOOKS_MAX_RETURNED', 3725);
define('QB_QUICKBOOKS_MAILTO', 'jrcscarrillo@gmail.com');
define('QB_PRIORITY_ASSEMBLY', 10);
define('QB_PRIORITY_INVOICE', 1);
$myfile = fopen("assembly.txt", "w") or die("Unable to open file!");
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
   QUICKBOOKS_EXPORT_ASSEMBLY => array('_quickbooks_assembly_export_request', '_quickbooks_assembly_export_response'),
   QUICKBOOKS_ADD_SALESORDER => array('_quickbooks_invoice_add_request', '_quickbooks_invoice_add_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_EX)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_EXPORT_ASSEMBLY, $date);
    }
    $Queue->enqueue(QUICKBOOKS_EXPORT_ASSEMBLY, 1, QB_PRIORITY_ASSEMBLY, NULL, $user);
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

function _quickbooks_assembly_export_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_assembly_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<BuildAssemblyQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                                <ModifiedDateRangeFilter>
                                    <FromModifiedDate>' . FECHAMODIFICACION . '</FromModifiedDate>
                                </ModifiedDateRangeFilter>
                            <OwnerID>0</OwnerID>
			</BuildAssemblyQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("assembly2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_assembly_initial_response() {
    
}

function _quickbooks_assembly_export_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_EXPORT_ASSEMBLY, null, QB_PRIORITY_ASSEMBLY, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("assembly3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    $_SESSION['lotes'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("assembly.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "BuildAssemblyRet";
    $lotes = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($lotes as $uno) {
        genLimpia_lotesdetalle();
        gentraverse_lotes($uno);

        if ($_SESSION['lotesdetalle']['IsPending'] === 'false') {
            fwrite($myfile, "Produccion " . $_SESSION['lotesdetalle']['IsPending'] . ' RefNumber ' . $_SESSION['lotesdetalle']['RefNumber']) . ' LC o MC ' . substr($_SESSION['lotesdetalle']['RefNumber'], 0, 2) . "<br>";
            if ((substr($_SESSION['lotesdetalle']['RefNumber'], 0, 2) === "LC") OR ( substr($_SESSION['lotesdetalle']['RefNumber'], 0, 2) === "MC")) {
                $existe = buscaIgual_lotesdetalle($db);
                if ($existe == "OK") {
                    quitaslashes_lotesdetalle();
                    $retorna = adiciona_lotesdetalle($db);
                    fwrite($myfile, "Produccion nueva" . $retorna . " \r\n");
                } elseif ($existe == "ACTUALIZA") {
                    quitaslashes_lotesdetalle();
                    $paso = actualiza_lotesdetalle($db);
                    fwrite($myfile, "Existe produccion " . $paso . " \r\n");
                } else {
                    fwrite($myfile, $existe . " \r\n");
                }

                $k++;
            }
        }
    }
    fwrite($myfile, "-------->  FIN DEL LOG \r\n");
    fclose($myfile);
    fclose($myfile1);

    return true;
}

function gentraverse_lotes($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'TxnID':
                    $_SESSION['lotesdetalle']['TxnID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['lotesdetalle']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['lotesdetalle']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['lotesdetalle']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'TxnNumber':
                    $_SESSION['lotesdetalle']['TxnNumber'] = $nivel1->nodeValue;
                    break;
                case 'RefNumber':
                    $_SESSION['lotesdetalle']['RefNumber'] = $nivel1->nodeValue;
                    break;
                case 'TxnDate':
                    $_SESSION['lotesdetalle']['TxnDate'] = $nivel1->nodeValue;
                    break;
                case 'IsPending':
                    $_SESSION['lotesdetalle']['IsPending'] = $nivel1->nodeValue;
                    break;
                case 'ItemInventoryAssemblyRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ItemInventoryAssemblyRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['lotesdetalle']['ItemRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['lotesdetalle']['ItemRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
                case 'Memo':
                    $_SESSION['lotesdetalle']['Memo'] = $nivel1->nodeValue;
                    break;
                case 'QuantityToBuild': $_SESSION['lotesdetalle']['QtyProducida'] = $nivel1->nodeValue;
                    $_SESSION['lotesdetalle']['QtyBuena'] = 0;
                    $_SESSION['lotesdetalle']['QtyMala'] = 0;
                    $_SESSION['lotesdetalle']['QtyLab'] = 0;
                    $_SESSION['lotesdetalle']['QtyMuestra'] = 0;
                    $_SESSION['lotesdetalle']['QtyReproceso'] = 0;
                    $_SESSION['lotesdetalle']['Estado'] = 'PASADO';
                    break;
            }
        }
    }
}

function genLimpia_lotesdetalle() {
    $_SESSION['lotesdetalle']['TxnID'] = ' ';
    $_SESSION['lotesdetalle']['TimeCreated'] = ' ';
    $_SESSION['lotesdetalle']['TimeModified'] = ' ';
    $_SESSION['lotesdetalle']['EditSequence'] = ' ';
    $_SESSION['lotesdetalle']['TxnDate'] = ' ';
    $_SESSION['lotesdetalle']['TxnNumber'] = ' ';
    $_SESSION['lotesdetalle']['RefNumber'] = ' ';
    $_SESSION['lotesdetalle']['ItemRef_ListID'] = ' ';
    $_SESSION['lotesdetalle']['ItemRef_FullName'] = ' ';
    $_SESSION['lotesdetalle']['EmployeeRef_ListID'] = ' ';
    $_SESSION['lotesdetalle']['EmployeeRef_FullName'] = ' ';
    $_SESSION['lotesdetalle']['Memo'] = ' ';
    $_SESSION['lotesdetalle']['QtyProducida'] = ' ';
    $_SESSION['lotesdetalle']['QtyBuena'] = ' ';
    $_SESSION['lotesdetalle']['QtyMala'] = ' ';
    $_SESSION['lotesdetalle']['QtyReproceso'] = ' ';
    $_SESSION['lotesdetalle']['QtyMuestra'] = ' ';
    $_SESSION['lotesdetalle']['QtyLab'] = ' ';
    $_SESSION['lotesdetalle']['IsPending'] = ' ';
    $_SESSION['lotesdetalle']['Estado'] = ' ';
}

function adiciona_lotesdetalle($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO lotesdetalle (  TxnID, TimeCreated, TimeModified, EditSequence, TxnDate, TxnNumber, RefNumber, ItemRef_ListID, ItemRef_FullName, EmployeeRef_ListID, EmployeeRef_FullName, Memo, QtyProducida, QtyBuena, QtyMala, QtyReproceso, QtyMuestra, QtyLab, IsPending, Estado) VALUES ( :TxnID, :TimeCreated, :TimeModified, :EditSequence, :TxnDate, :TxnNumber, :RefNumber, :ItemRef_ListID, :ItemRef_FullName, :EmployeeRef_ListID, :EmployeeRef_FullName, :Memo, :QtyProducida, :QtyBuena, :QtyMala, :QtyReproceso, :QtyMuestra, :QtyLab, :IsPending, :Estado)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TxnID', $_SESSION['lotesdetalle']['TxnID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['lotesdetalle']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['lotesdetalle']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['lotesdetalle']['EditSequence']);
        $stmt->bindParam(':TxnDate', $_SESSION['lotesdetalle']['TxnDate']);
        $stmt->bindParam(':TxnNumber', $_SESSION['lotesdetalle']['TxnNumber']);
        $stmt->bindParam(':RefNumber', $_SESSION['lotesdetalle']['RefNumber']);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['lotesdetalle']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['lotesdetalle']['ItemRef_FullName']);
        $stmt->bindParam(':EmployeeRef_ListID', $_SESSION['lotesdetalle']['EmployeeRef_ListID']);
        $stmt->bindParam(':EmployeeRef_FullName', $_SESSION['lotesdetalle']['EmployeeRef_FullName']);
        $stmt->bindParam(':Memo', $_SESSION['lotesdetalle']['Memo']);
        $stmt->bindParam(':QtyProducida', $_SESSION['lotesdetalle']['QtyProducida']);
        $stmt->bindParam(':QtyBuena', $_SESSION['lotesdetalle']['QtyBuena']);
        $stmt->bindParam(':QtyMala', $_SESSION['lotesdetalle']['QtyMala']);
        $stmt->bindParam(':QtyReproceso', $_SESSION['lotesdetalle']['QtyReproceso']);
        $stmt->bindParam(':QtyMuestra', $_SESSION['lotesdetalle']['QtyMuestra']);
        $stmt->bindParam(':QtyLab', $_SESSION['lotesdetalle']['QtyLab']);
        $stmt->bindParam(':IsPending', $_SESSION['lotesdetalle']['IsPending']);
        $stmt->bindParam(':Estado', $_SESSION['lotesdetalle']['Estado']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = $e->getLine() . $e->getMessage() . "\r\n";
    }
    return $estado;
}

function quitaslashes_lotesdetalle() {
    $_SESSION['lotesdetalle']['TxnID'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['TxnID']));
    $_SESSION['lotesdetalle']['TimeCreated'] = date('Y-m-d H:m:s', strtotime($_SESSION['lotesdetalle']['TimeCreated']));
    $_SESSION['lotesdetalle']['TimeModified'] = date('Y-m-d H:m:s', strtotime($_SESSION['lotesdetalle']['TimeModified']));
    $_SESSION['lotesdetalle']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['EditSequence']));
    $_SESSION['lotesdetalle']['TxnDate'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['TxnDate']));
    $_SESSION['lotesdetalle']['TxnNumber'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['TxnNumber']));
    $_SESSION['lotesdetalle']['RefNumber'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['RefNumber']));
    $_SESSION['lotesdetalle']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['ItemRef_ListID']));
    $_SESSION['lotesdetalle']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['ItemRef_FullName']));
    $_SESSION['lotesdetalle']['EmployeeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['EmployeeRef_ListID']));
    $_SESSION['lotesdetalle']['EmployeeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['EmployeeRef_FullName']));
    $_SESSION['lotesdetalle']['Memo'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['Memo']));
    $_SESSION['lotesdetalle']['QtyProducida'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyProducida']));
    $_SESSION['lotesdetalle']['QtyBuena'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyBuena']));
    $_SESSION['lotesdetalle']['QtyMala'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyMala']));
    $_SESSION['lotesdetalle']['QtyReproceso'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyReproceso']));
    $_SESSION['lotesdetalle']['QtyMuestra'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyMuestra']));
    $_SESSION['lotesdetalle']['QtyLab'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['QtyLab']));
    $_SESSION['lotesdetalle']['IsPending'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['IsPending']));
    $_SESSION['lotesdetalle']['Estado'] = htmlspecialchars(strip_tags($_SESSION['lotesdetalle']['Estado']));
}

function buscaIgual_lotesdetalle($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM lotesdetalle WHERE TxnID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['lotesdetalle']['TxnID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['TxnID'] === $_SESSION['lotesdetalle']['TxnID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado = $e->getLine() . $e->getMessage() . "\r\n";
    }

    return $estado;
}

function actualiza_lotesdetalle($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE lotesdetalle SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, TxnDate=:TxnDate, TxnNumber=:TxnNumber, RefNumber=:RefNumber, ItemRef_ListID=:ItemRef_ListID, ItemRef_FullName=:ItemRef_FullName, EmployeeRef_ListID=:EmployeeRef_ListID, EmployeeRef_FullName=:EmployeeRef_FullName, Memo=:Memo, QtyProducida=:QtyProducida, QtyBuena=:QtyBuena, QtyMala=:QtyMala, QtyReproceso=:QtyReproceso, QtyMuestra=:QtyMuestra, QtyLab=:QtyLab, IsPending=:IsPending, Estado=:Estado WHERE TxnID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['lotesdetalle']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['lotesdetalle']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['lotesdetalle']['EditSequence']);
        $stmt->bindParam(':TxnDate', $_SESSION['lotesdetalle']['TxnDate']);
        $stmt->bindParam(':TxnNumber', $_SESSION['lotesdetalle']['TxnNumber']);
        $stmt->bindParam(':RefNumber', $_SESSION['lotesdetalle']['RefNumber']);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['lotesdetalle']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['lotesdetalle']['ItemRef_FullName']);
        $stmt->bindParam(':EmployeeRef_ListID', $_SESSION['lotesdetalle']['EmployeeRef_ListID']);
        $stmt->bindParam(':EmployeeRef_FullName', $_SESSION['lotesdetalle']['EmployeeRef_FullName']);
        $stmt->bindParam(':Memo', $_SESSION['lotesdetalle']['Memo']);
        $stmt->bindParam(':QtyProducida', $_SESSION['lotesdetalle']['QtyProducida']);
        $stmt->bindParam(':QtyBuena', $_SESSION['lotesdetalle']['QtyBuena']);
        $stmt->bindParam(':QtyMala', $_SESSION['lotesdetalle']['QtyMala']);
        $stmt->bindParam(':QtyReproceso', $_SESSION['lotesdetalle']['QtyReproceso']);
        $stmt->bindParam(':QtyMuestra', $_SESSION['lotesdetalle']['QtyMuestra']);
        $stmt->bindParam(':QtyLab', $_SESSION['lotesdetalle']['QtyLab']);
        $stmt->bindParam(':IsPending', $_SESSION['lotesdetalle']['IsPending']);
        $stmt->bindParam(':Estado', $_SESSION['lotesdetalle']['Estado']);
        $stmt->bindParam(':clave', $_SESSION['lotesdetalle']['TxnID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = $e->getLine() . $e->getMessage() . "\r\n";
    }

    return $estado;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action === QUICKBOOKS_EXPORT_ASSEMBLY) {
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
