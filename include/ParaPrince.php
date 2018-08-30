<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


//$param = "bootstrap-guia.html";
//paraPrince($param);
function paraPrince($param) {
    $flagPDF = 'No se genero';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/info.coqueiros/include/prince.php';
    $prince = new Prince("C:/Program Files (x86)/Prince/engine/bin/prince");
    if(!$prince)
        {	die("<p>Prince instantiation failed</p>");	}

try{
    $prince->addStyleSheet('../css/bootstrap_prince.css');
    $prince->addScript('../js/bootstrap_prince.js');
    $prince->convert_file($param);
    $flagPDF = 'Genero';
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
return $flagPDF;
}