<?php
session_start();
if (!isset($_SESSION['carrillosteam'])) {    
require ('paraContinuar.html');
echo '<script type="text/javascript">'.
        "$(document).ready(function(){".
        "$('#mensaje').html('Usuario no ha ingresado al sistema');".
        "})".
        "</script>";
exit();
}
require_once 'creaApp.html';
    exit();

