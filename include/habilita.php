<?php

session_start();
$_SESSION['habilita'] = array();
$_SESSION['habilita']['mensaje'] = "OK";
if (!isset($_SESSION['carrillosteam'])) {
    $_SESSION['habilita']['mensaje'] = 'Sistema no ha sido inicializado apropiadamente';
} else {
    if (!isset($_POST['emailForm'])) {
        $_SESSION['habilita']['mensaje'] = 'No ha ingresado un usuario para habilitarlo';
    } else {
        if (!$_SESSION['administrador'] == 'SUPER') {
            $_SESSION['habilita']['mensaje'] = 'Usuario no tiene autorizacion';
        }
    }
}
if ($_SESSION['habilita']['mensaje'] == 'OK') {
    include_once '../docs/web_connector/conectaDB.php';
    $_SESSION['habilita']['email'] = $_POST['emailForm'];
    $paso = buscaUsuario();
    if ($paso == TRUE) {
        $_SESSION['habilita']['mensaje'] = 'Usuario habilitado';
    } else {
        $_SESSION['habilita']['mensaje'] = 'Usuario no existe';
    }
}
require_once ('paraContinuar.html');
echo '<script type="text/javascript">' .
 "$(document).ready(function(){" .
 "$('#mensaje').text('" . $_SESSION['habilita']['mensaje'] . "');" .
 "})" .
 "</script>";
exit();

function buscaUsuario() {
    $db = conecta_godaddy();
    $flagHabilita = FALSE;
    $sql = "select * from Usuarios where UsuariosEmail=:email";
    try {
        
    } catch (Exception $exc) {
        echo $exc->getTraceAsString();
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':email', $_SESSION['habilita']['email']);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $_SESSION['habilita']['mensaje'] = 'No se ha localizado al usuario';
    } else {
        $_SESSION['habilita']['id'] = $registro['idUsuarios'];
        $_SESSION['habilita']['pass'] = $registro['UsuariosPassword'];
        $_SESSION['habilita']['habilita'] = $registro['UsuariosHabilitado'];
        $_SESSION['habilita']['nombre'] = $registro['UsuariosNombre'];
        $_SESSION['habilita']['email'] = $registro['UsuariosEmail'];
        $_SESSION['habilita']['apellido'] = $registro['UsuariosApellido'];
        $_SESSION['habilita']['estado'] = $registro['UsuariosEstado'];
        $_SESSION['habilita']['administrador'] = $registro['Administrador'];
        $flagHabilita = habilitaUsuario($db);
    }
    $stmt = null;
    $db = null;
    return $flagHabilita;
}

function habilitaUsuario($db) {
    try {
        $sql = "UPDATE Usuarios SET UsuariosHabilitado = :habil where UsuariosEmail=:email";
        $stmt = $db->prepare($sql);
        $_SESSION['habilita']['habilita'] = 1;
        $stmt->bindParam(':email', $_SESSION['habilita']['email']);
        $stmt->bindParam(':habil', $_SESSION['habilita']['habilita']);
        $stmt->execute();
        enviaHabilitado();
    } catch (Exception $exc) {
        echo $exc->getTraceAsString();
    }


    return true;
}

function enviaHabilitado() {
    $to = $_SESSION['habilita']['email'] . ', ';
    $to .= $_SESSION['email'];
    $subject = 'Nuevo Usuario';
    $message = '<div><b>Nombre: </b>' . $_SESSION['habilita']['nombre'] . "<br>" . '<b>Apellido: </b>' . $_SESSION['habilita']['apellido'] . "<br>";
    $message .= '<b>Email: </b>' . $_SESSION['habilita']['email'] . "<br>";
    $message .= '<br><hr><br><span>Usted ha sido habilitado para utilizar el website www.ventasloscoqueiros.com</span></div>';

    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $headers .= 'To: ' . $_SESSION['habilita']['email'] . "\r\n";
    $headers .= 'From: No Contestar <ventas@ventasloscoqueiros.com>' . "\r\n";
    mail($to, $subject, $message, $headers);
}
