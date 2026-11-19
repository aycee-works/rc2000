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

  document.querySelectorAll('[data-quiz-steps]').forEach((form) => {
    const steps = [...form.querySelectorAll('[data-quiz-step]')];
    const progress = form.querySelector('[data-quiz-progress]');
    let current = 0;
    const show = (index) => {
      current = Math.max(0, Math.min(index, steps.length - 1));
      steps.forEach((step, i) => { step.hidden = i !== current; step.classList.toggle('is-current', i === current); });
      if (progress) progress.style.width = ((current + 1) / steps.length * 100) + '%';
    };
    form.querySelectorAll('[data-quiz-next]').forEach((button) => button.addEventListener('click', () => {
      const checked = steps[current].querySelector('input[type="radio"]:checked');
      if (!checked) { steps[current].querySelector('input[type="radio"]')?.focus(); return; }
      show(current + 1);
    }));
    form.querySelectorAll('[data-quiz-prev]').forEach((button) => button.addEventListener('click', () => show(current - 1)));
    form.addEventListener('submit', (event) => {
      if (!event.submitter?.hasAttribute('data-quiz-submit-score')) return;
      const unanswered = steps.findIndex((step) => !step.querySelector('input[type="radio"]:checked'));
      if (unanswered === -1) return;
      event.preventDefault();
      show(unanswered);
      steps[unanswered].querySelector('input[type="radio"]')?.focus();
    });
    show(0);
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
