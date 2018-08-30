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
   QUICKBOOKS_IMPORT_INVOICE => array('_facturas_request', '_facturas_response'),
   QUICKBOOKS_ADD_INVOICE => array('_facturas_request', '_facturas_response'),
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
    
}

function _facturas_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {

    $myfile = fopen("facturas.txt", "w") or die("Unable to open file!");
    $db = conecta_SYNC();
    $st_ped = "INIT";
    $st_det = "INIT";
    $st_cus = "INIT";
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
            $_SESSION['RefNumber'] = $registro['TxnID'];
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
                        <FullName >';
            $varcha = convert_ascii($producto['ItemRef_FullName']);
            $item_xml .= $varcha . '</FullName>
                    </ItemRef>
                    <Desc >';
            $varcha = convert_ascii($producto['Description']);
            $item_xml .= $varcha . '</Desc>
                    <Quantity >' . number_format($producto['Quantity'], 2, '.', '') . '</Quantity>                    
                    <Rate >' . number_format($producto['Rate'], 2, '.', '') . '</Rate>
                    <Amount >' . number_format($producto['Amount'], 2, '.', '') . '</Amount>
                </InvoiceLineAdd>';
        }
        $fecha = date('Y-m-d', strtotime($registro['TxnDate']));

        $xml = '<?xml version="1.0"?>
    <?qbxml version="' . $version . '"?>
        <QBXML>
           <QBXMLMsgsRq onError="stopOnError">
           <InvoiceAddRq>
           <InvoiceAdd>
           <CustomerRef>
                <ListID >' . $registro['CustomerRef_ListID'] . '</ListID>
                <FullName >';
        $varcha = convert_ascii($cliente['CustomerRef_FullName']);
        $xml .= '</FullName>
           </CustomerRef>
           <TxnDate >' . $fecha . '</TxnDate>
           <RefNumber >' . $registro['RefNumber'] . '</RefNumber>
           <BillAddress>';
        $varcha = convert_ascii($cliente['BillAddress_Addr1']);
        $xml .= '<Addr1 >' . $varcha . '</Addr1><City >' . $cliente['BillAddress_City'] . '</City>
            <State >' . $cliente['BillAddress_State'] . '</State>
            <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>
            <Country >' . $cliente['BillAddress_Country'] . '</Country></BillAddress>
           <ShipAddress>';
        $varcha = convert_ascii($cliente['BillAddress_Addr1']);
        $xml .= '<Addr1 >' . $varcha . '</Addr1><City >' . $cliente['BillAddress_City'] . '</City>
            <State >' . $cliente['BillAddress_State'] . '</State>
            <PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>
            <Country >' . $cliente['BillAddress_Country'] . '</Country>
           </ShipAddress>
           <PONumber >' . $registro['PONumber'] . '</PONumber>
           <TermsRef>
                <ListID >' . $registro['TermsRef_ListID'] . '</ListID>
                <FullName >';
        $varcha = convert_ascii($registro['TermsRef_FullName']);
        $xml .= $varcha . '</FullName>
           </TermsRef>';
        if ($registro['SalesRepRef_ListID'] === ' ' or $registro['SalesRepRef_ListID'] == null) {
            
        } else {
           $xml .= '<SalesRepRef>
                <ListID >' . $registro['SalesRepRef_ListID'] . '</ListID>
                <FullName >' . $registro['SalesRepRef_FullName'] . '</FullName> 
           </SalesRepRef>';
        }
        $xml .= '<Memo>' . $registro['Memo'] . '</Memo>
                ' . $item_xml . '
           </InvoiceAdd>
        </InvoiceAddRq>
    </QBXMLMsgsRq>
</QBXML>';
        fwrite($myfile, $xml);
        return $xml;
    } else {
        fwrite($myfile, 'No hay nada mas que procesar');
        return null;
    }
}

function _facturas_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $myfile1 = fopen("pedidos.xml", "w") or die("Unable to open file!");
    fwrite($myfile1, $xml);
    $db = conecta_SYNC();
    $estado = "INIT";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $sql = "UPDATE pedidos SET Status = 'FACTURADO' WHERE RefNumber = :RefNumber";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':RefNumber', $ID);
        $stmt->execute();
    } catch (PDOException $e) {
        fwrite($myfile1, 'ERROR JC!!! ' . $e->getMessage() . ' ' . $e->getTrace());
    }
    fwrite($myfile1, $idents);
}

function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg) {
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    if ($action == QUICKBOOKS_ADD_INVOICE) {
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

function convert_ascii($string) {

    $string = str_replace(
       array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'), array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'), $string
    );

    $string = str_replace(
       array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'), array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'), $string
    );

    $string = str_replace(
       array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'), array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'), $string
    );

    $string = str_replace(
       array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'), array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'), $string
    );

    $string = str_replace(
       array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'), array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'), $string
    );

    $string = str_replace(
       array('ñ', 'Ñ', 'ç', 'Ç'), array('n', 'N', 'c', 'C',), $string
    );

    return preg_replace('/[^A-Za-z0-9 ,.\-]/', ' ', $string); // Removes special chars.
}


