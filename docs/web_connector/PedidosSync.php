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
define('QB_PRIORITY_INVOICE', 1);

$map = array(
   QUICKBOOKS_IMPORT_INVOICE => array('_jrcs_pedidos_export_request', '_jrcs_pedidos_export_response'),
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
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_INVOICE)) {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_INVOICE, $date);
    }
    $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, 1, QB_PRIORITY_INVOICE, NULL, $user);
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

function _jrcs_pedidos_export_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
   
    $myfile = fopen("ordenes.txt", "a+") or die("Unable to open file!");
    $db = conecta_SYNC();
    $st_ped = "INIT";
    $st_det = "INIT";
    $st_cus = "INIT";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "SELECT * FROM pedidos WHERE Status = 'PASADO' ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($registro) {
            $st_ped = 'OK';
        } else {
            $st_ped = 'NO HAY';
        }
    } catch (PDOException $e) {
        fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage());
    }

    if ($st_ped === 'OK') {
        try {
            $sql = "SELECT * FROM pedidosdetalle WHERE IDKEY = :RefNumber ";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':RefNumber', $registro['TxnID']);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($items) {
                $st_det = 'OK';
            } else {
                $st_det = 'NO HAY';
            }
        } catch (PDOException $e) {
            fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage() . ' este pedido ' . $registro['RefNumber']);
        }
    }
    if ($st_det === 'OK') {
        try {
            $sql = "SELECT * FROM customer WHERE ListID = :RefCustomer ";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':RefCustomer', $registro['CustomerRef_ListID']);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cliente) {
                $st_cus = 'OK';
            } else {
                $st_cus = 'NO HAY';
            }
        } catch (PDOException $e) {
            fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage() . ' este cliente ' . $registro['CustomerRef_FullName']);
        }
    }

    if ($st_cus === 'OK') {
        $item_xml = "";
        foreach ($items as $producto) {
            $item_xml .= '<SalesOrderLineAdd>
                    <ItemRef>
                        <ListID >' . $producto['ItemRef_ListID'] . '</ListID>
                        <FullName >' . $producto['ItemRef_FullName'] . '</FullName>
                    </ItemRef>
                    <Desc >' . $producto['Description'] . '</Desc>
                    <Quantity >' . number_format($producto['Quantity'], 2 , '.', '') . '</Quantity>                    
                    <Rate >' . number_format($producto['Rate'], 2 , '.', '') . '</Rate>
                    <Amount >' . number_format($producto['Amount'], 2, '.', '') . '</Amount>
                </SalesOrderLineAdd>';
        }

$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
        <SalesOrderAddRq>
            <SalesOrderAdd>
                <CustomerRef>
                    <ListID >' . $registro['CustomerRef_ListID'] . '</ListID>
                    <FullName >' . $registro['CustomerRef_FullName'] . '</FullName> 
                </CustomerRef>  
                <RefNumber >' . $registro['RefNumber'] . '</RefNumber>
                <BillAddress>
                    <Addr1 >' . $cliente['BillAddress_Addr1'] . '</Addr1><City >' . $cliente['BillAddress_City'] . '</City>                     
                    <State >' . $cliente['BillAddress_State'] . '</State>                     
                    <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>                     
                    <Country >' . $cliente['BillAddress_Country'] . '</Country></BillAddress>
                <ShipAddress>
                    <Addr1 >' . $cliente['BillAddress_Addr1'] . '</Addr1><City >' . $cliente['BillAddress_City'] . '</City>                     
                    <State >' . $cliente['BillAddress_State'] . '</State>                     
                    <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>                     
                    <Country >' . $cliente['BillAddress_Country'] . '</Country></ShipAddress>
                <PONumber >' . $registro['PONumber'] . '</PONumber>
                <TermsRef>
                    <ListID >' . $registro['TermsRef_ListID'] . '</ListID>
                    <FullName >' . $registro['TermsRef_FullName'] . '</FullName> 
                </TermsRef>                      
                <SalesRepRef>
                    <ListID >' . $registro['SalesRepRef_ListID'] . '</ListID>
                    <FullName >' . $registro['SalesRepRef_FullName'] . '</FullName> 
                </SalesRepRef>
                <Memo>' . $registro['Memo'] . '</Memo>
                ' . $item_xml . '
            </SalesOrderAdd>
        </SalesOrderAddRq>
    </QBXMLMsgsRq>
</QBXML>';
        fwrite($myfile, $xml);
        return $xml;
    } else {
        fwrite($myfile, 'No hay nada mas que procesar');
        return null;
    }
}

function _jrcs_pedidos_export_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("pedidos.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $estado = "INIT";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "UPDATE pedidos SET Status = 'ORDENADO' WHERE RefNumber = :RefNumber";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo 'ERROR JC!!! ' . $e->getMessage() . '<br>';
        echo 'ERROR JC!!! ' . $estado . '<br>';
    }
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
