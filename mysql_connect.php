<?php
//$host = "localhost";
//$user = "coqueiro_qb";
//$pw = "freedom";
//$db = "coqueiro_qb";
//$link = mysqli_connect($host, $user, $pw, $db);
//if (!$link) {
//    die("Could not connect: " . mysqli_connect_error());
//}
//echo 'Connected successfully.';
//// Always close your connections!!
//mysqli_close($link);

//function db_connect() {
    $userName = "carrillo_db";
    $password = "AnyaCarrill0";
    $dbName = "carrillo_dbaurora";
    $server = "localhost";
    $db = new mysqli($server, $userName, $password, $dbName);
    if ($db->connect_errno) {
        die('Error de Conexion: ' . $db->connect_errno);
    }   
   echo "Se ha conectado";
//}
?>