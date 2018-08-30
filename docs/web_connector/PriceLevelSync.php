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
define('QB_PRIORITY_PRICELEVEL', 1);

$map = array(
   QUICKBOOKS_IMPORT_PRICELEVEL => array('_quickbooks_pricelevel_import_request', '_quickbooks_pricelevel_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_PRICELEVEL)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_PRICELEVEL, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_PRICELEVEL, 1, QB_PRIORITY_PRICELEVEL, NULL, $user);
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

function _quickbooks_pricelevel_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<PriceLevelQueryRq requestID="' . $requestID . '">
			</PriceLevelQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("pricelevel2.txt", "w") or die("Unable to open file");
    fwrite($myfile, $xml);

    fclose($myfile);
    return $xml;
}

function _quickbooks_pricelevel_initial_response() {
    $myfile = fopen("pricelevel1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Se ha dejado limpia la tabla de facturas de venta");
    fclose($myfile);
}

function _quickbooks_pricelevel_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_PRICELEVEL, null, QB_PRIORITY_PRICELEVEL, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("pricelevel3.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");

    $_SESSION['pricelevel'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("pricelevel.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "PriceLevelRet";
    $factura = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $k = 0;
    foreach ($factura as $uno) {
        $estado = "INIT";
        genLimpia_pricelevel();
        gentraverse_pricelevel($uno);
        $existe = buscaIgual_pricelevel($db);
        if ($existe == "OK") {
            quitaslashes_pricelevel();
            fwrite($myfile, "NO!!! Existe factura " . $_SESSION['pricelevel']['TxnID'] . " \r\n");
            $estado = adiciona_pricelevel($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_pricelevel();
            fwrite($myfile, "Existe factura " . $_SESSION['pricelevel']['TxnID'] . " \r\n");
            $estado = actualiza_pricelevel($db);
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso a la cabecera de facturas  " . $estado . " id " . $_SESSION['pricelevel']['TxnID'] . " \r\n");
        }

        $k++;
    }

    $param = "PriceLevelPerItemRet";
    $detalle = $doc->getElementsByTagName($param);
    foreach ($detalle as $uno) {
        $estado = "INIT";
        genLimpia_pricelevelperitemdetail();
        gentraverse_pricelevelperitemdetail($uno);
        $existe = buscaIgual_pricelevelperitemdetail($db);
        if ($existe == "OK") {
            quitaslashes_pricelevelperitemdetail();
            $estado = adiciona_pricelevelperitemdetail($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_pricelevelperitemdetail();
            $estado = actualiza_pricelevelperitemdetail($db);
        } else {
            fwrite($myfile, "Errores cuando lee detalle de la factura " . $_SESSION['pricelevelperitemdetail']['TxnLineID'] . " \r\n");
        }
        if ($estado != "INIT") {
            fwrite($myfile, "Errores cuando acceso a la tabla del detalle de facturas  " . $estado . " id " . $_SESSION['pricelevelperitemdetail']['TxnLineID'] . " \r\n");
        }
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

function gentraverse_pricelevelperitemdetail($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'CustomPrice':
                    $_SESSION['pricelevelperitemdetail']['CustomPrice'] = $nivel1->nodeValue;
                    break;
                case 'CustomPricePercent':
                    $_SESSION['pricelevelperitemdetail']['CustomPricePercent'] = $nivel1->nodeValue;
                    break;

                case 'ItemRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ItemRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['pricelevelperitemdetail']['ItemRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['pricelevelperitemdetail']['ItemRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
            }
        }
    }
}

function buscaIgual_pricelevelperitemdetail($db) {
    $estado = 'INIT';
    try {
        $sql = 'SELECT * FROM pricelevellinedetail WHERE IDKEY = :clave AND ItemRef_ListID = :item';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['pricelevelperitemdetail']['IDKEY']);
        $stmt->bindParam(':item', $_SESSION['pricelevelperitemdetail']['ItemRef_ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ItemRef_ListID'] === $_SESSION['pricelevelperitemdetail']['ItemRef_ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado .= " tiene errores " . $e->getMessage() . " posiblemente por " . $e->getLine();
    }

    return $estado;
}

function buscaIgual_pricelevel($db) {
    $estado = "ERR";
    try {
        $sql = "SELECT * FROM pricelevel WHERE ListID = :clave ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['pricelevel']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = "OK";
        } else {
            if ($registro['ListID'] === $_SESSION['pricelevel']['ListID']) {
                $estado = "ACTUALIZA";
            }
        }
    } catch (PDOException $e) {
        $estado = "Error en la base de datos " . $e->getMessage() . " Aproximadamente por " . $e->getLine();
    }
    return $estado;
}

function gentraverse_pricelevel($node) {

    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['pricelevel']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['pricelevel']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['pricelevel']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['pricelevel']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['pricelevel']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['pricelevel']['Name'] = $nivel1->nodeValue;
                    break;
                case 'PriceLevelType':
                    $_SESSION['pricelevel']['PriceLevelType'] = $nivel1->nodeValue;
                    break;
                case 'PriceLevelFixedPercentaje':
                    $_SESSION['pricelevel']['PriceLevelFixedPercentaje'] = $nivel1->nodeValue;
                    break;
                case 'CurrencyRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'CurrencyRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['pricelevel']['CurrencyRefRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['pricelevel']['CurrencyRefRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
            }
        }
    }
}

function genLimpia_pricelevel() {
    $_SESSION['pricelevel']['ListID'] = ' ';
    $_SESSION['pricelevel']['TimeCreated'] = ' ';
    $_SESSION['pricelevel']['TimeModified'] = ' ';
    $_SESSION['pricelevel']['EditSequence'] = ' ';
    $_SESSION['pricelevel']['Name'] = ' ';
    $_SESSION['pricelevel']['IsActive'] = 'true';
    $_SESSION['pricelevel']['PriceLevelType'] = ' ';
    $_SESSION['pricelevel']['PriceLevelFixedPercentage'] = ' ';
    $_SESSION['pricelevel']['CurrencyRef_ListID'] = ' ';
    $_SESSION['pricelevel']['CurrencyRef_FullName'] = ' ';
    $_SESSION['pricelevel']['Status'] = 'ACTIVO';
}

function genLimpia_pricelevelperitemdetail() {
    $_SESSION['pricelevelperitemdetail']['ItemRef_ListID'] = ' ';
    $_SESSION['pricelevelperitemdetail']['ItemRef_FullName'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomPrice'] = '0';
    $_SESSION['pricelevelperitemdetail']['CustomPricePercent'] = '0';
    $_SESSION['pricelevelperitemdetail']['CustomField1'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField2'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField3'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField4'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField5'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField6'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField7'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField8'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField9'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField10'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField11'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField12'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField13'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField14'] = ' ';
    $_SESSION['pricelevelperitemdetail']['CustomField15'] = ' ';
    $_SESSION['pricelevelperitemdetail']['IDKEY'] = ' ';
}

function adiciona_pricelevel() {
    $estado = 'ERR';
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = 'INSERT INTO pricelevel (  ListID, TimeCreated, TimeModified, EditSequence, Name, IsActive, PriceLevelType, PriceLevelFixedPercentage, CurrencyRef_ListID, CurrencyRef_FullName, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :IsActive, :PriceLevelType, :PriceLevelFixedPercentage, :CurrencyRef_ListID, :CurrencyRef_FullName, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['pricelevel']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['pricelevel']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['pricelevel']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['pricelevel']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['pricelevel']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['pricelevel']['IsActive']);
        $stmt->bindParam(':PriceLevelType', $_SESSION['pricelevel']['PriceLevelType']);
        $stmt->bindParam(':PriceLevelFixedPercentage', $_SESSION['pricelevel']['PriceLevelFixedPercentage']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['pricelevel']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['pricelevel']['CurrencyRef_FullName']);
        $stmt->bindParam(':Status', $_SESSION['pricelevel']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function adiciona_pricelevelperitemdetail() {
    $estado = 'ERR';
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = 'INSERT INTO pricelevelperitemdetail (  ItemRef_ListID, ItemRef_FullName, CustomPrice, CustomPricePercent, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, IDKEY, GroupIDKEY) VALUES ( :ItemRef_ListID, :ItemRef_FullName, :CustomPrice, :CustomPricePercent, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :IDKEY, :GroupIDKEY)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['pricelevelperitemdetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['pricelevelperitemdetail']['ItemRef_FullName']);
        $stmt->bindParam(':CustomPrice', $_SESSION['pricelevelperitemdetail']['CustomPrice']);
        $stmt->bindParam(':CustomPricePercent', $_SESSION['pricelevelperitemdetail']['CustomPricePercent']);
        $stmt->bindParam(':CustomField1', $_SESSION['pricelevelperitemdetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['pricelevelperitemdetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['pricelevelperitemdetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['pricelevelperitemdetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['pricelevelperitemdetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['pricelevelperitemdetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['pricelevelperitemdetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['pricelevelperitemdetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['pricelevelperitemdetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['pricelevelperitemdetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['pricelevelperitemdetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['pricelevelperitemdetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['pricelevelperitemdetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['pricelevelperitemdetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['pricelevelperitemdetail']['CustomField15']);
        $stmt->bindParam(':IDKEY', $_SESSION['pricelevelperitemdetail']['IDKEY']);
        $stmt->bindParam(':GroupIDKEY', $_SESSION['pricelevelperitemdetail']['GroupIDKEY']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function quitaslashes_pricelevel() {
    $_SESSION['pricelevel']['ListID'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['ListID']));
    $_SESSION['pricelevel']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['pricelevel']['TimeCreated']));
    $_SESSION['pricelevel']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['pricelevel']['TimeModified']));
    $_SESSION['pricelevel']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['EditSequence']));
    $_SESSION['pricelevel']['Name'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['Name']));
    $_SESSION['pricelevel']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['IsActive']));
    $_SESSION['pricelevel']['PriceLevelType'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['PriceLevelType']));
    $_SESSION['pricelevel']['PriceLevelFixedPercentage'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['PriceLevelFixedPercentage']));
    $_SESSION['pricelevel']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['CurrencyRef_ListID']));
    $_SESSION['pricelevel']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['CurrencyRef_FullName']));
    $_SESSION['pricelevel']['Status'] = htmlspecialchars(strip_tags($_SESSION['pricelevel']['Status']));
}

function quitaslashes_pricelevelperitemdetail() {
    $_SESSION['pricelevelperitemdetail']['ItemRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['ItemRef_ListID']));
    $_SESSION['pricelevelperitemdetail']['ItemRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['ItemRef_FullName']));
    $_SESSION['pricelevelperitemdetail']['CustomPrice'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomPrice']));
    $_SESSION['pricelevelperitemdetail']['CustomPricePercent'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomPricePercent']));
    $_SESSION['pricelevelperitemdetail']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField1']));
    $_SESSION['pricelevelperitemdetail']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField2']));
    $_SESSION['pricelevelperitemdetail']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField3']));
    $_SESSION['pricelevelperitemdetail']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField4']));
    $_SESSION['pricelevelperitemdetail']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField5']));
    $_SESSION['pricelevelperitemdetail']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField6']));
    $_SESSION['pricelevelperitemdetail']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField7']));
    $_SESSION['pricelevelperitemdetail']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField8']));
    $_SESSION['pricelevelperitemdetail']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField9']));
    $_SESSION['pricelevelperitemdetail']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField10']));
    $_SESSION['pricelevelperitemdetail']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField11']));
    $_SESSION['pricelevelperitemdetail']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField12']));
    $_SESSION['pricelevelperitemdetail']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField13']));
    $_SESSION['pricelevelperitemdetail']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField14']));
    $_SESSION['pricelevelperitemdetail']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['CustomField15']));
    $_SESSION['pricelevelperitemdetail']['IDKEY'] = htmlspecialchars(strip_tags($_SESSION['pricelevelperitemdetail']['IDKEY']));
}

function actualiza_pricelevelperitemdetail() {
    $estado = 'ERR';
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = 'UPDATE pricelevelperitemdetail SET ItemRef_FullName=:ItemRef_FullName, CustomPrice=:CustomPrice, CustomPricePercent=:CustomPricePercent, CustomField1=:CustomField1, CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, CustomField14=:CustomField14, CustomField15=:CustomField15 WHERE IDKEY = :clave AND ItemRef_ListID=:ItemRef_ListID;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ItemRef_ListID', $_SESSION['pricelevelperitemdetail']['ItemRef_ListID']);
        $stmt->bindParam(':ItemRef_FullName', $_SESSION['pricelevelperitemdetail']['ItemRef_FullName']);
        $stmt->bindParam(':CustomPrice', $_SESSION['pricelevelperitemdetail']['CustomPrice']);
        $stmt->bindParam(':CustomPricePercent', $_SESSION['pricelevelperitemdetail']['CustomPricePercent']);
        $stmt->bindParam(':CustomField1', $_SESSION['pricelevelperitemdetail']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['pricelevelperitemdetail']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['pricelevelperitemdetail']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['pricelevelperitemdetail']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['pricelevelperitemdetail']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['pricelevelperitemdetail']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['pricelevelperitemdetail']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['pricelevelperitemdetail']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['pricelevelperitemdetail']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['pricelevelperitemdetail']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['pricelevelperitemdetail']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['pricelevelperitemdetail']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['pricelevelperitemdetail']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['pricelevelperitemdetail']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['pricelevelperitemdetail']['CustomField15']);
        $stmt->bindParam(':clave', $_SESSION['pricelevel']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}

function actualiza_pricelevel() {
    $estado = 'ERR';
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = 'UPDATE pricelevel SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, Name=:Name, IsActive=:IsActive, PriceLevelType=:PriceLevelType, PriceLevelFixedPercentage=:PriceLevelFixedPercentage, CurrencyRef_ListID=:CurrencyRef_ListID, CurrencyRef_FullName=:CurrencyRef_FullName, Status=:Status WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['pricelevel']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['pricelevel']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['pricelevel']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['pricelevel']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['pricelevel']['IsActive']);
        $stmt->bindParam(':PriceLevelType', $_SESSION['pricelevel']['PriceLevelType']);
        $stmt->bindParam(':PriceLevelFixedPercentage', $_SESSION['pricelevel']['PriceLevelFixedPercentage']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['pricelevel']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['pricelevel']['CurrencyRef_FullName']);
        $stmt->bindParam(':Status', $_SESSION['pricelevel']['Status']);
        $stmt->bindParam(':clave', $_SESSION['pricelevel']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}
