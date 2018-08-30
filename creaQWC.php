<?php
session_start();
include_once './docs/web_connector/conectaDB.php';
if (!isset($_SESSION['carrillosteam'])) {
    require ('paraContinuar.html');
    echo '<script type="text/javascript">' .
    "$(document).ready(function(){" .
    "$('#mensaje').html('Usuario no ha ingresado al sistema');" .
    "})" .
    "</script>";
    exit();
}
?>
<html>
    <head>
        <title>Applicacion para WC</title>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

        <link rel="stylesheet" href="css/demo.css">
        <link href="//netdna.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/sky-forms.css">
        <link rel="stylesheet" href="css/sky-forms-orange.css">
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
        <script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.12.0/jquery.validate.js"></script>
        <script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.12.0/additional-methods.js"></script>
    </head>

    <body class="bg-black">
        <div class="container">
            <form id="sky-form" class="sky-form" method="post" action="./docs/web_connector/creaQWC.php" >
                <header>Registra Applicacion</header>

                <fieldset>
                    <label class="label">Ingrese ID y clave</label>	
                    <div class="row">	
                        <section class="col col-12">
                            <label class="label">Selecciona Aplicacion</label>
                            <label class="select">
                                <select id="appForm" name="appForm">
                                    <?php
                                    $db = conecta_SYNC();
                                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $sql = "SELECT * FROM aplicaciones";
                                    try {
                                        $stmt = $db->prepare($sql);
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . $row['nombre'] . "'>" . $row['nombre'] . " " . $row['descripcion'] . "</option>";
                                        }
                                        $stmt = null;
                                    } catch (PDOException $e) {
                                        print $e->getMessage();
                                    }
                                    ?>
                                </select>
                            </label>
                        </section>
                    </div>
                </fieldset>
                <fieldset>
                    <div class="row">	
                        <section class="col col-12">
                            <label class="label">Selecciona usuario</label>
                            <label class="select">
                                <select id="userForm" name="userForm">
                                    <?php
                                    $sql = "SELECT qb_username FROM quickbooks_user";
                                    try {
                                        $stmt = $db->prepare($sql);
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . $row['qb_username'] . "'>" . $row['qb_username'] . "</option>";
                                        }
                                        $stmt = null;
                                    } catch (PDOException $e) {
                                        print $e->getMessage();
                                    }
                                    ?>
                                </select>
                            </label>
                        </section>
                    </div>
                </fieldset>
                <fieldset>
                    <div class="row">
                        <section class="col col-12">
                            <label class="label">Nombre para su archivo</label>
                            <label class="input">
                                <i class="icon-append fa fa-file"></i>
                                <input type="text" name="fileForm" id="fileForm" placeholder="Nombre del archivo a grabar">
                            </label>
                        </section>
                    </div>
                </fieldset>

                <footer>
                    <label class="label">Al generarse este archivo como regla general debera guardarlo en un directorio especial del servidor WC</label>
                    <button type="submit" class="button">Procesar</button>
                    <button type="button" class="button button-secondary" onclick="window.history.back();">Regresar</button>
                </footer>
            </form>			
        </div>
        <script type="text/javascript">
            $(function () {
                $("#sky-form").validate({
                    rules: {
                        fileForm: {
                            minlength: 6,
                            maxlength: 50,
                            required: true
                        },
                        userForm: {
                            required: true
                        }
                    },
                    messages: {
                        fileForm: {
                            required: 'Ingrese un nombre de archivo',
                            minlength: "Minimo de 6 digitos",
                            maxlength: "Maximo de 50 digitos",
                        },
                        userForm: {
                            required: 'Seleccione un usuario',
                        }
                    },
                    errorPlacement: function (error, element) {
                        error.insertAfter(element.parent());
                    }
                });
            });
        </script>
    </body>
</html>