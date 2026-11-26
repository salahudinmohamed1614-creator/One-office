/* ONE OFFICE – public site scripts */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Mobile menu */
  var menuBtn = $('#menuBtn'), nav = $('#navLinks');
  function closeMenu() {
    nav.classList.remove('open');
    menuBtn.setAttribute('aria-expanded', 'false');
    menuBtn.innerHTML = '<i class="bi bi-list"></i>';
  }
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', String(open));
      menuBtn.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
    });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
  }

  /* Theme toggle */
  var themeBtn = $('#themeToggle');
  function currentTheme() {
    var t = document.documentElement.dataset.theme;
    if (t) return t;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function paintThemeIcon() {
    if (themeBtn) themeBtn.innerHTML = currentTheme() === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
  }
  if (themeBtn) {
    paintThemeIcon();
    themeBtn.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      try { localStorage.setItem('oo-theme', next); } catch (e) {}
      paintThemeIcon();
    });
  }

  /* Sticky nav shadow + back-to-top */
  var header = $('.nav'), toTop = $('#toTop');
  function onScroll() {
    var y = window.scrollY;
    if (header) header.classList.toggle('scrolled', y > 10);
    if (toTop) toTop.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Reveal on scroll + counters */
  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0, start = null, dur = 1400;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('visible');
        $$('[data-count]', en.target).forEach(animateCount);
        io.unobserve(en.target);
      });
    }, { threshold: 0.12 });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    $$('.reveal').forEach(function (el) { el.classList.add('visible'); });
  }

  /* Testimonial slider */
  var slider = $('#slider');
  if (slider) {
    $$('[data-slide]').forEach(function (b) {
      b.addEventListener('click', function () {
        var card = slider.firstElementChild;
        var w = card ? card.getBoundingClientRect().width + 24 : 300;
        var dir = parseInt(b.getAttribute('data-slide'), 10);
        var max = slider.scrollWidth - slider.clientWidth;
        if (dir > 0 && slider.scrollLeft >= max - 5) slider.scrollTo({ left: 0, behavior: 'smooth' });
        else slider.scrollBy({ left: dir * w, behavior: 'smooth' });
      });
    });
  }

  /* Services page: live search */
  var search = $('#svcSearch');
  if (search) {
    var empty = $('#svcEmpty');
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase(), shown = 0;
      $$('[data-cat]').forEach(function (cat) {
        var inCat = 0;
        $$('[data-svc]', cat).forEach(function (s) {
          var hit = !q || s.getAttribute('data-text').indexOf(q) !== -1;
          s.hidden = !hit;
          if (hit) { inCat++; s.classList.add('visible'); }
        });
        cat.hidden = inCat === 0;
        shown += inCat;
      });
      if (empty) empty.hidden = shown !== 0;
    });
  }

  /* Copy tracking number */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var src = $(b.getAttribute('data-copy'));
      if (!src) return;
      var text = src.textContent.trim(), label = $('span', b);
      var done = function () { if (label) { label.textContent = 'Copied ✓'; setTimeout(function () { label.textContent = 'Copy'; }, 2000); } };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy:', text); });
      } else {
        window.prompt('Copy:', text);
      }
    });
  });

  /* Client-side required-field check for normal (non-AJAX) forms */
  $$('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var bad = false;
      $$('[required]', form).forEach(function (f) {
        var invalid = f.type === 'checkbox' ? !f.checked : !f.checkValidity();
        f.classList.toggle('invalid', invalid);
        if (invalid) bad = true;
      });
      if (bad) {
        e.preventDefault();
        var first = $('.invalid', form);
        if (first) first.focus();
        return;
      }
      var btn = $('button[type=submit]', form);
      if (btn) { btn.disabled = true; var l = $('span', btn); if (l) l.textContent = 'Sending...'; }
    });
    $$('input, textarea, select', form).forEach(function (f) {
      f.addEventListener('input', function () { f.classList.remove('invalid'); });
      f.addEventListener('change', function () { f.classList.remove('invalid'); });
    });
  });

  /* Contact form via AJAX */
  var form = $('#contactForm'), alertBox = $('#formAlert');
  function showAlert(type, msg) {
    alertBox.innerHTML = '';
    var d = document.createElement('div');
    d.className = 'alert alert-' + type;
    d.textContent = msg;
    alertBox.appendChild(d);
  }
  if (form && window.fetch && window.FormData) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var invalid = false;
      $$('[required]', form).forEach(function (f) {
        var bad = !f.checkValidity();
        f.classList.toggle('invalid', bad);
        if (bad) invalid = true;
      });
      if (invalid) { showAlert('error', 'Please fill in the required fields correctly.'); return; }

      var btn = $('button[type=submit]', form), label = $('span', btn), old = label.textContent;
      btn.disabled = true; label.textContent = 'Sending...';
      fetch(form.action, {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          showAlert(res.ok ? 'success' : 'error', res.message);
          if (res.ok) form.reset();
        })
        .catch(function () { showAlert('error', 'Network error. Please try again.'); })
        .then(function () { btn.disabled = false; label.textContent = old; });
    });
    $$('input, textarea', form).forEach(function (f) {
      f.addEventListener('input', function () { f.classList.remove('invalid'); });
    });
  }
})();
