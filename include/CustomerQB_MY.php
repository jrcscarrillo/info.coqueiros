<?php
session_start();
// SAMPLE FOR METHOD INSERT()

include_once("../docs/class/class.customer.php");

$customer = new customer();
    $_SESSION['customer'] = array();
    $doc = new DOMDocument();
    $xml = "../docs/web_connector/customers.xml";
    $doc->load($xml);
    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    $myfile = fopen("batchcustomer.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "Iniciando el proceso de respuesta del SOAP ");
    foreach ($cliente as $uno) {
                $customer->genLimpia_customer();
                $customer->gentraverse_customer($uno);
                $existe = $customer->buscaIgual_customer();
                if ($existe == "OK") {
                    $customer->quitaslashes_customer();
                    $customer->genInsert_customer();
                    $customer->adiciona_customer();
                } elseif ($existe == "ACTUALIZA")  {
                    $customer->quitaslashes_customer();
                    $customer->genInsert_customer();
                    fwrite($myfile, "Existe cliente " . $_SESSION['customer']['ListID'] .  " RUC " . $_SESSION['customer']['AccountNumber'] . "  \r\n");
                    $customer->actualiza_customer();
                }
    }
     fclose($myfile);
    return true;
