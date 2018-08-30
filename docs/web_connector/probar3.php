<?php
session_start();
require_once 'conectaDB.php';
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
    $doc = new DOMDocument();
    $doc->load("respuesta.xml");
    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    foreach ($cliente as $uno) {
                limpiarCliente();
                traverseDocument($uno);
                grabacliente();
    }    
    exit();

function grabacliente() {
    $db = conecta_godaddy();
    $sql = "INSERT INTO my_customer_table(timecreated, timemodified, name, fullname, fname, lname, salutation, address, city, ";
    $sql .= "state, zipcode, country, email, quickbooks_listid, quickbooks_editsequence, quickbooks_errnum, ";
    $sql .= "quickbooks_errmsg, sales_rep_ref_listid, sales_rep_ref_fullname, sales_tax_code_ref_listid, ";
    $sql .= "sales_tax_code_ref_fullname, tax_code_ref_listid, tax_code_ref_fullname, ";
    $sql .= "item_sales_tax_ref_listid, item_sales_tax_ref_fullname) ";
    $sql .= "VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    $myfile = fopen("newfile.txt", "a+") or die("Unable to open file!");
    var_dump($_SESSION);
    if (!($stmt = $db->prepare($sql))) {
            fwrite($myfile, $db->error);
        } else {
            if (!$stmt->bind_param("sssssssssssssssssssssssss", $_SESSION['customer']['TimeCreated'], $_SESSION['customer']['TimeModified'], $_SESSION['customer']['Name'], $_SESSION['customer']['FullName'], $_SESSION['customer']['fname'], $_SESSION['customer']['lname'], $_SESSION['customer']['saludo'], $_SESSION['customer']['Address'], $_SESSION['customer']['City'], $_SESSION['customer']['State'], $_SESSION['customer']['PostalCode'], $_SESSION['customer']['Country'], $_SESSION['customer']['Email'], $_SESSION['customer']['ListID'], $_SESSION['customer']['sequence'], $_SESSION['customer']['errnum'], $_SESSION['customer']['errmsg'], $_SESSION['customer']['SalesRepID'], $_SESSION['customer']['SalesRep'], $_SESSION['customer']['TaxRefID'], $_SESSION['customer']['TaxRef'], $_SESSION['customer']['ItemTaxID'], $_SESSION['customer']['ItemTax'], $_SESSION['customer']['ItemTaxID'], $_SESSION['customer']['ItemTax'])) {
                fwrite($myfile, "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
            } else {
                if (!$stmt->execute()) {
                    fwrite($myfile, "Execute failed: (" . $stmt->errno . ") " . $stmt->error);
                } else {
                    fwrite($myfile, "Cliente ==>" . $_SESSION['customer']['ListID']);
                }
            }
        }
    $stmt->close();
    $db->close();
    fclose($myfile);
}

function traverseDocument($node) {
    $node->getElementsByTagName('Name')->item(0) === NULL ? $_SESSION['item']['name'] = " " : $_SESSION['item']['name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(0) === NULL ? $_SESSION['customer']['ListID'] = " " : $_SESSION['customer']['ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeCreated')->item(0) === NULL ? $_SESSION['customer']['TimeCreated'] = " " : $_SESSION['customer']['TimeCreated'] = $node->getElementsByTagName('TimeCreated')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeCreated')->item(0) === NULL ? $_SESSION['customer']['TimeModified'] = " " : $_SESSION['customer']['TimeModified'] = $node->getElementsByTagName('TimeModified')->item(0)->nodeValue;
    $node->getElementsByTagName('Name')->item(0) === NULL ? $_SESSION['customer']['Name'] = " " : $_SESSION['customer']['Name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) === NULL ? $_SESSION['customer']['FullName'] = " " : $_SESSION['customer']['FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('FirstName')->item(0) === NULL ? $_SESSION['customer']['fname'] = " " : $_SESSION['customer']['fname'] = $node->getElementsByTagName('FirstName')->item(0)->nodeValue;
    $node->getElementsByTagName('LastName')->item(0) === NULL ? $_SESSION['customer']['lname'] = " " : $_SESSION['customer']['lname'] = $node->getElementsByTagName('LastName')->item(0)->nodeValue;
    $node->getElementsByTagName('Salutation')->item(0) === NULL ? $_SESSION['customer']['saludo'] = " " : $_SESSION['customer']['saludo'] = $node->getElementsByTagName('Salutation')->item(0)->nodeValue;
    $node->getElementsByTagName('Email')->item(0) === NULL ? $_SESSION['customer']['email'] = " " : $_SESSION['customer']['email'] = $node->getElementsByTagName('Email')->item(0)->nodeValue;
    $node->getElementsByTagName('Country')->item(0) === NULL ? $_SESSION['customer']['Country'] = " " : $_SESSION['customer']['Country'] = $node->getElementsByTagName('Country')->item(0)->nodeValue;
    $node->getElementsByTagName('EditSequence')->item(0) === NULL ? $_SESSION['customer']['sequence'] = " " : $_SESSION['customer']['sequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
    $node->getElementsByTagName('IsActive')->item(0) === NULL ? $_SESSION['customer']['IsActive'] = TRUE : $_SESSION['customer']['IsActive'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
    $node->getElementsByTagName('FirstName')->item(0) === NULL ? $_SESSION['customer']['FirstName'] = " " : $_SESSION['customer']['FirstName'] = $node->getElementsByTagName('FirstName')->item(0)->nodeValue;
    $node->getElementsByTagName('MiddleName')->item(0) === NULL ? $_SESSION['customer']['MiddleName'] = " " : $_SESSION['customer']['MiddleName'] = $node->getElementsByTagName('MiddleName')->item(0)->nodeValue;
    $node->getElementsByTagName('LastName')->item(0) === NULL ? $_SESSION['customer']['LastName'] = " " : $_SESSION['customer']['LastName'] = $node->getElementsByTagName('LastName')->item(0)->nodeValue;
    $node->getElementsByTagName('Addr1')->item(0) === NULL ? $_SESSION['customer']['Address'] = " " : $_SESSION['customer']['Address'] = $node->getElementsByTagName('Addr1')->item(0)->nodeValue;
    $node->getElementsByTagName('City')->item(0) === NULL ? $_SESSION['customer']['City'] = " " : $_SESSION['customer']['City'] = $node->getElementsByTagName('City')->item(0)->nodeValue;
    $node->getElementsByTagName('State')->item(0) === NULL ? $_SESSION['customer']['State'] = " " : $_SESSION['customer']['State'] = $node->getElementsByTagName('State')->item(0)->nodeValue;
    $node->getElementsByTagName('ZipCode')->item(0) === NULL ? $_SESSION['customer']['PostalCode'] = " " : $_SESSION['customer']['PostalCode'] = $node->getElementsByTagName('ZipCode')->item(0)->nodeValue;
    $node->getElementsByTagName('Balance')->item(0) === NULL ? $_SESSION['customer']['Balance'] = " " : $_SESSION['customer']['Balance'] = $node->getElementsByTagName('Balance')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(1) === NULL ? $_SESSION['customer']['TermsRefID'] = " " : $_SESSION['customer']['TermsRefID'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
    $node->getElementsByTagName('ListID')->item(2) === NULL ? $_SESSION['customer']['SalesRepID'] = " " : $_SESSION['customer']['SalesRepID'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
    $node->getElementsByTagName('ListID')->item(3) === NULL ? $_SESSION['customer']['TaxRefID'] = " " : $_SESSION['customer']['TaxRefID'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
    $node->getElementsByTagName('ListID')->item(4) === NULL ? $_SESSION['customer']['ItemTaxID'] = " " : $_SESSION['customer']['ItemTaxID'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
    $node->getElementsByTagName('FullName')->item(1) === NULL ? $_SESSION['customer']['TermsRef'] = " " : $_SESSION['customer']['TermsRef'] = $node->getElementsByTagName('FullName')->item(1)->nodeValue;
    $node->getElementsByTagName('FullName')->item(2) === NULL ? $_SESSION['customer']['SalesRep'] = " " : $_SESSION['customer']['SalesRep'] = $node->getElementsByTagName('FullName')->item(2)->nodeValue;
    $node->getElementsByTagName('FullName')->item(3) === NULL ? $_SESSION['customer']['TaxRef'] = " " : $_SESSION['customer']['TaxRef'] = $node->getElementsByTagName('FullName')->item(3)->nodeValue;
    $node->getElementsByTagName('FullName')->item(4) === NULL ? $_SESSION['customer']['ItemTax'] = " " : $_SESSION['customer']['ItemTax'] = $node->getElementsByTagName('FullName')->item(4)->nodeValue;
}

function limpiarCliente() {
    $_SESSION['customer']['ListID'] = " ";
    $_SESSION['customer']['errnum'] = " ";
    $_SESSION['customer']['errmsg'] = " ";
    $_SESSION['customer']['TimeCreated'] = " ";
    $_SESSION['customer']['TimeModified'] = " ";
    $_SESSION['customer']['Name'] = " ";
    $_SESSION['customer']['FullName'] = " ";
    $_SESSION['customer']['fname'] = " ";
    $_SESSION['customer']['lname'] = " ";
    $_SESSION['customer']['saludo'] = " ";
    $_SESSION['customer']['email'] = " ";
    $_SESSION['customer']['Country'] = " ";
    $_SESSION['customer']['sequence'] = " ";
    $_SESSION['customer']['IsActive'] = TRUE;
    $_SESSION['customer']['FirstName'] = " ";
    $_SESSION['customer']['MiddleName'] = " ";
    $_SESSION['customer']['LastName'] = " ";
    $_SESSION['customer']['Address'] = " ";
    $_SESSION['customer']['City'] = " ";
    $_SESSION['customer']['State'] = " ";
    $_SESSION['customer']['PostalCode'] = " ";
    $_SESSION['customer']['Balance'] = " ";
    $_SESSION['customer']['TermsRefID'] = " ";
    $_SESSION['customer']['SalesRepID'] = " ";
    $_SESSION['customer']['TaxRefID'] = " ";
    $_SESSION['customer']['ItemTaxID'] = " ";
    $_SESSION['customer']['TermsRef'] = " ";
    $_SESSION['customer']['SalesRep'] = " ";
    $_SESSION['customer']['TaxRef'] = " ";
    $_SESSION['customer']['ItemTax'] = " ";
}
    