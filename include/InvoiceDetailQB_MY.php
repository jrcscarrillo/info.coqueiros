<?php
session_start();
// SAMPLE FOR METHOD INSERT()

include_once("../docs/class/class.invoicedetail.php");

$invoice = new invoicedetail();
    $_SESSION['invoice'] = array();
    $myfile = fopen("detalleInvoice.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de sincronizacion \r\n");
    $doc = new DOMDocument();
    $xml = "../docs/web_connector/invoice.xml";
    $doc->load($xml);
    $param = "InvoiceLineRet";
    $factura = $doc->getElementsByTagName($param);
    foreach ($factura as $uno) {
                $invoice->genLimpia_invoicedetail();
                $invoice->gentraverse_invoicedetail($uno);
                $existe = $invoice->buscaIgual_invoicedetail();
                if ($existe == "OK") {
                    $invoice->quitaslashes_invoicedetail();
                    $invoice->genInsert_invoicedetail();
                    fwrite($myfile, "NO!!! Existe factura " . $_SESSION['invoicedetail']['TxnLineID'] . " \r\n");
                    $invoice->adiciona_invoicedetail();
                } elseif ($existe == "ACTUALIZA")  {
                    $invoice->quitaslashes_invoicedetail();
                    $invoice->genInsert_invoicedetail();
                    fwrite($myfile, "Existe factura " . $_SESSION['invoicedetail']['TxnLineID'] . " \r\n");
                    $invoice->update_invoicedetail();
                }
    }
    fclose($myfile);
    echo("Se ha ejecutado con exito la operacion con las facturas del QB");
