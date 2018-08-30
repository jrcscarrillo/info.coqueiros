<?php

function conecta_SYNC() {
        $userName = "carrillo_db";
        $password = "AnyaCarrill0";
        $dbName = "carrillo_dbaurora";
        $server = "localhost";
        $charset = 'utf8';
        $dsn = "mysql:host=$server;dbname=$dbName;charset=$charset";
    try {
        $db = new PDO($dsn, $userName, $password);
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        print "Error!: " . $userName . "<br/>";
        print "Error!: " . $password . "<br/>";
        die();
    }
    return $db;
}
function conecta_PDO() {
    if ($_SESSION['basedatos'] == 'pruebas') {
        $userName = "jrcscarrillo";
        $password = "AnyaCarrill0";
        $dbName = "dbaurora";
        $server = "localhost";
        $charset = 'utf8';
        $dsn = "mysql:host=$server;dbname=$dbName;charset=$charset";
    } else {
        $userName = "auroraec_db";
        $password = "AnyaCarrill0";
        $dbName = "auroraec_aurora";
        $server = "localhost";
        $charset = 'utf8';
        $dsn = "mysql:host=$server;dbname=$dbName;charset=$charset";        
    }
    try {
        $db = new PDO($dsn, $userName, $password);
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    }
    return $db;
}

function conecta_godaddy() {
        $userName = "carrillo_db";
        $password = "AnyaCarrill0";
        $dbName = "carrillo_dbaurora";
        $server = "localhost";
        $charset = 'utf8';
        $dsn = "mysql:host=$server;dbname=$dbName;charset=$charset";
    try {
        $db = new PDO($dsn, $userName, $password);
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        print "Error!: " . $userName . "<br/>";
        print "Error!: " . $password . "<br/>";
        die();
    }
    return $db;
}

function conecta_DB() {
    $userName = "coqueiros_qb";
    $password = "freedom";
    $dbName = "coqueiro_qb";
    $server = "localhost";
    $db = new mysqli($server, $userName, $password, $dbName);
    if ($db->connect_errno) {
        die('Error de Conexion: ' . $db->connect_errno);
    }
    return $db;
}
