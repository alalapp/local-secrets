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
$secretService->incrementViewCount($id);

$secret['view_count'] = (int)($secret['view_count'] ?? 0) + 1;
$secret['last_viewed_at'] = date('Y-m-d H:i:s');

$pageTitle = $secret['service_name'];

ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-flex am-items-center am-gap-3">
            <a href="/local_secrets/index.php<?= $secret['category_id'] ? '?cat=' . $secret['category_id'] : '' ?>"
               class="am-back" title="Назад">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="am-eyebrow">Секрет</div>
                <h1 class="am-h1">
                    <?php if ($secret['is_favorite']): ?>
                        <i class="fas fa-star am-text-warning"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($secret['service_name']) ?>
                </h1>
            </div>
        </div>
    </div>
    <div class="am-page-head-actions">
        <a href="/local_secrets/pages/secret_form.php?id=<?= $id ?>" class="am-btn am-btn-primary am-btn-sm">
            <i class="fas fa-pen"></i> Редактировать
        </a>
    </div>
</div>

<?php if ($secret['category_name'] || !empty($secret['tags'])): ?>
    <div class="am-flex am-flex-wrap am-gap-2 am-mb-3">
        <?php if ($secret['category_name']): ?>
            <?php $cc = $secret['category_color'] ?? '#888'; ?>
            <span class="am-chip am-chip-cat"
                  style="background:<?= htmlspecialchars($cc) ?>22;color:<?= htmlspecialchars($cc) ?>;border-color:<?= htmlspecialchars($cc) ?>33;">
                <i class="fas <?= htmlspecialchars($secret['category_icon'] ?? 'fa-folder') ?>"></i>
                <?= htmlspecialchars($secret['category_name']) ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($secret['tags'])): ?>
            <?php foreach ($secret['tags'] as $tag): ?>
                <span class="am-chip am-chip-tag"><?= htmlspecialchars($tag['name']) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($secret['description']): ?>
    <div class="am-card-soft am-mb-3">
        <div class="am-text-sm" style="color: var(--am-text-2); line-height: 1.6;">
            <?= nl2br(htmlspecialchars($secret['description'])) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Поля секрета -->
<div class="am-card am-card-flush">
    <div class="am-card-head">
        <span><i class="fas fa-key"></i> Поля</span>
        <button class="am-btn am-btn-ghost am-btn-sm" id="toggleAllBtn" type="button" title="Показать/скрыть все">
            <i class="fas fa-eye"></i> Показать все
        </button>
    </div>
    <table class="am-field-table">
        <tbody>
            <?php foreach ($secret['fields'] as $field): ?>
                <?php
                $icon = match($field['field_type']) {
                    'password' => 'fa-lock',
                    'url'      => 'fa-link',
                    'email'    => 'fa-envelope',
                    'token'    => 'fa-key',
                    'note'     => 'fa-note-sticky',
                    default    => 'fa-font',
                };
                ?>
                <tr>
                    <td class="am-field-name">
                        <span class="am-field-name-inner">
                            <i class="fas <?= $icon ?>"></i>
                            <?= htmlspecialchars($field['field_name']) ?>
                        </span>
                    </td>
                    <td class="field-cell" data-field-id="<?= $field['id'] ?>">
                        <span class="am-field-cell is-masked field-masked">
                            <?= str_repeat('•', min(20, max(8, strlen($field['field_value'])))) ?>
                        </span>
                        <span class="am-field-cell field-value" style="display:none;">
                            <?= htmlspecialchars($field['field_value']) ?>
                        </span>
                    </td>
                    <td class="am-field-actions">
                        <span class="am-btn-group">
                            <button class="am-icon-btn toggle-field-btn" type="button" title="Показать">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="am-icon-btn is-info copy-field-btn" type="button"
                                    data-field-id="<?= $field['id'] ?>" title="Копировать">
                                <i class="fas fa-copy"></i>
                            </button>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="am-text-sm am-muted am-mt-3">
    Создан: <?= date('d.m.Y H:i', strtotime($secret['created_at'])) ?>
    · Обновлён: <?= date('d.m.Y H:i', strtotime($secret['updated_at'])) ?>
    · <i class="fas fa-eye"></i> Открыт <strong><?= (int)$secret['view_count'] ?></strong> раз
    <?php if (!empty($secret['last_viewed_at'])): ?>
        · последний просмотр: <?= date('d.m.Y H:i', strtotime($secret['last_viewed_at'])) ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
