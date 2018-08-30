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
define('QB_QUICKBOOKS_MAX_RETURNED', 25);
define('QB_PRIORITY_EMPLOYEE', 1);
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
define('FECHAMODIFICACION', $fecha);
fwrite($myfile, 'fechas : ' . FECHAMODIFICACION . '\r\n');
fclose($myfile);
$stmt = null;
$db = null;

$map = array(
   QUICKBOOKS_IMPORT_EMPLOYEE => array('_quickbooks_employee_import_request', '_quickbooks_employee_import_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_EMPLOYEE)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_EMPLOYEE, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_EMPLOYEE, 1, QB_PRIORITY_EMPLOYEE, NULL, $user);
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

function _quickbooks_employee_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID'])) {
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);
        _quickbooks_set_current_run($user, $action, $last);
        _quickbooks_employee_initial_response();
    } else {
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';
        $last = _quickbooks_get_current_run($user, $action);
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
			<EmployeeQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
                            <MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
                            <OwnerID>0</OwnerID>
			</EmployeeQueryRq>	
                    </QBXMLMsgsRq>
		</QBXML>';
    $myfile = fopen("newfile2.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, $xml);
    fclose($myfile);
    return $xml;
}

function _quickbooks_employee_initial_response() {
    $myfile = fopen("newfile1.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "QB WC Paso inicial ");
    fclose($myfile);
}

function _quickbooks_employee_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    if (!empty($idents['iteratorRemainingCount'])) {
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_EMPLOYEE, null, QB_PRIORITY_EMPLOYEE, array('iteratorID' => $idents['iteratorID']), $user);
    }
    $myfile = fopen("newfile3.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP \r\n");
    $_SESSION['employee'] = array();
    $doc = new DOMDocument();
    $doc->load($xml);
    $myfile1 = fopen("employees.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $param = "EmployeeRet";
    $empleado = $doc->getElementsByTagName($param);
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($empleado as $uno) {
        genLimpia_employee();
        gentraverse_employee($uno);
        $existe = buscaIgual_employee($db);
        if ($existe == "OK") {
            quitaslashes_employee();
            fwrite($myfile, "NO!!! Existe empleado " . $_SESSION['employee']['ListID'] . " \r\n");
            adiciona_employee($db);
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_employee();
            fwrite($myfile, "SI Existe empleado " . $_SESSION['employee']['ListID'] . " \r\n");
            update_employee($db);
        }
    }
    $db = null;
    fclose($myfile);
    return true;
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_IMPORT_EMPLOYEE) {
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

function _inicio() {
}

function genLimpia_employee() {
    $_SESSION['employee']['ListID'] = ' ';
    $_SESSION['employee']['TimeCreated'] = ' ';
    $_SESSION['employee']['TimeModified'] = ' ';
    $_SESSION['employee']['EditSequence'] = ' ';
    $_SESSION['employee']['Name'] = ' ';
    $_SESSION['employee']['IsActive'] = 'true';
    $_SESSION['employee']['Salutation'] = ' ';
    $_SESSION['employee']['FirstName'] = ' ';
    $_SESSION['employee']['MiddleName'] = ' ';
    $_SESSION['employee']['LastName'] = ' ';
    $_SESSION['employee']['Suffix'] = ' ';
    $_SESSION['employee']['JobTitle'] = ' ';
    $_SESSION['employee']['SupervisorRef_ListID'] = ' ';
    $_SESSION['employee']['SupervisorRef_FullName'] = ' ';
    $_SESSION['employee']['Department'] = ' ';
    $_SESSION['employee']['Description'] = ' ';
    $_SESSION['employee']['TargetBonus'] = ' ';
    $_SESSION['employee']['EmployeeAddress_Addr1'] = ' ';
    $_SESSION['employee']['EmployeeAddress_Addr2'] = ' ';
    $_SESSION['employee']['EmployeeAddress_Addr3'] = ' ';
    $_SESSION['employee']['EmployeeAddress_Addr4'] = ' ';
    $_SESSION['employee']['EmployeeAddress_City'] = ' ';
    $_SESSION['employee']['EmployeeAddress_State'] = ' ';
    $_SESSION['employee']['EmployeeAddress_PostalCode'] = ' ';
    $_SESSION['employee']['EmployeeAddress_Country'] = ' ';
    $_SESSION['employee']['PrintAs'] = ' ';
    $_SESSION['employee']['Phone'] = ' ';
    $_SESSION['employee']['Mobile'] = ' ';
    $_SESSION['employee']['Pager'] = ' ';
    $_SESSION['employee']['PagerPIN'] = ' ';
    $_SESSION['employee']['AltPhone'] = ' ';
    $_SESSION['employee']['Fax'] = ' ';
    $_SESSION['employee']['SSN'] = ' ';
    $_SESSION['employee']['Email'] = ' ';
    $_SESSION['employee']['EmergencyContactPrimaryName'] = ' ';
    $_SESSION['employee']['EmergencyContactPrimaryValue'] = ' ';
    $_SESSION['employee']['EmergencyContactPrimaryRelation'] = ' ';
    $_SESSION['employee']['EmergencyContactSecondaryName'] = ' ';
    $_SESSION['employee']['EmergencyContactSecondaryValue'] = ' ';
    $_SESSION['employee']['EmergencyContactSecondaryRelation'] = ' ';
    $_SESSION['employee']['EmployeeType'] = ' ';
    $_SESSION['employee']['Gender'] = ' ';
    $_SESSION['employee']['PartOrFullTime'] = ' ';
    $_SESSION['employee']['Exempt'] = ' ';
    $_SESSION['employee']['KeyEmployee'] = ' ';
    $_SESSION['employee']['HiredDate'] = '2010-01-01';
    $_SESSION['employee']['OriginalHireDate'] = '2010-01-01';
    $_SESSION['employee']['AdjustedServiceDate'] = '2010-01-01';
    $_SESSION['employee']['ReleasedDate'] = '2010-01-01';
    $_SESSION['employee']['BirthDate'] = '1985-01-01';
    $_SESSION['employee']['USCitizen'] = ' ';
    $_SESSION['employee']['Ethnicity'] = ' ';
    $_SESSION['employee']['Disabled'] = ' ';
    $_SESSION['employee']['DisabilityDesc'] = ' ';
    $_SESSION['employee']['OnFile'] = ' ';
    $_SESSION['employee']['WorkAuthExpireDate'] = ' ';
    $_SESSION['employee']['USVeteran'] = ' ';
    $_SESSION['employee']['MilitaryStatus'] = ' ';
    $_SESSION['employee']['AccountNumber'] = ' ';
    $_SESSION['employee']['Notes'] = ' ';
    $_SESSION['employee']['BillingRateRef_ListID'] = ' ';
    $_SESSION['employee']['BillingRateRef_FullName'] = ' ';
    $_SESSION['employee']['CustomField1'] = ' ';
    $_SESSION['employee']['CustomField2'] = ' ';
    $_SESSION['employee']['CustomField3'] = ' ';
    $_SESSION['employee']['CustomField4'] = ' ';
    $_SESSION['employee']['CustomField5'] = ' ';
    $_SESSION['employee']['CustomField6'] = ' ';
    $_SESSION['employee']['CustomField7'] = ' ';
    $_SESSION['employee']['CustomField8'] = ' ';
    $_SESSION['employee']['CustomField9'] = ' ';
    $_SESSION['employee']['CustomField10'] = ' ';
    $_SESSION['employee']['CustomField11'] = ' ';
    $_SESSION['employee']['CustomField12'] = ' ';
    $_SESSION['employee']['CustomField13'] = ' ';
    $_SESSION['employee']['CustomField14'] = ' ';
    $_SESSION['employee']['CustomField15'] = ' ';
    $_SESSION['employee']['Status'] = ' ';
}

function gentraverse_employee($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['employee']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['employee']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['employee']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['employee']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['employee']['Name'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['employee']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'Department':
                    $_SESSION['employee']['Department'] = $nivel1->nodeValue;
                    break;
                case 'Description':
                    $_SESSION['employee']['Description'] = $nivel1->nodeValue;
                    break;
                case 'EmployeeAddress':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Addr1':
                                $_SESSION['employee']['EmployeeAddress_Addr1'] = $nivel2->nodeValue;
                                break;
                            case 'Addr2':
                                $_SESSION['employee']['EmployeeAddress_Addr2'] = $nivel2->nodeValue;
                                break;
                            case 'Addr3':
                                $_SESSION['employee']['EmployeeAddress_Addr3'] = $nivel2->nodeValue;
                                break;
                            case 'Addr4':
                                $_SESSION['employee']['EmployeeAddress_Addr4'] = $nivel2->nodeValue;
                                break;
                            case 'Addr5':
                                $_SESSION['employee']['EmployeeAddress_Addr5'] = $nivel2->nodeValue;
                                break;
                            case 'City':
                                $_SESSION['employee']['EmployeeAddress_City'] = $nivel2->nodeValue;
                                break;
                            case 'State':
                                $_SESSION['employee']['EmployeeAddress_State'] = $nivel2->nodeValue;
                                break;
                            case 'PostalCode':
                                $_SESSION['employee']['EmployeeAddress_PostalCode'] = $nivel2->nodeValue;
                                break;
                            case 'Country':
                                $_SESSION['employee']['EmployeeAddress_Country'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'Phone':
                    $_SESSION['employee']['Phone'] = $nivel1->nodeValue;
                    break;
                case 'Mobile':
                    $_SESSION['employee']['Mobile'] = $nivel1->nodeValue;
                    break;
                case 'Email':
                    $_SESSION['employee']['Email'] = $nivel1->nodeValue;
                    break;
                case 'AccountNumber':
                    $_SESSION['employee']['AccountNumber'] = $nivel1->nodeValue;
                    break;
                case 'Status':
                    $_SESSION['employee']['Status'] = $nivel1->nodeValue;
                    break;
                default :
                    break;
            }
        }
    }
}

function adiciona_employee($db) {

    try {
        $sql = 'INSERT INTO employee (  ListID, TimeCreated, TimeModified, EditSequence, Name, IsActive, Salutation, FirstName, MiddleName, LastName, Suffix, JobTitle, SupervisorRef_ListID, SupervisorRef_FullName, Department, Description, TargetBonus, EmployeeAddress_Addr1, EmployeeAddress_Addr2, EmployeeAddress_Addr3, EmployeeAddress_Addr4, EmployeeAddress_City, EmployeeAddress_State, EmployeeAddress_PostalCode, EmployeeAddress_Country, PrintAs, Phone, Mobile, Pager, PagerPIN, AltPhone, Fax, SSN, Email, EmergencyContactPrimaryName, EmergencyContactPrimaryValue, EmergencyContactPrimaryRelation, EmergencyContactSecondaryName, EmergencyContactSecondaryValue, EmergencyContactSecondaryRelation, EmployeeType, Gender, PartOrFullTime, Exempt, KeyEmployee, HiredDate, OriginalHireDate, AdjustedServiceDate, ReleasedDate, BirthDate, USCitizen, Ethnicity, Disabled, DisabilityDesc, OnFile, WorkAuthExpireDate, USVeteran, MilitaryStatus, AccountNumber, Notes, BillingRateRef_ListID, BillingRateRef_FullName, CustomField1, CustomField2, CustomField3, CustomField4, CustomField5, CustomField6, CustomField7, CustomField8, CustomField9, CustomField10, CustomField11, CustomField12, CustomField13, CustomField14, CustomField15, Status) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :IsActive, :Salutation, :FirstName, :MiddleName, :LastName, :Suffix, :JobTitle, :SupervisorRef_ListID, :SupervisorRef_FullName, :Department, :Description, :TargetBonus, :EmployeeAddress_Addr1, :EmployeeAddress_Addr2, :EmployeeAddress_Addr3, :EmployeeAddress_Addr4, :EmployeeAddress_City, :EmployeeAddress_State, :EmployeeAddress_PostalCode, :EmployeeAddress_Country, :PrintAs, :Phone, :Mobile, :Pager, :PagerPIN, :AltPhone, :Fax, :SSN, :Email, :EmergencyContactPrimaryName, :EmergencyContactPrimaryValue, :EmergencyContactPrimaryRelation, :EmergencyContactSecondaryName, :EmergencyContactSecondaryValue, :EmergencyContactSecondaryRelation, :EmployeeType, :Gender, :PartOrFullTime, :Exempt, :KeyEmployee, :HiredDate, :OriginalHireDate, :AdjustedServiceDate, :ReleasedDate, :BirthDate, :USCitizen, :Ethnicity, :Disabled, :DisabilityDesc, :OnFile, :WorkAuthExpireDate, :USVeteran, :MilitaryStatus, :AccountNumber, :Notes, :BillingRateRef_ListID, :BillingRateRef_FullName, :Status)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['employee']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['employee']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['employee']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['employee']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['employee']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['employee']['IsActive']);
        $stmt->bindParam(':Salutation', $_SESSION['employee']['Salutation']);
        $stmt->bindParam(':FirstName', $_SESSION['employee']['FirstName']);
        $stmt->bindParam(':MiddleName', $_SESSION['employee']['MiddleName']);
        $stmt->bindParam(':LastName', $_SESSION['employee']['LastName']);
        $stmt->bindParam(':Suffix', $_SESSION['employee']['Suffix']);
        $stmt->bindParam(':JobTitle', $_SESSION['employee']['JobTitle']);
        $stmt->bindParam(':SupervisorRef_ListID', $_SESSION['employee']['SupervisorRef_ListID']);
        $stmt->bindParam(':SupervisorRef_FullName', $_SESSION['employee']['SupervisorRef_FullName']);
        $stmt->bindParam(':Department', $_SESSION['employee']['Department']);
        $stmt->bindParam(':Description', $_SESSION['employee']['Description']);
        $stmt->bindParam(':TargetBonus', $_SESSION['employee']['TargetBonus']);
        $stmt->bindParam(':EmployeeAddress_Addr1', $_SESSION['employee']['EmployeeAddress_Addr1']);
        $stmt->bindParam(':EmployeeAddress_Addr2', $_SESSION['employee']['EmployeeAddress_Addr2']);
        $stmt->bindParam(':EmployeeAddress_Addr3', $_SESSION['employee']['EmployeeAddress_Addr3']);
        $stmt->bindParam(':EmployeeAddress_Addr4', $_SESSION['employee']['EmployeeAddress_Addr4']);
        $stmt->bindParam(':EmployeeAddress_City', $_SESSION['employee']['EmployeeAddress_City']);
        $stmt->bindParam(':EmployeeAddress_State', $_SESSION['employee']['EmployeeAddress_State']);
        $stmt->bindParam(':EmployeeAddress_PostalCode', $_SESSION['employee']['EmployeeAddress_PostalCode']);
        $stmt->bindParam(':EmployeeAddress_Country', $_SESSION['employee']['EmployeeAddress_Country']);
        $stmt->bindParam(':PrintAs', $_SESSION['employee']['PrintAs']);
        $stmt->bindParam(':Phone', $_SESSION['employee']['Phone']);
        $stmt->bindParam(':Mobile', $_SESSION['employee']['Mobile']);
        $stmt->bindParam(':Pager', $_SESSION['employee']['Pager']);
        $stmt->bindParam(':PagerPIN', $_SESSION['employee']['PagerPIN']);
        $stmt->bindParam(':AltPhone', $_SESSION['employee']['AltPhone']);
        $stmt->bindParam(':Fax', $_SESSION['employee']['Fax']);
        $stmt->bindParam(':SSN', $_SESSION['employee']['SSN']);
        $stmt->bindParam(':Email', $_SESSION['employee']['Email']);
        $stmt->bindParam(':EmergencyContactPrimaryName', $_SESSION['employee']['EmergencyContactPrimaryName']);
        $stmt->bindParam(':EmergencyContactPrimaryValue', $_SESSION['employee']['EmergencyContactPrimaryValue']);
        $stmt->bindParam(':EmergencyContactPrimaryRelation', $_SESSION['employee']['EmergencyContactPrimaryRelation']);
        $stmt->bindParam(':EmergencyContactSecondaryName', $_SESSION['employee']['EmergencyContactSecondaryName']);
        $stmt->bindParam(':EmergencyContactSecondaryValue', $_SESSION['employee']['EmergencyContactSecondaryValue']);
        $stmt->bindParam(':EmergencyContactSecondaryRelation', $_SESSION['employee']['EmergencyContactSecondaryRelation']);
        $stmt->bindParam(':EmployeeType', $_SESSION['employee']['EmployeeType']);
        $stmt->bindParam(':Gender', $_SESSION['employee']['Gender']);
        $stmt->bindParam(':PartOrFullTime', $_SESSION['employee']['PartOrFullTime']);
        $stmt->bindParam(':Exempt', $_SESSION['employee']['Exempt']);
        $stmt->bindParam(':KeyEmployee', $_SESSION['employee']['KeyEmployee']);
        $stmt->bindParam(':HiredDate', $_SESSION['employee']['HiredDate']);
        $stmt->bindParam(':OriginalHireDate', $_SESSION['employee']['OriginalHireDate']);
        $stmt->bindParam(':AdjustedServiceDate', $_SESSION['employee']['AdjustedServiceDate']);
        $stmt->bindParam(':ReleasedDate', $_SESSION['employee']['ReleasedDate']);
        $stmt->bindParam(':BirthDate', $_SESSION['employee']['BirthDate']);
        $stmt->bindParam(':USCitizen', $_SESSION['employee']['USCitizen']);
        $stmt->bindParam(':Ethnicity', $_SESSION['employee']['Ethnicity']);
        $stmt->bindParam(':Disabled', $_SESSION['employee']['Disabled']);
        $stmt->bindParam(':DisabilityDesc', $_SESSION['employee']['DisabilityDesc']);
        $stmt->bindParam(':OnFile', $_SESSION['employee']['OnFile']);
        $stmt->bindParam(':WorkAuthExpireDate', $_SESSION['employee']['WorkAuthExpireDate']);
        $stmt->bindParam(':USVeteran', $_SESSION['employee']['USVeteran']);
        $stmt->bindParam(':MilitaryStatus', $_SESSION['employee']['MilitaryStatus']);
        $stmt->bindParam(':AccountNumber', $_SESSION['employee']['AccountNumber']);
        $stmt->bindParam(':Notes', $_SESSION['employee']['Notes']);
        $stmt->bindParam(':BillingRateRef_ListID', $_SESSION['employee']['BillingRateRef_ListID']);
        $stmt->bindParam(':BillingRateRef_FullName', $_SESSION['employee']['BillingRateRef_FullName']);
        $stmt->bindParam(':Status', $_SESSION['employee']['Status']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function quitaslashes_employee() {

    $_SESSION['employee']['ListID'] = htmlspecialchars(strip_tags($_SESSION['employee']['ListID']));
    $_SESSION['employee']['TimeCreated'] = date("Y-m-d H:m:s", strtotime($_SESSION['employee']['TimeCreated']));
    $_SESSION['employee']['TimeModified'] = date("Y-m-d H:m:s", strtotime($_SESSION['employee']['TimeCreated']));
    $_SESSION['employee']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['employee']['EditSequence']));
    $_SESSION['employee']['Name'] = htmlspecialchars(strip_tags($_SESSION['employee']['Name']));
    $_SESSION['employee']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['employee']['IsActive']));
    $_SESSION['employee']['Salutation'] = htmlspecialchars(strip_tags($_SESSION['employee']['Salutation']));
    $_SESSION['employee']['FirstName'] = htmlspecialchars(strip_tags($_SESSION['employee']['FirstName']));
    $_SESSION['employee']['MiddleName'] = htmlspecialchars(strip_tags($_SESSION['employee']['MiddleName']));
    $_SESSION['employee']['LastName'] = htmlspecialchars(strip_tags($_SESSION['employee']['LastName']));
    $_SESSION['employee']['Suffix'] = htmlspecialchars(strip_tags($_SESSION['employee']['Suffix']));
    $_SESSION['employee']['JobTitle'] = htmlspecialchars(strip_tags($_SESSION['employee']['JobTitle']));
    $_SESSION['employee']['SupervisorRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['employee']['SupervisorRef_ListID']));
    $_SESSION['employee']['SupervisorRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['employee']['SupervisorRef_FullName']));
    $_SESSION['employee']['Department'] = htmlspecialchars(strip_tags($_SESSION['employee']['Department']));
    $_SESSION['employee']['Description'] = htmlspecialchars(strip_tags($_SESSION['employee']['Description']));
    $_SESSION['employee']['TargetBonus'] = htmlspecialchars(strip_tags($_SESSION['employee']['TargetBonus']));
    $_SESSION['employee']['EmployeeAddress_Addr1'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_Addr1']));
    $_SESSION['employee']['EmployeeAddress_Addr2'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_Addr2']));
    $_SESSION['employee']['EmployeeAddress_Addr3'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_Addr3']));
    $_SESSION['employee']['EmployeeAddress_Addr4'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_Addr4']));
    $_SESSION['employee']['EmployeeAddress_City'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_City']));
    $_SESSION['employee']['EmployeeAddress_State'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_State']));
    $_SESSION['employee']['EmployeeAddress_PostalCode'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_PostalCode']));
    $_SESSION['employee']['EmployeeAddress_Country'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeAddress_Country']));
    $_SESSION['employee']['PrintAs'] = htmlspecialchars(strip_tags($_SESSION['employee']['PrintAs']));
    $_SESSION['employee']['Phone'] = htmlspecialchars(strip_tags($_SESSION['employee']['Phone']));
    $_SESSION['employee']['Mobile'] = htmlspecialchars(strip_tags($_SESSION['employee']['Mobile']));
    $_SESSION['employee']['Pager'] = htmlspecialchars(strip_tags($_SESSION['employee']['Pager']));
    $_SESSION['employee']['PagerPIN'] = htmlspecialchars(strip_tags($_SESSION['employee']['PagerPIN']));
    $_SESSION['employee']['AltPhone'] = htmlspecialchars(strip_tags($_SESSION['employee']['AltPhone']));
    $_SESSION['employee']['Fax'] = htmlspecialchars(strip_tags($_SESSION['employee']['Fax']));
    $_SESSION['employee']['SSN'] = htmlspecialchars(strip_tags($_SESSION['employee']['SSN']));
    $_SESSION['employee']['Email'] = htmlspecialchars(strip_tags($_SESSION['employee']['Email']));
    $_SESSION['employee']['EmergencyContactPrimaryName'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactPrimaryName']));
    $_SESSION['employee']['EmergencyContactPrimaryValue'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactPrimaryValue']));
    $_SESSION['employee']['EmergencyContactPrimaryRelation'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactPrimaryRelation']));
    $_SESSION['employee']['EmergencyContactSecondaryName'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactSecondaryName']));
    $_SESSION['employee']['EmergencyContactSecondaryValue'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactSecondaryValue']));
    $_SESSION['employee']['EmergencyContactSecondaryRelation'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmergencyContactSecondaryRelation']));
    $_SESSION['employee']['EmployeeType'] = htmlspecialchars(strip_tags($_SESSION['employee']['EmployeeType']));
    $_SESSION['employee']['Gender'] = htmlspecialchars(strip_tags($_SESSION['employee']['Gender']));
    $_SESSION['employee']['PartOrFullTime'] = htmlspecialchars(strip_tags($_SESSION['employee']['PartOrFullTime']));
    $_SESSION['employee']['Exempt'] = htmlspecialchars(strip_tags($_SESSION['employee']['Exempt']));
    $_SESSION['employee']['KeyEmployee'] = htmlspecialchars(strip_tags($_SESSION['employee']['KeyEmployee']));
    $_SESSION['employee']['HiredDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['HiredDate']));
    $_SESSION['employee']['OriginalHireDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['OriginalHireDate']));
    $_SESSION['employee']['AdjustedServiceDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['AdjustedServiceDate']));
    $_SESSION['employee']['ReleasedDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['ReleasedDate']));
    $_SESSION['employee']['BirthDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['BirthDate']));
    $_SESSION['employee']['USCitizen'] = htmlspecialchars(strip_tags($_SESSION['employee']['USCitizen']));
    $_SESSION['employee']['Ethnicity'] = htmlspecialchars(strip_tags($_SESSION['employee']['Ethnicity']));
    $_SESSION['employee']['Disabled'] = htmlspecialchars(strip_tags($_SESSION['employee']['Disabled']));
    $_SESSION['employee']['DisabilityDesc'] = htmlspecialchars(strip_tags($_SESSION['employee']['DisabilityDesc']));
    $_SESSION['employee']['OnFile'] = htmlspecialchars(strip_tags($_SESSION['employee']['OnFile']));
    $_SESSION['employee']['WorkAuthExpireDate'] = htmlspecialchars(strip_tags($_SESSION['employee']['WorkAuthExpireDate']));
    $_SESSION['employee']['USVeteran'] = htmlspecialchars(strip_tags($_SESSION['employee']['USVeteran']));
    $_SESSION['employee']['MilitaryStatus'] = htmlspecialchars(strip_tags($_SESSION['employee']['MilitaryStatus']));
    $_SESSION['employee']['AccountNumber'] = htmlspecialchars(strip_tags($_SESSION['employee']['AccountNumber']));
    $_SESSION['employee']['Notes'] = htmlspecialchars(strip_tags($_SESSION['employee']['Notes']));
    $_SESSION['employee']['BillingRateRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['employee']['BillingRateRef_ListID']));
    $_SESSION['employee']['BillingRateRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['employee']['BillingRateRef_FullName']));
    $_SESSION['employee']['Status'] = htmlspecialchars(strip_tags($_SESSION['employee']['Status']));
}

function buscaIgual_employee($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM employee WHERE ListID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['employee']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ListID'] === $_SESSION['employee']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        echo $e->getMessage();
    }

    return $estado;
}

function update_employee($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE employee SET TimeCreated=:TimeCreated,TimeModified=:TimeModified,EditSequence=:EditSequence,Name=:Name,IsActive=:IsActive,Salutation=:Salutation,FirstName=:FirstName,MiddleName=:MiddleName,LastName=:LastName,Suffix=:Suffix,JobTitle=:JobTitle,SupervisorRef_ListID=:SupervisorRef_ListID,SupervisorRef_FullName=:SupervisorRef_FullName,Department=:Department,Description=:Description,TargetBonus=:TargetBonus,EmployeeAddress_Addr1=:EmployeeAddress_Addr1,EmployeeAddress_Addr2=:EmployeeAddress_Addr2,EmployeeAddress_Addr3=:EmployeeAddress_Addr3,EmployeeAddress_Addr4=:EmployeeAddress_Addr4,EmployeeAddress_City=:EmployeeAddress_City,EmployeeAddress_State=:EmployeeAddress_State,EmployeeAddress_PostalCode=:EmployeeAddress_PostalCode,EmployeeAddress_Country=:EmployeeAddress_Country,PrintAs=:PrintAs,Phone=:Phone,Mobile=:Mobile,Pager=:Pager,PagerPIN=:PagerPIN,AltPhone=:AltPhone,Fax=:Fax,SSN=:SSN,Email=:Email,EmergencyContactPrimaryName=:EmergencyContactPrimaryName,EmergencyContactPrimaryValue=:EmergencyContactPrimaryValue,EmergencyContactPrimaryRelation=:EmergencyContactPrimaryRelation,EmergencyContactSecondaryName=:EmergencyContactSecondaryName,EmergencyContactSecondaryValue=:EmergencyContactSecondaryValue,EmergencyContactSecondaryRelation=:EmergencyContactSecondaryRelation,EmployeeType=:EmployeeType,Gender=:Gender,PartOrFullTime=:PartOrFullTime,Exempt=:Exempt,KeyEmployee=:KeyEmployee,HiredDate=:HiredDate,OriginalHireDate=:OriginalHireDate,AdjustedServiceDate=:AdjustedServiceDate,ReleasedDate=:ReleasedDate,BirthDate=:BirthDate,USCitizen=:USCitizen,Ethnicity=:Ethnicity,Disabled=:Disabled,DisabilityDesc=:DisabilityDesc,OnFile=:OnFile,WorkAuthExpireDate=:WorkAuthExpireDate,USVeteran=:USVeteran,MilitaryStatus=:MilitaryStatus,AccountNumber=:AccountNumber,Notes=:Notes,BillingRateRef_ListID=:BillingRateRef_ListID,BillingRateRef_FullName=:BillingRateRef_FullName,Status=:Status WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['employee']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['employee']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['employee']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['employee']['Name']);
        $stmt->bindParam(':IsActive', $_SESSION['employee']['IsActive']);
        $stmt->bindParam(':Salutation', $_SESSION['employee']['Salutation']);
        $stmt->bindParam(':FirstName', $_SESSION['employee']['FirstName']);
        $stmt->bindParam(':MiddleName', $_SESSION['employee']['MiddleName']);
        $stmt->bindParam(':LastName', $_SESSION['employee']['LastName']);
        $stmt->bindParam(':Suffix', $_SESSION['employee']['Suffix']);
        $stmt->bindParam(':JobTitle', $_SESSION['employee']['JobTitle']);
        $stmt->bindParam(':SupervisorRef_ListID', $_SESSION['employee']['SupervisorRef_ListID']);
        $stmt->bindParam(':SupervisorRef_FullName', $_SESSION['employee']['SupervisorRef_FullName']);
        $stmt->bindParam(':Department', $_SESSION['employee']['Department']);
        $stmt->bindParam(':Description', $_SESSION['employee']['Description']);
        $stmt->bindParam(':TargetBonus', $_SESSION['employee']['TargetBonus']);
        $stmt->bindParam(':EmployeeAddress_Addr1', $_SESSION['employee']['EmployeeAddress_Addr1']);
        $stmt->bindParam(':EmployeeAddress_Addr2', $_SESSION['employee']['EmployeeAddress_Addr2']);
        $stmt->bindParam(':EmployeeAddress_Addr3', $_SESSION['employee']['EmployeeAddress_Addr3']);
        $stmt->bindParam(':EmployeeAddress_Addr4', $_SESSION['employee']['EmployeeAddress_Addr4']);
        $stmt->bindParam(':EmployeeAddress_City', $_SESSION['employee']['EmployeeAddress_City']);
        $stmt->bindParam(':EmployeeAddress_State', $_SESSION['employee']['EmployeeAddress_State']);
        $stmt->bindParam(':EmployeeAddress_PostalCode', $_SESSION['employee']['EmployeeAddress_PostalCode']);
        $stmt->bindParam(':EmployeeAddress_Country', $_SESSION['employee']['EmployeeAddress_Country']);
        $stmt->bindParam(':PrintAs', $_SESSION['employee']['PrintAs']);
        $stmt->bindParam(':Phone', $_SESSION['employee']['Phone']);
        $stmt->bindParam(':Mobile', $_SESSION['employee']['Mobile']);
        $stmt->bindParam(':Pager', $_SESSION['employee']['Pager']);
        $stmt->bindParam(':PagerPIN', $_SESSION['employee']['PagerPIN']);
        $stmt->bindParam(':AltPhone', $_SESSION['employee']['AltPhone']);
        $stmt->bindParam(':Fax', $_SESSION['employee']['Fax']);
        $stmt->bindParam(':SSN', $_SESSION['employee']['SSN']);
        $stmt->bindParam(':Email', $_SESSION['employee']['Email']);
        $stmt->bindParam(':EmergencyContactPrimaryName', $_SESSION['employee']['EmergencyContactPrimaryName']);
        $stmt->bindParam(':EmergencyContactPrimaryValue', $_SESSION['employee']['EmergencyContactPrimaryValue']);
        $stmt->bindParam(':EmergencyContactPrimaryRelation', $_SESSION['employee']['EmergencyContactPrimaryRelation']);
        $stmt->bindParam(':EmergencyContactSecondaryName', $_SESSION['employee']['EmergencyContactSecondaryName']);
        $stmt->bindParam(':EmergencyContactSecondaryValue', $_SESSION['employee']['EmergencyContactSecondaryValue']);
        $stmt->bindParam(':EmergencyContactSecondaryRelation', $_SESSION['employee']['EmergencyContactSecondaryRelation']);
        $stmt->bindParam(':EmployeeType', $_SESSION['employee']['EmployeeType']);
        $stmt->bindParam(':Gender', $_SESSION['employee']['Gender']);
        $stmt->bindParam(':PartOrFullTime', $_SESSION['employee']['PartOrFullTime']);
        $stmt->bindParam(':Exempt', $_SESSION['employee']['Exempt']);
        $stmt->bindParam(':KeyEmployee', $_SESSION['employee']['KeyEmployee']);
        $stmt->bindParam(':HiredDate', $_SESSION['employee']['HiredDate']);
        $stmt->bindParam(':OriginalHireDate', $_SESSION['employee']['OriginalHireDate']);
        $stmt->bindParam(':AdjustedServiceDate', $_SESSION['employee']['AdjustedServiceDate']);
        $stmt->bindParam(':ReleasedDate', $_SESSION['employee']['ReleasedDate']);
        $stmt->bindParam(':BirthDate', $_SESSION['employee']['BirthDate']);
        $stmt->bindParam(':USCitizen', $_SESSION['employee']['USCitizen']);
        $stmt->bindParam(':Ethnicity', $_SESSION['employee']['Ethnicity']);
        $stmt->bindParam(':Disabled', $_SESSION['employee']['Disabled']);
        $stmt->bindParam(':DisabilityDesc', $_SESSION['employee']['DisabilityDesc']);
        $stmt->bindParam(':OnFile', $_SESSION['employee']['OnFile']);
        $stmt->bindParam(':WorkAuthExpireDate', $_SESSION['employee']['WorkAuthExpireDate']);
        $stmt->bindParam(':USVeteran', $_SESSION['employee']['USVeteran']);
        $stmt->bindParam(':MilitaryStatus', $_SESSION['employee']['MilitaryStatus']);
        $stmt->bindParam(':AccountNumber', $_SESSION['employee']['AccountNumber']);
        $stmt->bindParam(':Notes', $_SESSION['employee']['Notes']);
        $stmt->bindParam(':BillingRateRef_ListID', $_SESSION['employee']['BillingRateRef_ListID']);
        $stmt->bindParam(':BillingRateRef_FullName', $_SESSION['employee']['BillingRateRef_FullName']);
        $stmt->bindParam(':Status', $_SESSION['employee']['Status']);
        $stmt->bindParam(':clave', $_SESSION['employee']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}
