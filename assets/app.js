(function () {
  var root = document.documentElement, btn = document.getElementById('themeBtn');
  function paint() { if (btn) btn.textContent = root.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙'; }
  paint();
  if (btn) btn.addEventListener('click', function () {
    var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', t);
    try { localStorage.setItem('theme', t); } catch (e) {}
    paint();
  });

  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (m && !confirm(m)) e.preventDefault();
  });

  var fi = document.getElementById('image'), pv = document.getElementById('preview'), fn = document.getElementById('fname');
  if (fi) fi.addEventListener('change', function () {
    var f = fi.files[0];
    if (!f) { pv.hidden = true; fn.textContent = 'Maks. 2 MB'; return; }
    if (f.size > 2097152) { alert('Ukuran gambar maksimal 2 MB.'); fi.value = ''; pv.hidden = true; fn.textContent = 'Maks. 2 MB'; return; }
    pv.src = URL.createObjectURL(f); pv.hidden = false; fn.textContent = f.name;
  });

  setTimeout(function () {
    document.querySelectorAll('.flash.ok').forEach(function (el) { el.style.display = 'none'; });
  }, 4000);
})();
