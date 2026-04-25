<?php
/**
 * Форма добавления / редактирования секрета
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();
$tagService = new TagService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$secret = null;
$error = '';
$prefillCatId = (!$isEdit && isset($_GET['cat'])) ? (int)$_GET['cat'] : 0;

if ($isEdit) {
    $secret = $secretService->getById($id);
    if (!$secret) {
        header('Location: /local_secrets/index.php');
        exit;
    }
}

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $data = [
        'service_name' => trim($_POST['service_name'] ?? ''),
        'category_id'  => (int)($_POST['category_id'] ?? 0) ?: null,
        'description'  => trim($_POST['description'] ?? ''),
        'is_favorite'  => isset($_POST['is_favorite']) ? 1 : 0,
        'fields'       => [],
        'tags'         => [],
    ];

    $fieldNames = $_POST['field_name'] ?? [];
    $fieldValues = $_POST['field_value'] ?? [];
    $fieldTypes = $_POST['field_type'] ?? [];
    foreach ($fieldNames as $i => $name) {
        $name = trim($name);
        $value = $fieldValues[$i] ?? '';
        if ($name !== '' && $value !== '') {
            $data['fields'][] = [
                'name'  => $name,
                'value' => $value,
                'type'  => $fieldTypes[$i] ?? 'text',
            ];
        }
    }

    $tagsStr = trim($_POST['tags'] ?? '');
    if ($tagsStr !== '') {
        $data['tags'] = array_map('trim', explode(',', $tagsStr));
    }

    if (empty($data['service_name'])) {
        $error = 'Укажите название сервиса';
    } else {
        try {
            if ($isEdit) {
                $secretService->update($id, $data);
            } else {
                $id = $secretService->create($data);
            }
            header("Location: /local_secrets/pages/secret_view.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            $error = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? "Редактирование: {$secret['service_name']}" : 'Новый секрет';
$popularTags = $tagService->getPopular();

ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-flex am-items-center am-gap-3">
            <a href="<?= $isEdit ? "/local_secrets/pages/secret_view.php?id={$id}" : '/local_secrets/index.php' ?>"
               class="am-back" title="Назад">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="am-eyebrow"><?= $isEdit ? 'Редактирование' : 'Новая запись' ?></div>
                <h1 class="am-h1"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="am-alert am-alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<form method="POST" id="secretForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="am-card am-mb-3">
        <div class="am-field-row cols-3">
            <div class="am-field" style="grid-column: span 2;">
                <label class="am-label">Название сервиса <span class="am-req">*</span></label>
                <input type="text" name="service_name" class="am-input"
                       value="<?= htmlspecialchars($secret['service_name'] ?? $_POST['service_name'] ?? '') ?>"
                       placeholder="OpenAI, Firebase, Sber..." required>
            </div>
            <div class="am-field">
                <label class="am-label">Категория</label>
                <select name="category_id" class="am-select">
                    <option value="">— Без категории —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= ($secret['category_id'] ?? $_POST['category_id'] ?? $prefillCatId ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label class="am-check am-mb-3">
            <input type="checkbox" name="is_favorite" id="isFavorite"
                   <?= ($secret['is_favorite'] ?? false) ? 'checked' : '' ?>>
            <i class="fas fa-star am-text-warning"></i> В избранном
        </label>

        <div class="am-field">
            <label class="am-label">Описание / заметки</label>
            <textarea name="description" class="am-textarea" rows="2"
                      placeholder="Комментарии, инструкции…"><?= htmlspecialchars($secret['description'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="am-field am-mb-0">
            <label class="am-label">Теги <span class="am-muted">(через запятую)</span></label>
            <input type="text" name="tags" class="am-input"
                   value="<?= htmlspecialchars(
                       $isEdit && isset($secret['tags'])
                           ? implode(', ', array_column($secret['tags'], 'name'))
                           : ($_POST['tags'] ?? '')
                   ) ?>"
                   placeholder="api, production, personal…">
            <?php if ($popularTags): ?>
                <div class="am-flex am-flex-wrap am-gap-1 am-mt-2">
                    <?php foreach ($popularTags as $tag): ?>
                        <button type="button" class="am-chip am-chip-tag btn-add-tag"
                                data-tag="<?= htmlspecialchars($tag['name']) ?>">
                            <?= htmlspecialchars($tag['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Поля (ключ-значение) -->
    <div class="am-card am-card-flush am-mb-3">
        <div class="am-card-head">
            <span><i class="fas fa-key"></i> Поля</span>
            <button type="button" class="am-btn am-btn-ghost am-btn-sm" id="addFieldBtn">
                <i class="fas fa-plus"></i> Добавить поле
            </button>
        </div>
        <div class="am-card-body" id="fieldsContainer">
            <?php
            $fields = $secret['fields'] ?? $_POST['field_name'] ?? [];
            if (empty($fields)) {
                $fields = [
                    ['field_name' => 'login', 'field_value' => '', 'field_type' => 'text'],
                    ['field_name' => 'password', 'field_value' => '', 'field_type' => 'password'],
                ];
            }
            foreach ($fields as $i => $field):
                $fname = $field['field_name'] ?? ($field['name'] ?? '');
                $fvalue = $field['field_value'] ?? ($field['value'] ?? '');
                $ftype = $field['field_type'] ?? ($field['type'] ?? 'text');
            ?>
                <div class="field-row am-field-row am-mb-2"
                     style="grid-template-columns: 1fr 2fr 140px 36px; align-items: end;">
                    <div class="am-field am-mb-0">
                        <input type="text" name="field_name[]" class="am-input am-input-sm"
                               value="<?= htmlspecialchars($fname) ?>" placeholder="Название поля">
                    </div>
                    <div class="am-field am-mb-0">
                        <div class="am-input-group">
                            <input type="<?= $ftype === 'password' ? 'password' : 'text' ?>"
                                   name="field_value[]" class="am-input am-input-sm field-value-input"
                                   value="<?= htmlspecialchars($fvalue) ?>" placeholder="Значение">
                            <button type="button" class="am-input-group-addon toggle-visibility"
                                    title="Показать/скрыть">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="am-field am-mb-0">
                        <select name="field_type[]" class="am-select am-input-sm">
                            <option value="text" <?= $ftype === 'text' ? 'selected' : '' ?>>Текст</option>
                            <option value="password" <?= $ftype === 'password' ? 'selected' : '' ?>>Пароль</option>
                            <option value="url" <?= $ftype === 'url' ? 'selected' : '' ?>>URL</option>
                            <option value="email" <?= $ftype === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="token" <?= $ftype === 'token' ? 'selected' : '' ?>>Токен</option>
                            <option value="note" <?= $ftype === 'note' ? 'selected' : '' ?>>Заметка</option>
                        </select>
                    </div>
                    <button type="button" class="am-icon-btn is-danger remove-field" title="Удалить">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="am-flex am-gap-2">
        <button type="submit" class="am-btn am-btn-primary">
            <i class="fas fa-save"></i> <?= $isEdit ? 'Сохранить' : 'Создать' ?>
        </button>
        <a href="<?= $isEdit ? "/local_secrets/pages/secret_view.php?id={$id}" : '/local_secrets/index.php' ?>"
           class="am-btn am-btn-ghost">Отмена</a>
    </div>
</form>

<!-- Шаблон строки поля -->
<template id="fieldRowTemplate">
    <div class="field-row am-field-row am-mb-2"
         style="grid-template-columns: 1fr 2fr 140px 36px; align-items: end;">
        <div class="am-field am-mb-0">
            <input type="text" name="field_name[]" class="am-input am-input-sm" placeholder="Название поля">
        </div>
        <div class="am-field am-mb-0">
            <div class="am-input-group">
                <input type="text" name="field_value[]" class="am-input am-input-sm field-value-input" placeholder="Значение">
                <button type="button" class="am-input-group-addon toggle-visibility" title="Показать/скрыть">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="am-field am-mb-0">
            <select name="field_type[]" class="am-select am-input-sm">
                <option value="text">Текст</option>
                <option value="password">Пароль</option>
                <option value="url">URL</option>
                <option value="email">Email</option>
                <option value="token">Токен</option>
                <option value="note">Заметка</option>
            </select>
        </div>
        <button type="button" class="am-icon-btn is-danger remove-field" title="Удалить">
            <i class="fas fa-times"></i>
        </button>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const prefillCatId = <?= (int)$prefillCatId ?>;
    const categorySelect = document.querySelector('select[name="category_id"]');
    const fieldsContainer = document.getElementById('fieldsContainer');

    function buildRow(name, type, placeholder) {
        const row = document.createElement('div');
        row.className = 'field-row am-field-row am-mb-2';
        row.style.gridTemplateColumns = '1fr 2fr 140px 36px';
        row.style.alignItems = 'end';
        const inputType = type === 'password' ? 'password' : 'text';
        row.innerHTML = `
            <div class="am-field am-mb-0">
                <input type="text" name="field_name[]" class="am-input am-input-sm"
                       value="${escapeAttr(name)}" placeholder="Название поля">
            </div>
            <div class="am-field am-mb-0">
                <div class="am-input-group">
                    <input type="${inputType}" name="field_value[]"
                           class="am-input am-input-sm field-value-input"
                           placeholder="${escapeAttr(placeholder || '')}">
                    <button type="button" class="am-input-group-addon toggle-visibility" title="Показать/скрыть">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="am-field am-mb-0">
                <select name="field_type[]" class="am-select am-input-sm">
                    <option value="text" ${type==='text'?'selected':''}>Текст</option>
                    <option value="password" ${type==='password'?'selected':''}>Пароль</option>
                    <option value="url" ${type==='url'?'selected':''}>URL</option>
                    <option value="email" ${type==='email'?'selected':''}>Email</option>
                    <option value="token" ${type==='token'?'selected':''}>Токен</option>
                    <option value="note" ${type==='note'?'selected':''}>Заметка</option>
                </select>
            </div>
            <button type="button" class="am-icon-btn is-danger remove-field" title="Удалить">
                <i class="fas fa-times"></i>
            </button>
        `;
        return row;
    }

    function loadCategoryTemplate(catId, skipConfirm) {
        if (!catId) return;

        const existingValues = fieldsContainer.querySelectorAll('input[name="field_value[]"]');
        let hasValues = false;
        existingValues.forEach(input => {
            if (input.value.trim()) hasValues = true;
        });

        if (hasValues && !skipConfirm && !confirm('Заменить текущие поля шаблоном категории?')) {
            return;
        }

        fetch('/local_secrets/api/category_templates.php?category_id=' + encodeURIComponent(catId))
            .then(r => r.json())
            .then(resp => {
                if (resp.success && resp.data.length > 0) {
                    fieldsContainer.innerHTML = '';
                    resp.data.forEach(tpl => {
                        fieldsContainer.appendChild(buildRow(tpl.field_name, tpl.field_type, tpl.placeholder || ''));
                    });
                    if (typeof showToast === 'function') {
                        showToast('Загружен шаблон полей для категории', 'info');
                    }
                }
            });
    }

    if (!isEdit && prefillCatId) {
        loadCategoryTemplate(prefillCatId, true);
    }

    if (!isEdit && categorySelect) {
        categorySelect.addEventListener('change', function() {
            const catId = this.value;
            if (catId) loadCategoryTemplate(catId, false);
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
