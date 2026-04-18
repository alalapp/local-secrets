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

    // Собрать поля
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

    // Собрать теги
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

<div class="d-flex align-items-center mb-3">
    <a href="<?= $isEdit ? "/local_secrets/pages/secret_view.php?id={$id}" : '/local_secrets/index.php' ?>"
       class="btn btn-outline-secondary btn-sm me-3">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h4 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="secretForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Название сервиса <span class="text-danger">*</span></label>
                    <input type="text" name="service_name" class="form-control"
                           value="<?= htmlspecialchars($secret['service_name'] ?? $_POST['service_name'] ?? '') ?>"
                           placeholder="OpenAI, Firebase, Sber..." required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Категория</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Без категории —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($secret['category_id'] ?? $_POST['category_id'] ?? $prefillCatId ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="is_favorite" class="form-check-input" id="isFavorite"
                               <?= ($secret['is_favorite'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isFavorite">
                            <i class="fas fa-star text-warning"></i> Избранное
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Описание / Заметки</label>
                    <textarea name="description" class="form-control" rows="2"
                              placeholder="Комментарии, инструкции..."><?= htmlspecialchars($secret['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Теги <span class="text-muted small">(через запятую)</span></label>
                    <input type="text" name="tags" class="form-control"
                           value="<?= htmlspecialchars(
                               $isEdit && isset($secret['tags'])
                                   ? implode(', ', array_column($secret['tags'], 'name'))
                                   : ($_POST['tags'] ?? '')
                           ) ?>"
                           placeholder="api, production, personal...">
                    <?php if ($popularTags): ?>
                        <div class="mt-1">
                            <?php foreach ($popularTags as $tag): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 me-1 mt-1 btn-add-tag"
                                        data-tag="<?= htmlspecialchars($tag['name']) ?>">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Поля (ключ-значение) -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-key me-2"></i> Поля</span>
            <button type="button" class="btn btn-success btn-sm" id="addFieldBtn">
                <i class="fas fa-plus me-1"></i> Добавить поле
            </button>
        </div>
        <div class="card-body" id="fieldsContainer">
            <?php
            $fields = $secret['fields'] ?? $_POST['field_name'] ?? [];
            if (empty($fields)) {
                // Пустые поля по умолчанию для нового секрета
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
                <div class="row g-2 mb-2 field-row">
                    <div class="col-md-3">
                        <input type="text" name="field_name[]" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($fname) ?>" placeholder="Название поля">
                    </div>
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <input type="<?= $ftype === 'password' ? 'password' : 'text' ?>"
                                   name="field_value[]" class="form-control field-value-input"
                                   value="<?= htmlspecialchars($fvalue) ?>" placeholder="Значение">
                            <button type="button" class="btn btn-outline-secondary toggle-visibility"
                                    title="Показать/скрыть">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="field_type[]" class="form-select form-select-sm">
                            <option value="text" <?= $ftype === 'text' ? 'selected' : '' ?>>Текст</option>
                            <option value="password" <?= $ftype === 'password' ? 'selected' : '' ?>>Пароль</option>
                            <option value="url" <?= $ftype === 'url' ? 'selected' : '' ?>>URL</option>
                            <option value="email" <?= $ftype === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="token" <?= $ftype === 'token' ? 'selected' : '' ?>>Токен</option>
                            <option value="note" <?= $ftype === 'note' ? 'selected' : '' ?>>Заметка</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-field">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Сохранить' : 'Создать' ?>
        </button>
        <a href="<?= $isEdit ? "/local_secrets/pages/secret_view.php?id={$id}" : '/local_secrets/index.php' ?>"
           class="btn btn-secondary">Отмена</a>
    </div>
</form>

<!-- Шаблон строки поля -->
<template id="fieldRowTemplate">
    <div class="row g-2 mb-2 field-row">
        <div class="col-md-3">
            <input type="text" name="field_name[]" class="form-control form-control-sm" placeholder="Название поля">
        </div>
        <div class="col-md-6">
            <div class="input-group input-group-sm">
                <input type="text" name="field_value[]" class="form-control field-value-input" placeholder="Значение">
                <button type="button" class="btn btn-outline-secondary toggle-visibility" title="Показать/скрыть">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="col-md-2">
            <select name="field_type[]" class="form-select form-select-sm">
                <option value="text">Текст</option>
                <option value="password">Пароль</option>
                <option value="url">URL</option>
                <option value="email">Email</option>
                <option value="token">Токен</option>
                <option value="note">Заметка</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-field">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const prefillCatId = <?= (int)$prefillCatId ?>;
    const categorySelect = document.querySelector('select[name="category_id"]');

    function loadCategoryTemplate(catId, skipConfirm) {
        if (!catId) return;

        const existingValues = document.querySelectorAll('#fieldsContainer input[name="field_value[]"]');
        let hasValues = false;
        existingValues.forEach(input => {
            if (input.value.trim()) hasValues = true;
        });

        if (hasValues && !skipConfirm && !confirm('Заменить текущие поля шаблоном категории?')) {
            return;
        }

        $.get('/local_secrets/api/category_templates.php', { category_id: catId }, function(resp) {
            if (resp.success && resp.data.length > 0) {
                $('#fieldsContainer').empty();
                resp.data.forEach(function(tpl) {
                    const inputType = tpl.field_type === 'password' ? 'password' : 'text';
                    const row = `<div class="row g-2 mb-2 field-row">
                        <div class="col-md-3">
                            <input type="text" name="field_name[]" class="form-control form-control-sm"
                                   value="${escapeAttr(tpl.field_name)}" placeholder="Название поля">
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <input type="${inputType}" name="field_value[]"
                                       class="form-control field-value-input"
                                       placeholder="${escapeAttr(tpl.placeholder || '')}">
                                <button type="button" class="btn btn-outline-secondary toggle-visibility" title="Показать/скрыть">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="field_type[]" class="form-select form-select-sm">
                                <option value="text" ${tpl.field_type==='text'?'selected':''}>Текст</option>
                                <option value="password" ${tpl.field_type==='password'?'selected':''}>Пароль</option>
                                <option value="url" ${tpl.field_type==='url'?'selected':''}>URL</option>
                                <option value="email" ${tpl.field_type==='email'?'selected':''}>Email</option>
                                <option value="token" ${tpl.field_type==='token'?'selected':''}>Токен</option>
                                <option value="note" ${tpl.field_type==='note'?'selected':''}>Заметка</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-field">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>`;
                    $('#fieldsContainer').append(row);
                });
                if (typeof showToast === 'function') showToast('Загружен шаблон полей для категории', 'info');
            }
        });
    }

    if (!isEdit && prefillCatId) {
        loadCategoryTemplate(prefillCatId, true);
    }

    if (!isEdit && categorySelect) {
        categorySelect.addEventListener('change', function() {
            const catId = this.value;
            if (!catId) return;

            // Проверить, есть ли уже заполненные значения
            const existingValues = document.querySelectorAll('#fieldsContainer input[name="field_value[]"]');
            let hasValues = false;
            existingValues.forEach(input => {
                if (input.value.trim()) hasValues = true;
            });

            if (hasValues && !confirm('Заменить текущие поля шаблоном категории?')) {
                return;
            }

            $.get('/local_secrets/api/category_templates.php', { category_id: catId }, function(resp) {
                if (resp.success && resp.data.length > 0) {
                    $('#fieldsContainer').empty();
                    resp.data.forEach(function(tpl) {
                        const inputType = tpl.field_type === 'password' ? 'password' : 'text';
                        const row = `<div class="row g-2 mb-2 field-row">
                            <div class="col-md-3">
                                <input type="text" name="field_name[]" class="form-control form-control-sm"
                                       value="${escapeAttr(tpl.field_name)}" placeholder="Название поля">
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <input type="${inputType}" name="field_value[]"
                                           class="form-control field-value-input"
                                           placeholder="${escapeAttr(tpl.placeholder || '')}">
                                    <button type="button" class="btn btn-outline-secondary toggle-visibility" title="Показать/скрыть">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="field_type[]" class="form-select form-select-sm">
                                    <option value="text" ${tpl.field_type==='text'?'selected':''}>Текст</option>
                                    <option value="password" ${tpl.field_type==='password'?'selected':''}>Пароль</option>
                                    <option value="url" ${tpl.field_type==='url'?'selected':''}>URL</option>
                                    <option value="email" ${tpl.field_type==='email'?'selected':''}>Email</option>
                                    <option value="token" ${tpl.field_type==='token'?'selected':''}>Токен</option>
                                    <option value="note" ${tpl.field_type==='note'?'selected':''}>Заметка</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-field">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>`;
                        $('#fieldsContainer').append(row);
                    });
                    showToast('Загружен шаблон полей для категории', 'info');
                }
            });
        });
    }

    function escapeAttr(s) {
        if (!s) return '';
        return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;');
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
