<?php

session_start();
if (!isset($_POST['invoicestart']) || !isset($_POST['invoicefinish']) || !isset($_POST['billstart']) || !isset($_POST['billfinish']) || !isset($_POST['billcreditstart']) || !isset($_POST['billcreditfinish']) || !isset($_POST['creditmemostart']) || !isset($_POST['creditmemofinish']) || !isset($_POST['productionstart']) || !isset($_POST['productionfinish']) || !isset($_POST['otrosstart']) || !isset($_POST['otrosfinish'])) {
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('*** ERROR NO no ha ingresado las fechas para sincronizar QB');" .
    "})" .
    "</script>";
    exit();
}

date_default_timezone_set('America/Guayaquil');
include_once("../class/class.appliedtosync.php");

$appliedtosync = new appliedtosync();
$_SESSION['appliedtosync'] = array();
$appliedtosync->genLimpia_appliedtosync();
$fecha = new datetime();
$_SESSION['appliedtosync']['datecreated'] = $fecha->format("Y-m-d H:i:s");
$_SESSION['appliedtosync']['user'] = $_SESSION['email'];
$_SESSION['appliedtosync']['billDesde'] = date("Y-m-d H:i:s", strtotime($_POST['billstart']));
$_SESSION['appliedtosync']['billHasta'] = date("Y-m-d H:i:s", strtotime($_POST['billfinish']));
$_SESSION['appliedtosync']['invoiceDesde'] = date("Y-m-d H:i:s", strtotime($_POST['invoicestart']));
$_SESSION['appliedtosync']['invoiceHasta'] = date("Y-m-d H:i:s", strtotime($_POST['invoicefinish']));
$_SESSION['appliedtosync']['billCreditDesde'] = date("Y-m-d H:i:s", strtotime($_POST['billcreditstart']));
$_SESSION['appliedtosync']['billCreditHasta'] = date("Y-m-d H:i:s", strtotime($_POST['billcreditfinish']));
$_SESSION['appliedtosync']['creditMemoDesde'] = date("Y-m-d H:i:s", strtotime($_POST['creditmemostart']));
$_SESSION['appliedtosync']['creditMemoHasta'] = date("Y-m-d H:i:s", strtotime($_POST['creditmemofinish']));
$_SESSION['appliedtosync']['productionDesde'] = date("Y-m-d H:i:s", strtotime($_POST['productionstart']));
$_SESSION['appliedtosync']['productionHasta'] = date("Y-m-d H:i:s", strtotime($_POST['productionfinish']));
$_SESSION['appliedtosync']['retencionDesde'] = date("Y-m-d H:i:s", strtotime("2000-10-01"));
$_SESSION['appliedtosync']['retencionHasta'] = date("Y-m-d H:i:s", strtotime("2000-10-01"));
$_SESSION['appliedtosync']['otrosDesde'] = date("Y-m-d H:i:s", strtotime($_POST['otrosstart']));
$_SESSION['appliedtosync']['otrosHasta'] = date("Y-m-d H:i:s", strtotime($_POST['otrosfinish']));
$appliedtosync->quitaslashes_appliedtosync();
$appliedtosync->genInsert_appliedtosync();
$appliedtosync->adiciona_appliedtosync();
require_once 'paraContinuar.html';
echo '<script type="text/javascript">' .
 "$(document).ready(function(){" .
 "$('#mensaje').text('*** Las fechas se han registrado satisfactoriamente');" .
 "})" .
 "</script>";
