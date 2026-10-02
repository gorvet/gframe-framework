// alertToast.js

function alertToast(toastOptions) {
// Valores por defecto
const defaultOptions = {
	position: 'bottom-end',  
	timer: 5000,
	icon:'success',
	title:'Nada que mostrar' 
// Otros valores por defecto aquí
};
// Fusionar los valores por defecto con los proporcionados
const options = { ...defaultOptions, ...toastOptions };
 
return generaBsToast(options.title, options.icon, options.timer)

/*iziToast.settings({
timeout: options.timer,
message: options.title, 
transitionIn: 'flipInX',
transitionOut: 'flipOutX',
maxWidth:'350px',

});

/*if (options.icon=='success') {iziToast.success()}
else if (options.icon=='info') {iziToast.info()}
else if (options.icon=='warning') {iziToast.warning()}
else if (options.icon=='error') {iziToast.error()}

/*const Toast = Swal.mixin({
	toast: true,
	position: options.position,
	showConfirmButton: false,
	timer: options.timer,
	didOpen: (toast) => {
		toast.onmouseenter = Swal.stopTimer;
		toast.onmouseleave = Swal.resumeTimer;
	}
});*/

/*Toast.fire({
	icon: options.icon,
	title: options.title
});*/
;

}

function generaBsToast(msg, type, timer = 5000) {

  var kind = String(type || 'info').toLowerCase();
  if (kind !== 'success' && kind !== 'error' && kind !== 'warning' && kind !== 'info') {
    kind = 'info';
  }
  var icon = 'alert';
  if (kind == 'error') {
    icon = 'close';
  }
  if (kind == 'success') {
    icon = 'check';
  }

  var toast = `
  <div class="gtoast alert-` + kind + `" role="alert" aria-live="assertive" aria-atomic="true" >
  	<div class="d-flex">
  		<div class="gtoast-body">
  			<i class="gicon-`+ icon +`"></i>
  			<div><p>`;
  			toast += `
      </p></div>
  		</div>
  	</div>
  </div>`;

  var $toast = $(toast);
  $toast.find('p').text(String(msg == null ? '' : msg));
  var duration = Number(timer);
  if (!Number.isFinite(duration) || duration < 0) duration = 5000;
  $toast.css('--gframe-toast-duration', duration + 'ms');
  if (duration === 0) $toast.addClass('gtoast-persistent');
  $('#toastBox').append($toast);

  if (duration > 0) setTimeout(()=>{
		$toast.remove()
	},duration)
  return $toast;
  

}

function swalAlert(swalOptions) {

	const defaultOptions = {
		confirmButtonText: "Cerrar",
		allowOutsideClick: false,
		allowEscapeKey:false,
		buttonsStyling: false,
		reverseButtons: true,
		customClass: {
			confirmButton: "btn btn-primary",
			denyButton: "btn btn-tercero",
			cancelButton: "btn btn-secondary",

		},
	};
  const options = mergeDeep(defaultOptions, swalOptions);
  return Swal.fire(options);
}


function mergeDeep(target, source) {
  const result = Object.assign({}, target || {});
  for (const key of Object.keys(source || {})) {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') continue;
    const value = source[key];
    if (value && Object.getPrototypeOf(value) === Object.prototype) {
      result[key] = mergeDeep(result[key], value);
    } else {
      result[key] = Array.isArray(value) ? value.slice() : value;
    }
  }
  return result;
}

function showSpinner(id='', text='', show = true, ) {

	 if (id==false) {
		show=false
			}
		if (id==true) {
		show=true
			}
 if ($(id).length > 0 && (id!=true && id!=false)) {
 	$(id).prop('disabled', show).html(show ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + text : text);
  }
  else {
  	offTheButton(show)// si se recive solo false no entra por show sino por id
  }
}




function offTheButton(show) {
	if (show==false) {
		Swal.close();
	}
	else{
		Swal.fire({
			background: "transparent",
			backdrop:"transparent",
			allowOutsideClick: false,
			showConfirmButton: false,
			showCancelButton: false,
			customClass: {
				container: 'no-shadow',
				popup: 'swal-loading-popup',
				loader: 'newSize'
			},
			willOpen: () => {
				Swal.showLoading();
			},
		});
	}

}


 /*inicalizar el tootTips -- esto debe moverse para otro lado*/ 
if (typeof tooltipTriggerList === 'undefined') {
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}
