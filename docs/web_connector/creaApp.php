<?php

session_start();
if (!isset($_POST['idForm']) || !isset($_POST['urlForm']) || !isset($_POST['soporteForm'])) {
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('*** ERROR NO no ha ingresado los datos de la aplicacion');" .
    "})" .
    "</script>";
    exit();
}
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
include_once 'conectaDB.php';
$db = conecta_godaddy();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $sql = "INSERT INTO aplicaciones(nombre, descripcion, url, soporte, ultimoUsuario, fechaCreacion) VALUES( :nombre, :descripcion, :url, :soporte, :ultimoUsuario, :fechaCreacion)";
    $stmt = $db->prepare($sql);
    $usuario = "NO";
    $fecha = date("y/m/d");
    $stmt->bindParam(':nombre', $_POST['idForm']);
    $stmt->bindParam(':descripcion', $_POST['descForm']);
    $stmt->bindParam(':url', $_POST['urlForm']);
    $stmt->bindParam(':soporte', $_POST['soporteForm']);
    $stmt->bindParam(':ultimoUsuario', $usuario);
    $stmt->bindParam(':fechaCreacion', $fecha);
    $stmt->execute();
    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('Se adiciona otra aplicacion al sistema');" .
    "})" .
    "</script>";
} catch (PDOException $e) {
    echo $e->getMessage();
} 
