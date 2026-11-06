(function () {
  'use strict';

  var TESTIMONIALS_ENABLED = false;
  var TESTIMONIALS = [
  ];

  document.querySelectorAll('.nh-carriers').forEach(function (section) {
    var button = section.querySelector('.nh-carriers__toggle');
    if (!button) return;
    button.addEventListener('click', function () {
      var paused = section.getAttribute('data-paused') !== 'true';
      section.setAttribute('data-paused', String(paused));
      button.textContent = paused ? 'Resume scrolling' : 'Pause scrolling';
    });
  });

  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));

  function activateTab(tab, setFocus) {
    tabs.forEach(function (t) {
      var selected = t === tab;
      t.setAttribute('aria-selected', String(selected));
      t.tabIndex = selected ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !selected;
    });
    if (setFocus) tab.focus();
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { activateTab(tab, false); });
    tab.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
      else if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
      else if (e.key === 'Home') next = tabs[0];
      else if (e.key === 'End') next = tabs[tabs.length - 1];
      if (next) { e.preventDefault(); activateTab(next, true); }
    });
  });

  function openCoverageCard(id, smooth) {
    var card = document.getElementById(id);
    var tab = document.getElementById('tab-coverage');
    if (!card || !tab) return false;
    activateTab(tab, false);
    card.setAttribute('tabindex', '-1');
    card.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' });
    card.focus({ preventScroll: true });
    return true;
  }

  document.querySelectorAll('[data-open-tab]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var id = link.getAttribute('href').slice(1);
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (openCoverageCard(id, !reduce)) {
        e.preventDefault();
        history.replaceState(null, '', '#' + id);
      }
    });
  });

  if (/^#coverage-/.test(location.hash)) openCoverageCard(location.hash.slice(1), false);

  var lastNote = '';
  document.querySelectorAll('[data-interest]').forEach(function (link) {
    link.addEventListener('click', function () {
      var box = document.querySelector('.contact-form input[name="interests[]"][value="' + link.getAttribute('data-interest') + '"]');
      if (box) box.checked = true;
      var note = link.getAttribute('data-note');
      var comments = document.getElementById('comments');
      if (note && comments) {
        var current = comments.value.trim();
        if (!current || current === lastNote) {
          comments.value = note;
          lastNote = note;
        }
      }
      if (link.closest('.life-panel') && typeof window.gtag === 'function') {
        window.gtag('event', 'situation_quote_click', { situation: link.closest('.life-panel').getAttribute('data-situation') });
      }
    });
  });

  document.querySelectorAll('.life-picker input[name="situation"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'situation_select', { situation: radio.value });
      }
      var panel = document.getElementById('life-panel-' + radio.value);
      if (panel && window.innerWidth < 900) {
        var top = panel.getBoundingClientRect().top;
        var barH = document.querySelector('.mobile-bar') ? 80 : 0;
        if (top > window.innerHeight - barH - 140) {
          var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          var headerH = header ? header.offsetHeight : 0;
          window.scrollBy({ top: top - headerH - 16, behavior: reduce ? 'auto' : 'smooth' });
        }
      }
    });
  });

  document.querySelectorAll('.acc-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      document.getElementById(btn.getAttribute('aria-controls')).hidden = open;
    });
  });

  (function initTestimonials() {
    var section = document.getElementById('testimonials');
    if (!section || !TESTIMONIALS_ENABLED || TESTIMONIALS.length === 0) return;

    var track = section.querySelector('.carousel-track');
    var status = section.querySelector('[data-carousel-status]');
    var prev = section.querySelector('[data-carousel-prev]');
    var next = section.querySelector('[data-carousel-next]');
    var index = 0;

    function render() {
      var t = TESTIMONIALS[index];
      var fig = document.createElement('figure');
      fig.className = 'testimonial';
      fig.setAttribute('role', 'group');
      fig.setAttribute('aria-roledescription', 'slide');
      fig.setAttribute('aria-label', (index + 1) + ' of ' + TESTIMONIALS.length);
      var q = document.createElement('blockquote');
      q.textContent = '“' + t.quote + '”';
      var cap = document.createElement('figcaption');
      cap.textContent = '— ' + t.name + (t.place ? ', ' + t.place : '');
      fig.appendChild(q);
      fig.appendChild(cap);
      track.replaceChildren(fig);
      status.textContent = (index + 1) + ' / ' + TESTIMONIALS.length;
    }

    function go(step) {
      index = (index + step + TESTIMONIALS.length) % TESTIMONIALS.length;
      render();
    }

    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    if (TESTIMONIALS.length < 2) { prev.hidden = true; next.hidden = true; status.hidden = true; }

    section.hidden = false;
    render();
  })();

  var form = document.querySelector('.contact-form');
  if (form) {
    var errorBox = form.querySelector('[data-form-error]');
    var success = document.querySelector('[data-form-success]');
    var submitBtn = form.querySelector('[type="submit"]');
    var statusInput = form.querySelector('[name="turnstile_status"]');
    var widgetBox = form.querySelector('[data-turnstile]');
    var widgetId = null;
    var widgetState = 'idle';
    var widgetWaiters = [];

    form.querySelector('[name="form_loaded"]').value = String(Date.now());

    function settleWidget(state) {
      widgetState = state;
      statusInput.value = state === 'unavailable' ? 'unavailable' : '';
      widgetWaiters.splice(0).forEach(function (fn) { fn(); });
    }

    function loadTurnstile() {
      if (widgetState !== 'idle') return;
      var siteKey = widgetBox && widgetBox.getAttribute('data-sitekey');
      if (!siteKey) { settleWidget('unavailable'); return; }
      widgetState = 'loading';
      window.nhiTurnstileReady = function () {
        try {
          widgetId = window.turnstile.render(widgetBox, {
            sitekey: siteKey,
            action: 'contact',
            appearance: 'interaction-only',
            size: 'flexible',
            callback: function () { settleWidget('ready'); },
            'expired-callback': function () { widgetState = 'loading'; },
            'error-callback': function () { settleWidget('unavailable'); return true; }
          });
        } catch (err) {
          settleWidget('unavailable');
        }
      };
      var script = document.createElement('script');
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=nhiTurnstileReady';
      script.async = true;
      script.onerror = function () { settleWidget('unavailable'); };
      document.head.appendChild(script);
      setTimeout(function () { if (widgetState === 'loading') settleWidget('unavailable'); }, 15000);
    }

    function widgetSettled() {
      if (widgetState === 'idle') loadTurnstile();
      if (widgetState !== 'loading') return Promise.resolve();
      return new Promise(function (resolve) {
        widgetWaiters.push(resolve);
        setTimeout(function () {
          if (widgetState === 'loading') settleWidget('unavailable');
          resolve();
        }, 8000);
      });
    }

    function resetWidget() {
      if (widgetId === null || !window.turnstile) return;
      widgetState = 'loading';
      window.turnstile.reset(widgetId);
    }

    if ('IntersectionObserver' in window) {
      var watcher = new IntersectionObserver(function (entries) {
        if (entries.some(function (en) { return en.isIntersecting; })) {
          watcher.disconnect();
          loadTurnstile();
        }
      }, { rootMargin: '600px 0px' });
      watcher.observe(form);
    }
    form.addEventListener('focusin', loadTurnstile);

    function showError(msg, field) {
      errorBox.textContent = msg;
      errorBox.hidden = false;
      if (field) field.focus();
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      errorBox.hidden = true;

      var invalid = null;
      form.querySelectorAll('input, textarea').forEach(function (el) {
        if (el.name === 'company_website' || el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio') return;
        var ok = el.checkValidity() && !(el.required && !el.value.trim());
        el.setAttribute('aria-invalid', String(!ok));
        if (!ok && !invalid) invalid = el;
      });
      if (invalid) {
        var label = form.querySelector('label[for="' + invalid.id + '"]');
        var name = label ? label.textContent.replace('*', '').trim() : 'this field';
        showError('Please check ' + name + '.', invalid);
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending…';

      var sentAt = String(Date.now());
      widgetSettled()
        .then(function () {
          var data = new FormData(form);
          data.set('form_sent', sentAt);
          return fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams(data).toString()
          });
        })
        .then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok || !data.ok) throw new Error(data.message || 'HTTP ' + res.status);
          });
        })
        .then(function () {
          form.hidden = true;
          success.hidden = false;
          success.focus();
          if (typeof window.gtag === 'function') window.gtag('event', 'generate_lead');
        })
        .catch(function (err) {
          var msg = err && err.message && !/^HTTP |JSON|fetch/i.test(err.message)
            ? err.message : 'Sorry, something went wrong sending your request.';
          showError(msg + ' If it keeps happening, please call me at 605-321-5367.');
          resetWidget();
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send My Request';
        });
    });
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('a[href^="tel:"]');
    if (link && typeof window.gtag === 'function') {
      window.gtag('event', 'phone_click', { link_location: link.closest('.mobile-bar') ? 'mobile-bar' : link.closest('.site-header') ? 'header' : link.closest('.site-footer') ? 'footer' : 'content' });
    }
    var cal = e.target.closest && e.target.closest('a[href^="https://calendly.com/"]');
    if (cal && typeof window.gtag === 'function' && !cal.closest('dialog')) {
      window.gtag('event', 'schedule_click', { link_location: cal.closest('.hero') ? 'hero' : cal.closest('#contact') ? 'contact' : 'content' });
    }
  });

  var scheduleDialog = document.getElementById('schedule-dialog');
  if (scheduleDialog && typeof scheduleDialog.showModal === 'function') {
    var scheduleBody = scheduleDialog.querySelector('[data-schedule-body]');
    var scheduleOpener = null;

    var closeSchedule = function () {
      if (scheduleDialog.open) scheduleDialog.close();
    };

    scheduleDialog.addEventListener('close', function () {
      document.documentElement.classList.remove('has-dialog');
      if (scheduleOpener && document.contains(scheduleOpener)) scheduleOpener.focus();
    });

    scheduleDialog.addEventListener('click', function (e) {
      if (e.target === scheduleDialog) closeSchedule();
    });

    scheduleDialog.querySelector('[data-schedule-close]').addEventListener('click', closeSchedule);

    document.addEventListener('click', function (e) {
      var link = e.target.closest && e.target.closest('a[data-schedule]');
      if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      scheduleOpener = link;
      if (!scheduleBody.querySelector('iframe')) {
        var src = new URL(link.href);
        src.searchParams.set('embed_domain', window.location.hostname);
        src.searchParams.set('embed_type', 'Inline');
        var frame = document.createElement('iframe');
        frame.src = src.toString();
        frame.title = 'Pick a time with Nate Hall on Calendly';
        frame.allow = 'payment';
        frame.addEventListener('load', function () { frame.classList.add('is-loaded'); });
        scheduleBody.appendChild(frame);
      }
      document.documentElement.classList.add('has-dialog');
      scheduleDialog.showModal();
      scheduleDialog.querySelector('[data-schedule-close]').focus();
    });

    window.addEventListener('message', function (e) {
      if (e.origin !== 'https://calendly.com' || !e.data || e.data.event !== 'calendly.event_scheduled') return;
      if (typeof window.gtag === 'function') window.gtag('event', 'schedule_booked');
    });
  }
})();
