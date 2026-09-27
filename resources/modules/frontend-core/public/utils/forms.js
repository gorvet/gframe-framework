// Form validation feedback helpers.

function validationFeedback(form, datas) {
  var invalidItems = form.find(':invalid');
  invalidItems.each(function() {
    var fieldName = this.id;
    var errorMessage = '';

    if (datas.hasOwnProperty(fieldName)) {
      var validations = datas[fieldName];
      if (this.validity.valueMissing && validations.hasOwnProperty('valueMissing')) {
        errorMessage = validations.valueMissing;
      } else if (this.validity.tooShort && validations.hasOwnProperty('tooShort')) {
        errorMessage = validations.tooShort;
      } else if (this.validity.patternMismatch && validations.hasOwnProperty('patternMismatch')) {
        errorMessage = validations.patternMismatch;
      } else if (this.validity.typeMismatch && validations.hasOwnProperty('typeMismatch')) {
        errorMessage = validations.typeMismatch;
      } else if (this.validity.rangeUnderflow && validations.hasOwnProperty('rangeUnderflow')) {
        errorMessage = validations.rangeUnderflow;
      } else if (this.validity.rangeOverflow && validations.hasOwnProperty('rangeOverflow')) {
        errorMessage = validations.rangeOverflow;
      } else if (this.validity.stepMismatch && validations.hasOwnProperty('stepMismatch')) {
        errorMessage = validations.stepMismatch;
      } else if (this.validity.badInput && validations.hasOwnProperty('badInput')) {
        errorMessage = validations.badInput;
      } else if (this.validity.customError && validations.hasOwnProperty('customError')) {
        errorMessage = validations.customError;
      }
    }

    if (errorMessage !== '') {
      $('.validation_' + fieldName).html(errorMessage);
    } else {
      $('.validation_' + fieldName).removeClass('d-block');
    }
  });
}
