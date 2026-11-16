(() => {
  const themeToggle = document.querySelector('[data-theme-toggle]');
  if (themeToggle) {
    const themeIcon = themeToggle.querySelector('[data-theme-icon]');
    const themeLabel = themeToggle.querySelector('[data-theme-label]');
    const updateThemeButton = () => {
      const dark = document.documentElement.dataset.theme === 'dark';
      themeToggle.setAttribute('aria-pressed', String(dark));
      themeToggle.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} mode`);
      themeIcon.textContent = dark ? '☀' : '☾';
      themeLabel.textContent = dark ? 'Light mode' : 'Dark mode';
    };
    updateThemeButton();
    themeToggle.addEventListener('click', () => {
      const dark = document.documentElement.dataset.theme !== 'dark';
      document.documentElement.dataset.theme = dark ? 'dark' : 'light';
      try { localStorage.setItem('rc2000-theme', dark ? 'dark' : 'light'); } catch (e) {}
      updateThemeButton();
    });
  }

  const revealItems = document.querySelectorAll('[data-reveal]');
  if (revealItems.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('reveal-ready');
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.16 });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  document.querySelectorAll('[data-carousel]').forEach((root) => {
    const track = root.querySelector('.carousel-track');
    if (!track) return;
    const step = () => Math.max(220, track.clientWidth * 0.8);
    root.querySelector('[data-prev]')?.addEventListener('click', () => {
      track.scrollBy({ left: -step(), behavior: 'smooth' });
    });
    root.querySelector('[data-next]')?.addEventListener('click', () => {
      track.scrollBy({ left: step(), behavior: 'smooth' });
    });
  });

  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const buttons = [...tabs.querySelectorAll('[data-tab-target]')];
    const panels = [...tabs.parentElement.querySelectorAll('[data-tab-panel]')];
    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        const id = button.getAttribute('data-tab-target');
        buttons.forEach((item) => item.classList.toggle('is-active', item === button));
        panels.forEach((panel) => {
          const match = panel.getAttribute('data-tab-panel') === id;
          panel.hidden = !match;
          panel.classList.toggle('is-active', match);
        });
      });
    });
  });
    const feedbackDialog = document.getElementById('feedback-dialog');
  if (feedbackDialog) {
    const feedbackForm = document.getElementById('feedback-form');
    const feedbackStatus = document.getElementById('feedback-status');
    const showFeedbackStatus = (text, type) => {
      feedbackStatus.textContent = text;
      feedbackStatus.className = 'feedback-status is-' + type;
      feedbackStatus.hidden = false;
    };

    document.querySelectorAll('[data-feedback-open]').forEach((button) => {
      button.addEventListener('click', () => {
        feedbackStatus.hidden = true;
        feedbackDialog.showModal();
      });
    });
    feedbackDialog.querySelector('[data-feedback-close]').addEventListener('click', () => feedbackDialog.close());
    feedbackDialog.addEventListener('click', (event) => {
      if (event.target === feedbackDialog) feedbackDialog.close();
    });

    feedbackForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submit = feedbackForm.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const response = await fetch(feedbackForm.action, {
          method: 'POST',
          body: new FormData(feedbackForm),
          headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        showFeedbackStatus(data.message, data.success ? 'success' : 'error');
        if (data.success) feedbackForm.reset();
      } catch (error) {
        showFeedbackStatus('Could not reach the server. Please try again.', 'error');
      }
      submit.disabled = false;
    });
  }
})();
