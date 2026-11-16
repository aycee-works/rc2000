(() => {
  const toggle = document.querySelector('[data-admin-theme-toggle]');
  if (!toggle) return;
  const icon = toggle.querySelector('[data-admin-theme-icon]');
  const label = toggle.querySelector('[data-admin-theme-label]');
  const toolbar = toggle.closest('.portal-toolbar');

  // Keep the global toolbar on its usual row, but place it beside the action
  // button on the admin account list where the top-right position overlaps.
  const pageActions = document.querySelector('.sa-page-head .sa-page-actions');
  if (toolbar && pageActions) {
    pageActions.insertBefore(toolbar, pageActions.firstChild);
    toolbar.classList.add('portal-toolbar-inline');
  }

  const applyTheme = (theme) => {
    const dark = theme === 'dark';
    document.documentElement.dataset.adminTheme = dark ? 'dark' : 'light';
    toggle.setAttribute('aria-pressed', String(dark));
    toggle.setAttribute('aria-label', 'Switch to ' + (dark ? 'light' : 'dark') + ' mode');
    icon.textContent = dark ? '☀' : '☾';
    label.textContent = dark ? 'Light mode' : 'Dark mode';
    try { localStorage.setItem('rc2000-admin-theme', theme); } catch (error) {}
  };

  let saved = null;
  try { saved = localStorage.getItem('rc2000-admin-theme'); } catch (error) {}
  applyTheme(saved === 'dark' ? 'dark' : 'light');
  toggle.addEventListener('click', () => {
    applyTheme(document.documentElement.dataset.adminTheme === 'dark' ? 'light' : 'dark');
  });
})();
