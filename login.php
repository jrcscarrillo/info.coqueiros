<?php

/* 
 * @author: Juan Carrillo
 * @Dste:   7/19/2016
 * @Project: Punto de Venta
 * Este programa revisa que el usuario no este ingresado en el sistema
 * No ingresado: Envia la pantalla de ingreso al sistema
 * Ingresado: Envia mensaje
 * 
 */
session_start();
$salta = "./login.html";
$mensaje = "./paraContinuar.html";
if (!isset($_SESSION['carrillosteam'])) {
    require_once $salta;
} else {
require_once $mensaje;
echo '<script type="text/javascript">'.
        "$(document).ready(function(){".
        "$('#mensaje').html('Usuario ya esta ingresado an el sistema');".
        "})".
        "</script>";
}