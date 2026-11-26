/* ONE OFFICE – static demo (GitHub Pages): forms cannot be submitted */
(function () {
  'use strict';
  var css = '.demo-banner{background:#f47b20;color:#fff;text-align:center;font-weight:600;font-size:.9rem;padding:.55rem 1rem}' +
    '.demo-banner i{margin-right:.35rem}' +
    '.demo-msg{margin:0 0 .5rem;padding:.85rem 1rem;border-radius:12px;background:#ffedd5;color:#9a3412;font-weight:600;font-size:.95rem}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var text = 'This is a demo preview. Submitting forms and tracking requests works in the full PHP version of ONE OFFICE.';

  document.addEventListener('submit', function (e) {
    var form = e.target;
    e.preventDefault();
    e.stopImmediatePropagation();
    var msg = form.querySelector('.demo-msg');
    if (!msg) {
      msg = document.createElement('p');
      msg.className = 'demo-msg';
      msg.setAttribute('role', 'status');
      msg.textContent = text;
      form.insertBefore(msg, form.firstChild);
    }
    var btn = form.querySelector('button[type=submit]');
    if (btn) { btn.disabled = false; }
    msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, true);
})();
