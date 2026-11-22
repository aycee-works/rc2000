const portraitButton = document.querySelector('[data-portrait-open]');
const portraitDialog = document.querySelector('[data-portrait-dialog]');

portraitButton?.addEventListener('click', () => portraitDialog.showModal());
portraitDialog?.addEventListener('close', () => portraitButton.focus());

const bioToggle = document.querySelector('[data-founder-bio-toggle]');
const bio = document.getElementById(bioToggle.getAttribute('aria-controls'));

bio.hidden = true;
bioToggle.hidden = false;
bioToggle.addEventListener('click', () => {
  const isExpanding = bio.hidden;
  bio.hidden = !isExpanding;
  bioToggle.setAttribute('aria-expanded', String(isExpanding));
  bioToggle.textContent = isExpanding ? 'See Less' : 'See More';

  if (!isExpanding) bioToggle.scrollIntoView({ block: 'nearest' });
});

const phoneButton = document.querySelector('[data-copy-phone]');
const copyStatus = document.querySelector('[data-copy-status]');

phoneButton.addEventListener('click', async () => {
  const phoneNumber = phoneButton.textContent.trim();
  copyStatus.textContent = '';

  try {
    await navigator.clipboard.writeText(phoneNumber);
    copyStatus.textContent = 'Phone number copied.';
    return;
  } catch {
    // Some deployments serve the page over HTTP, where Clipboard API access is restricted.
  }

  const selection = window.getSelection();
  const range = document.createRange();
  range.selectNodeContents(phoneButton);
  selection.removeAllRanges();
  selection.addRange(range);

  let copied = false;
  try {
    copied = document.execCommand('copy');
  } catch {
    // Leave the number selected for manual copying.
  }
  if (copied) selection.removeAllRanges();
  copyStatus.textContent = copied ? 'Phone number copied.' : 'Phone number selected. Copy it manually.';
});
