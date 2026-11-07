/* Zoty support tickets – searchable selects, markdown editor toolbar, attachment dropzone. No dependencies. */
(function () {
  'use strict';
  var T = window.ZOTY_T || {};
  function t(k, d) { return T[k] || d; }

  /* ---------------------------------------------------------- searchable select (Jira-like "Search…") */
  function combo(sel) {
    var wrap = document.createElement('div');
    wrap.className = 'combo';
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'combo__input';
    input.placeholder = t('search', 'Search…');
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    if (sel.id) { input.id = sel.id + '__combo'; var lab = document.querySelector('label[for="' + sel.id + '"]'); if (lab) lab.htmlFor = input.id; }
    if (sel.getAttribute('aria-invalid')) input.setAttribute('aria-invalid', 'true');
    var list = document.createElement('ul');
    list.className = 'combo__list';
    list.setAttribute('role', 'listbox');
    list.id = (sel.id || 'c' + Math.random().toString(36).slice(2)) + '__list';
    input.setAttribute('aria-controls', list.id);
    list.hidden = true;
    var chev = document.createElement('span');
    chev.className = 'combo__chev';
    chev.setAttribute('aria-hidden', 'true');

    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    wrap.appendChild(input);
    wrap.appendChild(chev);
    wrap.appendChild(list);
    sel.classList.add('combo__native');
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');

    var opts = Array.prototype.filter.call(sel.options, function (o) { return o.value !== ''; });
    var active = -1, shown = [];

    function label() { var o = sel.options[sel.selectedIndex]; return o && o.value !== '' ? o.text : ''; }
    function render(q) {
      q = (q || '').toLowerCase();
      list.innerHTML = '';
      shown = opts.filter(function (o) { return !q || o.text.toLowerCase().indexOf(q) !== -1; });
      if (!shown.length) {
        var li = document.createElement('li');
        li.className = 'combo__empty';
        li.textContent = t('no_match', 'No matches');
        list.appendChild(li);
      }
      shown.forEach(function (o, i) {
        var li = document.createElement('li');
        li.className = 'combo__opt';
        li.id = list.id + '_' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', o.selected ? 'true' : 'false');
        li.textContent = o.text;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(o); });
        list.appendChild(li);
      });
      active = Math.max(0, shown.indexOf(sel.options[sel.selectedIndex]));
      mark();
    }
    function mark() {
      Array.prototype.forEach.call(list.querySelectorAll('.combo__opt'), function (li, i) {
        li.classList.toggle('is-active', i === active);
        if (i === active) { input.setAttribute('aria-activedescendant', li.id); li.scrollIntoView({ block: 'nearest' }); }
      });
    }
    function open() { if (!list.hidden) return; render(''); list.hidden = false; wrap.classList.add('is-open'); input.setAttribute('aria-expanded', 'true'); input.select(); }
    function close() { list.hidden = true; wrap.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); input.value = label(); }
    function pick(o) {
      sel.value = o.value;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      wrap.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
      close();
    }

    input.value = label();
    input.addEventListener('focus', open);
    input.addEventListener('click', open);
    input.addEventListener('input', function () { list.hidden = false; render(input.value); });
    input.addEventListener('blur', close);
    chev.addEventListener('mousedown', function (e) { e.preventDefault(); list.hidden ? input.focus() : input.blur(); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (list.hidden) { open(); return; }
        active = Math.min(shown.length - 1, Math.max(0, active + (e.key === 'ArrowDown' ? 1 : -1)));
        mark();
      } else if (e.key === 'Enter') {
        if (!list.hidden && shown[active]) { e.preventDefault(); pick(shown[active]); }
      } else if (e.key === 'Escape') {
        close();
      }
    });
  }
  document.querySelectorAll('select[data-combo]').forEach(combo);

  /* ---------------------------------------------------------- markdown toolbar */
  function rte(box) {
    var ta = box.querySelector('textarea');

    function sel() { return { a: ta.selectionStart, b: ta.selectionEnd, v: ta.value }; }
    function set(v, a, b) { ta.value = v; ta.focus(); ta.setSelectionRange(a, b); ta.dispatchEvent(new Event('input')); }
    function wrapWith(pre, post, ph) {
      var s = sel(), mid = s.v.slice(s.a, s.b) || ph;
      // toggle off when already wrapped
      if (s.v.slice(s.a - pre.length, s.a) === pre && s.v.slice(s.b, s.b + post.length) === post) {
        return set(s.v.slice(0, s.a - pre.length) + mid + s.v.slice(s.b + post.length), s.a - pre.length, s.a - pre.length + mid.length);
      }
      set(s.v.slice(0, s.a) + pre + mid + post + s.v.slice(s.b), s.a + pre.length, s.a + pre.length + mid.length);
    }
    function lines(fn) {
      var s = sel();
      var start = s.v.lastIndexOf('\n', s.a - 1) + 1;
      var endNl = s.v.indexOf('\n', s.b > s.a && s.v[s.b - 1] === '\n' ? s.b - 1 : s.b);
      var end = endNl === -1 ? s.v.length : endNl;
      var out = s.v.slice(start, end).split('\n').map(fn).join('\n');
      set(s.v.slice(0, start) + out + s.v.slice(end), start, start + out.length);
    }
    function prefix(p, re) {
      var all = true;
      sel().v.slice(sel().v.lastIndexOf('\n', sel().a - 1) + 1, sel().b).split('\n').forEach(function (l) { if (!re.test(l)) all = false; });
      var n = 0;
      lines(function (l) {
        var bare = l.replace(/^(#{1,3}\s+|[-*•]\s+|\d+[.)]\s+|>\s?)/, '');
        if (all) return bare;
        n++;
        return (typeof p === 'function' ? p(n) : p) + bare;
      });
    }
    var cmds = {
      p:      function () { lines(function (l) { return l.replace(/^#{1,3}\s+/, ''); }); },
      h1:     function () { prefix('# ', /^#\s/); },
      h2:     function () { prefix('## ', /^##\s/); },
      h3:     function () { prefix('### ', /^###\s/); },
      bold:   function () { wrapWith('**', '**', t('bold_text', 'bold text')); },
      italic: function () { wrapWith('*', '*', t('italic_text', 'italic text')); },
      strike: function () { wrapWith('~~', '~~', t('strike_text', 'text')); },
      ul:     function () { prefix('- ', /^[-*•]\s/); },
      ol:     function () { prefix(function (n) { return n + '. '; }, /^\d+[.)]\s/); },
      quote:  function () { prefix('> ', /^>\s?/); },
      code:   function () { wrapWith('`', '`', 'code'); },
      codeblock: function () {
        var s = sel(), mid = s.v.slice(s.a, s.b) || '';
        var pre = (s.a > 0 && s.v[s.a - 1] !== '\n' ? '\n' : '') + '```\n', post = '\n```\n';
        set(s.v.slice(0, s.a) + pre + mid + post + s.v.slice(s.b), s.a + pre.length, s.a + pre.length + mid.length);
      },
      link: function () {
        var s = sel(), text = s.v.slice(s.a, s.b);
        var url = window.prompt(t('link_prompt', 'Link URL'), 'https://');
        if (!url || !/^https?:\/\/\S+$/.test(url)) return ta.focus();
        var md = '[' + (text || url) + '](' + url + ')';
        set(s.v.slice(0, s.a) + md + s.v.slice(s.b), s.a + md.length, s.a + md.length);
      },
      attach: function () {
        var form = box.closest('form'), btn = form && (form.querySelector('[data-drop-browse]') || form.querySelector('[data-img-input]'));
        if (btn) btn.click();
      },
      table: function () {
        var s = sel(), NL = '\n';
        var pre = s.a === 0 ? '' : (s.v[s.a - 1] !== NL ? NL + NL : (s.a > 1 && s.v[s.a - 2] !== NL ? NL : ''));
        var col = t('col', 'Sloupec');
        var md = pre + '| ' + col + ' 1 | ' + col + ' 2 |' + NL + '|---|---|' + NL + '| | |' + NL;
        set(s.v.slice(0, s.a) + md + s.v.slice(s.b), s.a + pre.length + 2, s.a + pre.length + 2 + col.length + 2);
      },
      hr: function () {
        var s = sel(), NL = '\n';
        var pre = s.a === 0 ? '' : (s.v[s.a - 1] !== NL ? NL + NL : NL);
        set(s.v.slice(0, s.a) + pre + '---' + NL + NL + s.v.slice(s.b), s.a + pre.length + 5, s.a + pre.length + 5);
      }
    };

    box.querySelectorAll('[data-cmd]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the textarea selection
      b.addEventListener('click', function () {
        var pop = b.closest('.rte__pop');
        if (pop) { pop.hidden = true; pop.previousElementSibling.setAttribute('aria-expanded', 'false'); }
        (cmds[b.getAttribute('data-cmd')] || function () {})();
      });
    });
    box.querySelectorAll('[data-menu]').forEach(function (b) {
      var pop = b.nextElementSibling;
      b.addEventListener('click', function () {
        var open = pop.hidden;
        box.querySelectorAll('.rte__pop').forEach(function (p) { p.hidden = true; });
        pop.hidden = !open;
        b.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', function (e) { if (!b.parentNode.contains(e.target)) { pop.hidden = true; b.setAttribute('aria-expanded', 'false'); } });
    });
    ta.addEventListener('keydown', function (e) {
      if (!(e.ctrlKey || e.metaKey)) return;
      var k = e.key.toLowerCase();
      if (k === 'b') { e.preventDefault(); cmds.bold(); }
      else if (k === 'i') { e.preventDefault(); cmds.italic(); }
      else if (k === 'k') { e.preventDefault(); cmds.link(); }
    });
    // auto-grow
    var growMax = +ta.getAttribute('data-grow-max') || 640;
    function grow() { ta.style.height = 'auto'; ta.style.height = Math.min(Math.max(ta.scrollHeight + 2, 140), growMax) + 'px'; }
    ta.addEventListener('input', grow);
    grow();
    ta.addEventListener('focus', function () { box.classList.add('is-focus'); });
    ta.addEventListener('blur', function () { box.classList.remove('is-focus'); });
  }
  document.querySelectorAll('[data-rte]').forEach(rte);

  /* ---------------------------------------------------------- attachments: drag & drop, paste, browse */
  function drop(zone) {
    var input = zone.querySelector('[data-drop-input]'), list = zone.querySelector('[data-drop-list]');
    var form = zone.closest('form');
    var maxBytes = +zone.getAttribute('data-max') || 10485760, maxCount = +zone.getAttribute('data-count') || 10;
    var exts = (zone.getAttribute('data-ext') || '').split(',');
    var files = [];
    if (typeof DataTransfer === 'undefined') { input.hidden = false; return; }   // very old browser: plain file input

    function sync() {
      var dt = new DataTransfer();
      files.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
      list.innerHTML = '';
      files.forEach(function (f, i) {
        var li = document.createElement('li');
        li.className = 'drop__item';
        if (/^image\//.test(f.type)) {
          var img = document.createElement('img');
          img.alt = '';
          img.src = URL.createObjectURL(f);
          img.onload = function () { URL.revokeObjectURL(img.src); };
          li.appendChild(img);
        } else {
          var ic = document.createElement('span');
          ic.className = 'drop__ext';
          ic.textContent = (f.name.split('.').pop() || '').slice(0, 4).toUpperCase();
          li.appendChild(ic);
        }
        var name = document.createElement('span');
        name.className = 'drop__name';
        name.textContent = f.name;
        var size = document.createElement('small');
        size.textContent = f.size < 1048576 ? Math.max(1, Math.round(f.size / 1024)) + ' kB' : (f.size / 1048576).toFixed(1) + ' MB';
        name.appendChild(size);
        li.appendChild(name);
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'drop__rm';
        rm.setAttribute('aria-label', t('remove', 'Remove') + ' ' + f.name);
        rm.textContent = '×';
        rm.addEventListener('click', function () { files.splice(i, 1); sync(); });
        li.appendChild(rm);
        list.appendChild(li);
      });
    }
    function say(msg) {
      var p = zone.querySelector('.drop__err');
      p.textContent = msg || '';
      p.hidden = !msg;
    }
    function add(fl) {
      var errs = [];
      Array.prototype.forEach.call(fl, function (f) {
        var name = f.name && f.name !== 'image.png' ? f.name : 'screenshot-' + new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-') + '.' + ((f.type.split('/')[1] || 'png').replace('jpeg', 'jpg'));
        if (name !== f.name) f = new File([f], name, { type: f.type });
        var ext = (name.split('.').pop() || '').toLowerCase();
        if (exts.length && exts.indexOf(ext) === -1) errs.push(t('err_type', 'File type not allowed') + ': ' + name);
        else if (f.size > maxBytes) errs.push(t('err_size', 'File is too large') + ': ' + name);
        else if (files.length >= maxCount) errs.push(t('err_count', 'Too many files'));
        else files.push(f);
      });
      say(errs.join(' · '));
      sync();
    }

    zone.querySelector('[data-drop-browse]').addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () { var picked = Array.prototype.slice.call(input.files); add(picked); });
    ['dragenter', 'dragover'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'drop' || !zone.contains(e.relatedTarget)) zone.classList.remove('is-over'); });
    });
    zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
    // paste screenshots anywhere in the form (e.g. in the description)
    form.addEventListener('paste', function (e) {
      var fl = e.clipboardData && e.clipboardData.files;
      if (fl && fl.length) { e.preventDefault(); add(fl); }
    });
    // dropping a file on the editor adds it as an attachment too
    form.querySelectorAll('textarea').forEach(function (ta) {
      ta.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1) e.preventDefault(); });
      ta.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); add(e.dataTransfer.files); } });
    });
  }
  document.querySelectorAll('[data-drop]').forEach(drop);

  /* ---------------------------------------------------------- forms: no double submit, required check */
  document.querySelectorAll('form[data-ticket-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var bad = null;
      form.querySelectorAll('[data-required]').forEach(function (el) {
        var empty = el.type === 'radio' ? !form.querySelector('input[name="' + el.name + '"]:checked') : !String(el.value).trim();
        var field = el.closest('.tf');
        if (field) field.classList.toggle('is-invalid', empty);
        if (empty && !bad) bad = el;
      });
      if (bad) {
        e.preventDefault();
        var focus = bad.classList.contains('combo__native') ? bad.parentNode.querySelector('.combo__input') : bad;
        (focus.closest('.tf') || focus).scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { focus.focus({ preventScroll: true }); }, 250);
        return;
      }
      var b = form.querySelector('[type=submit]:not([formnovalidate])');
      if (b) { b.disabled = true; b.classList.add('is-busy'); }
    });
  });
})();
