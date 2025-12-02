(function () {
  var btn = document.getElementById('menu-toggle');
  var header = document.querySelector('header.site-header');
  var mobile = document.getElementById('mobile-menu');
  var backdrop = document.getElementById('mobile-backdrop');
  if (!btn || !header || !mobile) return;
  // focus-trap helpers
  function getFocusable(el) {
    return Array.prototype.slice.call(el.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter(function (node) { return node.offsetWidth || node.offsetHeight || node.getClientRects().length; });
  }

  var lastFocused = null;

  function setState(open) {
    if (open) {
      lastFocused = document.activeElement;
      header.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
      mobile.setAttribute('aria-hidden', 'false');
      if (backdrop) backdrop.classList.add('open');
      // move focus to first focusable element in mobile menu
      var focusables = getFocusable(mobile);
      if (focusables.length) focusables[0].focus();
      // attach focus trap
      document.addEventListener('keydown', trapHandler);
    } else {
      header.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      mobile.setAttribute('aria-hidden', 'true');
      if (backdrop) backdrop.classList.remove('open');
      // remove focus trap
      document.removeEventListener('keydown', trapHandler);
      // restore focus
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    }
  }

  function trapHandler(e) {
    // Escape always closes
    if (e.key === 'Escape' || e.key === 'Esc') {
      if (header.classList.contains('open')) setState(false);
      return;
    }
    if (e.key !== 'Tab') return;
    var focusables = getFocusable(mobile);
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey) { // SHIFT + TAB
      if (document.activeElement === first) {
        e.preventDefault();
        last.focus();
      }
    } else { // TAB
      if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    var isOpen = header.classList.toggle('open');
    setState(isOpen);
  });

  // close when clicking on backdrop
  if (backdrop) {
    backdrop.addEventListener('click', function () { setState(false); });
  }

  // close when clicking outside mobile menu on medium screens
  document.addEventListener('click', function (e) {
    if (!header.classList.contains('open')) return;
    var target = e.target;
    if (target === btn || header.contains(target) || mobile.contains(target)) return;
    setState(false);
  });

  // close button inside mobile menu
  var closeBtn = document.getElementById('mobile-close');
  if (closeBtn) closeBtn.addEventListener('click', function () { setState(false); });

  // ensure aria state is correct on load
  setState(false);
})();
