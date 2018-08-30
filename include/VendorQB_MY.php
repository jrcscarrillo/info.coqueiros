<?php
session_start();
// SAMPLE FOR METHOD INSERT()

include_once("../docs/class/class.vendor.php");

$vendor = new vendor();
    $_SESSION['vendor'] = array();
    $doc = new DOMDocument();
    $xml = "../docs/web_connector/vendors.xml";
    $doc->load($xml);
    $param = "VendorRet";
    $factura = $doc->getElementsByTagName($param);
    foreach ($factura as $uno) {
                $vendor->genLimpia_vendor();
                $vendor->gentraverse_vendor($uno);
                $existe = $vendor->buscaIgual_vendor();
                if ($existe == "OK") {
                    $vendor->quitaslashes_vendor();
                    $vendor->genInsert_vendor();
                    $vendor->adiciona_vendor();
                } elseif ($existe == "ACTUALIZA")  {
                    $vendor->quitaslashes_vendor();
                    $vendor->genInsert_vendor();
                    $vendor->update_vendor();
                }
    }
    return true;
