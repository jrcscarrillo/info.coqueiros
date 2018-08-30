<?php
session_start();
error_reporting(E_ALL);
include_once("../docs/class/class.mycustomer.php");

$customer = new mycustomer();
    $_SESSION['customer'] = array();
    $doc = new DOMDocument();
    $xml = "../resources/customers.xml";
    $doc->load($xml);
    $param = "CustomerRet";
    $cliente = $doc->getElementsByTagName($param);
    $myfile = fopen("mycustomer.txt", "w") or die("Unable to open file!");
    fwrite($myfile, "Inicio de sincronizacion \r\n");
    foreach ($cliente as $uno) {
                $customer->genLimpia_mycustomer();
                $customer->gentraverse_mycustomer($uno);
                $existe = $customer->buscaIgual_mycustomer();
                if ($existe == "OK") {
                    $customer->quitaslashes_mycustomer();
                    fwrite($myfile, "Nuevo Cliente " . $_SESSION['mycustomer']['ListID'] . "\r\n");
                    $customer->adiciona_mycustomer();
                } elseif ($existe == "ACTUALIZA")  {
                    $customer->quitaslashes_mycustomer();
                    fwrite($myfile, "Actualiza Cliente " . $_SESSION['mycustomer']['ListID'] . "\r\n");
                    $customer->update_mycustomer();
                }
    }
    return true;
