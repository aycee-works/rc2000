document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
  button.addEventListener('click', function () {
    var input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
});

(function () {
  var layout = document.querySelector('[data-auth-layout]');
  if (!layout) return;

  function setMode(mode, updateHistory) {
    if (mode !== 'login' && mode !== 'register') return;
    layout.dataset.mode = mode;
    layout.classList.toggle('is-register-mode', mode === 'register');
    document.title = (mode === 'register' ? 'Create an account' : 'Sign in') + ' | Reading Club 2000';

    layout.querySelectorAll('[data-auth-panel]').forEach(function (panel) {
      var active = panel.dataset.authPanel === mode;
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
      panel.inert = !active;
    });

    if (updateHistory) {
      var nextUrl = mode === 'register' ? layout.dataset.registerUrl : layout.dataset.loginUrl;
      window.history.pushState({ authMode: mode }, '', nextUrl);
    }

    var activePanel = layout.querySelector('[data-auth-panel="' + mode + '"]');
    var firstInput = activePanel && activePanel.querySelector('input:not([type="hidden"])');
    if (firstInput) window.setTimeout(function () { firstInput.focus({ preventScroll: true }); }, 360);
  }

  layout.querySelectorAll('[data-auth-mode]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      setMode(link.dataset.authMode, true);
    });
  });

  window.addEventListener('popstate', function () {
    setMode(window.location.pathname.endsWith('/register.php') ? 'register' : 'login', false);
  });
})();
