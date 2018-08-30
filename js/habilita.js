$(function() {
    $("#sky-form").validate({
        rules : {
            emailForm : {
                required : true,
                email : true
            }
        },
        messages : {
            emailForm : {
                required : 'Ingrese su correo electronico',
                email : 'Ingrese una direccion valida'
        }
    }
});
});
