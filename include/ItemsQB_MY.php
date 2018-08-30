<?php
session_start();
    $_SESSION['item'] = array();
    $doc = new DOMDocument();
    $xml = "../docs/web_connector/items.xml";
    $doc->load($xml);
    $param = "ItemServiceRet";
    $inventario = $doc->getElementsByTagName($param);
    foreach ($inventario as $uno) {
        gentraverse_items($uno);
    }

    exit();
    function gentraverse_items($node) {
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $nivel1) {
            switch ($nivel1->nodeName) {
                case 'ListID':
                    $_SESSION['item']['ListID'] = $nivel1->nodeValue;
                    break;
                case 'TimeCreated':
                    $_SESSION['item']['TimeCreated'] = $nivel1->nodeValue;
                    break;
                case 'TimeModified':
                    $_SESSION['item']['TimeModified'] = $nivel1->nodeValue;
                    break;
                case 'EditSequence':
                    $_SESSION['item']['EditSequence'] = $nivel1->nodeValue;
                    break;
                case 'Name':
                    $_SESSION['item']['Name'] = $nivel1->nodeValue;
                    break;
                case 'FullName':
                    $_SESSION['item']['FullName'] = $nivel1->nodeValue;
                    break;
                case 'BarCodeValue':
                    $_SESSION['item']['BarCodeValue'] = $nivel1->nodeValue;
                    break;
                case 'IsActive':
                    $_SESSION['item']['IsActive'] = $nivel1->nodeValue;
                    break;
                case 'ClassRef':
                case 'ParentRef':
                case 'UnitOfMeasureSetRef':
                case 'SalesTaxCodeRef':
                case 'IncomeAccountRef':
                case 'COGSAccountRef':
                case 'PrefVendorRef':
                case 'AssetAccountRef':
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel1->nodeName) {
                            case 'ClassRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['ClassRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['ClassRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'ParentRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['ParentRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['ParentRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'UnitOfMeasureSetRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['UnitOfMeasureSetRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['UnitOfMeasureSetRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'SalesTaxCodeRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['SalesTaxCodeRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['SalesTaxCodeRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'IncomeAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['IncomeAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['IncomeAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'COGSAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['COGSAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['COGSAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'PrefVendorRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['PrefVendorRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['PrefVendorRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                            case 'AssetAccountRef':
                                if ($nivel2->nodeName === 'ListID') {
                                    $_SESSION['item']['AssetAccountRef_ListID'] = $nivel2->nodeValue;
                                }
                                if ($nivel2->nodeName === 'FullName') {
                                    $_SESSION['item']['AssetAccountRef_FullName'] = $nivel2->nodeValue;
                                }
                                break;
                        }
                    }
                    break;
                case 'Sublevel':
                    $_SESSION['item']['Sublevel'] = $nivel1->nodeValue;
                    break;
                case 'ManufacturerPartNumber':
                    $_SESSION['item']['ManufacturerPartNumber'] = $nivel1->nodeValue;
                    break;
                case 'IsTaxIncluded':
                    $_SESSION['item']['IsTaxIncluded'] = $nivel1->nodeValue;
                    break;
                case 'SalesDesc':
                    $_SESSION['item']['SalesDesc'] = $nivel1->nodeValue;
                    break;
                case 'SalesOrPurchase';
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'Desc':
                                $_SESSION['item']['SalesDesc'] = $nivel2->nodeValue;
                                break;
                        }
                    }
                    break;
                case 'SalesPrice':
                    $_SESSION['item']['SalesPrice'] = $nivel1->nodeValue;
                    break;
                case 'PurchaseDesc':
                    $_SESSION['item']['PurchaseDesc'] = $nivel1->nodeValue;
                    break;
                case 'PurchaseCost':
                    $_SESSION['item']['PurchaseCost'] = $nivel1->nodeValue;
                    break;
                case 'ReorderPoint':
                    $_SESSION['item']['ReorderPoint'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnHand':
                    $_SESSION['item']['QuantityOnHand'] = $nivel1->nodeValue;
                    break;
                case 'AverageCost':
                    $_SESSION['item']['AverageCost'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnOrder':
                    $_SESSION['item']['QuantityOnOrder'] = $nivel1->nodeValue;
                    break;
                case 'QuantityOnSalesOrder':
                    $_SESSION['item']['QuantityOnSalesOrder'] = $nivel1->nodeValue;
                    break;
                case 'DataExtRet':
                    $DOpaso = 'Datos Adicionales => ';
                    foreach ($nivel1->childNodes as $nivel2) {
                        switch ($nivel2->nodeName) {
                            case 'DataExtName':
                                $_SESSION['item']['paso'] = $nivel2->nodeValue;
                                $DOpaso .= $_SESSION['item']['paso']; 
                                break;
                            case 'DataExtValue':
                                if ($_SESSION['item']['paso'] === "Placa") {
                                    $_SESSION['item']['placa'] = $nivel2->nodeValue;
                                    $DOpaso .= ' .- ' . $_SESSION['item']['placa'];
                                } elseif ($_SESSION['item']['paso'] === "Tipo ID") {
                                    $_SESSION['item']['tipoid'] = $nivel2->nodeValue;
                                    $DOpaso .= ' .- ' . $_SESSION['item']['tipoid'];
                                } elseif ($_SESSION['item']['paso'] === "Numero ID") {
                                    $_SESSION['item']['numeroid'] = $nivel2->nodeValue;
                                    $DOpaso .= ' .- ' . $_SESSION['item']['numeroid'];
                                } elseif ($_SESSION['item']['paso'] === "Email") {
                                    $_SESSION['item']['email'] = $nivel2->nodeValue;
                                    $DOpaso .= ' .- ' . $_SESSION['item']['email'];
                                }
                                break;
                        }
                    }
                    echo $DOpaso;
                    break;
            }
        }
    }
}    
