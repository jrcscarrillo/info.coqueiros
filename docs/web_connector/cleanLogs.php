<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
session_start();
include_once 'conectaDB.php';
$db = conecta_godaddy();
$sql = "TRUNCATE quickbooks_log";
$stmt = $db->prepare($sql) or die(mysqli_error($db));
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
$stmt = null;

$sql = "TRUNCATE quickbooks_queue";
$stmt = $db->prepare($sql) or die(mysqli_error($db));
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
$stmt = null;

$sql = "TRUNCATE quickbooks_ticket";
$stmt = $db->prepare($sql) or die(mysqli_error($db));
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
$stmt = null;

$mensaje = "Se han eliminado los mensajes de log en la base de datos";
require_once 'paraContinuar.html';
echo '<script type="text/javascript">' .
 "$(document).ready(function(){" .
 "$('#mensaje').text('" . $mensaje . "');" .
 "})" .
 "</script>";