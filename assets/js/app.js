// Baladiyati — shared behaviours.
document.addEventListener('DOMContentLoaded', function () {
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
