// AuthLostPaswword.js
// JS para validar y controlar por ajax las acciones de registro

!(function($) {
  "use strict";

//recuperar
$('#submit_recovery').on('click', function(event) {
 const form = $('#recovery');
 if (form[0].checkValidity()) {
   recoveryAcount();
 } else {
   event.preventDefault();
       // Mostrar mensajes de validación
         const datas = {
    "recovery_email": {
        "valueMissing": "Por favor, ingresa tu correo electrónico.",
        "patternMismatch": "Ingresa un correo electrónico válido."
    }
    
    
};
validationFeedback(form, datas)
 }
 form.addClass('was-validated');
});


function recoveryAcount(){
  let alertOptions = {icon: 'error', title:''};
  const formData = $('#recovery').serialize(); // Obtener los datos del formulario
  const email=$('#recovery_email').val();
  showSpinner('#submit_recovery', 'Recuparando tu cuenta')

  $.ajax({
    type: 'POST',
    url: site_url+'ajax/lostpassword',
    data: formData,
    
    dataType: 'json',
    success: function(response) {
      showSpinner('#submit_recovery', 'Recuperar', false)

      if(response.status == 'success') {
        let swalOptions = {
          icon: 'success',
          title: 'Recupera tu cuenta',
          html: '<p>Hemos enviado un correo electrónico a <strong>'+email+'</strong> con el enlace para recuperar tu cuenta. Si no lo encuentras, por favor, revisa en tu carpeta de spam.</p>',
        };
        
        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            //location.reload();
            window.location.href = site_url +'login/';// Al login
          }  
        }); 
     } 
     else {
      const recoveryErrorSwal = getRecoveryErrorSwalOptions(response);
      if (recoveryErrorSwal) {
        swalAlert(recoveryErrorSwal);
      } else {
        alertOptions.title = successError(response.message, response.code).title 
        alertToast(alertOptions);
      }
    }
  },
      error: function(xhr, status, error) {
       showSpinner('#submit_recovery', 'Recuperar', false)
       alertOptions.title = ajaxError(status, error).title
       alertToast(alertOptions);
     }


   });

}

function getRecoveryErrorSwalOptions(response) {
  if (response.code === 'not_exists') {
    return {
      icon: 'warning',
      title: 'Oops...',
      text: 'Parece que este correo no está registrado.',
    };
  }

  return null;
}




})(jQuery);
