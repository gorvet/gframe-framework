// passwordUtils.js
// Utility helpers for password generation, scoring and UI toggle.

const PASSWORD_UTILS_LOWER = "abcdefghijklmnopqrstuvwxyz";
const PASSWORD_UTILS_UPPER = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
const PASSWORD_UTILS_DIGITS = "0123456789";
const PASSWORD_UTILS_SYMBOLS = "!@#$%^&*()_+~`|}{[]:;?><,./-=";
const PASSWORD_UTILS_ALL =
  PASSWORD_UTILS_LOWER +
  PASSWORD_UTILS_UPPER +
  PASSWORD_UTILS_DIGITS +
  PASSWORD_UTILS_SYMBOLS;

function passwordUtilsRandomInt(maxExclusive) {
  const range = maxExclusive >>> 0;
  if (!range || !window.crypto || typeof window.crypto.getRandomValues !== "function") {
    return null;
  }
  const totalValues = 0x100000000;
  const limit = Math.floor(totalValues / range) * range;
  const values = new Uint32Array(1);
  do {
    window.crypto.getRandomValues(values);
  } while (values[0] >= limit);
  return values[0] % range;
}

function passwordUtilsPick(chars) {
  const index = passwordUtilsRandomInt(chars.length);
  return index === null ? null : chars.charAt(index);
}

function passwordUtilsShuffleChars(charsArray) {
  for (let i = charsArray.length - 1; i > 0; i--) {
    const j = passwordUtilsRandomInt(i + 1);
    if (j === null) return null;
    const tmp = charsArray[i];
    charsArray[i] = charsArray[j];
    charsArray[j] = tmp;
  }
  return charsArray;
}

// Generate a random password.
// Default length: 18 and always includes lower/upper/digit/symbol.
function generatePassword(length = 18) {
  if (typeof window === "undefined" || !window.crypto || typeof window.crypto.getRandomValues !== "function") {
    return null;
  }
  let finalLength = parseInt(length, 10);
  if (Number.isNaN(finalLength) || finalLength < 8 || finalLength > 72) {
    finalLength = 18;
  }

  const chars = [
    passwordUtilsPick(PASSWORD_UTILS_LOWER),
    passwordUtilsPick(PASSWORD_UTILS_UPPER),
    passwordUtilsPick(PASSWORD_UTILS_DIGITS),
    passwordUtilsPick(PASSWORD_UTILS_SYMBOLS),
  ];

  while (chars.length < finalLength) {
    chars.push(passwordUtilsPick(PASSWORD_UTILS_ALL));
  }

  const shuffled = passwordUtilsShuffleChars(chars);
  return shuffled === null ? null : shuffled.join("");
}

// Evaluate password strength.
// Kept intentionally strict to avoid weak passwords being accepted.
function evaluatePassword(password, email) {
  let scores = 0;

  if (password.length >= 8) {
    scores += 1;
    if (password.length >= 12) {
      scores += 1;
    }
    if (password.length >= 16) {
      scores += 1;
    }
  } else {
    scores += -3;
  }

  if (/[a-z]/.test(password)) {
    scores += 1;
  } else {
    scores += -1;
  }

  if (/[A-Z]/.test(password)) {
    scores += 1;
  } else {
    scores += -100;
  }

  if (/[0-9]/.test(password)) {
    scores += 1;
  } else {
    scores += -100;
  }

  if (/[^a-zA-Z0-9]/.test(password)) {
    scores += 1;
  } else {
    scores += -100;
  }

  const normalizedEmail = String(email || "").trim().toLowerCase();
  if (normalizedEmail !== "") {
    const parts = normalizedEmail.split("@");
    const username = parts[0] || "";
    const domainPart = parts[1] || "";
    const domainRoot = domainPart.split(".")[0] || "";

    const lowerPassword = String(password).toLowerCase();
    if (
      (username !== "" && lowerPassword.includes(username)) ||
      (domainRoot !== "" && lowerPassword.includes(domainRoot))
    ) {
      scores += -100;
    } else {
      scores += 1;
    }
  } else {
    scores += 1;
  }

  return scores;
}

// Update password strength meter.
function updateMeterPassword(password, email) {
  if (!password) {
    $(".passwordMeter").addClass("d-none");
    return;
  }

  const scores = evaluatePassword(password, email);
  $(".passwordMeter").removeClass("d-none");
  $(".passwordMeter").removeClass("weak medium strong");

  if (scores <= 2) {
    $(".passwordMeter").addClass("weak").text("Débil");
  } else if (scores <= 6) {
    $(".passwordMeter").addClass("medium").text("Media");
  } else {
    $(".passwordMeter").addClass("strong").text("Fuerte");
  }
}

function passwordValidate(password, email) {
  // La regla obligatoria coincide con GFrame\Auth\PasswordPolicy.
  // El medidor es orientativo; el servidor siempre valida de nuevo.
  if (typeof password !== "string" || typeof TextEncoder === "undefined") return false;
  const bytes = new TextEncoder().encode(password).length;
  return bytes >= 8 && bytes <= 72;
}

// Show/hide password input.
// It supports multiple input groups using delegated click handler.
function showPassword(inputSelector, buttonSelector) {
  $(document)
    .off("click.passwordUtils", buttonSelector)
    .on("click.passwordUtils", buttonSelector, function () {
      const $button = $(this);
      const $group = $button.closest(".input-group");
      const $input = $group.length
        ? $group.find(inputSelector).first()
        : $(inputSelector).first();

      if (!$input.length) {
        return;
      }

      const type = $input.attr("type");
      if (type === "password") {
        $input.attr("type", "text");
        $button.text("Ocultar");
      } else {
        $input.attr("type", "password");
        $button.text("Mostrar");
      }
    });
}

