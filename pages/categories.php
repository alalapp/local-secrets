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

    // Перезагрузить
    $categories = $categoryService->getAll();
}

$pageTitle = 'Категории';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-folder-tree me-2"></i> Категории</h4>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal"
            onclick="resetCategoryForm()">
        <i class="fas fa-plus me-1"></i> Добавить
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Иконка</th>
                    <th>Название</th>
                    <th>Цвет</th>
                    <th>Секретов</th>
                    <th style="width:120px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-folder') ?> fa-lg"
                               style="color: <?= htmlspecialchars($cat['color'] ?? '#888') ?>"></i>
                        </td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td>
                            <?php if ($cat['color']): ?>
                                <span class="badge" style="background-color: <?= htmlspecialchars($cat['color']) ?>">
                                    <?= htmlspecialchars($cat['color']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $cat['secret_count'] ?></td>
                        <td>
                            <button class="btn btn-outline-info btn-sm" title="Шаблон полей"
                                    onclick="editTemplates(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-list-check"></i>
                            </button>
                            <button class="btn btn-outline-warning btn-sm"
                                    onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if ($cat['secret_count'] == 0): ?>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Удалить категорию?')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка создания/редактирования -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" id="catAction" value="create">
                <input type="hidden" name="id" id="catId">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalTitle">Новая категория</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название</label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Иконка</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text" id="iconPreview">
                                <i class="fas fa-folder" id="iconPreviewIcon"></i>
                            </span>
                            <input type="text" name="icon" id="catIcon" class="form-control"
                                   placeholder="fa-key" readonly>
                        </div>
                        <div class="icon-picker-grid border rounded p-2" style="max-height:200px; overflow-y:auto;">
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
                                'fa-shield-halved', 'fa-eye', 'fa-fingerprint', 'fa-id-card',
                                'fa-folder', 'fa-folder-open', 'fa-file', 'fa-clipboard',
                                'fa-star', 'fa-heart', 'fa-flag', 'fa-bookmark',
                                'fa-chart-line', 'fa-chart-bar', 'fa-chart-pie', 'fa-diagram-project',
                                'fa-circle-nodes', 'fa-network-wired', 'fa-sitemap', 'fa-plug',
                            ];
                            foreach ($icons as $icon): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm m-1 icon-pick-btn"
                                        data-icon="<?= $icon ?>" style="width:38px;height:38px;" title="<?= $icon ?>">
                                    <i class="fas <?= $icon ?>"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цвет</label>
                        <input type="color" name="color" id="catColor" class="form-control form-control-color">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка редактирования шаблонов полей -->
<div class="modal fade" id="templatesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Шаблон полей: <span id="tplCatName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Эти поля автоматически подставляются при создании нового секрета в этой категории.
                </p>
                <div id="tplFieldsContainer"></div>
                <button type="button" class="btn btn-outline-success btn-sm mt-2" id="tplAddFieldBtn">
                    <i class="fas fa-plus me-1"></i> Добавить поле
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="tplSaveBtn">
                    <i class="fas fa-save me-1"></i> Сохранить
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Клик по иконке в picker
    document.querySelectorAll('.icon-pick-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const icon = this.dataset.icon;
            document.getElementById('catIcon').value = icon;
            document.getElementById('iconPreviewIcon').className = 'fas ' + icon;
            // Подсветить выбранную
            document.querySelectorAll('.icon-pick-btn').forEach(b => b.classList.remove('btn-info', 'active'));
            this.classList.add('btn-info', 'active');
        });
    });
});

function selectIconInPicker(iconClass) {
    document.querySelectorAll('.icon-pick-btn').forEach(b => b.classList.remove('btn-info', 'active'));
    if (iconClass) {
        const btn = document.querySelector('.icon-pick-btn[data-icon="' + iconClass + '"]');
        if (btn) btn.classList.add('btn-info', 'active');
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

// ============ Шаблоны полей ============
let tplCategoryId = 0;

function editTemplates(catId, catName) {
    tplCategoryId = catId;
    document.getElementById('tplCatName').textContent = catName;
    const container = document.getElementById('tplFieldsContainer');
    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';

    $.get('/local_secrets/api/category_templates.php', { category_id: catId }, function(resp) {
        container.innerHTML = '';
        if (resp.success) {
            if (resp.data.length === 0) {
                addTplRow('', 'text', '');
            } else {
                resp.data.forEach(function(f) {
                    addTplRow(f.field_name, f.field_type, f.placeholder || '');
                });
            }
        }
    });

    new bootstrap.Modal(document.getElementById('templatesModal')).show();
}

function addTplRow(name, type, placeholder) {
    const container = document.getElementById('tplFieldsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 tpl-field-row';
    row.innerHTML = `
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm tpl-name"
                   value="${escapeAttr(name)}" placeholder="Название поля">
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm tpl-type">
                <option value="text" ${type==='text'?'selected':''}>Текст</option>
                <option value="password" ${type==='password'?'selected':''}>Пароль</option>
                <option value="url" ${type==='url'?'selected':''}>URL</option>
                <option value="email" ${type==='email'?'selected':''}>Email</option>
                <option value="token" ${type==='token'?'selected':''}>Токен</option>
                <option value="note" ${type==='note'?'selected':''}>Заметка</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm tpl-placeholder"
                   value="${escapeAttr(placeholder)}" placeholder="Подсказка (placeholder)">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100 tpl-remove-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function escapeAttr(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;');
}

document.addEventListener('DOMContentLoaded', function() {
    // Добавить поле в шаблон
    document.getElementById('tplAddFieldBtn').addEventListener('click', function() {
        addTplRow('', 'text', '');
    });

    // Удалить поле из шаблона
    document.getElementById('tplFieldsContainer').addEventListener('click', function(e) {
        const btn = e.target.closest('.tpl-remove-btn');
        if (btn) {
            btn.closest('.tpl-field-row').remove();
        }
    });

    // Сохранить шаблон
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

        $.ajax({
            url: '/local_secrets/api/category_templates.php',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            data: JSON.stringify({ category_id: tplCategoryId, fields: fields }),
            success: function(resp) {
                if (resp.success) {
                    bootstrap.Modal.getInstance(document.getElementById('templatesModal')).hide();
                    showToast('Шаблон сохранён (' + fields.length + ' полей)', 'success');
                } else {
                    showToast('Ошибка: ' + resp.error, 'danger');
                }
            }
        });
    });
});

function editCategory(cat) {
    document.getElementById('catAction').value = 'update';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catIcon').value = cat.icon || '';
    document.getElementById('catColor').value = cat.color || '#666666';
    document.getElementById('catModalTitle').textContent = 'Редактировать: ' + cat.name;
    selectIconInPicker(cat.icon || '');
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
