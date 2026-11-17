document.querySelector('.menu-toggle')?.addEventListener('click', function () {
  const open = document.querySelector('.sidebar').classList.toggle('open');
  this.setAttribute('aria-expanded', String(open));
});
document.querySelector('.show-password')?.addEventListener('click', function () {
  const field = document.getElementById('password');
  const show = field.type === 'password';
  field.type = show ? 'text' : 'password';
  this.textContent = show ? 'Hide' : 'Show';
  this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
