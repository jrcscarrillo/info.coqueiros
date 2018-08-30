<?php
session_start();

$conecta = "../docs/web_connector/conectaDB.php";
$mensaje = "paraContinuar.html";
if (isset( $_SESSION['carrillosteam'] )) {
    if ($_SESSION['carrillosteam'] == 'carrillosteam') {
        require_once $mensaje;
    echo '<script type="text/javascript">'.
        "$(document).ready(function(){".
        "$('#mensaje').text('Usuario ya esta ingresado an el sistema');".
        "})".
        "</script>";
        exit();
}
}

include_once $conecta;
if (isset($_POST['email'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $flagDB = loginUsuario($email, $password, $mensaje);
    exit();
} else {
    require_once $mensaje;
    echo '<script type="text/javascript">'.
        "$(document).ready(function(){".
        "$('#mensaje').text('No ha ingresado datos');".
        "})".
        "</script>"; 
        exit();
}
function loginUsuario($email, $password, $mensaje) {

        $estado = '';
        $passencriptada = hash('sha256', $password);
        $db = conecta_godaddy();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
        $sql = "SELECT * FROM usuarios WHERE UsuariosEmail = :mail AND UsuariosPassword = :pass";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':mail', $email);
        $stmt->bindParam(':pass', $passencriptada);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        if ( $registro){
            $estado = "OK";
        } 
            
} catch(PDOException $e) {
    echo $e->getMessage();
} 


        if ($estado == 'OK'){
        
            if ($registro['UsuariosHabilitado'] == 1) {
                require_once $mensaje;
                echo '<script type="text/javascript">'.
                        "$(document).ready(function(){".
                        "$('#mensaje').text('El usuario ha ingresado satisfactoriamente');".
                        "})".
                        "</script>";
                $_SESSION['carrillosteam'] = 'carrillosteam';
                $_SESSION['nombre'] = $registro['UsuariosNombre'];
                $_SESSION['apellido'] = $registro['UsuariosApellido'];
                $_SESSION['email'] = $registro['UsuariosEmail'];
                $_SESSION['userStatus'] = $registro['UsuariosEstado'];
                $_SESSION['administrador'] = $registro['Administrador'];
                
            } else {
                require_once $mensaje;
                echo '<script type="text/javascript">'.
                        "$(document).ready(function(){".
                        "$('#mensaje').text('Usuario registrado pero no esta habilitado. Contactarse con el administrador');".
                        "})".
                        "</script>";
                }
        } else {
            require $mensaje;
            echo '<script type="text/javascript">'.
                    "$(document).ready(function(){".
                    "$('#mensaje').text('Usuario no existe');".
                    "})".
                    "</script>";
        }
$stmt = null;
$db = null;  

}