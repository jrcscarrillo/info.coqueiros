<?php
session_start();
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
    $doc = new DOMDocument();
    $doc->load("revisar.xml");
    $Tag_adi = $doc->getElementsByTagName('ItemQueryRs')->item(0);
    if ($Tag_adi->hasChildNodes()) {
        foreach ($Tag_adi->childNodes as $tipo) {
                    if ($tipo->nodeName == "ItemInventoryAssemblyRet"){
                        echo "Found element: \"$tipo->nodeName\"" . " ";
                        echo "\n"; 
                        traverseDocument($tipo);
                        var_dump($_SESSION);
                }
            }
        }
    exit();

function traverseDocument($node) {
            $node->getElementsByTagName('Name')->item(0) === NULL ? $_SESSION['item']['name'] = " " : $_SESSION['item']['name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
            $node->getElementsByTagName('FullName')->item(0) === NULL ? $_SESSION['item']['fullname'] = " " : $_SESSION['item']['fullname'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
            $node->getElementsByTagName('SalesDesc')->item(0) === NULL ? $_SESSION['item']['description'] = " " : $_SESSION['item']['description'] = $node->getElementsByTagName('SalesDesc')->item(0)->nodeValue; 
            $node->getElementsByTagName('ListID')->item(0) === NULL ? : $_SESSION['item']['quickbooks_listid'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
            $node->getElementsByTagName('EditSequence')->item(0) === NULL ? $_SESSION['item']['quickbooks_editsequence'] = " " : $_SESSION['item']['quickbooks_editsequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
            $_SESSION['item']['quickbooks_errnum'] = " ";
            $_SESSION['item']['quickbooks_errmsg'] = " ";
            $node->getElementsByTagName('IsActive')->item(0) === NULL ? : $_SESSION['item']['is_active'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(1) === NULL ? : $_SESSION['item']['parent_reference_listid'] = $node->getElementsByTagName('ListID')->item(1)->nodeValue;
            $node->getElementsByTagName('FullName')->item(1) === NULL ? : $_SESSION['item']['parent_reference_full_name'] = $node->getElementsByTagName('FullName')->item(1)->nodeValue;
            $node->getElementsByTagName('Sublevel')->item(0) === NULL ? : $_SESSION['item']['sublevel'] = $node->getElementsByTagName('Sublevel')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(2) === NULL ? : $_SESSION['item']['unit_of_measure_set_ref_listid'] = $node->getElementsByTagName('ListID')->item(2)->nodeValue;
            $node->getElementsByTagName('FullName')->item(2) === NULL ? : $_SESSION['item']['unit_of_measure_set_ref_fullname'] = $node->getElementsByTagName('FullName')->item(2)->nodeValue;
            $_SESSION['item']['type'] = "Assembly";
            $node->getElementsByTagName('ListID')->item(3) === NULL ? : $_SESSION['item']['sales_tax_code_ref_listid'] = $node->getElementsByTagName('ListID')->item(3)->nodeValue;
            $node->getElementsByTagName('FullName')->item(3) === NULL ? : $_SESSION['item']['sales_tax_code_ref_fullname'] = $node->getElementsByTagName('FullName')->item(3)->nodeValue;
            $node->getElementsByTagName('SalesDesc')->item(0) === NULL ? : $_SESSION['item']['sales_desc'] = $node->getElementsByTagName('SalesDesc')->item(0)->nodeValue;
            $node->getElementsByTagName('SalesPrice')->item(0) === NULL ? : $_SESSION['item']['sales_price'] = $node->getElementsByTagName('SalesPrice')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(4) === NULL ? : $_SESSION['item']['income_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(4)->nodeValue;
            $node->getElementsByTagName('FullName')->item(4) === NULL ? : $_SESSION['item']['income_account_ref_fullname'] = $node->getElementsByTagName('FullName')->item(4)->nodeValue;
            $node->getElementsByTagName('PurchaseCost')->item(0) === NULL ? : $_SESSION['item']['purchase_cost'] = $node->getElementsByTagName('PurchaseCost')->item(0)->nodeValue;
            $node->getElementsByTagName('ListID')->item(5) === NULL ? : $_SESSION['item']['COGS_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(5)->nodeValue;
            $node->getElementsByTagName('FullName')->item(5) === NULL ? : $_SESSION['item']['COGS_account_ref_fullname'] = $node->getElementsByTagName('FullName')->item(5)->nodeValue;
            $node->getElementsByTagName('ListID')->item(6) === NULL ? : $_SESSION['item']['assests_account_ref_listid'] = $node->getElementsByTagName('ListID')->item(6)->nodeValue;
            $node->getElementsByTagName('FullName')->item(6) === NULL ? : $_SESSION['item']['assests_acc'] = $node->getElementsByTagName('FullName')->item(6)->nodeValue;
            $_SESSION['item']['purchase_desc'] = " ";
            $node->getElementsByTagName('QuantityOnHand')->item(0) === NULL ? : $_SESSION['item']['QuantityOnHand'] = $node->getElementsByTagName('QuantityOnHand')->item(0)->nodeValue;
            $node->getElementsByTagName('QuantityOnOrder')->item(0) === NULL ? : $_SESSION['item']['QuantityOnOrder'] = $node->getElementsByTagName('QuantityOnOrder')->item(0)->nodeValue;
            $node->getElementsByTagName('QuantityOnSalesOrder')->item(0) === NULL ? : $_SESSION['item']['QuantityOnSalesOrder'] = $node->getElementsByTagName('QuantityOnSalesOrder')->item(0)->nodeValue;
            $node->getElementsByTagName('AverageCost')->item(0) === NULL ? : $_SESSION['item']['AverageCost'] = $node->getElementsByTagName('AverageCost')->item(0)->nodeValue;
    }
