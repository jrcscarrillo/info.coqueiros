<?php

function _quickbooks_invoice_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale) {
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $myfile = fopen("ordenes.txt", "a+") or die("Unable to open file!");
    $st_ped = "INIT";
    $st_det = "INIT";
    $st_cus = "INIT";
    fwrite($myfile, "requestID " . $requestID . " user " . $user . " action " . $action . " id " . $ID . " action time " . $last_action_time . " locale " . $locale . "\r\n");
    try {
        $sql = "SELECT * FROM pedidos WHERE RefNumber = :refnumber ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':refnumber', $ID);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($registro) {
            $st_ped = 'OK';
            fwrite($myfile, 'Pedido encontrado ' . $registro['TxnID']);
            if ($registro['Status'] === 'FACTURADO') {
                $st_ped = 'FACTURADO';
                fwrite($myfile, 'Pedido ya facturado ' . $registro['TxnID']);
            }
        } else {
            $st_ped = 'NO HAY';
            fwrite($myfile, 'Pedido no encontrado ' . $ID);
        }
    } catch (PDOException $e) {
        fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage());
    }

    if ($st_ped === 'OK') {
        try {
            $sql = "SELECT * FROM pedidosdetalle WHERE IDKEY = :RefNumber AND Rate > 0";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':RefNumber', $registro['TxnID']);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($items) {
                $st_det = 'OK';
                fwrite($myfile, 'Helados encontrados ' . $items);
            } else {
                $st_det = 'NO HAY';
                fwrite($myfile, 'Helados no encontrados ' . $ID);
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
                fwrite($myfile, 'Cliente encontrado ' . $cliente['Name']);
            } else {
                $st_cus = 'NO HAY';
                fwrite($myfile, 'Cliene no encontrado ' . $registro['CustomerRef_FullName']);
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
                </SalesOrderLineAdd>';
        }
        $txnID = $registro['RefNumber'];
        $cortesias = cortesias($db, $myfile, $txnID);
        if ($cortesias) {
            $item_xml .= '<SalesOrderLineAdd>
                    <Desc >CORTESIAS</Desc>
                </SalesOrderLineAdd>';
            foreach ($cortesias as $producto) {
                $item_xml .= '<SalesOrderLineAdd>
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
                </SalesOrderLineAdd>';
            }
        }

        $bonos = bonificaciones($db, $myfile, $txnID);
        if ($bonos) {
            $item_xml .= '<SalesOrderLineAdd>
                    <Desc >BONIFICACIONES</Desc>
                </SalesOrderLineAdd>';
            foreach ($bonos as $producto) {
                $item_xml .= '<SalesOrderLineAdd>
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
                </SalesOrderLineAdd>';
            }
        }

        $fecha = date('Y-m-d', strtotime($registro['TxnDate']));

        $xml = '<?xml version="1.0"?>
    <?qbxml version="' . $version . '"?>
        <QBXML>
           <QBXMLMsgsRq onError="stopOnError">
           <SalesOrderAddRq>
           <SalesOrderAdd>
           <CustomerRef>
                <ListID >' . $registro['CustomerRef_ListID'] . '</ListID>
                <FullName >';
        $varcha = convert_ascii($registro['CustomerRef_FullName']);
        $xml .= $varcha . '</FullName>
           </CustomerRef>
           <TxnDate >' . $fecha . '</TxnDate>
           <RefNumber >' . $registro['RefNumber'] . '</RefNumber>
           <BillAddress>';
        $varcha = convert_ascii($cliente['BillAddress_Addr1']);
        $xml .= '<Addr1 >' . $varcha . '</Addr1>';
        if ($cliente['BillAddress_City'] > " ") {
            $xml .= '<City >' . $cliente['BillAddress_City'] . '</City>';
        }
        if ($cliente['BillAddress_State'] > " ") {
            $xml .= '<State >' . $cliente['BillAddress_State'] . '</State>';
        }
        if ($cliente['BillAddress_PostalCode'] > " ") {
            $xml .= '<PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>';
        }
        if ($cliente['BillAddress_Country'] > " ") {
            $xml .= '<Country >' . $cliente['BillAddress_Country'] . '</Country>';
        }
        $xml .= '</BillAddress><ShipAddress>';
        $varcha = convert_ascii($cliente['BillAddress_Addr1']);
        $xml .= '<Addr1 >' . $varcha . '</Addr1>';
        if ($cliente['BillAddress_City'] > " ") {
            $xml .= '<City >' . $cliente['BillAddress_City'] . '</City>';
        }
        if ($cliente['BillAddress_State'] > " ") {
            $xml .= '<State >' . $cliente['BillAddress_State'] . '</State>';
        }
        if ($cliente['BillAddress_PostalCode'] > " ") {
            $xml .= '<PostalCode >' . $cliente['BillAddress_PostalCode'] . '</PostalCode>';
        }
        if ($cliente['BillAddress_Country'] > " ") {
            $xml .= '<Country >' . $cliente['BillAddress_Country'] . '</Country>';
        }
        $xml .= '</ShipAddress><PONumber >' . $registro['PONumber'] . '</PONumber>';
        if ($registro['TermsRef_ListID'] === ' ' or $registro['TermsRef_ListID'] == null) {
            
        } else {
            $xml .= '<TermsRef><ListID >' . $registro['TermsRef_ListID'] . '</ListID><FullName >';
            $varcha = convert_ascii($registro['TermsRef_FullName']);
            $xml .= $varcha . '</FullName></TermsRef>';
        }
        if ($registro['SalesRepRef_ListID'] === ' ' or $registro['SalesRepRef_ListID'] == null) {
            
        } else {
            $xml .= '<SalesRepRef><ListID >' . $registro['SalesRepRef_ListID'] . '</ListID><FullName >' . $registro['SalesRepRef_FullName'] . '</FullName> </SalesRepRef>';
        }
        $xml .= '<Memo>' . $registro['Memo'] . '</Memo>
                ' . $item_xml . '
           </SalesOrderAdd>
        </SalesOrderAddRq>
    </QBXMLMsgsRq>
</QBXML>';
        $_SESSION['refnumber'] = $registro['RefNumber'];
        fwrite($myfile, $xml);
        return $xml;
    }
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_invoice_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents) {
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $sql = "UPDATE pedidos SET TxnID = :listid, EditSequence = :sequence, Status = 'FACTURADO' WHERE RefNumber = :id";
        $stmt = $db->prepare($sql);

        $stmt->bindParam(':id', $ID);
        $stmt->bindParam(':listid', htmlspecialchars($idents['TxnID']));
        $stmt->bindParam(':sequence', htmlspecialchars($idents['EditSequence']));
        $stmt->execute();
    } catch (PDOException $e) {
        echo('ERROR JC!!! invoice response' . $e->getTrace() . ' tipo error ' . $e->getMessage());
    }
}

/**
 * Catch and handle an error from QuickBooks
 */
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

function cortesias($db, $myfile, $txnID) {
    try {
        $sql = "SELECT * FROM pedidosdetalle WHERE IDKEY = :RefNumber AND Rate = 0";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':RefNumber', $txnID);
        $stmt->execute();
        $cortesias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($cortesias) {
            $st_cortesia = 'OK';
            fwrite($myfile, 'Helados con cortesia encontrados ' . $items);
            return $cortesias;
        } else {
            $st_cortesia = 'NO HAY';
            fwrite($myfile, 'Helados no cortesia encontrados ' . $ID);
            return false;
        }
    } catch (PDOException $e) {
        fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage() . ' este pedido ' . $txnID);
    }
}

function bonificaciones($db, $myfile, $txnID) {
    try {
        $sql = "SELECT * FROM bonificadetalle WHERE IDKEY = :RefNumber";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':RefNumber', $txnID);
        $stmt->execute();
        $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($bonos) {
            $st_bonos = 'OK';
            fwrite($myfile, 'Helados con bonificacion encontrados ' . $items);
            return $bonos;
        } else {
            $st_bonos = 'NO HAY';
            fwrite($myfile, 'Helados no bonificacion encontrados ' . $ID);
            return false;
        }
    } catch (PDOException $e) {
        fwrite($myfile, 'ERROR JC!!! ' . $e->getTrace() . ' tipo error ' . $e->getMessage() . ' este pedido ' . $txnID);
    }
}
