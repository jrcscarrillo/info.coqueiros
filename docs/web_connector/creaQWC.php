<?php

session_start();
error_reporting(1);
if (!isset($_POST['appForm']) || !isset($_POST['userForm']) || !isset($_POST['fileForm'])) {
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('*** ERROR NO no ha ingresado los datos de la aplicacion');" .
    "})" .
    "</script>";
    exit();
}
require_once '../../QuickBooks.php';
include_once 'conectaDB.php';
$db = conecta_SYNC();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $sql = "SELECT * FROM aplicaciones WHERE nombre = :app";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':app', $_POST['appForm']);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        require_once 'paraContinuar.html';
        echo '<script type="text/javascript">' .
        "$(document).ready(function(){" .
        "$('#mensaje').text('*** ERROR No existen datos para esta aplicacion');" .
        "})" .
        "</script>";
        exit();
    } else {
        $name = $registro['nombre'];
        $descrip = $registro['descripcion'];
        $appurl = $registro['url'];
        $appsupport = $registro['soporte'];
        $username = $_POST['userForm'];
        $file = $_POST['fileForm'];
    }
} catch (PDOException $e) {
    echo 'ERROR JC!!! ' . $e->getMessage() . '<br>';
    echo 'ERROR JC!!! ' . $estado . '<br>';
}
$fileid = guid();
$ownerid = guid();
$qbtype = QUICKBOOKS_TYPE_QBFS; // You can leave this as-is unless you're using QuickBooks POS
$readonly = false; // No, we want to write data to QuickBooks
$run_every_n_seconds = 60; // Run every 600 seconds (10 minutes)
$QWC = new QuickBooks_WebConnector_QWC($name, $descrip, $appurl, $appsupport, $username, $fileid, $ownerid, $qbtype, $readonly, $run_every_n_seconds);
$xml = $QWC->generate();

        
header('Content-type: text/xml');
header('Content-Disposition: attachment; filename="' . $file . '".qwc');
print($xml);
exit;

function guid($opt = true) {       //  Set to true/false as your default way to do this.
    if (function_exists('com_create_guid')) {
        if ($opt) {
            return com_create_guid();
        } else {
            return trim(com_create_guid(), '{}');
        }
    } else {
        mt_srand((double) microtime() * 10000);    // optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);    // "-"
        $left_curly = $opt ? chr(123) : "";     //  "{"
        $right_curly = $opt ? chr(125) : "";    //  "}"
        $uuid = $left_curly
           . substr($charid, 0, 8) . $hyphen
           . substr($charid, 8, 4) . $hyphen
           . substr($charid, 12, 4) . $hyphen
           . substr($charid, 16, 4) . $hyphen
           . substr($charid, 20, 12)
           . $right_curly;
        return $uuid;
    }
}
