const copyTicketButton = document.getElementById('receipt-copy');
const ticketNumber = document.getElementById('receipt-ticket-number');
const copyTicketHelp = document.getElementById('receipt-copy-help');

copyTicketButton.hidden = false;
copyTicketButton.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(ticketNumber.textContent);
    copyTicketHelp.textContent = 'Ticket number copied.';
  } catch {
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(ticketNumber);
    selection.removeAllRanges();
    selection.addRange(range);
    copyTicketHelp.textContent = document.execCommand('copy')
      ? 'Ticket number copied.'
      : 'Ticket number selected. Copy it manually.';
  }
});
