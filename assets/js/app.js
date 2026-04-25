/**
 * Local Secrets — vanilla JS (no jQuery, no Bootstrap)
 */
(function () {
  'use strict';

  // ---------- helpers ----------
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }
  function escapeAttr(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  window.escapeHtml = escapeHtml;
  window.escapeAttr = escapeAttr;

  // ---------- toasts ----------
  function ensureToastContainer() {
    let c = $('#amToasts');
    if (!c) {
      c = document.createElement('div');
      c.className = 'am-toasts';
      c.id = 'amToasts';
      document.body.appendChild(c);
    }
    return c;
  }
  function showToast(message, type) {
    type = type || 'success';
    const map = { success: 'success', danger: 'danger', warning: 'warning', info: 'info', primary: 'success' };
    const cls = map[type] || 'success';
    const c = ensureToastContainer();
    const t = document.createElement('div');
    t.className = 'am-toast ' + cls;
    t.innerHTML = '<span class="am-toast-dot"></span><span></span>';
    t.lastElementChild.textContent = message;
    c.appendChild(t);
    setTimeout(() => {
      t.style.opacity = '0';
      t.style.transition = 'opacity .2s';
      setTimeout(() => t.remove(), 200);
    }, 3200);
  }
  window.showToast = showToast;

  // ---------- confirm modal ----------
  function confirmDialog(opts) {
    return new Promise((resolve) => {
      const backdrop = document.createElement('div');
      backdrop.className = 'am-modal-backdrop';
      backdrop.innerHTML =
        '<div class="am-modal" role="dialog" aria-modal="true">' +
          '<div class="am-modal-head">' +
            '<h3 class="am-modal-title"></h3>' +
            '<button type="button" class="am-modal-close" aria-label="Закрыть"><i class="fas fa-times"></i></button>' +
          '</div>' +
          '<div class="am-modal-body"></div>' +
          '<div class="am-modal-foot">' +
            '<button type="button" class="am-btn am-btn-ghost am-btn-sm" data-act="cancel">Отмена</button>' +
            '<button type="button" class="am-btn am-btn-danger am-btn-sm" data-act="ok"></button>' +
          '</div>' +
        '</div>';
      backdrop.querySelector('.am-modal-title').textContent = opts.title || 'Подтвердите действие';
      backdrop.querySelector('.am-modal-body').textContent = opts.text || '';
      backdrop.querySelector('[data-act="ok"]').textContent = opts.okText || 'Удалить';

      function close(result) {
        backdrop.remove();
        document.removeEventListener('keydown', onKey);
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') close(false);
        if (e.key === 'Enter') close(true);
      }
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) close(false);
      });
      backdrop.querySelector('.am-modal-close').addEventListener('click', () => close(false));
      backdrop.querySelector('[data-act="cancel"]').addEventListener('click', () => close(false));
      backdrop.querySelector('[data-act="ok"]').addEventListener('click', () => close(true));
      document.addEventListener('keydown', onKey);
      document.body.appendChild(backdrop);
      backdrop.querySelector('[data-act="ok"]').focus();
    });
  }
  window.confirmDialog = confirmDialog;

  // ---------- modal helpers ----------
  function openModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('am-hidden');
    modalEl.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const onKey = (e) => {
      if (e.key === 'Escape') closeModal(modalEl);
    };
    modalEl._amKeyHandler = onKey;
    document.addEventListener('keydown', onKey);
    const firstInput = modalEl.querySelector('input:not([type=hidden]), textarea, select, button');
    if (firstInput) setTimeout(() => firstInput.focus(), 50);
  }
  function closeModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.add('am-hidden');
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (modalEl._amKeyHandler) {
      document.removeEventListener('keydown', modalEl._amKeyHandler);
      delete modalEl._amKeyHandler;
    }
  }
  window.openModal = openModal;
  window.closeModal = closeModal;

  // Wire up data-am-modal-* attributes
  document.addEventListener('click', function (e) {
    // Trigger
    const trigger = e.target.closest('[data-am-modal-open]');
    if (trigger) {
      const id = trigger.getAttribute('data-am-modal-open');
      const m = document.getElementById(id);
      if (m) openModal(m);
      return;
    }
    // Close
    const closer = e.target.closest('[data-am-modal-close]');
    if (closer) {
      const m = closer.closest('.am-modal-backdrop');
      if (m) closeModal(m);
      return;
    }
    // Backdrop click
    if (e.target.classList && e.target.classList.contains('am-modal-backdrop')) {
      closeModal(e.target);
    }
  });

  // ---------- theme ----------
  function setTheme(theme) {
    document.documentElement.setAttribute('data-am-theme', theme);
    try { localStorage.setItem('theme', theme); } catch (_) {}
    const icon = $('#themeIcon');
    if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  }
  window.toggleTheme = function () {
    const current = document.documentElement.getAttribute('data-am-theme') || 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
  };

  // ---------- avatar dropdown ----------
  function setupDropdowns() {
    document.addEventListener('click', function (e) {
      const trigger = e.target.closest('[data-am-dropdown-toggle]');
      if (trigger) {
        const id = trigger.getAttribute('data-am-dropdown-toggle');
        const dd = document.getElementById(id);
        if (dd) {
          $$('.am-dropdown.is-open').forEach((d) => { if (d !== dd) d.classList.remove('is-open'); });
          dd.classList.toggle('is-open');
        }
        e.stopPropagation();
        return;
      }
      // close on outside click
      if (!e.target.closest('.am-dropdown')) {
        $$('.am-dropdown.is-open').forEach((d) => d.classList.remove('is-open'));
      }
    });
  }

  // ---------- sidebar toggle (mobile) ----------
  function setupBurger() {
    const burger = $('#amBurger');
    const shell = $('.am-shell');
    if (!burger || !shell) return;
    burger.addEventListener('click', function (e) {
      e.stopPropagation();
      shell.classList.toggle('is-sidebar-open');
    });
    document.addEventListener('click', function (e) {
      if (!shell.classList.contains('is-sidebar-open')) return;
      if (!e.target.closest('.am-sidebar') && !e.target.closest('#amBurger')) {
        shell.classList.remove('is-sidebar-open');
      }
    });
  }

  // ---------- global search ----------
  function setupSearch() {
    const input = $('#globalSearch');
    const results = $('#searchResults');
    if (!input || !results) return;

    let timer;
    input.addEventListener('input', function () {
      const q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { results.classList.remove('is-open'); results.innerHTML = ''; return; }
      timer = setTimeout(() => {
        fetch('/local_secrets/api/search.php?q=' + encodeURIComponent(q))
          .then((r) => r.json())
          .then((resp) => {
            results.innerHTML = '';
            if (resp.success && resp.data && resp.data.length > 0) {
              resp.data.forEach((s) => {
                const a = document.createElement('a');
                a.className = 'am-search-result';
                a.href = '/local_secrets/pages/secret_view.php?id=' + s.id;
                let html = '<i class="fas fa-key am-muted"></i>' +
                           '<span class="am-flex-1">' + escapeHtml(s.service_name) + '</span>';
                if (s.category_name) {
                  html += '<span class="am-chip am-chip-cat" style="background:' + escapeAttr(s.category_color || '#888') + '20;color:' + escapeAttr(s.category_color || '#888') + ';border-color:' + escapeAttr(s.category_color || '#888') + '40">' +
                          escapeHtml(s.category_name) + '</span>';
                }
                a.innerHTML = html;
                results.appendChild(a);
              });
            } else {
              const empty = document.createElement('div');
              empty.className = 'am-search-empty';
              empty.textContent = 'Ничего не найдено';
              results.appendChild(empty);
            }
            results.classList.add('is-open');
          })
          .catch(() => {});
      }, 300);
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('#globalSearch') && !e.target.closest('#searchResults')) {
        results.classList.remove('is-open');
      }
    });
  }

  // ---------- secret_view: toggle / copy ----------
  function setupSecretView() {
    document.addEventListener('click', function (e) {
      const toggleBtn = e.target.closest('.toggle-field-btn');
      if (toggleBtn) {
        const cell = toggleBtn.closest('tr').querySelector('.field-cell');
        if (!cell) return;
        const masked = cell.querySelector('.field-masked');
        const value = cell.querySelector('.field-value');
        const icon = toggleBtn.querySelector('i');
        const showing = value && value.style.display !== 'none' && value.classList.contains('is-shown');
        if (showing) {
          value.style.display = 'none'; value.classList.remove('is-shown');
          if (masked) masked.style.display = '';
          if (icon) icon.className = 'fas fa-eye';
        } else {
          if (masked) masked.style.display = 'none';
          if (value) { value.style.display = ''; value.classList.add('is-shown'); }
          if (icon) icon.className = 'fas fa-eye-slash';
        }
      }

      const copyBtn = e.target.closest('.copy-field-btn');
      if (copyBtn) {
        const fieldId = copyBtn.getAttribute('data-field-id');
        if (!fieldId) return;
        fetch('/local_secrets/api/clipboard.php?id=' + encodeURIComponent(fieldId))
          .then((r) => r.json())
          .then((resp) => {
            if (resp.success) {
              navigator.clipboard.writeText(resp.value).then(() => {
                const i = copyBtn.querySelector('i');
                if (i) {
                  i.className = 'fas fa-check';
                  setTimeout(() => { i.className = 'fas fa-copy'; }, 1200);
                }
                showToast('Скопировано', 'success');
              }).catch(() => showToast('Не удалось скопировать', 'danger'));
            } else {
              showToast('Ошибка: ' + (resp.error || 'unknown'), 'danger');
            }
          })
          .catch(() => showToast('Сетевая ошибка', 'danger'));
      }
    });

    const toggleAll = $('#toggleAllBtn');
    if (toggleAll) {
      toggleAll.addEventListener('click', function () {
        const valueShown = $$('.field-value.is-shown').length > 0;
        if (valueShown) {
          $$('.field-value').forEach((v) => { v.style.display = 'none'; v.classList.remove('is-shown'); });
          $$('.field-masked').forEach((m) => { m.style.display = ''; });
          $$('.toggle-field-btn i').forEach((i) => { i.className = 'fas fa-eye'; });
          toggleAll.innerHTML = '<i class="fas fa-eye"></i> Показать все';
        } else {
          $$('.field-value').forEach((v) => { v.style.display = ''; v.classList.add('is-shown'); });
          $$('.field-masked').forEach((m) => { m.style.display = 'none'; });
          $$('.toggle-field-btn i').forEach((i) => { i.className = 'fas fa-eye-slash'; });
          toggleAll.innerHTML = '<i class="fas fa-eye-slash"></i> Скрыть все';
        }
      });
    }
  }

  // ---------- secret_form: dynamic field rows + toggle visibility ----------
  function setupSecretForm() {
    const addBtn = $('#addFieldBtn');
    const container = $('#fieldsContainer');
    const tpl = $('#fieldRowTemplate');
    if (addBtn && container && tpl) {
      addBtn.addEventListener('click', function () {
        const clone = tpl.content.cloneNode(true);
        container.appendChild(clone);
      });
    }

    document.addEventListener('click', function (e) {
      const removeBtn = e.target.closest('.remove-field');
      if (removeBtn) {
        const row = removeBtn.closest('.field-row');
        if (row) row.remove();
      }

      const toggleVis = e.target.closest('.toggle-visibility');
      if (toggleVis) {
        const group = toggleVis.closest('.am-input-group');
        if (!group) return;
        const inp = group.querySelector('.field-value-input');
        const icon = toggleVis.querySelector('i');
        if (!inp) return;
        if (inp.type === 'password') {
          inp.type = 'text';
          if (icon) icon.className = 'fas fa-eye-slash';
        } else {
          inp.type = 'password';
          if (icon) icon.className = 'fas fa-eye';
        }
      }

      const tagBtn = e.target.closest('.btn-add-tag');
      if (tagBtn) {
        const tag = tagBtn.getAttribute('data-tag');
        const input = $('input[name="tags"]');
        if (!input || !tag) return;
        const current = input.value.trim();
        const tags = current ? current.split(',').map((t) => t.trim()) : [];
        if (!tags.includes(tag)) {
          tags.push(tag);
          input.value = tags.join(', ');
        }
      }
    });
  }

  // ---------- delete via modal ----------
  function setupDeleteButtons() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-delete');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name') || 'эту запись';
      confirmDialog({
        title: 'Подтвердите удаление',
        text: 'Удалить «' + name + '»?',
        okText: 'Удалить'
      }).then((ok) => {
        if (!ok) return;
        const form = $('#deleteForm');
        const idInput = $('#deleteId');
        if (!form || !idInput) return;
        idInput.value = id;
        form.submit();
      });
    });
  }

  // ---------- session timeout ----------
  function setupSessionTimeout() {
    if (typeof window.SESSION_TIMEOUT !== 'number') return;
    const TIMEOUT = window.SESSION_TIMEOUT;
    let lastActivity = Date.now();
    let warned = false;
    ['mousemove', 'keypress', 'click'].forEach((evt) => {
      document.addEventListener(evt, () => { lastActivity = Date.now(); warned = false; });
    });
    setInterval(function () {
      const elapsed = (Date.now() - lastActivity) / 1000;
      const remaining = TIMEOUT - elapsed;
      if (remaining <= 0) {
        window.location.href = '/local_secrets/login.php?timeout=1';
      } else if (remaining <= 300 && !warned) {
        showToast('Сессия истечёт через 5 минут', 'warning');
        warned = true;
      }
    }, 30000);
  }

  // ---------- alert auto-dismiss ----------
  function setupAlertClose() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.am-alert-close');
      if (btn) {
        const a = btn.closest('.am-alert');
        if (a) a.remove();
      }
    });
  }

  // ---------- init ----------
  function init() {
    setTheme(localStorage.getItem('theme') || 'light');
    setupDropdowns();
    setupBurger();
    setupSearch();
    setupSecretView();
    setupSecretForm();
    setupDeleteButtons();
    setupSessionTimeout();
    setupAlertClose();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
