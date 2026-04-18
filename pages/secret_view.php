<?php
/**
 * Просмотр секрета (с маскировкой значений)
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$id = (int)($_GET['id'] ?? 0);
$secret = $secretService->getById($id);

if (!$secret) {
    header('Location: /local_secrets/index.php');
    exit;
}

Logger::log('view', 'secret', $id, "Просмотр: {$secret['service_name']}");

$pageTitle = $secret['service_name'];

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
        <a href="/local_secrets/index.php<?= $secret['category_id'] ? '?cat=' . $secret['category_id'] : '' ?>" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h4 class="mb-0">
            <?php if ($secret['is_favorite']): ?>
                <i class="fas fa-star text-warning me-1"></i>
            <?php endif; ?>
            <?= htmlspecialchars($secret['service_name']) ?>
        </h4>
        <?php if ($secret['category_name']): ?>
            <span class="badge ms-2" style="background-color: <?= htmlspecialchars($secret['category_color'] ?? '#666') ?>">
                <i class="fas <?= htmlspecialchars($secret['category_icon'] ?? 'fa-folder') ?> me-1"></i>
                <?= htmlspecialchars($secret['category_name']) ?>
            </span>
        <?php endif; ?>
    </div>
    <div>
        <a href="/local_secrets/pages/secret_form.php?id=<?= $id ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-pen me-1"></i> Редактировать
        </a>
    </div>
</div>

<?php if ($secret['description']): ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <small class="text-muted"><?= nl2br(htmlspecialchars($secret['description'])) ?></small>
        </div>
    </div>
<?php endif; ?>

<?php if ($secret['tags']): ?>
    <div class="mb-3">
        <?php foreach ($secret['tags'] as $tag): ?>
            <span class="badge border border-secondary text-secondary me-1"><?= htmlspecialchars($tag['name']) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Поля секрета -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-key me-2"></i> Поля
        <button class="btn btn-outline-info btn-sm float-end" id="toggleAllBtn" title="Показать/скрыть все">
            <i class="fas fa-eye me-1"></i> Показать все
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <tbody>
                <?php foreach ($secret['fields'] as $field): ?>
                    <tr>
                        <td style="width:200px" class="fw-semibold">
                            <?php
                            $icon = match($field['field_type']) {
                                'password' => 'fa-lock',
                                'url'      => 'fa-link',
                                'email'    => 'fa-envelope',
                                'token'    => 'fa-key',
                                'note'     => 'fa-sticky-note',
                                default    => 'fa-font',
                            };
                            ?>
                            <i class="fas <?= $icon ?> me-2 text-muted"></i>
                            <?= htmlspecialchars($field['field_name']) ?>
                        </td>
                        <td class="field-cell" data-field-id="<?= $field['id'] ?>">
                            <span class="field-masked">
                                <?= str_repeat('*', min(20, max(8, strlen($field['field_value'])))) ?>
                            </span>
                            <span class="field-value" style="display:none; word-break:break-all;">
                                <?= htmlspecialchars($field['field_value']) ?>
                            </span>
                        </td>
                        <td style="width:100px" class="text-end">
                            <button class="btn btn-outline-secondary btn-sm toggle-field-btn" title="Показать">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-info btn-sm copy-field-btn"
                                    data-field-id="<?= $field['id'] ?>" title="Копировать">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-muted small mt-3">
    Создан: <?= date('d.m.Y H:i', strtotime($secret['created_at'])) ?>
    &middot; Обновлён: <?= date('d.m.Y H:i', strtotime($secret['updated_at'])) ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
