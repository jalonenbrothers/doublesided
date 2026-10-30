/* views.js — progressive enhancement for issue view modes (no-JS = plain
 * ?view= links). With JS:
 *   - instant client-side switching between the server-rendered sections
 *   - ?view= kept shareable via history.replaceState (canonical stays the
 *     bare issue URL — no duplicate-content URLs)
 *   - keyboard: arrow keys move between toggle buttons (roving focus)
 *   - scroll position is remembered per view mode while on the page
 * Progressive enhancement contract: everything works without this file. */
(function () {
  'use strict';

  var article = document.querySelector('article.issue[data-view]');
  if (!article) return;
  var buttons = Array.prototype.slice.call(
    document.querySelectorAll('.view-toggle .view-btn'));
  var sections = Array.prototype.slice.call(
    document.querySelectorAll('#views > .view'));
  var wrap = document.getElementById('views');
  if (!buttons.length || !sections.length || !wrap) return;

  var current = article.getAttribute('data-view');
  var scrollMemory = {}; // viewKey -> scrollY
  var scroller = document.scrollingElement || document.documentElement;

  function setView(key, push) {
    if (!sections.some(function (s) { return s.getAttribute('data-view') === key; })) return;
    if (key === current && push !== true) return;

    // remember where the reader was
    scrollMemory[current] = window.scrollY;

    sections.forEach(function (s) {
      var active = s.getAttribute('data-view') === key;
      if (active) { s.removeAttribute('hidden'); } else { s.setAttribute('hidden', ''); }
    });

    buttons.forEach(function (b) {
      var active = b.getAttribute('data-view') === key;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (active) { b.setAttribute('aria-current', 'true'); } else { b.removeAttribute('aria-current'); }
    });

    current = key;
    article.setAttribute('data-view', key);

    // shareable URL without triggering a reload / duplicate-content page
    var url = key === 'merged'
      ? location.pathname
      : location.pathname + '?view=' + encodeURIComponent(key);
    history.replaceState(null, '', url);

    // jump back to where the reader was in this mode, else to the toggle bar
    var y = scrollMemory[key];
    if (typeof y === 'number') {
      window.scrollTo(0, y);
    } else {
      var top = wrap.getBoundingClientRect().top + window.scrollY - 12;
      window.scrollTo(0, Math.max(top, 0));
    }
  }

  buttons.forEach(function (b) {
    b.addEventListener('click', function (ev) {
      ev.preventDefault();
      setView(b.getAttribute('data-view'), true);
      b.focus();
    });
  });

  // roving keyboard nav across the toggle group (WCAG 2.2 AA target)
  buttons.forEach(function (b, i) {
    b.addEventListener('keydown', function (ev) {
      var delta = 0;
      if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') delta = 1;
      if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') delta = -1;
      if (!delta) return;
      ev.preventDefault();
      var n = buttons[(i + delta + buttons.length) % buttons.length];
      n.focus();
      setView(n.getAttribute('data-view'), true);
    });
  });

  // deep links like #source-src-imf work after a JS view switch: if the
  // target lives inside a hidden section, reveal that view first.
  if (location.hash.length > 1) {
    var target = document.getElementById(location.hash.slice(1));
    if (target) {
      var owner = target.closest ? target.closest('#views > .view') : null;
      if (owner && owner.hasAttribute('hidden')) {
        setView(owner.getAttribute('data-view'), false);
      }
    }
  }
})();
