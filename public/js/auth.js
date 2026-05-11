(() => {
  function setupPasswordToggle(btn) {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      const icon = btn.querySelector('i.fa-eye, i.fa-eye-slash');
      const label = btn.getAttribute('aria-label');
      if (icon) {
        icon.classList.replace(isPassword ? 'fa-eye' : 'fa-eye-slash', isPassword ? 'fa-eye-slash' : 'fa-eye');
      }
      if (label) {
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
      }
    });
  }

  document.querySelectorAll('.password-toggle[data-target]').forEach(setupPasswordToggle);

  const form = document.querySelector('#registerForm form');
  if (!form) return;

  const password = document.getElementById('password');
  const passwordConfirmation = document.getElementById('password_confirmation');
  const passwordLengthError = document.getElementById('password-length-error');
  const passwordMatchError = document.getElementById('password-match-error');

  function show(el, message) {
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
  }

  function hide(el) {
    if (!el) return;
    el.textContent = '';
    el.classList.add('d-none');
  }

  function validateClient() {
    let ok = true;

    const pw = password?.value ?? '';
    const conf = passwordConfirmation?.value ?? '';

    // 1) Minimum length hint (server validation will be the source of truth).
    if (pw && pw.length < 8) {
      show(passwordLengthError, 'Minimum 8 characters');
      ok = false;
    } else {
      hide(passwordLengthError);
    }

    // 2) Confirmation match (only validate when user types confirmation).
    if (conf) {
      if (pw !== conf) {
        show(passwordMatchError, 'Passwords do not match');
        ok = false;
      } else {
        hide(passwordMatchError);
      }
    } else {
      hide(passwordMatchError);
    }

    return ok;
  }

  password?.addEventListener('input', () => {
    validateClient();
  });
  passwordConfirmation?.addEventListener('input', () => {
    validateClient();
  });

  form.addEventListener('submit', (e) => {
    // Prevent obvious client-side mismatch; server will still validate.
    if (!validateClient()) e.preventDefault();
  });
})();

