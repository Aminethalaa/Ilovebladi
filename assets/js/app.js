// Baladiyati — shared behaviours.
document.addEventListener('DOMContentLoaded', function () {
  var BASE = document.body.dataset.base || './';

  // ---------- PWA: service worker ----------
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(BASE + 'sw.js', { scope: BASE }).catch(function () {});
  }

  // ---------- PWA: install button (Chrome/Edge/Android) ----------
  var installBtn = document.getElementById('installBtn');
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (installBtn) installBtn.hidden = false;
  });
  if (installBtn) {
    installBtn.addEventListener('click', function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () { installBtn.hidden = true; deferredPrompt = null; });
    });
  }
  window.addEventListener('appinstalled', function () { if (installBtn) installBtn.hidden = true; });

  // ---------- Web Push: contextual opt-in ----------
  var pushCard = document.getElementById('pushCard');
  function urlB64ToUint8(s) {
    var pad = '='.repeat((4 - (s.length % 4)) % 4);
    var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
  function pushSubscribe(reg) {
    return reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlB64ToUint8(pushCard.dataset.vapid),
    }).then(function (sub) {
      var j = sub.toJSON();
      return fetch(pushCard.dataset.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: pushCard.dataset.csrf, endpoint: sub.endpoint, keys: j.keys }),
      });
    });
  }
  if (pushCard && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window) {
    navigator.serviceWorker.ready.then(function (reg) {
      if (Notification.permission === 'granted') {
        reg.pushManager.getSubscription().then(function (sub) {
          if (!sub) pushSubscribe(reg); // silent re-subscribe, permission already granted
        });
      } else if (Notification.permission === 'default') {
        pushCard.hidden = false; // only ask via an explicit user tap
        document.getElementById('pushBtn').addEventListener('click', function () {
          Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') { pushCard.hidden = true; return; }
            pushSubscribe(reg).then(function () {
              pushCard.classList.add('push-done');
              pushCard.querySelector('.push-body').textContent = pushCard.dataset.doneText;
              document.getElementById('pushBtn').hidden = true;
            });
          });
        });
      }
    });
  }
  // Mobile nav
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainnav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () { nav.classList.toggle('open'); });
  }

  // Animated stat counters
  var counters = document.querySelectorAll('.stat-num[data-count]');
  if (counters.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        var target = parseInt(en.target.dataset.count, 10) || 0;
        var start = null;
        function tick(ts) {
          if (!start) start = ts;
          var p = Math.min(1, (ts - start) / 900);
          en.target.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
          if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (c) { io.observe(c); });
  } else {
    counters.forEach(function (c) { c.textContent = parseInt(c.dataset.count, 10).toLocaleString(); });
  }

  // Wilaya -> commune cascading select
  var wilaya = document.getElementById('wilayaSel');
  var commune = document.getElementById('communeSel');
  if (wilaya && commune && wilaya.dataset.communesUrl) {
    wilaya.addEventListener('change', function () {
      commune.innerHTML = '<option value=""></option>';
      if (!wilaya.value) return;
      fetch(wilaya.dataset.communesUrl + '?wilaya=' + encodeURIComponent(wilaya.value))
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          rows.forEach(function (c) {
            var op = document.createElement('option');
            op.value = c.id;
            op.textContent = c.name;
            commune.appendChild(op);
          });
        });
    });
  }

  // Register page: citizen/association toggle
  var typeRadios = document.querySelectorAll('.type-toggle input[type="radio"]');
  if (typeRadios.length) {
    var refresh = function () {
      var isAssoc = document.querySelector('.type-toggle input:checked').value === 'association';
      document.querySelectorAll('.type-opt').forEach(function (o) {
        o.classList.toggle('on', o.querySelector('input').checked);
      });
      document.querySelectorAll('.assoc-only').forEach(function (el) { el.hidden = !isAssoc; });
      var nameLabel = document.getElementById('nameLabel');
      if (nameLabel) {
        nameLabel.textContent = isAssoc ? nameLabel.dataset.labelAssoc : nameLabel.dataset.labelCitizen;
      }
    };
    typeRadios.forEach(function (r) { r.addEventListener('change', refresh); });
  }

  // Category picker highlight (radios for complaints, checkboxes for association domains)
  document.querySelectorAll('.cat-picker input').forEach(function (r) {
    r.addEventListener('change', function () {
      var opt = r.closest('.cat-opt');
      if (r.type === 'radio') {
        document.querySelectorAll('.cat-picker .cat-opt').forEach(function (o) {
          o.classList.toggle('on', o.querySelector('input').checked);
        });
      } else if (opt) {
        opt.classList.toggle('on', r.checked);
      }
    });
  });

  // ---------- multi-step wizard (progressive enhancement) ----------
  document.querySelectorAll('form.wizard').forEach(function (form) {
    var steps = Array.prototype.slice.call(form.querySelectorAll('.wizard-step'));
    if (steps.length < 2) return;
    var current = 0;
    form.classList.add('wizard-on');

    var prog = document.createElement('div');
    prog.className = 'wizard-progress';
    steps.forEach(function (s, i) {
      var dot = document.createElement('div');
      dot.className = 'wizard-dot';
      dot.innerHTML = '<span class="wd-num">' + (i + 1) + '</span>'
        + '<span class="wd-label">' + (s.dataset.title || '') + '</span>';
      prog.appendChild(dot);
    });
    form.insertBefore(prog, form.firstChild);

    var nav = document.createElement('div');
    nav.className = 'wizard-nav';
    var back = document.createElement('button');
    back.type = 'button'; back.className = 'btn btn-ghost wizard-back';
    back.innerHTML = '<span class="wz-ar">→</span> ' + (form.dataset.back || 'Back');
    var next = document.createElement('button');
    next.type = 'button'; next.className = 'btn btn-primary wizard-next';
    next.innerHTML = (form.dataset.next || 'Next') + ' <span class="wz-ar">←</span>';
    nav.appendChild(back); nav.appendChild(next);
    form.appendChild(nav);

    function announceMaps() {
      var maps = (window.Baladiyati && window.Baladiyati.maps) || [];
      maps.forEach(function (m) { setTimeout(function () { try { m.invalidateSize(); } catch (e) {} }, 60); });
    }

    function show(idx, dir, scroll) {
      steps[current].classList.remove('active');
      steps.forEach(function (s) { s.hidden = true; });
      current = idx;
      var s = steps[current];
      s.hidden = false;
      s.classList.remove('wz-anim');
      void s.offsetWidth;
      s.classList.add('active', 'wz-anim');
      prog.querySelectorAll('.wizard-dot').forEach(function (d, i) {
        d.classList.toggle('done', i < current);
        d.classList.toggle('on', i === current);
      });
      back.style.visibility = current === 0 ? 'hidden' : 'visible';
      var last = current === steps.length - 1;
      next.hidden = last;
      form.querySelectorAll('.wizard-submit').forEach(function (b) { b.hidden = !last; });
      announceMaps();
      if (scroll) {
        var top = form.getBoundingClientRect().top + window.pageYOffset - 74;
        window.scrollTo({ top: top, behavior: 'smooth' });
      }
    }

    function validStep() {
      var inputs = steps[current].querySelectorAll('input, select, textarea');
      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].disabled || inputs[i].type === 'hidden') continue;
        if (!inputs[i].checkValidity()) { inputs[i].reportValidity(); return false; }
      }
      return true;
    }

    next.addEventListener('click', function () {
      if (validStep()) show(Math.min(current + 1, steps.length - 1), 1, true);
    });
    back.addEventListener('click', function () { show(Math.max(current - 1, 0), -1, true); });
    // Enter in a text field advances instead of submitting early
    form.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && current < steps.length - 1) {
        e.preventDefault();
        if (validStep()) show(current + 1, 1, true);
      }
    });

    steps.forEach(function (s, i) { s.hidden = i !== 0; });
    show(0, 1, false);
  });

  // ---------- share: copy link (with Web Share API when available) ----------
  document.querySelectorAll('.share-bar').forEach(function (bar) {
    var copyBtn = bar.querySelector('.share-copy');
    if (!copyBtn) return;
    copyBtn.addEventListener('click', function () {
      var url = bar.dataset.shareUrl;
      if (navigator.share) {
        navigator.share({ title: bar.dataset.shareText, url: url }).catch(function () {});
        return;
      }
      var done = function () {
        var el = document.createElement('div');
        el.className = 'toast';
        el.textContent = bar.dataset.copied;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 2200);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(done, done);
      } else {
        var ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        ta.remove(); done();
      }
    });
  });

  // ---------- active nav highlighting ----------
  var here = location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.mainnav a').forEach(function (a) {
    var href = (a.getAttribute('href') || '').split('/').pop().split('?')[0];
    if (href && href === here) a.classList.add('active');
  });

  // ---------- scroll reveal ----------
  if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var reveal = document.querySelectorAll('.section .card, .stat-card, .step, .grid-3 > *, .list > .list-item, .cat-bar, .table-wrap');
    var ro = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        ro.unobserve(en.target);
        en.target.classList.add('revealed');
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    reveal.forEach(function (el, i) {
      el.classList.add('will-reveal');
      el.style.transitionDelay = Math.min(i % 8, 6) * 45 + 'ms';
      ro.observe(el);
    });
  }
});
