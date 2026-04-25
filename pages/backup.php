<?php
/**
 * Резервное копирование — экспорт/импорт БД и настроек
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$categories      = $categoryService->getAll();
$stats           = (new SecretService())->getStats();

$importSuccess = htmlspecialchars($_GET['import_success'] ?? '');
$importError   = htmlspecialchars($_GET['import_error']   ?? '');

$db         = Database::getInstance();
$dbSizeMb   = $db->fetchColumn(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
     FROM information_schema.tables
     WHERE table_schema = DATABASE()"
);

$pageTitle = 'Резервное копирование';
ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow">Администрирование</div>
        <h1 class="am-h1"><i class="fas fa-database am-muted"></i> Резервное копирование</h1>
    </div>
</div>

<?php if ($importSuccess): ?>
    <div class="am-alert am-alert-success">
        <i class="fas fa-circle-check"></i>
        <span><?= $importSuccess ?></span>
        <button type="button" class="am-alert-close" aria-label="Закрыть"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>
<?php if ($importError): ?>
    <div class="am-alert am-alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <span><?= $importError ?></span>
        <button type="button" class="am-alert-close" aria-label="Закрыть"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<!-- Статус -->
<div class="am-flex am-flex-wrap am-gap-2 am-mb-4">
    <span class="am-chip">
        <i class="fas fa-lock"></i> <?= (int)$stats['fields'] ?> зашифрованных полей
    </span>
    <span class="am-chip">
        <i class="fas fa-key"></i> <?= (int)$stats['total'] ?> секретов
    </span>
    <?php if ($dbSizeMb !== null): ?>
        <span class="am-chip">
            <i class="fas fa-hard-drive"></i> ~<?= $dbSizeMb ?> МБ в БД
        </span>
    <?php endif; ?>
</div>

<div class="am-grid am-grid-cols-2 am-mb-3">

    <!-- Экспорт БД -->
    <div class="am-card">
        <h3 class="am-h3"><i class="fas fa-file-arrow-down am-text-success"></i> Экспорт базы данных</h3>
        <p class="am-text-sm am-muted am-mb-3">
            Полный SQL-дамп всех таблиц: секреты (зашифрованы AES-256),
            категории, теги, лог активности.
        </p>
        <div class="am-alert am-alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <span>
                Дамп содержит зашифрованные данные. Для восстановления потребуется
                тот же <strong>ключ шифрования</strong> из <code>config.php</code>.
            </span>
        </div>
        <form method="POST" action="/local_secrets/api/backup.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action"     value="export_sql">
            <button type="submit" class="am-btn am-btn-primary am-btn-sm">
                <i class="fas fa-download"></i> Скачать SQL-дамп
            </button>
        </form>
    </div>

    <!-- Экспорт настроек -->
    <div class="am-card">
        <h3 class="am-h3"><i class="fas fa-sliders am-text-info"></i> Экспорт настроек</h3>
        <p class="am-text-sm am-muted am-mb-3">
            Сохраняет параметры приложения в JSON-файл:
            отображение, сессия, таймауты, конфигурация LLM.
        </p>
        <div class="am-alert am-alert-info">
            <i class="fas fa-circle-info"></i>
            <span>
                Ключ шифрования и учётные данные БД
                <strong>не включены</strong> в этот файл.
            </span>
        </div>
        <form method="POST" action="/local_secrets/api/backup.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action"     value="export_settings">
            <button type="submit" class="am-btn am-btn-ghost am-btn-sm">
                <i class="fas fa-download"></i> Скачать настройки (JSON)
            </button>
        </form>
    </div>
</div>

<!-- Ключ шифрования -->
<div class="am-card am-mb-3" style="border-color: var(--am-amber);">
    <h3 class="am-h3" style="color: var(--am-amber);">
        <i class="fas fa-key"></i> Ключ шифрования (ENCRYPTION_KEY)
    </h3>
    <div class="am-alert am-alert-danger">
        <i class="fas fa-skull-crossbones"></i>
        <span>
            <strong>Критически важно!</strong>
            Без этого ключа все зашифрованные данные будут <strong>безвозвратно утеряны</strong>.
            Храните ключ отдельно от дампа базы данных — в менеджере паролей или на бумаге.
        </span>
    </div>
    <div class="am-input-group">
        <input type="password" id="encKeyInput" class="am-input am-input-mono"
               value="<?= htmlspecialchars(ENCRYPTION_KEY) ?>"
               readonly autocomplete="off">
        <button class="am-input-group-addon" type="button"
                id="encKeyToggle" onclick="toggleEncKey()" title="Показать/скрыть">
            <i class="fas fa-eye" id="encKeyEyeIcon"></i>
        </button>
        <button class="am-input-group-addon" type="button"
                onclick="copyEncKey()" title="Скопировать ключ">
            <i class="fas fa-copy"></i>
        </button>
    </div>
    <p class="am-text-sm am-muted am-mt-2 am-mb-0">
        Ключ хранится в <code>config.php</code>. Сохраните резервную копию
        всего этого файла в надёжном месте.
    </p>
</div>

<div class="am-grid am-grid-cols-2">
    <!-- Восстановление из дампа -->
    <div class="am-card" style="border-color: var(--am-red);">
        <h3 class="am-h3" style="color: var(--am-red);">
            <i class="fas fa-file-arrow-up"></i> Восстановление из дампа
        </h3>
        <p class="am-text-sm am-muted am-mb-3">
            Импортирует SQL-дамп в базу данных.
            <strong>Существующие таблицы будут удалены и перезаписаны!</strong>
        </p>
        <div class="am-alert am-alert-danger">
            <i class="fas fa-triangle-exclamation"></i>
            <span>
                После восстановления убедитесь, что ключ шифрования
                в <code>config.php</code> соответствует тому, который использовался
                при создании дампа.
            </span>
        </div>
        <form method="POST" action="/local_secrets/api/backup.php"
              enctype="multipart/form-data"
              onsubmit="return confirmSqlImport()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action"     value="import_sql">
            <div class="am-field">
                <label class="am-label">SQL-файл резервной копии</label>
                <input type="file" name="sql_file" class="am-input am-input-sm"
                       accept=".sql" required>
            </div>
            <label class="am-check am-mb-3">
                <input type="checkbox" id="confirmSqlCheck" required>
                <span class="am-text-warning">Я понимаю, что текущие данные будут перезаписаны</span>
            </label>
            <button type="submit" class="am-btn am-btn-danger am-btn-sm">
                <i class="fas fa-upload"></i> Восстановить базу данных
            </button>
        </form>
    </div>

    <!-- Импорт настроек -->
    <div class="am-card">
        <h3 class="am-h3"><i class="fas fa-file-import am-text-info"></i> Импорт настроек</h3>
        <p class="am-text-sm am-muted am-mb-3">
            Восстанавливает настройки приложения из ранее сохранённого JSON-файла.
            Ключ шифрования и данные БД не затрагиваются.
        </p>
        <div class="am-alert am-alert-secondary">
            <i class="fas fa-circle-info"></i>
            <span>После импорта перезагрузите страницу, чтобы новые настройки вступили в силу.</span>
        </div>
        <form method="POST" action="/local_secrets/api/backup.php"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action"     value="import_settings">
            <div class="am-field">
                <label class="am-label">JSON-файл настроек</label>
                <input type="file" name="settings_file" class="am-input am-input-sm"
                       accept=".json" required>
            </div>
            <button type="submit" class="am-btn am-btn-primary am-btn-sm">
                <i class="fas fa-upload"></i> Импортировать настройки
            </button>
        </form>
    </div>
</div>

<script>
function toggleEncKey() {
    const input = document.getElementById('encKeyInput');
    const icon  = document.getElementById('encKeyEyeIcon');
    if (input.type === 'password') {
        input.type    = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type    = 'password';
        icon.className = 'fas fa-eye';
    }
}

function copyEncKey() {
    const key = document.getElementById('encKeyInput').value;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(key).then(() => {
            showToast('Ключ шифрования скопирован', 'success');
        });
    } else {
        const tmp = document.createElement('textarea');
        tmp.value = key;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        showToast('Ключ шифрования скопирован', 'success');
    }
}

function confirmSqlImport() {
    return confirm(
        'ВНИМАНИЕ!\n\n' +
        'Текущие данные базы данных будут полностью перезаписаны данными из файла.\n\n' +
        'Убедитесь, что у вас есть актуальная резервная копия.\n\n' +
        'Продолжить восстановление?'
    );
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
