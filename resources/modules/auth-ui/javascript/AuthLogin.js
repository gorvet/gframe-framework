// AuthLogin.js
// JS gestionar el login, la validación de cuenta 
// y recuperacion de contraseña

!(function($) {
  "use strict";

// Inicializamos valores

const urlParams = new URLSearchParams(window.location.search);
const validateToken = urlParams.get('v');
const redirectToken = urlParams.get('rd');

if (validateToken) {
  validateAcount(validateToken)
}

//Login
$('#submit_login').on('click', function(event) {
  const form = $('#login');

  if (form[0].checkValidity()) {
    login();
  } 
  else {
    event.preventDefault();
    // Mostrar mensajes de validación
    const datas = {
      "login_email": {
        "valueMissing": "Por favor, ingresa tu correo electrónico.",
        "patternMismatch": "Ingresa un correo electrónico válido."
      },
      "login_password": {
        "valueMissing": "Por favor, no olvides tu contraseña."
      }  
    };
    validationFeedback(form, datas)
  }
  form.addClass('was-validated');
});

 
function login(){
  let alertOptions, swalOptions;
  alertOptions = swalOptions = {icon: 'error', title: ''};
  const formData = $('#login').serialize() + (redirectToken !== null ? '&rd=' + encodeURIComponent(redirectToken) : ''); // Obtener los datos del formulario
 
  $.ajax({
    type: 'POST',
    url: site_url+'ajax/login',
    data: formData,
    dataType: 'json',
    success: function(response) {
      if(response.status === 'success' || response.code === 'already_logged') {
        localStorage.removeItem('session_closed');
        const target = response.redirect
          ? site_url + response.redirect
          : site_url + 'login/';
        window.location.href = target;
      } 
      else {
        const authSwal = getLoginErrorSwalOptions(response);
        if (authSwal) {
          swalAlert(authSwal);
        } else {
          alertOptions.title = successError(response.message, response.code).title 
          alertToast(alertOptions);
        }
      }
    },
    error: function(xhr, status, error) {
      if (xhr.responseJSON && xhr.responseJSON.code === 'already_logged') {
        window.location.href = site_url + 'login/';
        return;
      }
      alertOptions.title = ajaxError(status, error).title
      alertToast(alertOptions);
    }
  });
}

//Reenviar el correo de verificación
$(document).on('click', '#verifyAcount', function(event) {
  event.preventDefault();
  const form = $('#login');
    if (form[0].checkValidity()) {
      verifyAcount();
    } 
    else {
      event.preventDefault();
      event.stopPropagation();
    }
    form.addClass('was-validated');
});

function verifyAcount(){
  let alertOptions, swalOptions;
  alertOptions = swalOptions = {icon: 'error', title: ''};
  const formData = $('#login').serialize(); // Obtener los datos del formulario
  const email = $('#login_email').val();
  
  showSpinner(true)
 
  $.ajax({
    type: 'POST',
    url: site_url+'ajax/verifyacount',
    data: formData,
    dataType: 'json',
    success: function(response) {
       showSpinner(false)
      if(response.status == 'success') {
        const swalOptions = {
          icon: 'success',
          title: '¡Por favor revisa tu correo electrónico!',
          html: '<p>Hemos enviado un correo electrónico a <strong>'+email+'</strong> con el enlace para validar tu cuenta. Si no lo encuentras revisa en tu carpeta de spam.</p>',
        };

        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            window.location.href = site_url+'login/'
          }  
        }); 
      } 
      else {
        if (response.code==='invalid_user') {
          swalOptions.title = 'Usuario incorrecto.';
          swalAlert(swalOptions)
        }
        else if (response.code==='suspended_account') {
          swalOptions.title = 'Su cuenta ha sido suspendida.';
          swalAlert(swalOptions)
        }  
        else{
          alertOptions.title = successError(response.message, response.code).title 
          alertToast(alertOptions);
        }
      }
    },
    error: function(xhr, status, error) {
      showSpinner(false)
      alertOptions.title = ajaxError(status, error).title
      alertToast(alertOptions);
    }
  });
}

function getLoginErrorSwalOptions(response) {
  if (response.code === 'invalid_user') {
    return {
      icon: 'error',
      title: 'Usuario o contraseña incorrecta.'
    };
  }

  if (response.code === 'suspended_account') {
    return {
      icon: 'error',
      title: 'Su cuenta ha sido suspendida.'
    };
  }

  if (response.code === 'unverified_account') {
    return {
      icon: 'error',
      title: 'Cuenta sin verificar',
      html: '<p>No has verificado tu cuenta. Por favor, revisa tu correo para completar el proceso de verificación. Si no lo encuentras puedes <a id="verifyAcount" href="#">solicitar uno nuevo.</a></p>'
    };
  }

  return null;
}

/*validar la cuenta*/
function validateAcount(validateToken){
  const formData = $('#login').serialize(); // Obtener los datos del formulario
  let alertOptions, swalOptions;
  alertOptions = swalOptions = {icon: 'error', title: ''};


  $.ajax({
    type: 'POST',
    url: site_url+'ajax/validateacount',
    data: formData+'&vtoken='+validateToken,
    
    dataType: 'json',
    success: function(response) {
      //showLoader(false)
      if(response.status == 'success') {
        swalOptions = {
          icon: 'success',
          title: 'Su cuenta ha sido verificada',
          confirmButtonText: 'Acceder',
        };

        swalAlert(swalOptions).then((result) => {
          if (result.isConfirmed) {
            window.location.href = site_url+'login/';// Al login
          }  
        }); 
      } 
      else {
        if (response.code==='invalid_token') {
          swalOptions = {
            icon: 'error',
            title: 'Token no válido',
          }
          swalAlert(swalOptions).then((result) => {
            if (result.isConfirmed) {
              window.location.href = site_url+'login/';// Al login
            }  
          }); 
        }
        else if (response.code==='suspended_account') {
          swalOptions = {
            icon: 'error',
            title: 'Su cuenta ha sido suspendida.',
          }
          swalAlert(swalOptions).then((result) => {
            if (result.isConfirmed) {
             window.location.href = site_url+'login/';// Al login
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
      //showLoader(false)
      alertOptions.title = ajaxError(status, error).title
      alertToast(alertOptions);
    }
  });
}

showPassword('.pswd', '.showPassword')

})(jQuery);
