// AuthResetpassword.js
// JS para validar y controlar por ajax las acciones de registro

!(function($) {
  "use strict";

// Inicializamos valores
const toLogin = site_url+'login'
const urlParams = new URLSearchParams(window.location.search);
const rptoken = urlParams.get('rp');
if (rptoken) {
  $('#rpuser_token').val(rptoken)
}


$(window).on('load', function() {
  // Generamos contraseña aleatorea y actualziamos el MeterPassword
  $('#reset_password').val(generatePassword());
  updateMeterPassword($("#reset_password").val(),""); 
});


$('#submit_reset').on('click', function(event) {
 const form = $('#reset');
  if (!rptoken) {
    let swalOptions = {
           icon: 'error',
          title: 'Token no válido',
        };

        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            //location.reload();
            window.location.href = site_url + 'login/';// Al login
          }  
        }); 

  } else { 
   if (form[0].checkValidity() ) {
       reset_pasw();
   } else {
       event.preventDefault() 
       event.stopPropagation();
      window.location.href = toLogin;// Al login
   }
   form.addClass('was-validated');
   }
});

 function reset_pasw(){
  let alertOptions = {icon: 'error', title:''};
  const formData = $('#reset').serialize(); // Obtener los datos del formulario
   showSpinner('#submit_reset', 'Estableciendo contraseña')
     $.ajax({
      type: 'POST',
      url: site_url+'ajax/resetpassword',
    data: formData ,
      dataType: 'json',
      success: function(response) {
        showSpinner('#submit_reset', 'Establecer', false)
        if(response.status == 'success') {
         let swalOptions = {
          icon: 'success',
          title: 'Contraseña restablecida',
          text: 'Su contraseña ha sido restablecida con éxito',
        };

        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            //location.reload();
            window.location.href = site_url + 'login/';// Al login
          }  
        }); 
        } 
        else {
            if (response.code==='invalid_token') {
            swalOptions = {
          icon: 'error',
          title: 'Token no válido',
         };
        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            //location.reload();
           window.location.href = toLogin;// Al login
          }  
        });
            }
        else{
        alertOptions.title = successError(response.message, response.code).title 
          alertToast(alertOptions);
        }
         }
      },
     error: function(xhr, status, error) {
      showSpinner('#submit_reset', 'Establecer', false)
      alertOptions.title = ajaxError(status, error).title
      alertToast(alertOptions);
    }
  
    });

 }


$('#reset_password').on('keyup focusout focus', function() {
  updateMeterPassword($("#reset_password").val(),"")
  var extensionValida = passwordValidate($("#reset_password").val(),"");
   if (!extensionValida) {
    this.setCustomValidity('Por favor, contraseña');
  } else {
    this.setCustomValidity('');
  }
})

showPassword('.pswd', '.showPassword')

})(jQuery);
