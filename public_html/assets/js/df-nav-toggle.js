(function () {
  var css = '.df-nav-toggle{display:inline-flex;flex-direction:column;justify-content:center;gap:4px;width:42px;height:42px;padding:0 10px;margin:0 10px 0 0;border:1px solid #CBD5E1;border-radius:10px;background:#fff;cursor:pointer;vertical-align:middle}' +
    '.df-nav-toggle span{display:block;height:2px;width:20px;background:#0F172A;border-radius:2px}' +
    '.df-nav-collapsed{display:none!important}' +
    '.df-nav-open{display:flex!important;flex-wrap:wrap}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  document.querySelectorAll('nav[aria-label="Seller navigation"], nav.seller-nav-df').forEach(function (nav) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'df-nav-toggle';
    btn.setAttribute('aria-label', 'Show or hide seller menu');
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<span></span><span></span><span></span>';
    nav.parentNode.insertBefore(btn, nav);

    // Menu always starts hidden, whatever the screen size or previous state.
    nav.classList.remove('df-nav-open');
    nav.classList.add('df-nav-collapsed');

    btn.addEventListener('click', function () {
      var isOpen = nav.classList.contains('df-nav-open');
      nav.classList.toggle('df-nav-open', !isOpen);
      nav.classList.toggle('df-nav-collapsed', isOpen);
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  });
})();
