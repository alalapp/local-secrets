<?php
/**
 * Умное добавление — вставить неструктурированный текст, LLM распарсит
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$llm = new LlmParser();
$llmAvailable = $llm->isAvailable();

$currentCategoryId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$currentCategory = $currentCategoryId ? $categoryService->getById($currentCategoryId) : null;

$pageTitle = 'Умное добавление';

// Обработка массового сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_entries' && verify_csrf()) {
    $entries = json_decode($_POST['entries_json'] ?? '[]', true);
    $saved = 0;
    foreach ($entries as $entry) {
        if (empty($entry['service_name'])) continue;

        $catId = null;
        if (!empty($entry['category'])) {
            foreach ($categories as $cat) {
                if (mb_strtolower($cat['name']) === mb_strtolower($entry['category'])) {
                    $catId = $cat['id'];
                    break;
                }
            }
        }

        $data = [
            'service_name' => $entry['service_name'],
            'category_id'  => $catId,
            'description'  => $entry['description'] ?? '',
            'is_favorite'  => 0,
            'fields'       => $entry['fields'] ?? [],
            'tags'         => $entry['tags'] ?? [],
        ];

        try {
            $secretService->create($data);
            $saved++;
        } catch (Throwable $e) {
            Logger::error("Ошибка сохранения entry '{$entry['service_name']}': " . $e->getMessage());
        }
    }

    header("Location: /local_secrets/index.php?saved={$saved}");
    exit;
}

ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-flex am-items-center am-gap-3">
            <a href="/local_secrets/index.php" class="am-back" title="Назад">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="am-eyebrow">Парсинг</div>
                <h1 class="am-h1"><i class="fas fa-wand-magic-sparkles am-text-info"></i> Умное добавление</h1>
            </div>
        </div>
    </div>
</div>

<?php if ($currentCategory): ?>
    <div class="am-alert am-alert-info">
        <i class="fas <?= htmlspecialchars($currentCategory['icon'] ?? 'fa-folder') ?>"
           style="color: <?= htmlspecialchars($currentCategory['color'] ?? '#888') ?>"></i>
        <span class="am-flex-1">
            Парсинг в контексте категории: <strong><?= htmlspecialchars($currentCategory['name']) ?></strong>.
            Новые записи по умолчанию будут отнесены к ней.
        </span>
        <a href="/local_secrets/pages/smart_add.php" class="am-btn am-btn-ghost am-btn-xs">
            <i class="fas fa-times"></i> Сбросить
        </a>
    </div>
<?php endif; ?>

<?php if (!$llmAvailable): ?>
    <div class="am-alert am-alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>LM Studio не доступен (<?= htmlspecialchars(LLM_API_URL) ?>). Запустите LM Studio и загрузите модель.</span>
    </div>
<?php endif; ?>

<!-- Шаг 1: Вставить текст -->
<div id="step1" class="am-card am-card-flush am-mb-3">
    <div class="am-card-head">
        <span><i class="fas fa-paste"></i> Шаг 1: вставьте неструктурированный текст</span>
    </div>
    <div class="am-card-body">
        <textarea id="rawText" class="am-textarea am-input-mono" rows="12"
                  placeholder="Вставьте сюда текст с логинами, паролями, API-ключами…&#10;&#10;Пример:&#10;OpenAI API Key: sk-proj-abc123…&#10;Login: admin&#10;Password: secret123"></textarea>
        <div class="am-flex am-justify-between am-items-center am-mt-3 am-flex-wrap am-gap-2">
            <span class="am-text-sm am-muted">LLM или быстрый парсинг разберут текст на сервисы и поля</span>
            <div class="am-flex am-gap-2">
                <button id="fallbackBtn" class="am-btn am-btn-ghost am-btn-sm" type="button"
                        title="Мгновенный парсинг по паттернам, без LLM">
                    <i class="fas fa-bolt"></i> Быстрый парсинг
                </button>
                <button id="analyzeBtn" class="am-btn am-btn-primary am-btn-sm" type="button"
                        <?= !$llmAvailable ? 'disabled' : '' ?>
                        title="Парсинг через LLM (медленнее, но точнее)">
                    <i class="fas fa-wand-magic-sparkles"></i> LLM-анализ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Шаг 2: Результат -->
<div id="step2" class="am-hidden">
    <div class="am-card am-card-flush am-mb-3">
        <div class="am-card-head">
            <span><i class="fas fa-check-double"></i> Шаг 2: проверьте результат</span>
            <span class="am-chip blue" id="entriesCount"></span>
        </div>
        <div class="am-card-body" id="entriesPreview"></div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="save_entries">
        <input type="hidden" name="entries_json" id="entriesJson">
        <div class="am-flex am-gap-2">
            <button type="submit" class="am-btn am-btn-primary" id="saveAllBtn">
                <i class="fas fa-save"></i> Сохранить все
            </button>
            <button type="button" class="am-btn am-btn-ghost" id="backBtn">
                <i class="fas fa-arrow-left"></i> Назад к тексту
            </button>
        </div>
    </form>
</div>

<!-- Спиннер -->
<div id="spinner" class="am-loading am-hidden">
    <span class="am-spinner am-spinner-lg"></span>
    <p class="am-mb-0">LLM анализирует текст…</p>
</div>

<script>
const categoriesList = <?= json_encode(array_column($categories, 'name'), JSON_UNESCAPED_UNICODE) ?>;
const CURRENT_CATEGORY_ID = <?= (int)$currentCategoryId ?>;
let parsedEntries = [];

document.addEventListener('DOMContentLoaded', function() {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const spinner = document.getElementById('spinner');
    const spinnerText = spinner.querySelector('p');
    const rawText = document.getElementById('rawText');

    function show(el)  { el.classList.remove('am-hidden'); }
    function hide(el)  { el.classList.add('am-hidden'); }

    function runParse(url, label) {
        const text = rawText.value.trim();
        if (!text) return;

        hide(step1); hide(step2); show(spinner);
        spinnerText.textContent = label;

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 200000);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' },
            body: JSON.stringify({ text: text, category_id: CURRENT_CATEGORY_ID || null }),
            signal: controller.signal
        })
        .then(r => r.json())
        .then(resp => {
            clearTimeout(timer);
            hide(spinner);
            if (resp.success && resp.data && resp.data.entries && resp.data.entries.length > 0) {
                parsedEntries = resp.data.entries;
                renderEntries(parsedEntries);
                show(step2);
            } else {
                if (url.includes('llm_parse')) {
                    showToast('LLM не справился, пробую быстрый парсинг…', 'warning');
                    runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг…');
                } else {
                    showToast(resp.error || 'Не удалось распознать данные', 'warning');
                    show(step1);
                }
            }
        })
        .catch(err => {
            clearTimeout(timer);
            hide(spinner);
            if (url.includes('llm_parse')) {
                showToast('LLM ошибка, переключаюсь на быстрый парсинг…', 'warning');
                runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг…');
            } else {
                show(step1);
                showToast('Ошибка: ' + (err.message || 'сеть'), 'danger');
            }
        });
    }

    document.getElementById('analyzeBtn').addEventListener('click', () =>
        runParse('/local_secrets/api/llm_parse.php', 'LLM анализирует текст…')
    );
    document.getElementById('fallbackBtn').addEventListener('click', () =>
        runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг…')
    );
    document.getElementById('backBtn').addEventListener('click', function () {
        hide(step2); show(step1);
    });

    document.getElementById('entriesPreview').addEventListener('input', syncEntriesJson);
    document.getElementById('entriesPreview').addEventListener('change', syncEntriesJson);

    document.querySelector('form[method="POST"][action=""], form[method="POST"]:not([action])')
        ?.addEventListener('submit', syncEntriesJson);
});

function renderEntries(entries) {
    const countEl = document.getElementById('entriesCount');
    countEl.textContent = entries.length + ' сервис(ов)';
    let html = '';
    entries.forEach((entry, idx) => {
        const tagsStr = (entry.tags || []).join(', ');
        html += `<div class="am-card-soft am-mb-3" data-idx="${idx}">
            <div class="am-field-row cols-3 am-mb-2" style="grid-template-columns: 2fr 1fr;">
                <div class="am-field am-mb-0">
                    <label class="am-label">Название сервиса</label>
                    <input type="text" class="am-input am-input-sm entry-name" data-idx="${idx}"
                           value="${escapeAttr(entry.service_name)}" placeholder="Название сервиса">
                </div>
                <div class="am-field am-mb-0">
                    <label class="am-label">Категория</label>
                    <select class="am-select am-input-sm entry-category" data-idx="${idx}">
                        <option value="">— Без категории —</option>
                        ${buildCategoryOptions(entry.category)}
                    </select>
                </div>
            </div>`;
        if (entry.description) {
            html += `<p class="am-text-sm am-muted am-mb-2">${escapeHtml(entry.description)}</p>`;
        }
        html += '<div class="am-table-wrap am-mb-2"><table class="am-table"><tbody>';
        (entry.fields || []).forEach(f => {
            const masked = f.type === 'password' || f.type === 'token'
                ? '••••••••' : escapeHtml(f.value);
            html += `<tr>
                <td class="am-fw-500" style="width:200px">${escapeHtml(f.name)}</td>
                <td class="am-mono am-text-sm" style="word-break:break-all;">${masked}</td>
                <td style="width:90px"><span class="am-chip am-chip-sq">${escapeHtml(f.type)}</span></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        html += `<div class="am-field am-mb-0">
            <label class="am-label">Теги <span class="am-muted">(через запятую)</span></label>
            <input type="text" class="am-input am-input-sm entry-tags" data-idx="${idx}"
                   value="${escapeAttr(tagsStr)}" placeholder="api, production, personal…">
        </div>`;
        html += '</div>';
    });
    document.getElementById('entriesPreview').innerHTML = html;
    syncEntriesJson();
}

function syncEntriesJson() {
    document.querySelectorAll('.entry-name').forEach(el => {
        const i = parseInt(el.dataset.idx, 10);
        if (parsedEntries[i]) parsedEntries[i].service_name = el.value.trim();
    });
    document.querySelectorAll('.entry-category').forEach(el => {
        const i = parseInt(el.dataset.idx, 10);
        if (parsedEntries[i]) parsedEntries[i].category = el.value;
    });
    document.querySelectorAll('.entry-tags').forEach(el => {
        const i = parseInt(el.dataset.idx, 10);
        if (parsedEntries[i]) {
            const raw = el.value.trim();
            parsedEntries[i].tags = raw === '' ? [] : raw.split(',').map(s => s.trim()).filter(s => s !== '');
        }
    });
    document.getElementById('entriesJson').value = JSON.stringify(parsedEntries);
}

function buildCategoryOptions(current) {
    const cur = (current || '').toLowerCase();
    let found = false;
    let html = '';
    categoriesList.forEach(function(name) {
        const selected = name.toLowerCase() === cur;
        if (selected) found = true;
        html += `<option value="${escapeAttr(name)}"${selected ? ' selected' : ''}>${escapeHtml(name)}</option>`;
    });
    if (!found && current) {
        html += `<option value="${escapeAttr(current)}" selected>${escapeHtml(current)} (новая)</option>`;
    }
    return html;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
