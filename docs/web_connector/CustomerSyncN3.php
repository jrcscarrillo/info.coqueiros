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
define('QB_PRIORITY_CUSTOMER', 10);
define('QB_PRIORITY_INVOICE', 1);
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
   QUICKBOOKS_IMPORT_CUSTOMER => array('_quickbooks_customer_import_request', '_quickbooks_customer_import_response'),
   QUICKBOOKS_ADD_SALESORDER => array('_quickbooks_invoice_add_request', '_quickbooks_invoice_add_response'),
   QUICKBOOKS_EXPORT_ASSEMBLY => array('_quickbooks_assembly_export_request', '_quickbooks_assembly_export_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, 1, QB_PRIORITY_CUSTOMER, NULL, $user);
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

function _quickbooks_customer_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_customer_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <FromModifiedDate>' . FECHAMODIFICACION . '</FromModifiedDate>
                            <OwnerID>0</OwnerID>
			</CustomerQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_customer_initial_response() {
    
}

function _quickbooks_customer_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, null, QB_PRIORITY_CUSTOMER, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    $_SESSION['customer'] = array();
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("customers.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($cliente as $uno) {
        genLimpia_customer();
//        $params2 = $cliente->item($k)->getElementsByTagName('ListID');
//        $_SESSION['customer']['ListID'] = $params2->item(0)->nodeValue;
//        fwrite($myfile, "Cliente a revisar" . $_SESSION['customer']['ListID'] . " \r\n");
        gentraverse_customer($uno);
        $params2 = $cliente->item($k)->getElementsByTagName('DataExtRet'); //digg categories with in Section
        $i = 0; // values is used to iterate categories  
        foreach ($params2 as $p) {
            $params3 = $params2->item($i)->getElementsByTagName('DataExtName'); //dig Arti into Categories
            $params4 = $params2->item($i)->getElementsByTagName('DataExtValue'); //dig Arti into Categories
            $j = 0; //values used to interate Arti
            foreach ($params3 as $p2) {
                switch ($params3->item($j)->nodeValue) {
                    case 'Ruta':
                        $_SESSION['customer']['CustomField1'] = $params4->item($j)->nodeValue;
                        break;
                    case 'Facturacion Electronica Tipo':
                        $_SESSION['customer']['CustomField2'] = $params4->item($j)->nodeValue;
                        break;
                    case 'Lleva':
                        $_SESSION['customer']['CustomField3'] = $params4->item($j)->nodeValue;
                        break;
                    case 'Rise':
                        $_SESSION['customer']['CustomField4'] = $params4->item($j)->nodeValue;
                        break;
                }
                $j++;
            }
            $i++;
        }
        $existe = buscaIgual_customer($db);
        if ($existe == "OK") {
            quitaslashes_customer();
            $retorna = adiciona_customer($db);
            fwrite($myfile, "Cliente nuevo" . $retorna . " \r\n");
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_customer();
            $paso = actualiza_customer($db);
            fwrite($myfile, "Existe cliente " . $paso . " \r\n");
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
    if ($action == QUICKBOOKS_IMPORT_CUSTOMER) {
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

function actualiza_customer($db) {
    $estado = $_SESSION['customer']['ListID'] . ' Nombre ' . $_SESSION['customer']['Name'] . ' AccountNumber ' . $_SESSION['customer']['AccountNumber'];

    try {
        $sql = 'UPDATE customer SET TimeCreated=:p1, TimeModified=:p2, EditSequence=:p3, Name=:p4, FullName=:p5, IsActive=:p6, ClassRef_ListID=:p7, '
           . 'ClassRef_FullName=:p8, ParentRef_ListID=:p9, ParentRef_FullName=:p10, Sublevel=:p11, CompanyName=:p12, Salutation=:p13, FirstName=:p4, '
           . 'MiddleName=:p15, LastName=:p16, Suffix=:p17, BillAddress_Addr1=:p18, BillAddress_Addr2=:p19, BillAddress_Addr3=:p20, BillAddress_Addr4=:p21, '
           . 'BillAddress_Addr5=:p22, BillAddress_City=:p23, BillAddress_State=:p24, BillAddress_PostalCode=:p25, BillAddress_Country=:p26, '
           . 'BillAddress_Note=:p27, ShipAddress_Addr1=:p28, ShipAddress_Addr2=:p29, ShipAddress_Addr3=:p30, '
           . 'ShipAddress_Addr4=:p31, ShipAddress_Addr5=:p32, ShipAddress_City=:p33, ShipAddress_State=:p34, '
           . 'ShipAddress_PostalCode=:p35, ShipAddress_Country=:p36, ShipAddress_Note=:p37, PrintAs=:p38, Phone=:p39, Mobile=:p40, '
           . 'Pager=:p41, AltPhone=:p42, Fax=:p43, Email=:p44, Cc=:p45, Contact=:p46, '
           . 'AltContact=:p47, CustomerTypeRef_ListID=:p48, CustomerTypeRef_FullName=:p49, TermsRef_ListID=:p50, '
           . 'TermsRef_FullName=:p51, SalesRepRef_ListID=:p52, SalesRepRef_FullName=:p53, Balance=:p54, TotalBalance=:p55, '
           . 'SalesTaxCodeRef_ListID=:p56, SalesTaxCodeRef_FullName=:p57, ItemSalesTaxRef_ListID=:p58, ItemSalesTaxRef_FullName=:p59, '
           . 'SalesTaxCountry=:p60, ResaleNumber=:p61, AccountNumber=:p62, CreditLimit=:p63, PreferredPaymentMethodRef_ListID=:p64, '
           . 'PreferredPaymentMethodRef_FullName=:p65, CreditCardNumber=:p66, ExpirationMonth=:p67, ExpirationYear=:p68, NameOnCard=:p69, '
           . 'CreditCardAddress=:p70, CreditCardPostalCode=:p71, JobStatus=:p72, JobStartDate=:p73, JobProjectedEndDate=:p74, JobEndDate=:p75, '
           . 'JobDesc=:p76, JobTypeRef_ListID=:p77, JobTypeRef_FullName=:p78, Notes=:p79, PriceLevelRef_ListID=:p80, PriceLevelRef_FullName=:p81, '
           . 'TaxRegistrationNumber=:p82, CurrencyRef_ListID=:p83, CurrencyRef_FullName=:p84, IsStatementWithParent=:p85, '
           . 'PreferredDeliveryMethod=:p86, CustomField1=:CustomField1, '
           . 'CustomField2=:CustomField2, CustomField3=:CustomField3, CustomField4=:CustomField4, CustomField5=:CustomField5, '
           . 'CustomField6=:CustomField6, CustomField7=:CustomField7, CustomField8=:CustomField8, CustomField9=:CustomField9, '
           . 'CustomField10=:CustomField10, CustomField11=:CustomField11, CustomField12=:CustomField12, CustomField13=:CustomField13, '
           . 'CustomField14=:CustomField14, CustomField15=:CustomField15, Status=:Status WHERE ListID = :clave';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':p1', $_SESSION['customer']['TimeCreated']);
        $stmt->bindParam(':p2', $_SESSION['customer']['TimeModified']);
        $stmt->bindParam(':p3', $_SESSION['customer']['EditSequence']);
        $stmt->bindParam(':p4', $_SESSION['customer']['Name']);
        $stmt->bindParam(':p5', $_SESSION['customer']['FullName']);
        $stmt->bindParam(':p6', $_SESSION['customer']['IsActive']);
        $stmt->bindParam(':p7', $_SESSION['customer']['ClassRef_ListID']);
        $stmt->bindParam(':p8', $_SESSION['customer']['ClassRef_FullName']);
        $stmt->bindParam(':p9', $_SESSION['customer']['ParentRef_ListID']);
        $stmt->bindParam(':p10', $_SESSION['customer']['ParentRef_FullName']);
        $stmt->bindParam(':p11', $_SESSION['customer']['Sublevel']);
        $stmt->bindParam(':p12', $_SESSION['customer']['CompanyName']);
        $stmt->bindParam(':p13', $_SESSION['customer']['Salutation']);
        $stmt->bindParam(':p14', $_SESSION['customer']['FirstName']);
        $stmt->bindParam(':p15', $_SESSION['customer']['MiddleName']);
        $stmt->bindParam(':p16', $_SESSION['customer']['LastName']);
        $stmt->bindParam(':p17', $_SESSION['customer']['Suffix']);
        $stmt->bindParam(':p18', $_SESSION['customer']['BillAddress_Addr1']);
        $stmt->bindParam(':p19', $_SESSION['customer']['BillAddress_Addr2']);
        $stmt->bindParam(':p20', $_SESSION['customer']['BillAddress_Addr3']);
        $stmt->bindParam(':p21', $_SESSION['customer']['BillAddress_Addr4']);
        $stmt->bindParam(':p22', $_SESSION['customer']['BillAddress_Addr5']);
        $stmt->bindParam(':p23', $_SESSION['customer']['BillAddress_City']);
        $stmt->bindParam(':p24', $_SESSION['customer']['BillAddress_State']);
        $stmt->bindParam(':p25', $_SESSION['customer']['BillAddress_PostalCode']);
        $stmt->bindParam(':p26', $_SESSION['customer']['BillAddress_Country']);
        $stmt->bindParam(':p27', $_SESSION['customer']['BillAddress_Note']);
        $stmt->bindParam(':p28', $_SESSION['customer']['ShipAddress_Addr1']);
        $stmt->bindParam(':p29', $_SESSION['customer']['ShipAddress_Addr2']);
        $stmt->bindParam(':p30', $_SESSION['customer']['ShipAddress_Addr3']);
        $stmt->bindParam(':p31', $_SESSION['customer']['ShipAddress_Addr4']);
        $stmt->bindParam(':p32', $_SESSION['customer']['ShipAddress_Addr5']);
        $stmt->bindParam(':p33', $_SESSION['customer']['ShipAddress_City']);
        $stmt->bindParam(':p34', $_SESSION['customer']['ShipAddress_State']);
        $stmt->bindParam(':p35', $_SESSION['customer']['ShipAddress_PostalCode']);
        $stmt->bindParam(':p36', $_SESSION['customer']['ShipAddress_Country']);
        $stmt->bindParam(':p37', $_SESSION['customer']['ShipAddress_Note']);
        $stmt->bindParam(':p38', $_SESSION['customer']['PrintAs']);
        $stmt->bindParam(':p39', $_SESSION['customer']['Phone']);
        $stmt->bindParam(':p40', $_SESSION['customer']['Mobile']);
        $stmt->bindParam(':p41', $_SESSION['customer']['Pager']);
        $stmt->bindParam(':p42', $_SESSION['customer']['AltPhone']);
        $stmt->bindParam(':p43', $_SESSION['customer']['Fax']);
        $stmt->bindParam(':p44', $_SESSION['customer']['Email']);
        $stmt->bindParam(':p45', $_SESSION['customer']['Cc']);
        $stmt->bindParam(':p46', $_SESSION['customer']['Contact']);
        $stmt->bindParam(':p47', $_SESSION['customer']['AltContact']);
        $stmt->bindParam(':p48', $_SESSION['customer']['CustomerTypeRef_ListID']);
        $stmt->bindParam(':p49', $_SESSION['customer']['CustomerTypeRef_FullName']);
        $stmt->bindParam(':p50', $_SESSION['customer']['TermsRef_ListID']);
        $stmt->bindParam(':p51', $_SESSION['customer']['TermsRef_FullName']);
        $stmt->bindParam(':p52', $_SESSION['customer']['SalesRepRef_ListID']);
        $stmt->bindParam(':p53', $_SESSION['customer']['SalesRepRef_FullName']);
        $stmt->bindParam(':p54', $_SESSION['customer']['Balance']);
        $stmt->bindParam(':p55', $_SESSION['customer']['TotalBalance']);
        $stmt->bindParam(':p56', $_SESSION['customer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':p57', $_SESSION['customer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':p58', $_SESSION['customer']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':p59', $_SESSION['customer']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':p60', $_SESSION['customer']['SalesTaxCountry']);
        $stmt->bindParam(':p61', $_SESSION['customer']['ResaleNumber']);
        $stmt->bindParam(':p62', $_SESSION['customer']['AccountNumber']);
        $stmt->bindParam(':p63', $_SESSION['customer']['CreditLimit']);
        $stmt->bindParam(':p64', $_SESSION['customer']['PreferredPaymentMethodRef_ListID']);
        $stmt->bindParam(':p65', $_SESSION['customer']['PreferredPaymentMethodRef_FullName']);
        $stmt->bindParam(':p66', $_SESSION['customer']['CreditCardNumber']);
        $stmt->bindParam(':p67', $_SESSION['customer']['ExpirationMonth']);
        $stmt->bindParam(':p68', $_SESSION['customer']['ExpirationYear']);
        $stmt->bindParam(':p69', $_SESSION['customer']['NameOnCard']);
        $stmt->bindParam(':p70', $_SESSION['customer']['CreditCardAddress']);
        $stmt->bindParam(':p71', $_SESSION['customer']['CreditCardPostalCode']);
        $stmt->bindParam(':p72', $_SESSION['customer']['JobStatus']);
        $stmt->bindParam(':p73', $_SESSION['customer']['JobStartDate']);
        $stmt->bindParam(':p74', $_SESSION['customer']['JobProjectedEndDate']);
        $stmt->bindParam(':p75', $_SESSION['customer']['JobEndDate']);
        $stmt->bindParam(':p76', $_SESSION['customer']['JobDesc']);
        $stmt->bindParam(':p77', $_SESSION['customer']['JobTypeRef_ListID']);
        $stmt->bindParam(':p78', $_SESSION['customer']['JobTypeRef_FullName']);
        $stmt->bindParam(':p79', $_SESSION['customer']['Notes']);
        $stmt->bindParam(':p80', $_SESSION['customer']['PriceLevelRef_ListID']);
        $stmt->bindParam(':p81', $_SESSION['customer']['PriceLevelRef_FullName']);
        $stmt->bindParam(':p82', $_SESSION['customer']['TaxRegistrationNumber']);
        $stmt->bindParam(':p83', $_SESSION['customer']['CurrencyRef_ListID']);
        $stmt->bindParam(':p84', $_SESSION['customer']['CurrencyRef_FullName']);
        $stmt->bindParam(':p85', $_SESSION['customer']['IsStatementWithParent']);
        $stmt->bindParam(':p86', $_SESSION['customer']['PreferredDeliveryMethod']);
        $stmt->bindParam(':CustomField1', $_SESSION['customer']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['customer']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['customer']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['customer']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['customer']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['customer']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['customer']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['customer']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['customer']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['customer']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['customer']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['customer']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['customer']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['customer']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['customer']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['customer']['Status']);

        $stmt->bindParam(':clave', $_SESSION['customer']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . '<br>' . $_SESSION['customer']['ListID'] . ' Nombre ' . $_SESSION['customer']['Name'] . ' mes ' . $_SESSION['customer']['ExpirationMonth'];
    }
    $stmt = null;
    return $estado;
}

function buscaIgual_customer($db) {
    $estado = "ERR";

    try {
        $sql = 'SELECT * FROM customer WHERE ListID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['customer']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ListID'] === $_SESSION['customer']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . '<br>' . $_SESSION['customer']['ListID'] . ' campo ' . $_SESSION['customer']['Name'];
    }
    $stmt = null;
    return $estado;
}

function genLimpia_customer() {
    $_SESSION['customer']['ListID'] = ' ';
    $_SESSION['customer']['TimeCreated'] = ' ';
    $_SESSION['customer']['TimeModified'] = ' ';
    $_SESSION['customer']['EditSequence'] = ' ';
    $_SESSION['customer']['Name'] = ' ';
    $_SESSION['customer']['FullName'] = ' ';
    $_SESSION['customer']['IsActive'] = 'true';
    $_SESSION['customer']['ClassRef_ListID'] = ' ';
    $_SESSION['customer']['ClassRef_FullName'] = ' ';
    $_SESSION['customer']['ParentRef_ListID'] = ' ';
    $_SESSION['customer']['ParentRef_FullName'] = ' ';
    $_SESSION['customer']['Sublevel'] = 0;
    $_SESSION['customer']['CompanyName'] = ' ';
    $_SESSION['customer']['Salutation'] = ' ';
    $_SESSION['customer']['FirstName'] = ' ';
    $_SESSION['customer']['MiddleName'] = ' ';
    $_SESSION['customer']['LastName'] = ' ';
    $_SESSION['customer']['Suffix'] = ' ';
    $_SESSION['customer']['BillAddress_Addr1'] = ' ';
    $_SESSION['customer']['BillAddress_Addr2'] = ' ';
    $_SESSION['customer']['BillAddress_Addr3'] = ' ';
    $_SESSION['customer']['BillAddress_Addr4'] = ' ';
    $_SESSION['customer']['BillAddress_Addr5'] = ' ';
    $_SESSION['customer']['BillAddress_City'] = ' ';
    $_SESSION['customer']['BillAddress_State'] = ' ';
    $_SESSION['customer']['BillAddress_PostalCode'] = ' ';
    $_SESSION['customer']['BillAddress_Country'] = ' ';
    $_SESSION['customer']['BillAddress_Note'] = ' ';
    $_SESSION['customer']['ShipAddress_Addr1'] = ' ';
    $_SESSION['customer']['ShipAddress_Addr2'] = ' ';
    $_SESSION['customer']['ShipAddress_Addr3'] = ' ';
    $_SESSION['customer']['ShipAddress_Addr4'] = ' ';
    $_SESSION['customer']['ShipAddress_Addr5'] = ' ';
    $_SESSION['customer']['ShipAddress_City'] = ' ';
    $_SESSION['customer']['ShipAddress_State'] = ' ';
    $_SESSION['customer']['ShipAddress_PostalCode'] = ' ';
    $_SESSION['customer']['ShipAddress_Country'] = ' ';
    $_SESSION['customer']['ShipAddress_Note'] = ' ';
    $_SESSION['customer']['PrintAs'] = ' ';
    $_SESSION['customer']['Phone'] = ' ';
    $_SESSION['customer']['Mobile'] = ' ';
    $_SESSION['customer']['Pager'] = ' ';
    $_SESSION['customer']['AltPhone'] = ' ';
    $_SESSION['customer']['Fax'] = ' ';
    $_SESSION['customer']['Email'] = ' ';
    $_SESSION['customer']['Cc'] = ' ';
    $_SESSION['customer']['Contact'] = ' ';
    $_SESSION['customer']['AltContact'] = ' ';
    $_SESSION['customer']['CustomerTypeRef_ListID'] = ' ';
    $_SESSION['customer']['CustomerTypeRef_FullName'] = ' ';
    $_SESSION['customer']['TermsRef_ListID'] = ' ';
    $_SESSION['customer']['TermsRef_FullName'] = ' ';
    $_SESSION['customer']['SalesRepRef_ListID'] = ' ';
    $_SESSION['customer']['SalesRepRef_FullName'] = ' ';
    $_SESSION['customer']['Balance'] = 0;
    $_SESSION['customer']['TotalBalance'] = 0;
    $_SESSION['customer']['SalesTaxCodeRef_ListID'] = ' ';
    $_SESSION['customer']['SalesTaxCodeRef_FullName'] = ' ';
    $_SESSION['customer']['ItemSalesTaxRef_ListID'] = ' ';
    $_SESSION['customer']['ItemSalesTaxRef_FullName'] = ' ';
    $_SESSION['customer']['SalesTaxCountry'] = ' ';
    $_SESSION['customer']['ResaleNumber'] = ' ';
    $_SESSION['customer']['AccountNumber'] = ' ';
    $_SESSION['customer']['CreditLimit'] = 0;
    $_SESSION['customer']['PreferredPaymentMethodRef_ListID'] = ' ';
    $_SESSION['customer']['PreferredPaymentMethodRef_FullName'] = ' ';
    $_SESSION['customer']['CreditCardNumber'] = ' ';
    $_SESSION['customer']['ExpirationMonth'] = 0;
    $_SESSION['customer']['ExpirationYear'] = 0;
    $_SESSION['customer']['NameOnCard'] = ' ';
    $_SESSION['customer']['CreditCardAddress'] = ' ';
    $_SESSION['customer']['CreditCardPostalCode'] = ' ';
    $_SESSION['customer']['JobStatus'] = ' ';
    $_SESSION['customer']['JobStartDate'] = '2018-01-01';
    $_SESSION['customer']['JobProjectedEndDate'] = '2018-01-01';
    $_SESSION['customer']['JobEndDate'] = '2018-01-01';
    $_SESSION['customer']['JobDesc'] = ' ';
    $_SESSION['customer']['JobTypeRef_ListID'] = ' ';
    $_SESSION['customer']['JobTypeRef_FullName'] = ' ';
    $_SESSION['customer']['Notes'] = ' ';
    $_SESSION['customer']['PriceLevelRef_ListID'] = ' ';
    $_SESSION['customer']['PriceLevelRef_FullName'] = ' ';
    $_SESSION['customer']['TaxRegistrationNumber'] = ' ';
    $_SESSION['customer']['CurrencyRef_ListID'] = ' ';
    $_SESSION['customer']['CurrencyRef_FullName'] = ' ';
    $_SESSION['customer']['IsStatementWithParent'] = 'false';
    $_SESSION['customer']['PreferredDeliveryMethod'] = ' ';
    $_SESSION['customer']['CustomField1'] = 'SIN RUTA';
    $_SESSION['customer']['CustomField2'] = ' ';
    $_SESSION['customer']['CustomField3'] = ' ';
    $_SESSION['customer']['CustomField4'] = ' ';
    $_SESSION['customer']['CustomField5'] = ' ';
    $_SESSION['customer']['CustomField6'] = ' ';
    $_SESSION['customer']['CustomField7'] = ' ';
    $_SESSION['customer']['CustomField8'] = ' ';
    $_SESSION['customer']['CustomField9'] = ' ';
    $_SESSION['customer']['CustomField10'] = 'SIN IMPRIMIR';
    $_SESSION['customer']['CustomField11'] = ' ';
    $_SESSION['customer']['CustomField12'] = ' ';
    $_SESSION['customer']['CustomField13'] = ' ';
    $_SESSION['customer']['CustomField14'] = ' ';
    $_SESSION['customer']['CustomField15'] = 'SIN FIRMAR';
    $_SESSION['customer']['Status'] = 'GENERADO';
}

function gentraverse_customer($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['customer']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['customer']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['customer']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['customer']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['customer']['Name'] = $nivel1->nodeValue;
                    break;
                case 'FullName':
                    $_SESSION['customer']['FullName'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['customer']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'Sublevel':
                    $_SESSION['customer']['Sublevel'] = $nivel1->nodeValue;
                    break;
                case 'CompanyName':
                    $_SESSION['customer']['CompanyName'] = $nivel1->nodeValue;
                    break;
                case 'Salutation':
                    $_SESSION['customer']['Salutation'] = $nivel1->nodeValue;
                    break;
                case 'FirstName':
                    $_SESSION['customer']['FirstName'] = $nivel1->nodeValue;
                    break;
                case 'MiddleName':
                    $_SESSION['customer']['MiddleName'] = $nivel1->nodeValue;
                    break;
                case 'LastName':
                    $_SESSION['customer']['LastName'] = $nivel1->nodeValue;
                    break;
                case 'Suffix':
                    $_SESSION['customer']['Suffix'] = $nivel1->nodeValue;
                    break;
                case 'BillAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['customer']['BillAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['customer']['BillAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['customer']['BillAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['customer']['BillAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['customer']['BillAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['customer']['BillAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['customer']['BillAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['customer']['BillAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['customer']['BillAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['customer']['BillAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'ShipAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['customer']['ShipAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['customer']['ShipAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['customer']['ShipAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['customer']['ShipAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['customer']['ShipAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['customer']['ShipAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['customer']['ShipAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['customer']['ShipAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['customer']['ShipAddress_Country'] = $nivel2->nodeValue;
                                break;
                            case 'Note':
                                $_SESSION['customer']['ShipAddress_Note'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'Phone':
                    $_SESSION['customer']['Phone'] = $nivel1->nodeValue;
                    break;
                case 'Mobile':
                    $_SESSION['customer']['Mobile'] = $nivel1->nodeValue;
                    break;
                case 'Pager':
                    $_SESSION['customer']['Pager'] = $nivel1->nodeValue;
                    break;
                case 'AltPhone':
                    $_SESSION['customer']['AltPhone'] = $nivel1->nodeValue;
                    break;
                case 'Fax':
                    $_SESSION['customer']['Fax'] = $nivel1->nodeValue;
                    break;
                case 'Email':
                    $_SESSION['customer']['Email'] = $nivel1->nodeValue;
                    break;
                case 'Cc':
                    $_SESSION['customer']['Cc'] = $nivel1->nodeValue;
                    break;
                case 'Contact':
                    $_SESSION['customer']['Contact'] = $nivel1->nodeValue;
                    break;
                case 'AltContact':
                    $_SESSION['customer']['AltContact'] = $nivel1->nodeValue;
                    break;
                case 'ClassRef':
                case 'ParentRef':
                case 'CustomerTypeRef':
                case 'TermsRef':
                case 'SalesRepRef':
                case 'SalesTaxCodeRef':
                case 'ItemSalesTaxRef':
                case 'PreferredPaymentMethodRef':
                case 'JobTypeRef':
                case 'PriceLevelRef':
                case 'CurrencyRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ParentRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['ParentRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['ParentRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CustomerTypeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['CustomerTypeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['CustomerTypeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'TermsRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['TermsRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['TermsRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesRepRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['SalesRepRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['SalesRepRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxCodeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['SalesTaxCodeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['SalesTaxCodeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ItemSalesTaxRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['ItemSalesTaxRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['ItemSalesTaxRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PreferredPaymentMethodRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['PreferredPaymentMethodRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['PreferredPaymentMethodRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'JobTypeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['JobTypeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['JobTypeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PriceLevelRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['PriceLevelRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['PriceLevelRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'CurrencyRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['customer']['CurrencyRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['customer']['CurrencyRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
                case 'Balance': $_SESSION['customer']['Balance'] = $nivel1->nodeValue;
                    break;
                case 'TotalBalance': $_SESSION['customer']['TotalBalance'] = $nivel1->nodeValue;
                    break;
                case 'SalesTaxCountry': $_SESSION['customer']['SalesTaxCountry'] = $nivel1->nodeValue;
                    break;
                case 'ResaleNumber': $_SESSION['customer']['ResaleNumber'] = $nivel1->nodeValue;
                    break;
                case 'AccountNumber': $_SESSION['customer']['AccountNumber'] = $nivel1->nodeValue;
                    break;
                case 'CreditLimit': $_SESSION['customer']['CreditLimit'] = $nivel1->nodeValue;
                    break;

                case 'JobStatus': $_SESSION['customer']['JobStatus'] = $nivel1->nodeValue;
                    break;
                case 'JobStartDate': $_SESSION['customer']['JobStartDate'] = $nivel1->nodeValue;
                    break;
                case 'JobProjectedEndDate': $_SESSION['customer']['JobProjectedEndDate'] = $nivel1->nodeValue;
                    break;
                case 'JobEndDate': $_SESSION['customer']['JobEndDate'] = $nivel1->nodeValue;
                    break;
                case 'JobDesc': $_SESSION['customer']['JobDesc'] = $nivel1->nodeValue;
                    break;
                case 'Notes': $_SESSION['customer']['Notes'] = $nivel1->nodeValue;
                    break;
            }
        }
    }
}

function adiciona_customer($db) {
    $estado = $_SESSION['customer']['ListID'] . ' campo ' . $_SESSION['customer']['Name'];

    try {
        $sql = 'INSERT INTO customer (ListID, TimeCreated, TimeModified, EditSequence, Name, FullName, IsActive, ClassRef_ListID, ClassRef_FullName, ParentRef_ListID, ParentRef_FullName, Sublevel, CompanyName, Salutation, FirstName, MiddleName, LastName, Suffix, BillAddress_Addr1, BillAddress_Addr2, BillAddress_Addr3, BillAddress_Addr4, BillAddress_Addr5, BillAddress_City, BillAddress_State, BillAddress_PostalCode, BillAddress_Country, BillAddress_Note, ShipAddress_Addr1, ShipAddress_Addr2, ShipAddress_Addr3, ShipAddress_Addr4, ShipAddress_Addr5, ShipAddress_City, ShipAddress_State, ShipAddress_PostalCode, ShipAddress_Country, ShipAddress_Note, PrintAs, Phone, Mobile, Pager, AltPhone, Fax, Email, Cc, Contact, AltContact, CustomerTypeRef_ListID, CustomerTypeRef_FullName, TermsRef_ListID, TermsRef_FullName, SalesRepRef_ListID, SalesRepRef_FullName, Balance, TotalBalance, SalesTaxCodeRef_ListID, SalesTaxCodeRef_FullName, ItemSalesTaxRef_ListID, ItemSalesTaxRef_FullName, SalesTaxCountry, ResaleNumber, AccountNumber, CreditLimit, PreferredPaymentMethodRef_ListID, PreferredPaymentMethodRef_FullName, CreditCardNumber, ExpirationMonth, ExpirationYear, NameOnCard, CreditCardAddress, CreditCardPostalCode, JobStatus, JobStartDate, JobProjectedEndDate, JobEndDate, JobDesc, JobTypeRef_ListID, JobTypeRef_FullName, Notes, PriceLevelRef_ListID, PriceLevelRef_FullName, TaxRegistrationNumber, CurrencyRef_ListID, CurrencyRef_FullName, IsStatementWithParent, PreferredDeliveryMethod, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :FullName, :IsActive, :ClassRef_ListID, :ClassRef_FullName, :ParentRef_ListID, :ParentRef_FullName, :Sublevel, :CompanyName, :Salutation, :FirstName, :MiddleName, :LastName, :Suffix, :BillAddress_Addr1, :BillAddress_Addr2, :BillAddress_Addr3, :BillAddress_Addr4, :BillAddress_Addr5, :BillAddress_City, :BillAddress_State, :BillAddress_PostalCode, :BillAddress_Country, :BillAddress_Note, :ShipAddress_Addr1, :ShipAddress_Addr2, :ShipAddress_Addr3, :ShipAddress_Addr4, :ShipAddress_Addr5, :ShipAddress_City, :ShipAddress_State, :ShipAddress_PostalCode, :ShipAddress_Country, :ShipAddress_Note, :PrintAs, :Phone, :Mobile, :Pager, :AltPhone, :Fax, :Email, :Cc, :Contact, :AltContact, :CustomerTypeRef_ListID, :CustomerTypeRef_FullName, :TermsRef_ListID, :TermsRef_FullName, :SalesRepRef_ListID, :SalesRepRef_FullName, :Balance, :TotalBalance, :SalesTaxCodeRef_ListID, :SalesTaxCodeRef_FullName, :ItemSalesTaxRef_ListID, :ItemSalesTaxRef_FullName, :SalesTaxCountry, :ResaleNumber, :AccountNumber, :CreditLimit, :PreferredPaymentMethodRef_ListID, :PreferredPaymentMethodRef_FullName, :CreditCardNumber, :ExpirationMonth, :ExpirationYear, :NameOnCard, :CreditCardAddress, :CreditCardPostalCode, :JobStatus, :JobStartDate, :JobProjectedEndDate, :JobEndDate, :JobDesc, :JobTypeRef_ListID, :JobTypeRef_FullName, :Notes, :PriceLevelRef_ListID, :PriceLevelRef_FullName, :TaxRegistrationNumber, :CurrencyRef_ListID, :CurrencyRef_FullName, :IsStatementWithParent, :PreferredDeliveryMethod, :CustomField1, :CustomField2, :CustomField3, :CustomField4, :CustomField5, :CustomField6, :CustomField7, :CustomField8, :CustomField9, :CustomField10, :CustomField11, :CustomField12, :CustomField13, :CustomField14, :CustomField15, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['customer']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['customer']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['customer']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['customer']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['customer']['Name']);
        $stmt->bindParam(':FullName', $_SESSION['customer']['FullName']);
        $stmt->bindParam(':IsActive', $_SESSION['customer']['IsActive']);
        $stmt->bindParam(':ClassRef_ListID', $_SESSION['customer']['ClassRef_ListID']);
        $stmt->bindParam(':ClassRef_FullName', $_SESSION['customer']['ClassRef_FullName']);
        $stmt->bindParam(':ParentRef_ListID', $_SESSION['customer']['ParentRef_ListID']);
        $stmt->bindParam(':ParentRef_FullName', $_SESSION['customer']['ParentRef_FullName']);
        $stmt->bindParam(':Sublevel', $_SESSION['customer']['Sublevel']);
        $stmt->bindParam(':CompanyName', $_SESSION['customer']['CompanyName']);
        $stmt->bindParam(':Salutation', $_SESSION['customer']['Salutation']);
        $stmt->bindParam(':FirstName', $_SESSION['customer']['FirstName']);
        $stmt->bindParam(':MiddleName', $_SESSION['customer']['MiddleName']);
        $stmt->bindParam(':LastName', $_SESSION['customer']['LastName']);
        $stmt->bindParam(':Suffix', $_SESSION['customer']['Suffix']);
        $stmt->bindParam(':BillAddress_Addr1', $_SESSION['customer']['BillAddress_Addr1']);
        $stmt->bindParam(':BillAddress_Addr2', $_SESSION['customer']['BillAddress_Addr2']);
        $stmt->bindParam(':BillAddress_Addr3', $_SESSION['customer']['BillAddress_Addr3']);
        $stmt->bindParam(':BillAddress_Addr4', $_SESSION['customer']['BillAddress_Addr4']);
        $stmt->bindParam(':BillAddress_Addr5', $_SESSION['customer']['BillAddress_Addr5']);
        $stmt->bindParam(':BillAddress_City', $_SESSION['customer']['BillAddress_City']);
        $stmt->bindParam(':BillAddress_State', $_SESSION['customer']['BillAddress_State']);
        $stmt->bindParam(':BillAddress_PostalCode', $_SESSION['customer']['BillAddress_PostalCode']);
        $stmt->bindParam(':BillAddress_Country', $_SESSION['customer']['BillAddress_Country']);
        $stmt->bindParam(':BillAddress_Note', $_SESSION['customer']['BillAddress_Note']);
        $stmt->bindParam(':ShipAddress_Addr1', $_SESSION['customer']['ShipAddress_Addr1']);
        $stmt->bindParam(':ShipAddress_Addr2', $_SESSION['customer']['ShipAddress_Addr2']);
        $stmt->bindParam(':ShipAddress_Addr3', $_SESSION['customer']['ShipAddress_Addr3']);
        $stmt->bindParam(':ShipAddress_Addr4', $_SESSION['customer']['ShipAddress_Addr4']);
        $stmt->bindParam(':ShipAddress_Addr5', $_SESSION['customer']['ShipAddress_Addr5']);
        $stmt->bindParam(':ShipAddress_City', $_SESSION['customer']['ShipAddress_City']);
        $stmt->bindParam(':ShipAddress_State', $_SESSION['customer']['ShipAddress_State']);
        $stmt->bindParam(':ShipAddress_PostalCode', $_SESSION['customer']['ShipAddress_PostalCode']);
        $stmt->bindParam(':ShipAddress_Country', $_SESSION['customer']['ShipAddress_Country']);
        $stmt->bindParam(':ShipAddress_Note', $_SESSION['customer']['ShipAddress_Note']);
        $stmt->bindParam(':PrintAs', $_SESSION['customer']['PrintAs']);
        $stmt->bindParam(':Phone', $_SESSION['customer']['Phone']);
        $stmt->bindParam(':Mobile', $_SESSION['customer']['Mobile']);
        $stmt->bindParam(':Pager', $_SESSION['customer']['Pager']);
        $stmt->bindParam(':AltPhone', $_SESSION['customer']['AltPhone']);
        $stmt->bindParam(':Fax', $_SESSION['customer']['Fax']);
        $stmt->bindParam(':Email', $_SESSION['customer']['Email']);
        $stmt->bindParam(':Cc', $_SESSION['customer']['Cc']);
        $stmt->bindParam(':Contact', $_SESSION['customer']['Contact']);
        $stmt->bindParam(':AltContact', $_SESSION['customer']['AltContact']);
        $stmt->bindParam(':CustomerTypeRef_ListID', $_SESSION['customer']['CustomerTypeRef_ListID']);
        $stmt->bindParam(':CustomerTypeRef_FullName', $_SESSION['customer']['CustomerTypeRef_FullName']);
        $stmt->bindParam(':TermsRef_ListID', $_SESSION['customer']['TermsRef_ListID']);
        $stmt->bindParam(':TermsRef_FullName', $_SESSION['customer']['TermsRef_FullName']);
        $stmt->bindParam(':SalesRepRef_ListID', $_SESSION['customer']['SalesRepRef_ListID']);
        $stmt->bindParam(':SalesRepRef_FullName', $_SESSION['customer']['SalesRepRef_FullName']);
        $stmt->bindParam(':Balance', $_SESSION['customer']['Balance']);
        $stmt->bindParam(':TotalBalance', $_SESSION['customer']['TotalBalance']);
        $stmt->bindParam(':SalesTaxCodeRef_ListID', $_SESSION['customer']['SalesTaxCodeRef_ListID']);
        $stmt->bindParam(':SalesTaxCodeRef_FullName', $_SESSION['customer']['SalesTaxCodeRef_FullName']);
        $stmt->bindParam(':ItemSalesTaxRef_ListID', $_SESSION['customer']['ItemSalesTaxRef_ListID']);
        $stmt->bindParam(':ItemSalesTaxRef_FullName', $_SESSION['customer']['ItemSalesTaxRef_FullName']);
        $stmt->bindParam(':SalesTaxCountry', $_SESSION['customer']['SalesTaxCountry']);
        $stmt->bindParam(':ResaleNumber', $_SESSION['customer']['ResaleNumber']);
        $stmt->bindParam(':AccountNumber', $_SESSION['customer']['AccountNumber']);
        $stmt->bindParam(':CreditLimit', $_SESSION['customer']['CreditLimit']);
        $stmt->bindParam(':PreferredPaymentMethodRef_ListID', $_SESSION['customer']['PreferredPaymentMethodRef_ListID']);
        $stmt->bindParam(':PreferredPaymentMethodRef_FullName', $_SESSION['customer']['PreferredPaymentMethodRef_FullName']);
        $stmt->bindParam(':CreditCardNumber', $_SESSION['customer']['CreditCardNumber']);
        $stmt->bindParam(':ExpirationMonth', $_SESSION['customer']['ExpirationMonth']);
        $stmt->bindParam(':ExpirationYear', $_SESSION['customer']['ExpirationYear']);
        $stmt->bindParam(':NameOnCard', $_SESSION['customer']['NameOnCard']);
        $stmt->bindParam(':CreditCardAddress', $_SESSION['customer']['CreditCardAddress']);
        $stmt->bindParam(':CreditCardPostalCode', $_SESSION['customer']['CreditCardPostalCode']);
        $stmt->bindParam(':JobStatus', $_SESSION['customer']['JobStatus']);
        $stmt->bindParam(':JobStartDate', $_SESSION['customer']['JobStartDate']);
        $stmt->bindParam(':JobProjectedEndDate', $_SESSION['customer']['JobProjectedEndDate']);
        $stmt->bindParam(':JobEndDate', $_SESSION['customer']['JobEndDate']);
        $stmt->bindParam(':JobDesc', $_SESSION['customer']['JobDesc']);
        $stmt->bindParam(':JobTypeRef_ListID', $_SESSION['customer']['JobTypeRef_ListID']);
        $stmt->bindParam(':JobTypeRef_FullName', $_SESSION['customer']['JobTypeRef_FullName']);
        $stmt->bindParam(':Notes', $_SESSION['customer']['Notes']);
        $stmt->bindParam(':PriceLevelRef_ListID', $_SESSION['customer']['PriceLevelRef_ListID']);
        $stmt->bindParam(':PriceLevelRef_FullName', $_SESSION['customer']['PriceLevelRef_FullName']);
        $stmt->bindParam(':TaxRegistrationNumber', $_SESSION['customer']['TaxRegistrationNumber']);
        $stmt->bindParam(':CurrencyRef_ListID', $_SESSION['customer']['CurrencyRef_ListID']);
        $stmt->bindParam(':CurrencyRef_FullName', $_SESSION['customer']['CurrencyRef_FullName']);
        $stmt->bindParam(':IsStatementWithParent', $_SESSION['customer']['IsStatementWithParent']);
        $stmt->bindParam(':PreferredDeliveryMethod', $_SESSION['customer']['PreferredDeliveryMethod']);
        $stmt->bindParam(':CustomField1', $_SESSION['customer']['CustomField1']);
        $stmt->bindParam(':CustomField2', $_SESSION['customer']['CustomField2']);
        $stmt->bindParam(':CustomField3', $_SESSION['customer']['CustomField3']);
        $stmt->bindParam(':CustomField4', $_SESSION['customer']['CustomField4']);
        $stmt->bindParam(':CustomField5', $_SESSION['customer']['CustomField5']);
        $stmt->bindParam(':CustomField6', $_SESSION['customer']['CustomField6']);
        $stmt->bindParam(':CustomField7', $_SESSION['customer']['CustomField7']);
        $stmt->bindParam(':CustomField8', $_SESSION['customer']['CustomField8']);
        $stmt->bindParam(':CustomField9', $_SESSION['customer']['CustomField9']);
        $stmt->bindParam(':CustomField10', $_SESSION['customer']['CustomField10']);
        $stmt->bindParam(':CustomField11', $_SESSION['customer']['CustomField11']);
        $stmt->bindParam(':CustomField12', $_SESSION['customer']['CustomField12']);
        $stmt->bindParam(':CustomField13', $_SESSION['customer']['CustomField13']);
        $stmt->bindParam(':CustomField14', $_SESSION['customer']['CustomField14']);
        $stmt->bindParam(':CustomField15', $_SESSION['customer']['CustomField15']);
        $stmt->bindParam(':Status', $_SESSION['customer']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        $estado = 'ERROR JC!!! ' . $e->getMessage() . '<br>' . $_SESSION['customer']['ListID'] . ' Nombre ' . $_SESSION['customer']['Name'] . ' mes ' . $_SESSION['customer']['ExpirationMonth'];
    }
    return $estado;
}

function quitaslashes_customer() {
    $_SESSION['customer']['ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['ListID']));
    $_SESSION['customer']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['customer']['TimeCreated']));
    $_SESSION['customer']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['customer']['TimeModified']));
    $_SESSION['customer']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['customer']['EditSequence']));
    $_SESSION['customer']['Name'] = htmlspecialchars(strip_tags($_SESSION['customer']['Name']));
    $_SESSION['customer']['FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['FullName']));
    $_SESSION['customer']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['customer']['IsActive']));
    $_SESSION['customer']['ClassRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['ClassRef_ListID']));
    $_SESSION['customer']['ClassRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['ClassRef_FullName']));
    $_SESSION['customer']['ParentRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['ParentRef_ListID']));
    $_SESSION['customer']['ParentRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['ParentRef_FullName']));
    $_SESSION['customer']['Sublevel'] = htmlspecialchars(strip_tags($_SESSION['customer']['Sublevel']));
    $_SESSION['customer']['CompanyName'] = htmlspecialchars(strip_tags($_SESSION['customer']['CompanyName']));
    $_SESSION['customer']['Salutation'] = htmlspecialchars(strip_tags($_SESSION['customer']['Salutation']));
    $_SESSION['customer']['FirstName'] = htmlspecialchars(strip_tags($_SESSION['customer']['FirstName']));
    $_SESSION['customer']['MiddleName'] = htmlspecialchars(strip_tags($_SESSION['customer']['MiddleName']));
    $_SESSION['customer']['LastName'] = htmlspecialchars(strip_tags($_SESSION['customer']['LastName']));
    $_SESSION['customer']['Suffix'] = htmlspecialchars(strip_tags($_SESSION['customer']['Suffix']));
    $_SESSION['customer']['BillAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Addr1']));
    $_SESSION['customer']['BillAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Addr2']));
    $_SESSION['customer']['BillAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Addr3']));
    $_SESSION['customer']['BillAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Addr4']));
    $_SESSION['customer']['BillAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Addr5']));
    $_SESSION['customer']['BillAddress_City'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_City']));
    $_SESSION['customer']['BillAddress_State'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_State']));
    $_SESSION['customer']['BillAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_PostalCode']));
    $_SESSION['customer']['BillAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Country']));
    $_SESSION['customer']['BillAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['customer']['BillAddress_Note']));
    $_SESSION['customer']['ShipAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Addr1']));
    $_SESSION['customer']['ShipAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Addr2']));
    $_SESSION['customer']['ShipAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Addr3']));
    $_SESSION['customer']['ShipAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Addr4']));
    $_SESSION['customer']['ShipAddress_Addr5'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Addr5']));
    $_SESSION['customer']['ShipAddress_City'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_City']));
    $_SESSION['customer']['ShipAddress_State'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_State']));
    $_SESSION['customer']['ShipAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_PostalCode']));
    $_SESSION['customer']['ShipAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Country']));
    $_SESSION['customer']['ShipAddress_Note'] = htmlspecialchars(strip_tags($_SESSION['customer']['ShipAddress_Note']));
    $_SESSION['customer']['PrintAs'] = htmlspecialchars(strip_tags($_SESSION['customer']['PrintAs']));
    $_SESSION['customer']['Phone'] = htmlspecialchars(strip_tags($_SESSION['customer']['Phone']));
    $_SESSION['customer']['Mobile'] = htmlspecialchars(strip_tags($_SESSION['customer']['Mobile']));
    $_SESSION['customer']['Pager'] = htmlspecialchars(strip_tags($_SESSION['customer']['Pager']));
    $_SESSION['customer']['AltPhone'] = htmlspecialchars(strip_tags($_SESSION['customer']['AltPhone']));
    $_SESSION['customer']['Fax'] = htmlspecialchars(strip_tags($_SESSION['customer']['Fax']));
    $_SESSION['customer']['Email'] = htmlspecialchars(strip_tags($_SESSION['customer']['Email']));
    $_SESSION['customer']['Cc'] = htmlspecialchars(strip_tags($_SESSION['customer']['Cc']));
    $_SESSION['customer']['Contact'] = htmlspecialchars(strip_tags($_SESSION['customer']['Contact']));
    $_SESSION['customer']['AltContact'] = htmlspecialchars(strip_tags($_SESSION['customer']['AltContact']));
    $_SESSION['customer']['CustomerTypeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomerTypeRef_ListID']));
    $_SESSION['customer']['CustomerTypeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomerTypeRef_FullName']));
    $_SESSION['customer']['TermsRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['TermsRef_ListID']));
    $_SESSION['customer']['TermsRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['TermsRef_FullName']));
    $_SESSION['customer']['SalesRepRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['SalesRepRef_ListID']));
    $_SESSION['customer']['SalesRepRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['SalesRepRef_FullName']));
    $_SESSION['customer']['Balance'] = htmlspecialchars(strip_tags($_SESSION['customer']['Balance']));
    $_SESSION['customer']['TotalBalance'] = htmlspecialchars(strip_tags($_SESSION['customer']['TotalBalance']));
    $_SESSION['customer']['SalesTaxCodeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['SalesTaxCodeRef_ListID']));
    $_SESSION['customer']['SalesTaxCodeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['SalesTaxCodeRef_FullName']));
    $_SESSION['customer']['ItemSalesTaxRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['ItemSalesTaxRef_ListID']));
    $_SESSION['customer']['ItemSalesTaxRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['ItemSalesTaxRef_FullName']));
    $_SESSION['customer']['SalesTaxCountry'] = htmlspecialchars(strip_tags($_SESSION['customer']['SalesTaxCountry']));
    $_SESSION['customer']['ResaleNumber'] = htmlspecialchars(strip_tags($_SESSION['customer']['ResaleNumber']));
    $_SESSION['customer']['AccountNumber'] = htmlspecialchars(strip_tags($_SESSION['customer']['AccountNumber']));
    $_SESSION['customer']['CreditLimit'] = htmlspecialchars(strip_tags($_SESSION['customer']['CreditLimit']));
    $_SESSION['customer']['PreferredPaymentMethodRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['PreferredPaymentMethodRef_ListID']));
    $_SESSION['customer']['PreferredPaymentMethodRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['PreferredPaymentMethodRef_FullName']));
    $_SESSION['customer']['CreditCardNumber'] = htmlspecialchars(strip_tags($_SESSION['customer']['CreditCardNumber']));
    $_SESSION['customer']['ExpirationMonth'] = htmlspecialchars(strip_tags($_SESSION['customer']['ExpirationMonth']));
    $_SESSION['customer']['ExpirationYear'] = htmlspecialchars(strip_tags($_SESSION['customer']['ExpirationYear']));
    $_SESSION['customer']['NameOnCard'] = htmlspecialchars(strip_tags($_SESSION['customer']['NameOnCard']));
    $_SESSION['customer']['CreditCardAddress'] = htmlspecialchars(strip_tags($_SESSION['customer']['CreditCardAddress']));
    $_SESSION['customer']['CreditCardPostalCode'] = htmlspecialchars(strip_tags($_SESSION['customer']['CreditCardPostalCode']));
    $_SESSION['customer']['JobStatus'] = htmlspecialchars(strip_tags($_SESSION['customer']['JobStatus']));
    $_SESSION['customer']['JobStartDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['customer']['JobStartDate']));
    $_SESSION['customer']['JobProjectedEndDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['customer']['JobProjectedEndDate']));
    $_SESSION['customer']['JobEndDate'] = date("Y-m-d H:m:s", strtotime($_SESSION['customer']['JobEndDate']));
    $_SESSION['customer']['JobDesc'] = htmlspecialchars(strip_tags($_SESSION['customer']['JobDesc']));
    $_SESSION['customer']['JobTypeRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['JobTypeRef_ListID']));
    $_SESSION['customer']['JobTypeRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['JobTypeRef_FullName']));
    $_SESSION['customer']['Notes'] = htmlspecialchars(strip_tags($_SESSION['customer']['Notes']));
    $_SESSION['customer']['PriceLevelRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['PriceLevelRef_ListID']));
    $_SESSION['customer']['PriceLevelRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['PriceLevelRef_FullName']));
    $_SESSION['customer']['TaxRegistrationNumber'] = htmlspecialchars(strip_tags($_SESSION['customer']['TaxRegistrationNumber']));
    $_SESSION['customer']['CurrencyRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['customer']['CurrencyRef_ListID']));
    $_SESSION['customer']['CurrencyRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['customer']['CurrencyRef_FullName']));
    $_SESSION['customer']['IsStatementWithParent'] = htmlspecialchars(strip_tags($_SESSION['customer']['IsStatementWithParent']));
    $_SESSION['customer']['PreferredDeliveryMethod'] = htmlspecialchars(strip_tags($_SESSION['customer']['PreferredDeliveryMethod']));
    $_SESSION['customer']['CustomField1'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField1']));
    $_SESSION['customer']['CustomField2'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField2']));
    $_SESSION['customer']['CustomField3'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField3']));
    $_SESSION['customer']['CustomField4'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField4']));
    $_SESSION['customer']['CustomField5'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField5']));
    $_SESSION['customer']['CustomField6'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField6']));
    $_SESSION['customer']['CustomField7'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField7']));
    $_SESSION['customer']['CustomField8'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField8']));
    $_SESSION['customer']['CustomField9'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField9']));
    $_SESSION['customer']['CustomField10'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField10']));
    $_SESSION['customer']['CustomField11'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField11']));
    $_SESSION['customer']['CustomField12'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField12']));
    $_SESSION['customer']['CustomField13'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField13']));
    $_SESSION['customer']['CustomField14'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField14']));
    $_SESSION['customer']['CustomField15'] = htmlspecialchars(strip_tags($_SESSION['customer']['CustomField15']));
    $_SESSION['customer']['Status'] = htmlspecialchars(strip_tags($_SESSION['customer']['Status']));
}
