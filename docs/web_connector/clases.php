<?php

session_start();

error_reporting(1);

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Guayaquil');
}
require_once 'conectaDB.php';

    $doc = new DOMDocument();
    $doc->load('revisar.xml');
    $db = conecta_SYNC();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $param = "ClassRet";
    $clases = $doc->getElementsByTagName($param);
    $k = 0;
    foreach ($clases as $uno) {
        genLimpia_bodegas();
        gentraverse_bodegas($uno);

        $existe = buscaIgual_bodegas($db);
        if ($existe == "OK") {
            quitaslashes_bodegas();
            $retorna = adiciona_bodegas($db);
            fwrite($myfile, "Produccion nueva" . $retorna . " \r\n");
        } elseif ($existe == "ACTUALIZA") {
            quitaslashes_bodegas();
            $paso = actualiza_bodegas($db);
            fwrite($myfile, "Existe produccion " . $paso . " \r\n");
        } else {
            fwrite($myfile, $existe . " \r\n");
        }

        $k++;
    }
    fwrite($myfile, "-------->  FIN DEL LOG \r\n");
    fclose($myfile);
    fclose($myfile1);

function genLimpia_bodegas() {
    $_SESSION['bodegas']['ListID'] = ' ';
    $_SESSION['bodegas']['TimeCreated'] = ' ';
    $_SESSION['bodegas']['TimeModified'] = ' ';
    $_SESSION['bodegas']['EditSequence'] = ' ';
    $_SESSION['bodegas']['Name'] = ' ';
    $_SESSION['bodegas']['FullName'] = ' ';
    $_SESSION['bodegas']['IsActive'] = ' ';
    $_SESSION['bodegas']['ParentRef_ListID'] = ' ';
    $_SESSION['bodegas']['ParentRef_FullName'] = ' ';
    $_SESSION['bodegas']['Sublevel'] = ' ';
    $_SESSION['bodegas']['Status'] = ' ';
    $_SESSION['bodegas']['Estado'] = ' ';
}

function gentraverse_bodegas($node) {
    $node->getElementsByTagName('ListID')->item(0) == NULL ? $_SESSION['bodegas']['ListID'] = ' ' : $_SESSION['bodegas']['ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeCreated')->item(0) == NULL ? $_SESSION['bodegas']['TimeCreated'] = '2010-08-10' : $_SESSION['bodegas']['TimeCreated'] = $node->getElementsByTagName('TimeCreated')->item(0)->nodeValue;
    $node->getElementsByTagName('TimeModified')->item(0) == NULL ? $_SESSION['bodegas']['TimeModified'] = '2010-08-10' : $_SESSION['bodegas']['TimeModified'] = $node->getElementsByTagName('TimeModified')->item(0)->nodeValue;
    $node->getElementsByTagName('EditSequence')->item(0) == NULL ? $_SESSION['bodegas']['EditSequence'] = 0 : $_SESSION['bodegas']['EditSequence'] = $node->getElementsByTagName('EditSequence')->item(0)->nodeValue;
    $node->getElementsByTagName('Name')->item(0) == NULL ? $_SESSION['bodegas']['Name'] = ' ' : $_SESSION['bodegas']['Name'] = $node->getElementsByTagName('Name')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(0) == NULL ? $_SESSION['bodegas']['FullName'] = ' ' : $_SESSION['bodegas']['FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('IsActive')->item(0) == NULL ? $_SESSION['bodegas']['IsActive'] = ' ' : $_SESSION['bodegas']['IsActive'] = $node->getElementsByTagName('IsActive')->item(0)->nodeValue;
    $node->getElementsByTagName('ListID')->item(1) == NULL ? $_SESSION['bodegas']['ParentRef_ListID'] = ' ' : $_SESSION['bodegas']['ParentRef_ListID'] = $node->getElementsByTagName('ListID')->item(0)->nodeValue;
    $node->getElementsByTagName('FullName')->item(1) == NULL ? $_SESSION['bodegas']['ParentRef_FullName'] = ' ' : $_SESSION['bodegas']['ParentRef_FullName'] = $node->getElementsByTagName('FullName')->item(0)->nodeValue;
    $node->getElementsByTagName('Sublevel')->item(0) == NULL ? $_SESSION['bodegas']['Sublevel'] = 0 : $_SESSION['bodegas']['Sublevel'] = $node->getElementsByTagName('Sublevel')->item(0)->nodeValue;
    $node->getElementsByTagName('Status')->item(0) == NULL ? $_SESSION['bodegas']['Status'] = ' ' : $_SESSION['bodegas']['Status'] = $node->getElementsByTagName('Status')->item(0)->nodeValue;
    $node->getElementsByTagName('Estado')->item(0) == NULL ? $_SESSION['bodegas']['Estado'] = ' ' : $_SESSION['bodegas']['Estado'] = $node->getElementsByTagName('Estado')->item(0)->nodeValue;
}

function adiciona_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'INSERT INTO bodegas (  ListID, TimeCreated, TimeModified, EditSequence, Name, FullName, IsActive, ParentRef_ListID, ParentRef_FullName, Sublevel, Status, Estado) VALUES ( :ListID, :TimeCreated, :TimeModified, :EditSequence, :Name, :FullName, :IsActive, :ParentRef_ListID, :ParentRef_FullName, :Sublevel, :Status, :Estado)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':ListID', $_SESSION['bodegas']['ListID']);
        $stmt->bindParam(':TimeCreated', $_SESSION['bodegas']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['bodegas']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['bodegas']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['bodegas']['Name']);
        $stmt->bindParam(':FullName', $_SESSION['bodegas']['FullName']);
        $stmt->bindParam(':IsActive', $_SESSION['bodegas']['IsActive']);
        $stmt->bindParam(':ParentRef_ListID', $_SESSION['bodegas']['ParentRef_ListID']);
        $stmt->bindParam(':ParentRef_FullName', $_SESSION['bodegas']['ParentRef_FullName']);
        $stmt->bindParam(':Sublevel', $_SESSION['bodegas']['Sublevel']);
        $stmt->bindParam(':Status', 'CON-MOV');
        $stmt->bindParam(':Estado', $_SESSION['bodegas']['Estado']);
        $stmt->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function quitaslashes_bodegas() {
    $_SESSION['bodegas']['ListID'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ListID']));
    $_SESSION['bodegas']['TimeCreated'] = date('Y-m-d H:m:s', strtotime($_SESSION['bodegas']['TimeCreated']));
    $_SESSION['bodegas']['TimeModified'] = date('Y-m-d H:m:s', strtotime($_SESSION['bodegas']['TimeModified']));
    $_SESSION['bodegas']['EditSequence'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['EditSequence']));
    $_SESSION['bodegas']['Name'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Name']));
    $_SESSION['bodegas']['FullName'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['FullName']));
    $_SESSION['bodegas']['IsActive'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['IsActive']));
    $_SESSION['bodegas']['ParentRef_ListID'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ParentRef_ListID']));
    $_SESSION['bodegas']['ParentRef_FullName'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['ParentRef_FullName']));
    $_SESSION['bodegas']['Sublevel'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Sublevel']));
    $_SESSION['bodegas']['Status'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Status']));
    $_SESSION['bodegas']['Estado'] = htmlspecialchars(strip_tags($_SESSION['bodegas']['Estado']));
}

function buscaIgual_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'SELECT * FROM bodegas WHERE ListID = :clave ';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':clave', $_SESSION['bodegas']['ListID']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $estado = 'OK';
        } else {
            if ($registro['ListID'] === $_SESSION['bodegas']['ListID']) {
                $estado = 'ACTUALIZA';
            }
        }
    } catch (PDOException $e) {
        echo $e->getMessage();
    }

    return $estado;
}

function actualiza_bodegas($db) {
    $estado = 'ERR';
    try {
        $sql = 'UPDATE bodegas SET TimeCreated=:TimeCreated, TimeModified=:TimeModified, EditSequence=:EditSequence, Name=:Name, FullName=:FullName, IsActive=:IsActive, ParentRef_ListID=:ParentRef_ListID, ParentRef_FullName=:ParentRef_FullName, Sublevel=:Sublevel, Estado=:Estado WHERE ListID = :clave;';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':TimeCreated', $_SESSION['bodegas']['TimeCreated']);
        $stmt->bindParam(':TimeModified', $_SESSION['bodegas']['TimeModified']);
        $stmt->bindParam(':EditSequence', $_SESSION['bodegas']['EditSequence']);
        $stmt->bindParam(':Name', $_SESSION['bodegas']['Name']);
        $stmt->bindParam(':FullName', $_SESSION['bodegas']['FullName']);
        $stmt->bindParam(':IsActive', $_SESSION['bodegas']['IsActive']);
        $stmt->bindParam(':ParentRef_ListID', $_SESSION['bodegas']['ParentRef_ListID']);
        $stmt->bindParam(':ParentRef_FullName', $_SESSION['bodegas']['ParentRef_FullName']);
        $stmt->bindParam(':Sublevel', $_SESSION['bodegas']['Sublevel']);
        $stmt->bindParam(':Estado', $_SESSION['bodegas']['Estado']);
        $stmt->bindParam(':clave', $_SESSION['bodegas']['ListID']);
        $stmt->execute();
    } catch (PDOException $e) {
        
    }
}

