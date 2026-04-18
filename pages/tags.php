<?php
/**
 * Управление тегами (CRUD)
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();
$tagService = new TagService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $tagService->create(trim($_POST['name'] ?? ''));
            $success = 'Тег создан';
        } elseif ($action === 'rename') {
            $tagService->rename((int)$_POST['id'], trim($_POST['name'] ?? ''));
            $success = 'Тег обновлён';
        } elseif ($action === 'delete') {
            $tagService->delete((int)$_POST['id']);
            $success = 'Тег удалён';
        } elseif ($action === 'merge') {
            $tagService->merge((int)$_POST['source_id'], (int)$_POST['target_id']);
            $success = 'Теги объединены';
        } elseif ($action === 'cleanup') {
            $removed = $tagService->cleanup();
            $success = "Удалено неиспользуемых тегов: {$removed}";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$tags = $tagService->getAll();
$pageTitle = 'Теги';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-tags me-2"></i> Теги</h4>
    <div class="d-flex gap-2">
        <form method="POST" class="d-inline" onsubmit="return confirm('Удалить все неиспользуемые теги?')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="cleanup">
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-broom me-1"></i> Очистить неиспользуемые
            </button>
        </form>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#tagModal"
                onclick="resetTagForm()">
            <i class="fas fa-plus me-1"></i> Добавить
        </button>
    </div>
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
                    <th>Название</th>
                    <th>Секретов</th>
                    <th style="width:220px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tags)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Нет тегов</td></tr>
                <?php else: ?>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td>
                                <span class="badge border border-secondary text-secondary">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </span>
                            </td>
                            <td><?= (int)$tag['secret_count'] ?></td>
                            <td>
                                <button class="btn btn-outline-warning btn-sm"
                                        title="Переименовать"
                                        onclick="editTag(<?= htmlspecialchars(json_encode($tag), ENT_QUOTES) ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm"
                                        title="Объединить с другим тегом"
                                        onclick="mergeTag(<?= (int)$tag['id'] ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-code-merge"></i>
                                </button>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Удалить тег «<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>»?<?= $tag['secret_count'] > 0 ? '\nОн будет отвязан от ' . (int)$tag['secret_count'] . ' секрет(ов).' : '' ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка создания/переименования -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" id="tagAction" value="create">
                <input type="hidden" name="id" id="tagId">
                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalTitle">Новый тег</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Название</label>
                    <input type="text" name="name" id="tagName" class="form-control" required maxlength="50">
                    <small class="text-muted">Если такое имя уже существует при переименовании — теги будут объединены.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка слияния -->
<div class="modal fade" id="mergeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="merge">
                <input type="hidden" name="source_id" id="mergeSourceId">
                <div class="modal-header">
                    <h5 class="modal-title">Объединить тег «<span id="mergeSourceName"></span>»</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Целевой тег</label>
                    <select name="target_id" id="mergeTargetId" class="form-select" required>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?= $tag['id'] ?>" data-name="<?= htmlspecialchars($tag['name']) ?>">
                                <?= htmlspecialchars($tag['name']) ?> (<?= (int)$tag['secret_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-2">
                        Все секреты исходного тега будут перенесены на целевой, исходный тег будет удалён.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Объединить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetTagForm() {
    document.getElementById('tagAction').value = 'create';
    document.getElementById('tagId').value = '';
    document.getElementById('tagName').value = '';
    document.getElementById('tagModalTitle').textContent = 'Новый тег';
}

function editTag(tag) {
    document.getElementById('tagAction').value = 'rename';
    document.getElementById('tagId').value = tag.id;
    document.getElementById('tagName').value = tag.name;
    document.getElementById('tagModalTitle').textContent = 'Переименовать: ' + tag.name;
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}

function mergeTag(id, name) {
    document.getElementById('mergeSourceId').value = id;
    document.getElementById('mergeSourceName').textContent = name;
    const select = document.getElementById('mergeTargetId');
    Array.from(select.options).forEach(function(opt) {
        opt.hidden = parseInt(opt.value, 10) === id;
        opt.disabled = opt.hidden;
    });
    const firstVisible = Array.from(select.options).find(o => !o.hidden);
    if (firstVisible) select.value = firstVisible.value;
    new bootstrap.Modal(document.getElementById('mergeModal')).show();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
