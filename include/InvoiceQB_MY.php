<?php
session_start();
// SAMPLE FOR METHOD INSERT()

include_once("../docs/class/class.invoice.php");

$invoice = new invoice();
    $_SESSION['invoice'] = array();
    $doc = new DOMDocument();
    $xml = "../resources/invoice.xml";
    $doc->load($xml);
    $param = "InvoiceRet";
    $factura = $doc->getElementsByTagName($param);
    foreach ($factura as $uno) {
                $invoice->genLimpia_invoice();
                $invoice->gentraverse_invoice($uno);
                $existe = $invoice->buscaIgual_invoice();
                if ($existe == "OK") {
                    $invoice->quitaslashes_invoice();
                    $invoice->genInsert_invoice();
                    $invoice->adiciona_invoice();
                } elseif ($existe == "ACTUALIZA")  {
                    $invoice->quitaslashes_invoice();
                    $invoice->genInsert_invoice();
                    $invoice->update_invoice();
                }
    }
    return true;
