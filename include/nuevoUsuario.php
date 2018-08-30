<?php

/*
 * Autor:   Juan Carrillo
 * Fecha:   Junio 22 2014
 * Fecha:   Noviembre 27 2017
 * Proyecto: Comprobantes Electronicos
 */
session_start();
error_reporting(1);
include_once '../docs/web_connector/conectaDB.php';
if (isset($_POST['emailForm'])) {
    $_SESSION['email'] = $_POST['emailForm'];
    $_SESSION['password'] = $_POST['passForm'];
    $_SESSION['nombre'] = $_POST['nombreForm'];
    $_SESSION['apellido'] = $_POST['apellidoForm'];

    $_SESSION['encriptada'] = hash('sha256', $password);
    if ($_SESSION['email'] == "jrcscarrillo@gmail.com") {
        $_SESSION['habilita'] = 1;
        $_SESSION['admin'] = 'SUPER';
        $_SESSION['estado'] = 1;
    } else {
        $_SESSION['habilita'] = 0;
        $_SESSION['admin'] = 'USUARIO';
        $_SESSION['estado'] = 0;
    }
    $db = conecta_godaddy();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $flagDB = chkUsuario($db);

    require_once 'paraContinuar.html';
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').text('" . $flagDB . "');" .
    "})" .
    "</script>";
}

function chkUsuario($db) {

    $sql = "select * from usuarios where UsuariosEmail=:email";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $_SESSION['email']);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registro) {
            $flagnew = nuevoUsuario($db);
        } else {
            if ($_SESSION['email'] == $registro['UsuariosEmail']) {
                $flagNew = "Nombre de Usuario Ya Existe";
            } elseif ($_SESSION['encriptada'] == $registro['UsuariosPassword']) {
                $flagNew = "Password Invalida";
            }
        }
    } catch (Exception $exc) {
        print "Error!: " . $exc->getMessage() . "<br/>" . $exc->getTraceAsString() . "<br>";
        print "Error!: " . $_SESSION['email'] . "<br/>";
        print "Error!: " . $_SESSION['password'] . "<br/>";
        die();
    }
    $db = null;
    return $flagNew;
}

function nuevoUsuario($db) {

    $sql = "insert into usuarios(UsuariosEmail, UsuariosPassword, UsuariosHabilitado, UsuariosNombre, UsuariosApellido, UsuariosEstado, Administrador";
    $sql .= ") values(:email, :pass, :habil, :nombre, :apellido, :estado, :admin)";
    $estadoUsuario = "Por grabar";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $_SESSION['email']);
        $stmt->bindParam(':pass', $_SESSION['encriptada']);
        $stmt->bindParam(':habil', $_SESSION['habilita']);
        $stmt->bindParam(':nombre', $_SESSION['nombre']);
        $stmt->bindParam(':apellido', $_SESSION['apellido']);
        $stmt->bindParam(':estado', $_SESSION['estado']);
        $stmt->bindParam(':admin', $_SESSION['admin']);
        $stmt->execute();
        $estadoUsuario = "Usuario adicionado";
        chkMail();
    } catch (Exception $exc) {
        print "Error!: " . $exc->getMessage() . "<br/>" . $exc->getTraceAsString() . "<br>";
        print "Error!: " . $_SESSION['email'] . "<br/>";
        print "Error!: " . $_SESSION['password'] . "<br/>";
        die();
    }


    if ($estadoUsuario = "Usuario adicionado") {
        return $estadoUsuario . " Usted se ha registrado en el sistema de ventas de LOS COQUEIROS, se enviara un email indicandole que esta habilitado";
    } else {
        return $estadoUsuario;
    }
}

function chkMail() {

    if ($_SESSION['email'] == "jrcscarrillo@gmail.com") {
        $mensaje = "Usted se ha registrado en el sistema de comprobantes electronicos, esta habilitado";
    } else {
        $mensaje = "Usted se ha registrado en el sistema de comprobantes electronicos, se enviara un email indicandole que esta habilitado";
    }
    $to = $_SESSION['email'] . ', ';
    $to .= 'jrcscarrillo@gmail.com';
    $subject = 'Nuevo Usuario';
    $message = '<div><b>Nombre: </b>' . $_SESSION['nombre'] . "<br>" . '<b>Apellido: </b>' . $_SESSION['apellido'] . "<br>";
    $message .= '<br><hr><br><span>' . $mensaje . '</span></div>';

    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $headers .= 'To: ' . $_SESSION['email'] . "\r\n";
    $headers .= 'From: No Contestar <ventas@ventasloscoqueiros.com>' . "\r\n";
    mail($to, $subject, $message, $headers);
}
