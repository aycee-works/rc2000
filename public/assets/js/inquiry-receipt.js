const copyTicketButton = document.getElementById('receipt-copy');
const ticketNumber = document.getElementById('receipt-ticket-number');
const copyTicketHelp = document.getElementById('receipt-copy-help');
const copyLabel = ticketNumber.dataset.copyLabel || 'Ticket number';

copyTicketButton.hidden = false;
copyTicketButton.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(ticketNumber.textContent);
    copyTicketHelp.textContent = `${copyLabel} copied.`;
  } catch {
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(ticketNumber);
    selection.removeAllRanges();
    selection.addRange(range);
    copyTicketHelp.textContent = document.execCommand('copy')
      ? `${copyLabel} copied.`
      : `${copyLabel} selected. Copy it manually.`;
  }
});
