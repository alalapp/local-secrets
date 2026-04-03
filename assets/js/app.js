/**
 * Local Secrets Manager — JavaScript
 */

$(document).ready(function() {

    // ============ Глобальный поиск ============
    let searchTimeout;
    $('#globalSearch').on('input', function() {
        const query = $(this).val().trim();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            $('#searchResults').hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.get('/local_secrets/api/search.php', { q: query }, function(resp) {
                if (resp.success && resp.data.length > 0) {
                    let html = '';
                    resp.data.forEach(function(s) {
                        const cat = s.category_name
                            ? `<span class="badge ms-2" style="background:${s.category_color || '#666'};font-size:0.7rem">${s.category_name}</span>`
                            : '';
                        html += `<a class="dropdown-item" href="/local_secrets/pages/secret_view.php?id=${s.id}">
                            ${escapeHtml(s.service_name)}${cat}
                        </a>`;
                    });
                    $('#searchResults').html(html).show();
                } else {
                    $('#searchResults').html('<div class="dropdown-item text-muted">Ничего не найдено</div>').show();
                }
            });
        }, 300);
    });

    // Скрыть результаты при клике вне
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#globalSearch, #searchResults').length) {
            $('#searchResults').hide();
        }
    });

    // ============ Копирование в буфер обмена ============
    $(document).on('click', '.copy-field-btn', function() {
        const btn = $(this);
        const fieldId = btn.data('field-id');

        $.get('/local_secrets/api/clipboard.php', { id: fieldId }, function(resp) {
            if (resp.success) {
                navigator.clipboard.writeText(resp.value).then(function() {
                    // Анимация
                    const icon = btn.find('i');
                    icon.removeClass('fa-copy').addClass('fa-check copied-animation');
                    showToast('Скопировано!', 'success');
                    setTimeout(function() {
                        icon.removeClass('fa-check copied-animation').addClass('fa-copy');
                    }, 1500);
                });
            } else {
                showToast('Ошибка: ' + resp.error, 'danger');
            }
        });
    });

    // ============ Показать/скрыть поле в просмотре ============
    $(document).on('click', '.toggle-field-btn', function() {
        const cell = $(this).closest('tr').find('.field-cell');
        const masked = cell.find('.field-masked');
        const value = cell.find('.field-value');
        const icon = $(this).find('i');

        if (masked.is(':visible')) {
            masked.hide();
            value.show();
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            value.hide();
            masked.show();
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ============ Показать/скрыть все поля ============
    $('#toggleAllBtn').on('click', function() {
        const allMasked = $('.field-masked:visible').length > 0;

        if (allMasked) {
            $('.field-masked').hide();
            $('.field-value').show();
            $('.toggle-field-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
            $(this).html('<i class="fas fa-eye-slash me-1"></i> Скрыть все');
        } else {
            $('.field-value').hide();
            $('.field-masked').show();
            $('.toggle-field-btn i').removeClass('fa-eye-slash').addClass('fa-eye');
            $(this).html('<i class="fas fa-eye me-1"></i> Показать все');
        }
    });

    // ============ Форма — добавить поле ============
    $('#addFieldBtn').on('click', function() {
        const template = document.getElementById('fieldRowTemplate');
        if (template) {
            const clone = template.content.cloneNode(true);
            $('#fieldsContainer').append(clone);
        }
    });

    // ============ Форма — удалить поле ============
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.field-row').remove();
    });

    // ============ Форма — toggle visibility в input ============
    $(document).on('click', '.toggle-visibility', function() {
        const input = $(this).closest('.input-group').find('.field-value-input');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ============ Добавить тег из подсказок ============
    $(document).on('click', '.btn-add-tag', function() {
        const tag = $(this).data('tag');
        const input = $('input[name="tags"]');
        const current = input.val().trim();
        const tags = current ? current.split(',').map(t => t.trim()) : [];
        if (!tags.includes(tag)) {
            tags.push(tag);
            input.val(tags.join(', '));
        }
    });

    // ============ Удаление через модалку ============
    $(document).on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#deleteModalBody').text('Удалить "' + name + '"?');
        $('#deleteConfirmBtn').off('click').on('click', function() {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        });
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });

    // ============ Таймаут сессии — предупреждение ============
    const SESSION_TIMEOUT = 1800; // 30 минут
    let lastActivity = Date.now();
    let warningShown = false;

    $(document).on('mousemove keypress click', function() {
        lastActivity = Date.now();
        warningShown = false;
    });

    setInterval(function() {
        const elapsed = (Date.now() - lastActivity) / 1000;
        const remaining = SESSION_TIMEOUT - elapsed;

        if (remaining <= 0) {
            window.location.href = '/local_secrets/login.php?timeout=1';
        } else if (remaining <= 300 && !warningShown) {
            showToast('Сессия истечёт через 5 минут', 'warning');
            warningShown = true;
        }
    }, 30000);
});

// ============ Toast уведомления ============
function showToast(message, type) {
    type = type || 'info';
    const colors = {
        success: '#198754', danger: '#dc3545', warning: '#ffc107', info: '#0dcaf0'
    };
    const toast = document.getElementById('appToast');
    const body = document.getElementById('toastBody');
    body.textContent = message;
    toast.style.borderLeft = '4px solid ' + (colors[type] || colors.info);
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============ Переключение темы ============
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-bs-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('theme', next);
    updateThemeIcon(next);
}

function updateThemeIcon(theme) {
    const icon = document.getElementById('themeIcon');
    if (!icon) return;
    if (theme === 'dark') {
        icon.className = 'fas fa-sun';
    } else {
        icon.className = 'fas fa-moon';
    }
}

// Инициализировать иконку при загрузке
document.addEventListener('DOMContentLoaded', function() {
    updateThemeIcon(localStorage.getItem('theme') || 'dark');
});
