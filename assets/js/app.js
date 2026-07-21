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

  // New complaint: category picker highlight
  document.querySelectorAll('.cat-picker input[type="radio"]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.cat-opt').forEach(function (o) {
        o.classList.toggle('on', o.querySelector('input').checked);
      });
    });
  });
});
