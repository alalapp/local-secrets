<?php
/**
 * Управление категориями
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();
$error = '';
$success = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $categoryService->create(
                trim($_POST['name']),
                trim($_POST['icon'] ?? '') ?: null,
                trim($_POST['color'] ?? '') ?: null
            );
            $success = 'Категория создана';
        } elseif ($action === 'update') {
            $categoryService->update(
                (int)$_POST['id'],
                trim($_POST['name']),
                trim($_POST['icon'] ?? '') ?: null,
                trim($_POST['color'] ?? '') ?: null
            );
            $success = 'Категория обновлена';
        } elseif ($action === 'delete') {
            $categoryService->delete((int)$_POST['id']);
            $success = 'Категория удалена';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $categories = $categoryService->getAll();
}

$pageTitle = 'Категории';
ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow">Управление</div>
        <h1 class="am-h1"><i class="fas fa-folder-tree am-muted"></i> Категории</h1>
    </div>
    <div class="am-page-head-actions">
        <button class="am-btn am-btn-primary am-btn-sm" type="button"
                data-am-modal-open="categoryModal" onclick="resetCategoryForm()">
            <i class="fas fa-plus"></i> Добавить
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="am-alert am-alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="am-alert am-alert-success">
        <i class="fas fa-circle-check"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<div class="am-table-wrap">
    <table class="am-table">
        <thead>
            <tr>
                <th class="am-td-shrink">Иконка</th>
                <th>Название</th>
                <th>Цвет</th>
                <th class="am-td-num">Секретов</th>
                <th class="am-td-actions">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="am-td-shrink">
                        <i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-folder') ?>"
                           style="color: <?= htmlspecialchars($cat['color'] ?? 'var(--am-text-3)') ?>; font-size: 18px;"></i>
                    </td>
                    <td class="am-fw-500"><?= htmlspecialchars($cat['name']) ?></td>
                    <td>
                        <?php if ($cat['color']): ?>
                            <span class="am-chip am-mono"
                                  style="background:<?= htmlspecialchars($cat['color']) ?>22;color:<?= htmlspecialchars($cat['color']) ?>;border-color:<?= htmlspecialchars($cat['color']) ?>33;">
                                <?= htmlspecialchars($cat['color']) ?>
                            </span>
                        <?php else: ?>
                            <span class="am-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="am-td-num"><?= (int)$cat['secret_count'] ?></td>
                    <td class="am-td-actions">
                        <span class="am-btn-group">
                            <button class="am-icon-btn is-info" type="button" title="Шаблон полей"
                                    onclick="editTemplates(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-list-check"></i>
                            </button>
                            <button class="am-icon-btn is-warning" type="button" title="Редактировать"
                                    onclick="editCategory(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if ($cat['secret_count'] == 0): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Удалить категорию?')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="am-icon-btn is-danger" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Модалка создания/редактирования -->
<div class="am-modal-backdrop am-hidden" id="categoryModal" aria-hidden="true">
    <div class="am-modal" role="dialog" aria-modal="true">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="catAction" value="create">
            <input type="hidden" name="id" id="catId">

            <div class="am-modal-head">
                <h3 class="am-modal-title" id="catModalTitle">Новая категория</h3>
                <button type="button" class="am-modal-close" data-am-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="am-modal-body">
                <div class="am-field">
                    <label class="am-label">Название</label>
                    <input type="text" name="name" id="catName" class="am-input" required>
                </div>

                <div class="am-field">
                    <label class="am-label">Иконка</label>
                    <div class="am-input-with-prefix am-mb-2">
                        <span class="am-icon-preview" id="iconPreview">
                            <i class="fas fa-folder" id="iconPreviewIcon"></i>
                        </span>
                        <input type="text" name="icon" id="catIcon" class="am-input"
                               placeholder="fa-key" readonly>
                    </div>
                    <div class="am-icon-picker">
                        <?php
                        $icons = [
                            'fa-key', 'fa-lock', 'fa-shield-halved', 'fa-user-shield',
                            'fa-building-columns', 'fa-credit-card', 'fa-money-bill', 'fa-wallet',
                            'fa-cloud', 'fa-cloud-arrow-up', 'fa-server', 'fa-hard-drive',
                            'fa-database', 'fa-table', 'fa-warehouse', 'fa-box-archive',
                            'fa-envelope', 'fa-at', 'fa-inbox', 'fa-paper-plane',
                            'fa-fire', 'fa-bolt', 'fa-rocket', 'fa-atom',
                            'fa-globe', 'fa-earth-americas', 'fa-link', 'fa-wifi',
                            'fa-share-nodes', 'fa-hashtag', 'fa-thumbs-up', 'fa-comment',
                            'fa-comments', 'fa-message', 'fa-bell', 'fa-bullhorn',
                            'fa-cube', 'fa-cubes', 'fa-puzzle-piece', 'fa-gear',
                            'fa-code', 'fa-terminal', 'fa-file-code', 'fa-bug',
                            'fa-robot', 'fa-microchip', 'fa-brain', 'fa-wand-magic-sparkles',
                            'fa-users-gear', 'fa-users', 'fa-user-tie', 'fa-building',
                            'fa-shop', 'fa-cart-shopping', 'fa-truck', 'fa-box',
                            'fa-phone', 'fa-mobile-screen', 'fa-laptop', 'fa-desktop',
                            'fa-camera', 'fa-video', 'fa-image', 'fa-palette',
                            'fa-music', 'fa-gamepad', 'fa-book', 'fa-graduation-cap',
                            'fa-eye', 'fa-fingerprint', 'fa-id-card',
                            'fa-folder', 'fa-folder-open', 'fa-file', 'fa-clipboard',
                            'fa-star', 'fa-heart', 'fa-flag', 'fa-bookmark',
                            'fa-chart-line', 'fa-chart-bar', 'fa-chart-pie', 'fa-diagram-project',
                            'fa-circle-nodes', 'fa-network-wired', 'fa-sitemap', 'fa-plug',
                        ];
                        foreach ($icons as $icon): ?>
                            <button type="button" class="am-icon-pick-btn"
                                    data-icon="<?= $icon ?>" title="<?= $icon ?>">
                                <i class="fas <?= $icon ?>"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="am-field am-mb-0">
                    <label class="am-label">Цвет</label>
                    <input type="color" name="color" id="catColor" class="am-input am-input-color" value="#666666">
                </div>
            </div>
            <div class="am-modal-foot">
                <button type="button" class="am-btn am-btn-ghost am-btn-sm" data-am-modal-close>Отмена</button>
                <button type="submit" class="am-btn am-btn-primary am-btn-sm">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модалка редактирования шаблонов полей -->
<div class="am-modal-backdrop am-hidden" id="templatesModal" aria-hidden="true">
    <div class="am-modal am-modal-lg" role="dialog" aria-modal="true">
        <div class="am-modal-head">
            <h3 class="am-modal-title">Шаблон полей: <span id="tplCatName"></span></h3>
            <button type="button" class="am-modal-close" data-am-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="am-modal-body">
            <p class="am-text-sm am-muted am-mb-3">
                Эти поля автоматически подставляются при создании нового секрета в этой категории.
            </p>
            <div id="tplFieldsContainer"></div>
            <button type="button" class="am-btn am-btn-ghost am-btn-sm am-mt-2" id="tplAddFieldBtn">
                <i class="fas fa-plus"></i> Добавить поле
            </button>
        </div>
        <div class="am-modal-foot">
            <button type="button" class="am-btn am-btn-ghost am-btn-sm" data-am-modal-close>Отмена</button>
            <button type="button" class="am-btn am-btn-primary am-btn-sm" id="tplSaveBtn">
                <i class="fas fa-save"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Клик по иконке в picker
    document.querySelectorAll('.am-icon-pick-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const icon = this.dataset.icon;
            document.getElementById('catIcon').value = icon;
            document.getElementById('iconPreviewIcon').className = 'fas ' + icon;
            document.querySelectorAll('.am-icon-pick-btn').forEach(b => b.classList.remove('is-active'));
            this.classList.add('is-active');
        });
    });

    // Add tpl row
    document.getElementById('tplAddFieldBtn').addEventListener('click', function() {
        addTplRow('', 'text', '');
    });

    // Delete tpl row (delegated)
    document.getElementById('tplFieldsContainer').addEventListener('click', function(e) {
        const btn = e.target.closest('.tpl-remove-btn');
        if (btn) btn.closest('.tpl-field-row').remove();
    });

    // Save tpl
    document.getElementById('tplSaveBtn').addEventListener('click', function() {
        const rows = document.querySelectorAll('.tpl-field-row');
        const fields = [];
        rows.forEach(function(row) {
            const name = row.querySelector('.tpl-name').value.trim();
            if (name) {
                fields.push({
                    field_name: name,
                    field_type: row.querySelector('.tpl-type').value,
                    placeholder: row.querySelector('.tpl-placeholder').value.trim()
                });
            }
        });

        fetch('/local_secrets/api/category_templates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' },
            body: JSON.stringify({ category_id: tplCategoryId, fields: fields })
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                closeModal(document.getElementById('templatesModal'));
                showToast('Шаблон сохранён (' + fields.length + ' полей)', 'success');
            } else {
                showToast('Ошибка: ' + resp.error, 'danger');
            }
        });
    });
});

function selectIconInPicker(iconClass) {
    document.querySelectorAll('.am-icon-pick-btn').forEach(b => b.classList.remove('is-active'));
    if (iconClass) {
        const btn = document.querySelector('.am-icon-pick-btn[data-icon="' + iconClass + '"]');
        if (btn) btn.classList.add('is-active');
        document.getElementById('iconPreviewIcon').className = 'fas ' + iconClass;
    } else {
        document.getElementById('iconPreviewIcon').className = 'fas fa-folder';
    }
}

function resetCategoryForm() {
    document.getElementById('catAction').value = 'create';
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catIcon').value = '';
    document.getElementById('catColor').value = '#666666';
    document.getElementById('catModalTitle').textContent = 'Новая категория';
    selectIconInPicker('');
}

function editCategory(cat) {
    document.getElementById('catAction').value = 'update';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catIcon').value = cat.icon || '';
    document.getElementById('catColor').value = cat.color || '#666666';
    document.getElementById('catModalTitle').textContent = 'Редактировать: ' + cat.name;
    selectIconInPicker(cat.icon || '');
    openModal(document.getElementById('categoryModal'));
}

// ============ Шаблоны полей ============
let tplCategoryId = 0;

function editTemplates(catId, catName) {
    tplCategoryId = catId;
    document.getElementById('tplCatName').textContent = catName;
    const container = document.getElementById('tplFieldsContainer');
    container.innerHTML = '<div class="am-loading"><span class="am-spinner"></span> Загрузка…</div>';

    fetch('/local_secrets/api/category_templates.php?category_id=' + encodeURIComponent(catId))
        .then(r => r.json())
        .then(resp => {
            container.innerHTML = '';
            if (resp.success) {
                if (resp.data.length === 0) {
                    addTplRow('', 'text', '');
                } else {
                    resp.data.forEach(f => addTplRow(f.field_name, f.field_type, f.placeholder || ''));
                }
            }
        });

    openModal(document.getElementById('templatesModal'));
}

function addTplRow(name, type, placeholder) {
    const container = document.getElementById('tplFieldsContainer');
    const row = document.createElement('div');
    row.className = 'tpl-field-row am-field-row am-mb-2';
    row.style.gridTemplateColumns = '1fr 140px 1fr 36px';
    row.style.alignItems = 'end';
    row.innerHTML = `
        <div class="am-field am-mb-0">
            <input type="text" class="am-input am-input-sm tpl-name"
                   value="${escapeAttr(name)}" placeholder="Название поля">
        </div>
        <div class="am-field am-mb-0">
            <select class="am-select am-input-sm tpl-type">
                <option value="text" ${type==='text'?'selected':''}>Текст</option>
                <option value="password" ${type==='password'?'selected':''}>Пароль</option>
                <option value="url" ${type==='url'?'selected':''}>URL</option>
                <option value="email" ${type==='email'?'selected':''}>Email</option>
                <option value="token" ${type==='token'?'selected':''}>Токен</option>
                <option value="note" ${type==='note'?'selected':''}>Заметка</option>
            </select>
        </div>
        <div class="am-field am-mb-0">
            <input type="text" class="am-input am-input-sm tpl-placeholder"
                   value="${escapeAttr(placeholder)}" placeholder="Подсказка (placeholder)">
        </div>
        <button type="button" class="am-icon-btn is-danger tpl-remove-btn" title="Удалить">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
