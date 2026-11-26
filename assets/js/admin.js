/* ONE Office – admin scripts */
(function () {
  'use strict';
  var body = document.body;

  // Mobile sidebar
  var toggle = document.getElementById('sideToggle');
  var backdrop = document.getElementById('backdrop');
  if (toggle) toggle.addEventListener('click', function () { body.classList.toggle('side-open'); });
  if (backdrop) backdrop.addEventListener('click', function () { body.classList.remove('side-open'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') body.classList.remove('side-open'); });

  // Confirm before destructive actions
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Live icon preview
  var iconInput = document.querySelector('[data-icon-input]');
  var iconPreview = document.querySelector('[data-icon-preview]');
  if (iconInput && iconPreview) {
    iconInput.addEventListener('input', function () {
      var v = iconInput.value.trim();
      if (/^bi-[a-z0-9-]+$/.test(v)) iconPreview.className = 'bi ' + v;
    });
  }

  // Image preview before upload
  document.querySelectorAll('input[type=file][accept^="image"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { alert('Image is too large (max 2 MB).'); input.value = ''; return; }
      var wrap = input.closest('.img-field');
      var img = wrap.querySelector('img.thumb');
      if (!img) { img = document.createElement('img'); img.className = 'thumb lg'; img.alt = ''; wrap.insertBefore(img, wrap.firstChild); }
      img.src = URL.createObjectURL(file);
    });
  });

  // Print buttons
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  // Whole table row opens the record (links/buttons inside keep working)
  document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a, button, form, input, select, textarea')) return;
      window.location.href = tr.getAttribute('data-href');
    });
  });

  // Auto-hide success alerts
  setTimeout(function () {
    document.querySelectorAll('.content > .alert-success').forEach(function (a) {
      a.style.transition = 'opacity .5s'; a.style.opacity = '0';
      setTimeout(function () { a.remove(); }, 500);
    });
  }, 4000);
})();
