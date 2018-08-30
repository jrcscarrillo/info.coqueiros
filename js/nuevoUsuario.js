/* 
 * @Author      Juan Carrillo
 * @Project     Comprobantes Electronicos
 * @Date(       18 de Julio del 2014
 */

$(function() {
    $("#sky-form").validate({
        rules : {
            passForm : {
                minlength : 6,
                maxlength : 23,
                required : true
            },
            nombreForm : {
                required : true,
                minlength : 3,
                maxlength : 30
            },
            apellidoForm : {
                required : true,
                minlength : 3,
                maxlength : 30
            },
            emailForm : {
                required : true,
                email : true
            }
        },
        messages : {
            passForm : {
                required : 'Ingrese una password valida',
                minlength : "Minimo 6 caracteres",
                maxlength : "Maximo 23 caracteres"
            },
            nombreForm : {
                required : 'Ingrese un nombre valido',
                minlength : "Minimo 3 caracteres",
                maxlength : "Maximo 30 caracteres"
            },
            emailForm : {
                required : 'Ingrese su correo electronico',
                email : 'Ingrese una direccion valida'
            },
            apellidoForm : {
                required : 'Ingrese un apellido valido',
                minlength : "Minimo 3 caracteres",
                maxlength : "Maximo 30 caracteres"
        }
    }
});
});
