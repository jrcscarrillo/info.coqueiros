<?php

error_reporting(1);
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Guayaquil');
}

require_once 'QuickBooks.php';
$user = 'jrcscarrillo';
$pass = 'f9234568';

$dsn = 'mysqli://carrillo_db:AnyaCarrill0@localhost/carrillo_dbaurora';

$map = array(
   QUICKBOOKS_ADD_INVOICE => array('_add_request', '_add_response'),
);
$errmap = array(
   500 => '_quickbooks_error_e500_notfound',
   1 => '_quickbooks_error_e500_notfound',
   '*' => '_quickbooks_error_catchall'
);
$hooks = array(
   QuickBooks_WebConnector_Handlers::HOOK_LOGINSUCCESS => '_quickbooks_hook_loginsuccess'
);

$log_level = QUICKBOOKS_LOG_DEVELOP;

$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN;

$soap_options = array();

$handler_options = array(
   'deny_concurrent_logins' => false,
   'deny_reallyfast_logins' => false,
);

$driver_options = array();

$callback_options = array(
);
define('QB_QUICKBOOKS_DSN', $dsn);
define('QB_QUICKBOOKS_MAILTO', 'jrcscarrillo@gmail.com');
QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);
$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);
$response = $Server->handle(true, true);

function _add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    $myfile = fopen("ordenes.txt", "a+") or die("Unable to open file!");
    $db = conecta_SYNC();
    $st_ped = "INIT";
    $st_det = "INIT";
    $st_cus = "INIT";
    fwrite($myfile, 'requestID ' . $requestID . ' user ' . $user . ' action ' . $action . ' id ' . $ID . ' action time ' . $last_action_time . ' locale ' . $locale . '\r\n');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "SELECT * FROM pedidos WHERE RefNumber = :refnumber ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':refnumber', $ID);
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
            $item_xml .= '<InvoiceLineAdd>
                    <ItemRef>
                        <ListID >' . $producto['ItemRef_ListID'] . '</ListID>
                        <FullName >' . $producto['ItemRef_FullName'] . '</FullName>
                    </ItemRef>
                    <Desc >' . $producto['Description'] . '</Desc>
                    <Quantity >' . number_format($producto['Quantity'], 2, '.', '') . '</Quantity>                    
                    <Rate >' . number_format($producto['Rate'], 2, '.', '') . '</Rate>
                    <Amount >' . number_format($producto['Amount'], 2, '.', '') . '</Amount>
                </InvoiceLineAdd>';
        }
    }


    $xml = '<?xml version="1.0" ?>
        <?qbxml version="' . $version . '"?>
<QBXML>
    <QBXMLMsgsRq onError="stopOnError">
        <InvoiceAddRq requestID="' . $requestID . '">
            <InvoiceAdd>
                <CustomerRef>
                    <ListID >' . $registro['CustomerRef_ListID'] . '</ListID>
                    <FullName >' . $registro['CustomerRef_FullName'] . '</FullName> 
                </CustomerRef>  
                <RefNumber >' . $registro['RefNumber'] . '</RefNumber>
                <BillAddress>
                    <Addr1 >' . $cliente['BillAddress_Addr1'] . '</Addr1>                    
                    <City >' . $cliente['BillAddress_City'] . '</City>                     
                    <State >' . $cliente['BillAddress_State'] . '</State>                     
                    <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>                     
                    <Country >' . $cliente['BillAddress_Country'] . '</Country>                     
                </BillAddress>
                <ShipAddress>
                    <Addr1 >' . $cliente['BillAddress_Addr1'] . '</Addr1>                    
                    <City >' . $cliente['BillAddress_City'] . '</City>                     
                    <State >' . $cliente['BillAddress_State'] . '</State>                     
                    <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>                     
                    <Country >' . $cliente['BillAddress_Country'] . '</Country>                                                         
                </ShipAddress>
                <PONumber >' . $registro['PONumber'] . '</PONumber>
                <TermsRef>
                    <ListID >' . $registro['TermsRef_ListID'] . '</ListID>
                    <FullName >' . $registro['TermsRef_FullName'] . '</FullName> 
                </TermsRef>                      
                <SalesRepRef>
                    <ListID >' . $registro['SalesRepRef_ListID'] . '</ListID>
                    <FullName >' . $registro['SalesRepRef_FullName'] . '</FullName> 
                </SalesRepRef>
                <Memo>' . $registro['Memo'] . '</Memo>' .
       $item_xml .
       '</InvoiceAdd>
        </InvoiceAddRq>
    </QBXMLMsgsRq>
</QBXML>';
    $_SESSION['refnumber'] = $registro['RefNumber'];
    fwrite($myfile, $xml);
    return $xml;
}

function _add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("pedidos.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $estado = "INIT";
    $refnumber = $_SESSION['refnumber'];
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "UPDATE pedidos SET Status = 'ORDENADO' WHERE RefNumber = :RefNumber";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':RefNumber', $refnumber);
        $stmt->execute();
    } catch (PDOException $e) {
        echo 'ERROR JC!!! ' . $e->getMessage() . '<br>';
        echo 'ERROR JC!!! ' . $estado . '<br>';
    }
}

function conecta_SYNC() {
    $userName = "carrillo_db";
    $password = "AnyaCarrill0";
    $dbName = "carrillo_dbaurora";
    $server = "localhost";
    $charset = 'utf8';
    $dsn = "mysql:host=$server;dbname=$dbName;charset=$charset";
    try {
        $db = new PDO($dsn, $userName, $password);
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        print "Error!: " . $userName . "<br/>";
        print "Error!: " . $password . "<br/>";
        die();
    }
    return $db;
}

function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    $db = conecta_SYNC();
    $st_ped = "INIT";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "SELECT * FROM pedidos WHERE Status = 'PASADO' ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $registro = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($registro) {
            $st_ped = 'OK';
        }
    } catch (PDOException $e) {
        echo ('ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage());
    }
    if ($st_ped === "OK") {
        foreach ($registro as $factura) {
            $refnumber = $factura['RefNumber'];
            $Queue->enqueue(QUICKBOOKS_ADD_INVOICE, $refnumber, 0, NULL, $user);
        }
    }
}

function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $message = '';
    $message .= 'Request ID: ' . $requestID . "\r\n";
    $message .= 'User: ' . $user . "\r\n";
    $message .= 'Action: ' . $action . "\r\n";
    $message .= 'ID: ' . $ID . "\r\n";
    $message .= 'Extra: ' . print_r($extra, true) . "\r\n";
    $message .= 'Error number: ' . $errnum . "\r\n";
    $message .= 'Error message: ' . $errmsg . "\r\n";

    mail(QB_QUICKBOOKS_MAILTO, 'QuickBooks error occured!', $message);
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action === QUICKBOOKS_ADD_INVOICE) {
        return true;
    }
    return false;
}
