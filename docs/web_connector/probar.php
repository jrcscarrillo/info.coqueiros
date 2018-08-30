<?php

require_once 'conectaDB.php';

$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
fwrite($myfile, "Inicio recoger fechas de sincronizacion ");
$db = conecta_SYNC();
$estado = "ERR";
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $sql = 'SELECT * FROM appliedtosync ORDER BY id DESC LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($registro) {
        $estado = 'OK';
    } else {
        $estado = 'NO HAY';
    }
} catch (PDOException $e) {
    echo 'ERROR JC!!! ' . $e->getMessage() . '<br>';
    echo 'ERROR JC!!! ' . $estado . '<br>';
}

//define("AU_DESDE", $registro['invoiceDesde']);
//define("AU_HASTA", $registro['invoiceHasta']);

date_default_timezone_set('America/Guayaquil');

$fecha = date(DATE_RFC2822);
echo 'solo date ' . $fecha . '<br>';

$fecha = new datetime();
echo 'ahora datetime ' . $fecha->format("Y/m/d H:i:s") . '<br>';

$fecha = new datetime($registro['otrosDesde']);
$usar = $fecha->sub(new DateInterval("P7D"));
$cambiar = $usar->format("Y-m-d");
echo 'ahora OOP sub interval ' . $fecha->format("Y/m/d H:i:s") . '<br>';
echo 'y despues convertido ' . $usar->format("Y/m/d H:i:s") . '<br>';
echo 'y despues para usar ' . $cambiar . '<br>';

$fecha = getdate();
echo 'ahora con getdate ' . $fecha['year'] . '-' . $fecha['mon'] . '-' . $fecha['mday'] . '<br>';

//$fecha = date("Y-m-d", strtotime($registro['invoiceDesde']));
//echo 'solo date formato ' . $fecha . '<br>';

