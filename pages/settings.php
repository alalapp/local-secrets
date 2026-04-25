<?php
/**
 * Настройки — смена PIN, информация
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

/**
 * Обновить define'ы прямо в config.php
 * @param array<string, mixed> $defines ['KEY' => value, ...]
 */
function _saveConfig(array $defines): void {
    $configPath = APP_ROOT . '/config.php';
    $content = file_get_contents($configPath);

    foreach ($defines as $key => $value) {
        $exported = is_string($value) ? var_export($value, true) : $value;
        $newLine = "define('{$key}', {$exported});";

        $pattern = "/(?:if\s*\(!defined\('{$key}'\)\)\s*)?define\('{$key}',.+?\);/";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        }
    }

    file_put_contents($configPath, $content);
}

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$error = '';
$success = '';

// Смена PIN / общие настройки / LLM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_pin') {
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';
        $confirmPin = $_POST['confirm_pin'] ?? '';

        $verify = Auth::verifyPin($currentPin);
        if (!$verify['success']) {
            $error = 'Неверный текущий PIN';
        } elseif ($newPin !== $confirmPin) {
            $error = 'Новые PIN-коды не совпадают';
        } else {
            try {
                Auth::setPin($newPin);
                $success = 'PIN успешно изменён';
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();

    } elseif ($action === 'save_general') {
        $perPage = max(5, min(100, (int)($_POST['per_page'] ?? 10)));
        $dashLimit = max(3, min(50, (int)($_POST['dashboard_limit'] ?? 10)));
        _saveConfig([
            'PER_PAGE'        => $perPage,
            'DASHBOARD_LIMIT' => $dashLimit,
        ]);
        $success = 'Настройки сохранены. Перезагрузите страницу для применения.';
        Logger::log('update', 'settings', 0, 'Обновлены общие настройки');

    } elseif ($action === 'save_llm') {
        $llmUrl = trim($_POST['llm_url'] ?? '');
        $llmModel = trim($_POST['llm_model'] ?? '');
        $llmTimeout = (int)($_POST['llm_timeout'] ?? 180);
        $llmMaxTokens = (int)($_POST['llm_max_tokens'] ?? 4000);
        $llmTemp = (float)($_POST['llm_temperature'] ?? 0.1);

        if (empty($llmUrl)) {
            $error = 'URL не может быть пустым';
        } else {
            _saveConfig([
                'LLM_API_URL'     => $llmUrl,
                'LLM_MODEL'       => $llmModel,
                'LLM_TIMEOUT'     => $llmTimeout,
                'LLM_MAX_TOKENS'  => $llmMaxTokens,
                'LLM_TEMPERATURE' => $llmTemp,
            ]);
            $success = 'Настройки LLM сохранены. Перезагрузите страницу для применения.';
            Logger::log('update', 'settings', 0, 'Обновлены настройки LLM');
        }
    }
}

$llm = new LlmParser();
$llmInfo = $llm->getServerInfo();

$pageTitle = 'Настройки';
ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow">Администрирование</div>
        <h1 class="am-h1"><i class="fas fa-sliders am-muted"></i> Настройки</h1>
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

<div class="am-grid am-grid-cols-2 am-block">
    <!-- Смена PIN -->
    <div class="am-card am-card-flush">
        <div class="am-card-head"><i class="fas fa-lock"></i> Смена PIN-кода</div>
        <div class="am-card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="change_pin">
                <div class="am-field">
                    <label class="am-label">Текущий PIN</label>
                    <input type="password" name="current_pin" class="am-input"
                           inputmode="numeric" pattern="[0-9]*" required>
                </div>
                <div class="am-field">
                    <label class="am-label">Новый PIN (<?= PIN_MIN_LENGTH ?>-<?= PIN_MAX_LENGTH ?> цифр)</label>
                    <input type="password" name="new_pin" class="am-input"
                           inputmode="numeric" pattern="[0-9]*"
                           minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>" required>
                </div>
                <div class="am-field">
                    <label class="am-label">Подтвердите новый PIN</label>
                    <input type="password" name="confirm_pin" class="am-input"
                           inputmode="numeric" pattern="[0-9]*"
                           minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>" required>
                </div>
                <button type="submit" class="am-btn am-btn-primary am-btn-sm">
                    <i class="fas fa-save"></i> Сменить PIN
                </button>
            </form>
        </div>
    </div>

    <!-- Статистика и отображение -->
    <div class="am-flex-col am-gap-3" style="display: flex; flex-direction: column; gap: 16px;">
        <div class="am-card am-card-flush">
            <div class="am-card-head"><i class="fas fa-chart-bar"></i> Статистика</div>
            <div class="am-card-body" style="padding: 0;">
                <table class="am-table">
                    <tbody>
                        <tr><td>Всего секретов</td><td class="am-td-num am-fw-600"><?= (int)$stats['total'] ?></td></tr>
                        <tr><td>В избранном</td><td class="am-td-num am-fw-600"><?= (int)$stats['favorites'] ?></td></tr>
                        <tr><td>Полей (зашифровано)</td><td class="am-td-num am-fw-600"><?= (int)$stats['fields'] ?></td></tr>
                        <tr><td>Категорий использовано</td><td class="am-td-num am-fw-600"><?= (int)$stats['categories_used'] ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="am-card am-card-flush">
            <div class="am-card-head"><i class="fas fa-eye"></i> Отображение</div>
            <div class="am-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save_general">
                    <div class="am-field-row cols-2">
                        <div class="am-field">
                            <label class="am-label">Записей на странице</label>
                            <input type="number" name="per_page" class="am-input am-input-sm"
                                   value="<?= PER_PAGE ?>" min="5" max="100" step="5">
                        </div>
                        <div class="am-field">
                            <label class="am-label">Карточек на главной</label>
                            <input type="number" name="dashboard_limit" class="am-input am-input-sm"
                                   value="<?= DASHBOARD_LIMIT ?>" min="3" max="50" step="1">
                        </div>
                    </div>
                    <button type="submit" class="am-btn am-btn-primary am-btn-sm">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- LM Studio -->
<div class="am-card am-card-flush am-mb-3">
    <div class="am-card-head">
        <span><i class="fas fa-robot"></i> LM Studio</span>
        <?php if ($llmInfo['available']): ?>
            <span class="am-chip green"><i class="fas fa-check"></i> Доступен</span>
        <?php else: ?>
            <span class="am-chip red"><i class="fas fa-times"></i> Недоступен</span>
        <?php endif; ?>
    </div>
    <div class="am-card-body">
        <?php if ($llmInfo['available'] && !empty($llmInfo['models'])): ?>
            <div class="am-mb-3">
                <span class="am-text-sm am-muted">Загруженные модели:</span>
                <span class="am-flex am-flex-wrap am-gap-1 am-mt-1">
                    <?php foreach ($llmInfo['models'] as $model): ?>
                        <button type="button" class="am-chip am-chip-tag"
                                onclick="document.querySelector('input[name=llm_model]').value='<?= htmlspecialchars($model, ENT_QUOTES) ?>'"
                                title="Подставить в поле модели">
                            <?= htmlspecialchars($model) ?>
                        </button>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php elseif (!$llmInfo['available'] && !empty($llmInfo['error'])): ?>
            <div class="am-alert am-alert-warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($llmInfo['error']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save_llm">
            <div class="am-field-row cols-3" style="grid-template-columns: 2fr 1fr;">
                <div class="am-field">
                    <label class="am-label">API Endpoint (URL)</label>
                    <input type="url" name="llm_url" class="am-input am-input-sm"
                           value="<?= htmlspecialchars(LLM_API_URL) ?>"
                           placeholder="http://192.168.30.10:1234/v1/chat/completions">
                </div>
                <div class="am-field">
                    <label class="am-label">Модель</label>
                    <input type="text" name="llm_model" class="am-input am-input-sm"
                           value="<?= htmlspecialchars(LLM_MODEL) ?>"
                           placeholder="qwen/qwen3-vl-4b">
                </div>
            </div>
            <div class="am-field-row cols-3">
                <div class="am-field">
                    <label class="am-label">Таймаут (сек)</label>
                    <input type="number" name="llm_timeout" class="am-input am-input-sm"
                           value="<?= LLM_TIMEOUT ?>" min="10" max="600">
                </div>
                <div class="am-field">
                    <label class="am-label">Max tokens</label>
                    <input type="number" name="llm_max_tokens" class="am-input am-input-sm"
                           value="<?= LLM_MAX_TOKENS ?>" min="500" max="16000">
                </div>
                <div class="am-field">
                    <label class="am-label">Temperature</label>
                    <input type="number" name="llm_temperature" class="am-input am-input-sm"
                           value="<?= LLM_TEMPERATURE ?>" min="0" max="2" step="0.05">
                </div>
            </div>
            <button type="submit" class="am-btn am-btn-primary am-btn-sm am-mt-2">
                <i class="fas fa-save"></i> Сохранить настройки LLM
            </button>
        </form>
    </div>
</div>

<div class="am-card">
    <h3 class="am-h3"><i class="fas fa-circle-info am-muted"></i> О приложении</h3>
    <p class="am-mb-1"><strong><?= APP_NAME ?></strong> v<?= APP_VERSION ?></p>
    <p class="am-text-sm am-muted am-mb-0">
        Локальное хранилище учётных данных с AES-256 шифрованием.
        Данные хранятся только на этом компьютере. Таймаут сессии: <?= SESSION_TIMEOUT / 60 ?> мин.
    </p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
