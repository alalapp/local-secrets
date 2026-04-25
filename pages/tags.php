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

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow">Управление</div>
        <h1 class="am-h1"><i class="fas fa-tags am-muted"></i> Теги</h1>
    </div>
    <div class="am-page-head-actions">
        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить все неиспользуемые теги?')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="cleanup">
            <button type="submit" class="am-btn am-btn-ghost am-btn-sm">
                <i class="fas fa-broom"></i> Очистить неиспользуемые
            </button>
        </form>
        <button class="am-btn am-btn-primary am-btn-sm" type="button"
                data-am-modal-open="tagModal" onclick="resetTagForm()">
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
                <th>Название</th>
                <th class="am-td-num">Секретов</th>
                <th class="am-td-actions">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tags)): ?>
                <tr><td colspan="3" class="am-text-center am-muted" style="padding: 32px;">Нет тегов</td></tr>
            <?php else: ?>
                <?php foreach ($tags as $tag): ?>
                    <tr>
                        <td>
                            <span class="am-chip am-chip-tag"><?= htmlspecialchars($tag['name']) ?></span>
                        </td>
                        <td class="am-td-num"><?= (int)$tag['secret_count'] ?></td>
                        <td class="am-td-actions">
                            <span class="am-btn-group">
                                <button class="am-icon-btn is-warning" type="button" title="Переименовать"
                                        onclick='editTag(<?= json_encode($tag, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="am-icon-btn is-info" type="button" title="Объединить с другим тегом"
                                        onclick="mergeTag(<?= (int)$tag['id'] ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-code-merge"></i>
                                </button>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Удалить тег «<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>»?<?= $tag['secret_count'] > 0 ? '\nОн будет отвязан от ' . (int)$tag['secret_count'] . ' секрет(ов).' : '' ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <button type="submit" class="am-icon-btn is-danger" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Модалка создания/переименования -->
<div class="am-modal-backdrop am-hidden" id="tagModal" aria-hidden="true">
    <div class="am-modal" role="dialog" aria-modal="true">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="tagAction" value="create">
            <input type="hidden" name="id" id="tagId">
            <div class="am-modal-head">
                <h3 class="am-modal-title" id="tagModalTitle">Новый тег</h3>
                <button type="button" class="am-modal-close" data-am-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="am-modal-body">
                <div class="am-field">
                    <label class="am-label">Название</label>
                    <input type="text" name="name" id="tagName" class="am-input" required maxlength="50">
                    <div class="am-help">
                        Если такое имя уже существует — теги будут объединены при переименовании.
                    </div>
                </div>
            </div>
            <div class="am-modal-foot">
                <button type="button" class="am-btn am-btn-ghost am-btn-sm" data-am-modal-close>Отмена</button>
                <button type="submit" class="am-btn am-btn-primary am-btn-sm">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модалка слияния -->
<div class="am-modal-backdrop am-hidden" id="mergeModal" aria-hidden="true">
    <div class="am-modal" role="dialog" aria-modal="true">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="merge">
            <input type="hidden" name="source_id" id="mergeSourceId">
            <div class="am-modal-head">
                <h3 class="am-modal-title">Объединить тег «<span id="mergeSourceName"></span>»</h3>
                <button type="button" class="am-modal-close" data-am-modal-close>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="am-modal-body">
                <div class="am-field">
                    <label class="am-label">Целевой тег</label>
                    <select name="target_id" id="mergeTargetId" class="am-select" required>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?= $tag['id'] ?>" data-name="<?= htmlspecialchars($tag['name']) ?>">
                                <?= htmlspecialchars($tag['name']) ?> (<?= (int)$tag['secret_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="am-help">
                        Все секреты исходного тега будут перенесены на целевой, исходный тег будет удалён.
                    </div>
                </div>
            </div>
            <div class="am-modal-foot">
                <button type="button" class="am-btn am-btn-ghost am-btn-sm" data-am-modal-close>Отмена</button>
                <button type="submit" class="am-btn am-btn-primary am-btn-sm">Объединить</button>
            </div>
        </form>
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
    openModal(document.getElementById('tagModal'));
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
    openModal(document.getElementById('mergeModal'));
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
