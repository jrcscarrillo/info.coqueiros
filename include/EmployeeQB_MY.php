<?php
session_start();
// SAMPLE FOR METHOD INSERT()

include_once("../docs/class/class.employee.php");

$employee = new employee();
    $_SESSION['employee'] = array();
    $doc = new DOMDocument();
    $xml = "../resources/empleados.xml";
    $doc->load($xml);
    $param = "EmployeeRet";
    $empleado = $doc->getElementsByTagName($param);
    foreach ($empleado as $uno) {
                $employee->genLimpia_employee();
                $employee->gentraverse_employee($uno);
                $existe = $employee->buscaIgual_employee();
                if ($existe == "OK") {
                    $employee->quitaslashes_employee();
                    $employee->adiciona_employee();
                } elseif ($existe == "ACTUALIZA")  {
                    $employee->quitaslashes_employee();
                    $employee->update_employee();
                }
    }
    return true;
