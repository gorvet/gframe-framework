// AuthRegister.js
// JS para validar y controlar por ajax las acciones de registro

!(function($) {
  "use strict";
const toLostpassword = site_url + 'login/lostpassword/'

$(window).on('load', function() {
// Generamos contraseña aleatorea y actualziamos el MeterPassword
$('#register_password').val(generatePassword());
updateMeterPassword($("#register_password").val(),$("#register_email").val()); 
 
});


// Register
$('#submit_register').on('click', function(event) {
  const form = $('#register');
  if (form[0].checkValidity()) {
    registerAcount();
  } else {
    event.preventDefault();
    // Mostrar mensajes de validación
    const datas = {
      "register_email": {
        "valueMissing": "Por favor, ingresa tu correo electrónico.",
        "patternMismatch": "Ingresa un correo electrónico válido."
      },
      "register_password": {
        "valueMissing": "Por favor, no olvides tu contraseña.",
        "customError": ""
      }
    };
    validationFeedback(form, datas)
  }
  form.addClass('was-validated');
});

function registerAcount(){
  let alertOptions = {icon: 'error', title:''};
  let swalOptions
  const formData = $('#register').serialize();
  const email = $('#register_email').val();
  /* Mostramos un loader hasta que se procese la solocitud */
  showSpinner('#submit_register', 'Creando tu cuenta')
  $.ajax({
    type: 'POST',   
    url: site_url+'ajax/register',
    data: formData,   
    dataType: 'json',
success: function(response) {
  showSpinner('#submit_register', 'Crear cuenta', false)
  if(response.status === 'success') {
    // enviamos el correo de confirmación
    //sendRegisterMail(email)
     swalOptions = {
      icon: 'success',
      title: '¡Por favor revisa tu correo electrónico!',
      html: '<p>Hemos enviado un correo electrónico a <strong>'+email+'</strong> con el enlace para validar tu cuenta. Si no lo encuentras revisa en tu carpeta de spam.</p>',
    };
    swalAlert(swalOptions).then((result) => {
      if (result.isConfirmed) {
        window.location.href = site_url + 'login/';// Al login
      }  
    }); 
    return;
  }
  if (response.code === 'user_exists' || isExistingUserResponse(response)) {
    swalOptions = {
      icon: 'warning',
      title: '¡Este usuario ya existe!',
      html: '<p>El correo electrónico está registrado. Si olvidaste tu contraseña, puedes recuperarla haciendo <a href="'+ toLostpassword +'">click aquí</a>.</p>',
    };
    swalAlert(swalOptions);
    return;
  }

  alertOptions.title = successError(response.message || '', response.code || '').title || 'Ha ocurrido un error.';
  alertToast(alertOptions);
    },
    error: function(xhr, status, error) { 
      showSpinner('#submit_register', 'Crear cuenta', false)
      alertOptions.title=ajaxError(status, error).title 
      alertToast(alertOptions);
    }
  });
  
}

function isExistingUserResponse(response) {
  if (response.code === 'user_exists') {
    return true;
  }

  const message = String(response.message || '');
  return message.indexOf('duplicate entry') !== -1
    || message.indexOf('duplicado') !== -1
    || message.indexOf('ya existe') !== -1
    || message.indexOf('ya está registrado') !== -1
    || message.indexOf('ya esta registrado') !== -1;
}
  

 
$('#register_password').on('keyup focusout ', function() {
  updateMeterPassword($("#register_password").val(),$("#register_email").val())
  var extensionValida = passwordValidate($("#register_password").val(),$("#register_email").val());
  if (!extensionValida ) {
    this.setCustomValidity('Por favor, contraseña');
    $(this).parent().next('.invalid-feedback').addClass('d-none');
   
  } else {
    this.setCustomValidity('');
    $(this).parent().next('.invalid-feedback').removeClass('d-none');

  }

  if ( $(this).val()=='' ) {
    $(this).parent().next('.invalid-feedback').removeClass('d-none');
  }
})

showPassword('.pswd', '.showPassword')

})(jQuery);
