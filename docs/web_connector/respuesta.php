<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
session_start();
$_SESSION['customer'] = array();
$db = conecta();
$doc = new DOMDocument();
    $param = "./respuesta.xml";
    $doc->load($param);

    $sql = "INSERT INTO customers(ListID, TimeCreated, TimeModified, Name, FullName, IsActive, FirstName, MiddleName, LastName, ";
    $sql .= "Contact, Address, City, State, PostalCode, Balance, TermsRef, SalesRep, TaxRef, ItemTax) ";
    $sql .= "VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    foreach ($cliente as $uno) {
        limpiarCliente();
        traverseDocument($uno);
        $stmt = $db->prepare($sql) or die(mysqli_error($db));
        if (!$stmt->bind_param("sssssssssssssssisss", $_SESSION['customer']['ListID'], $_SESSION['customer']['TimeCreated'], $_SESSION['customer']['TimeModified'], $_SESSION['customer']['Name'], $_SESSION['customer']['FullName'], $_SESSION['customer']['IsActive'], $_SESSION['customer']['FirstName'], $_SESSION['customer']['MiddleName'], $_SESSION['customer']['LastName'], $_SESSION['customer']['Contact'], $_SESSION['customer']['Address'], $_SESSION['customer']['City'], $_SESSION['customer']['State'], $_SESSION['customer']['PostalCode'], $_SESSION['customer']['Balance'], $_SESSION['customer']['TermsRef'], $_SESSION['customer']['SalesRep'], $_SESSION['customer']['TaxRef'], $_SESSION['customer']['ItemTax'])) {
            echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }            
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
            $newId = $db->insert_id;
        $stmt->close();
}

$db->close();
function traverseDocument( $node )
{
  switch ( $node->nodeType )
  {
    case XML_ELEMENT_NODE:
        switch ($node->tagName) {
            case "ListID":
                $_SESSION['customer']['ListID'] = $node->nodeValue;
                break;
            case "TimeCreated":
                $_SESSION['customer']['TimeCreated'] = $node->nodeValue;
                break;
            case "TimeModified":
                $_SESSION['customer']['TimeModified'] = $node->nodeValue;
                break;
            case "Name":
                $_SESSION['customer']['Name'] = $node->nodeValue;
                break;
            case "FullName":
                $_SESSION['customer']['FullName'] = $node->nodeValue;
                break;
            case "Addr1":
                if ($node->parentNode->tagName == 'BillAddress') {
                $_SESSION['customer']['Address'] = $node->nodeValue;
                }
                break;
            case "City":
                if ($node->parentNode->tagName == 'BillAddress') {
                $_SESSION['customer']['City'] = $node->nodeValue;
                }
                break;
            case "State":
                if ($node->parentNode->tagName == 'BillAddress') {
                $_SESSION['customer']['State'] = $node->nodeValue;
                }
                break;
            case "PostalCode":
                if ($node->parentNode->tagName == 'BillAddress') {
                $_SESSION['customer']['PostalCode'] = $node->nodeValue;
                }
                break;
            case "Balance":
                $_SESSION['customer']['Balance'] = strval($node->nodeValue);
                break;
            case "JobStatus":
//                $wk_fecha = preg_replace('#(\d{2})/(\d{2})/(\d{4})#', '$3/$2/$1', $wk_fecha);
                break;
            case "FullName":
                if ($node->parentNode->tagName == 'ItemSalesTaxRef') {
                    $_SESSION['customer']['ItemTax'] = $node->nodeValue;
                } elseif ($node->parentNode->tagName == 'SalesTaxCodeRef'){
                    $_SESSION['customer']['TaxRef'] = $node->nodeValue;
                } elseif ($node->parentNode->tagName == 'SalesRepRef'){
                    $_SESSION['customer']['SalesRep'] = $node->nodeValue;
                } elseif ($node->parentNode->tagName == 'TermsRef'){
                    $_SESSION['customer']['TermsRef'] = $node->nodeValue;
                }
                break;
            default:
                break;
        }
      break;
}
if ( $node->hasChildNodes() ) {
  foreach ( $node->childNodes as $child ) {
    traverseDocument( $child );
  }
 }
}

function conecta() {
    $userName = "coqueiros_qb";
    $password = "freedom";
    $dbName = "coqueiro_qb";
    $server = "localhost";
    $db = new mysqli($server, $userName, $password, $dbName);
    if ($db->connect_errno) {
        die('Error de Conexion: ' . $db->connect_errno);
    }
    return $db;
}

function limpiarCliente() {
    $_SESSION['customer']['ListID'] = " ";
    $_SESSION['customer']['TimeCreated'] = " ";
    $_SESSION['customer']['TimeModified'] = " ";
    $_SESSION['customer']['Name'] = " ";
    $_SESSION['customer']['FullName'] = " ";
    $_SESSION['customer']['IsActive'] = TRUE;
    $_SESSION['customer']['FirstName'] = " ";
    $_SESSION['customer']['MiddleName'] = " ";
    $_SESSION['customer']['LastName'] = " ";
    $_SESSION['customer']['Address'] = " ";
    $_SESSION['customer']['City'] = " ";
    $_SESSION['customer']['State'] = " ";
    $_SESSION['customer']['PostalCode'] = " ";
    $_SESSION['customer']['Balance'] = " ";
    $_SESSION['customer']['TermsRef'] = " ";
    $_SESSION['customer']['SalesRep'] = " ";
    $_SESSION['customer']['TaxRef'] = " ";
    $_SESSION['customer']['ItemTax'] = " ";
}