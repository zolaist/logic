<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$database = getLogicDataStore();
$loginError = '';

function adminReturnPath(): string
{
    $return = (string) ($_REQUEST['return'] ?? 'admin.php');

    if ($return === '' || str_starts_with($return, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $return) === 1) {
        return 'admin.php';
    }

    return $return;
}

$returnPath = adminReturnPath();

if (($_GET['action'] ?? '') === 'logout') {
    logoutLogicUser();
    header('Location: ' . $returnPath);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (loginLogicUser($database, $username, $password) && currentLogicUserIsAdmin()) {
        header('Location: ' . $returnPath);
        exit;
    }

    logoutLogicUser();
    $loginError = '관리자 계정으로 로그인해야 합니다.';
}

$currentUser = currentLogicUser();
$isAdmin = currentLogicUserIsAdmin();

?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>콘텐츠 관리 - 자연 연역 실험실</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #ffffff;
            --surface: #ffffff;
            --ink: #1d2329;
            --muted: #66717c;
            --line: #d8dce1;
            --accent: #2b9a91;
            --accent-dark: #176d68;
            --accent-weak: #dcf6f2;
            --accent-soft: #f3fcfa;
            --accent-hover: #e4f8f4;
            --accent-border: #c5eee8;
            --accent-border-strong: #94dcd2;
            --accent-ring: rgba(43, 154, 145, 0.18);
            --danger: #b42318;
            --danger-soft: #fff0ee;
            --soft: var(--accent-soft);
            --shadow: 0 12px 32px rgba(24, 36, 45, 0.1);
            --radius-panel: 16px;
            --radius-control: 8px;
            --font-ui: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-ui);
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button,
        .button-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 10px;
            border: 1px solid var(--line);
            border-radius: var(--radius-control);
            background: var(--surface);
            color: var(--muted);
            font-size: 13px;
            font-weight: 750;
            cursor: pointer;
        }

        button:hover,
        button:focus-visible,
        .button-link:hover,
        .button-link:focus-visible {
            border-color: var(--accent-border-strong);
            background: var(--accent-soft);
            color: var(--accent-dark);
        }

        .button-link {
            text-decoration: none;
        }

        button.primary,
        .button-link.primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }

        button.primary:hover,
        button.primary:focus-visible,
        .button-link.primary:hover,
        .button-link.primary:focus-visible {
            border-color: var(--accent-dark);
            background: var(--accent-dark);
            color: #fff;
        }

        button.danger {
            border-color: #f3b8b1;
            background: var(--danger-soft);
            color: var(--danger);
        }

        button.danger:hover,
        button.danger:focus-visible {
            border-color: #e69b92;
            background: #ffe7e4;
            color: var(--danger);
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 36px;
            border: 1px solid var(--line);
            border-radius: var(--radius-control);
            background: #fff;
            color: var(--ink);
            padding: 8px 10px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent-border-strong);
            box-shadow: 0 0 0 3px var(--accent-ring);
            outline: none;
        }

        textarea {
            min-height: 92px;
            resize: vertical;
            line-height: 1.45;
        }

        label {
            display: grid;
            gap: 5px;
            color: #3f4b55;
            font-size: 13px;
            font-weight: 700;
        }

        .admin-shell {
            width: min(100%, 1120px);
            margin: 0 auto;
            padding: 18px 16px 28px;
        }

        .login-shell {
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 18px;
        }

        .login-panel {
            width: min(100%, 360px);
            border: 1px solid var(--line);
            border-radius: var(--radius-panel);
            background: var(--surface);
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .login-panel h1 {
            margin-bottom: 6px;
        }

        .login-form {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: 25px;
            line-height: 1.2;
        }

        .subtitle {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .top-actions,
        .tabs,
        .seed-actions,
        .form-actions,
        .item-actions,
        .account-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .top-actions {
            justify-content: flex-end;
        }

        .tabs {
            position: relative;
            justify-content: space-between;
            align-items: flex-start;
            margin: 0 0 14px;
        }

        .tab-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .tab {
            padding: 0 14px;
        }

        .tab.is-active {
            border-color: var(--accent-border-strong);
            background: var(--accent-soft);
            color: var(--accent-dark);
        }

        .status {
            min-height: 22px;
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .status.is-error {
            color: var(--danger);
        }

        .account-panel {
            position: relative;
            margin: 0;
            overflow: visible;
        }

        .account-summary {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 32px;
            padding: 0 10px;
            border: 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 750;
            cursor: pointer;
            list-style: none;
        }

        .account-summary::marker {
            content: "";
        }

        .account-summary::-webkit-details-marker {
            display: none;
        }

        .account-summary:hover,
        .account-summary:focus-visible,
        .account-panel.is-open .account-summary {
            color: var(--accent-dark);
        }

        .account-form {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 20;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            width: min(720px, calc(100vw - 32px));
            gap: 10px;
            align-items: end;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius-panel);
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .account-form.hidden {
            display: none;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: 14px;
            align-items: start;
        }

        .classification-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.6fr);
            gap: 14px;
            align-items: start;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: var(--radius-panel);
            background: var(--surface);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel.account-panel {
            overflow: visible;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--line);
        }

        .panel-header h2 {
            margin: 0;
            font-size: 16px;
        }

        .list {
            display: grid;
        }

        .item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            padding: 11px 12px;
            border-bottom: 1px solid var(--line);
        }

        .item[draggable="true"] {
            cursor: grab;
        }

        .item.is-dragging {
            opacity: 0.45;
        }

        .item:last-child {
            border-bottom: 0;
        }

        .exercise-category {
            border-bottom: 1px solid var(--line);
        }

        .exercise-category:last-child {
            border-bottom: 0;
        }

        .exercise-category-header {
            padding: 12px 12px 11px;
            background: var(--accent-soft);
        }

        .exercise-category-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 900;
        }

        .exercise-section {
            border-top: 1px solid var(--line);
        }

        .exercise-section-header {
            background: var(--surface);
        }

        .exercise-section-header:hover,
        .exercise-section-header:focus-within {
            background: var(--accent-soft);
        }

        .exercise-section-summary {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--ink);
            padding: 0 12px 0 20px;
            font-size: 14px;
            font-weight: 750;
            text-align: left;
            cursor: pointer;
        }

        .exercise-section-summary:hover,
        .exercise-section-summary:focus-visible {
            background: transparent;
        }

        .exercise-section-summary::before {
            display: none;
        }

        .exercise-section-summary::after {
            display: grid;
            flex: 0 0 auto;
            width: 22px;
            height: 22px;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 50%;
            color: var(--accent-dark);
            font-size: 14px;
            font-weight: 900;
            line-height: 1;
            content: "▾";
        }

        .exercise-section.is-open .exercise-section-summary::after {
            content: "▴";
        }

        .exercise-section-title {
            flex: 1;
        }

        .exercise-section-items {
            display: none;
            border-top: 1px solid var(--line);
        }

        .exercise-section.is-open .exercise-section-items {
            display: grid;
        }

        .exercise-section-items .item {
            padding: 8px 12px 8px 22px;
        }

        .exercise-section-items .item-actions button {
            min-height: 22px;
            padding: 0 6px;
            border-color: var(--line);
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .exercise-section-items .item h3 {
            margin-bottom: 3px;
            font-size: 13px;
            font-weight: 650;
        }

        .exercise-section-items .preview {
            margin-top: 2px;
            font-size: 12px;
        }

        .exercise-section-add {
            min-height: 22px;
            padding: 0 6px;
            border-color: var(--line);
            background: #fff;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
        }

        .exercise-section-add:hover,
        .exercise-section-add:focus-visible,
        .exercise-section-items .item-actions button:hover,
        .exercise-section-items .item-actions button:focus-visible {
            border-color: var(--accent-border-strong);
            color: var(--accent-dark);
        }

        .item h3 {
            margin: 0 0 5px;
            font-size: 14px;
        }

        .meta,
        .preview {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .preview {
            margin-top: 4px;
            color: #3f4b55;
        }

        .editor {
            display: grid;
            gap: 10px;
            padding: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .wide {
            grid-column: 1 / -1;
        }

        .title-field {
            grid-column: 1 / -1;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 820px) {
            .topbar,
            .layout,
            .classification-layout {
                grid-template-columns: 1fr;
            }

            .topbar {
                display: grid;
            }

            .tabs {
                justify-content: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .account-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php if (!$isAdmin): ?>
    <main class="login-shell">
        <section class="login-panel" aria-labelledby="loginTitle">
            <h1 id="loginTitle">관리자 로그인</h1>
            <p class="subtitle">관리자 계정으로 로그인해야 콘텐츠 관리 페이지를 열 수 있습니다.</p>
            <?php if ($loginError !== ''): ?>
                <p class="status is-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form class="login-form" method="post" action="admin.php">
                <input type="hidden" name="return" value="<?= htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    아이디
                    <input name="username" autocomplete="username" required autofocus>
                </label>
                <label>
                    암호
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="primary" type="submit">로그인</button>
            </form>
        </section>
    </main>
<?php else: ?>
    <main class="admin-shell">
        <header class="topbar">
            <div>
                <h1>콘텐츠 관리</h1>
                <p class="subtitle">예제와 연습문제를 추가, 수정, 삭제합니다.</p>
            </div>
            <div class="top-actions">
                <div class="panel account-panel">
                    <button class="account-summary" id="accountToggleButton" type="button" aria-expanded="false" aria-controls="passwordForm">계정 암호 변경</button>
                    <form class="account-form hidden" id="passwordForm">
                        <label>
                            현재 암호
                            <input name="currentPassword" type="password" autocomplete="current-password" required>
                        </label>
                        <label>
                            새 암호
                            <input name="newPassword" type="password" autocomplete="new-password" minlength="4" required>
                        </label>
                        <label>
                            새 암호 확인
                            <input name="confirmPassword" type="password" autocomplete="new-password" minlength="4" required>
                        </label>
                        <div class="account-actions">
                            <button class="primary" type="submit">암호 변경</button>
                        </div>
                    </form>
                </div>
                <a class="button-link" href="./">메인 화면</a>
                <a class="button-link" href="admin.php?action=logout&amp;return=./">로그아웃</a>
            </div>
        </header>

        <nav class="tabs" aria-label="관리 대상">
            <div class="tab-list">
                <button class="tab is-active" type="button" data-resource="examples">예제</button>
                <button class="tab" type="button" data-resource="exercises">연습문제</button>
                <button class="tab" type="button" data-resource="classifications">연습문제 분류</button>
            </div>
            <div class="seed-actions">
                <button id="seedImportButton" class="danger" type="button">Seed &gt; DB</button>
                <button id="seedExportButton" class="primary" type="button">DB &gt; Seed</button>
            </div>
        </nav>

        <p class="status" id="status" aria-live="polite"></p>

        <div class="classification-layout hidden" id="classificationPanel">
            <section class="panel" aria-labelledby="classificationListTitle">
                <div class="panel-header">
                    <h2 id="classificationListTitle">분류 목록</h2>
                </div>
                <div class="list" id="classificationList"></div>
            </section>

            <section class="panel" aria-labelledby="classificationFormTitle">
                <div class="panel-header">
                    <h2 id="classificationFormTitle">분류 편집</h2>
                    <span class="meta" id="classificationEditingId">새 항목</span>
                </div>
                <form class="editor" id="classificationForm">
                    <label>
                        종류
                        <select name="type">
                            <option value="category">범주</option>
                            <option value="section">섹션</option>
                        </select>
                    </label>
                    <label>
                        소속 범주
                        <select name="categoryId"></select>
                    </label>
                    <label>
                        이름
                        <input name="title" required>
                    </label>
                    <div class="form-actions">
                        <button class="primary" type="submit">저장</button>
                        <button id="newClassificationButton" type="button">새 항목</button>
                        <button id="deleteClassificationButton" class="danger" type="button">삭제</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="layout">
            <section class="panel" aria-labelledby="listTitle">
                <div class="panel-header">
                    <h2 id="listTitle">예제 목록</h2>
                    <button id="newButton" class="primary" type="button">새 항목</button>
                </div>
                <div class="list" id="contentList"></div>
            </section>

            <section class="panel" aria-labelledby="editorTitle">
                <div class="panel-header">
                    <h2 id="editorTitle">예제 편집</h2>
                    <span class="meta" id="editingId">새 항목</span>
                </div>
                <form class="editor" id="contentForm">
                    <div class="form-grid">
                        <label data-example-only>
                            종류
                            <select name="kind">
                                <option value="rule">규칙 예제</option>
                                <option value="guide">입력 가이드</option>
                            </select>
                        </label>
                        <label>
                            범주
                            <select name="category"></select>
                        </label>
                        <label>
                            섹션
                            <select name="section"></select>
                        </label>
                        <label class="title-field">
                            제목
                            <input name="title" required>
                        </label>
                        <label data-example-only>
                            ruleKey
                            <input name="ruleKey">
                        </label>
                        <label data-example-only>
                            guideKey
                            <input name="guideKey">
                        </label>
                        <label data-example-only>
                            variantIndex
                            <input name="variantIndex" type="number" value="0">
                        </label>
                        <label class="wide">
                            문제
                            <textarea name="problem" required></textarea>
                        </label>
                        <label data-example-only class="wide">
                            정답
                            <textarea name="answer"></textarea>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="primary" type="submit">저장</button>
                        <button id="deleteSelectedButton" class="danger" type="button">삭제</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <script>
        const state = {
            resource: 'examples',
            examples: [],
            exercises: [],
            classifications: [],
            selectedId: null,
            selectedClassification: null,
            draggedExerciseId: null,
            draggedSectionId: null,
        };
        let statusTimer = null;

        const apiPath = 'api/admin-content.php';
        const tabs = document.querySelectorAll('.tab');
        const statusLine = document.querySelector('#status');
        const listTitle = document.querySelector('#listTitle');
        const editorTitle = document.querySelector('#editorTitle');
        const editingId = document.querySelector('#editingId');
        const contentList = document.querySelector('#contentList');
        const contentForm = document.querySelector('#contentForm');
        const accountPanel = document.querySelector('.account-panel');
        const accountToggleButton = document.querySelector('#accountToggleButton');
        const passwordForm = document.querySelector('#passwordForm');
        const contentLayout = document.querySelector('.layout');
        const classificationPanel = document.querySelector('#classificationPanel');
        const classificationList = document.querySelector('#classificationList');
        const classificationForm = document.querySelector('#classificationForm');
        const classificationEditingId = document.querySelector('#classificationEditingId');
        const categorySelect = contentForm.elements.category;
        const sectionSelect = contentForm.elements.section;

        function setStatus(message, isError = false, options = {}) {
            clearTimeout(statusTimer);
            statusLine.textContent = message;
            statusLine.classList.toggle('is-error', isError);

            if (options.temporary) {
                statusTimer = setTimeout(() => {
                    statusLine.textContent = '';
                    statusLine.classList.remove('is-error');
                }, options.duration || 2500);
            }
        }

        function requestOptions(options = {}) {
            const headers = new Headers(options.headers || {});

            return { ...options, headers };
        }

        async function api(resource, options = {}) {
            const id = options.id ? `&id=${encodeURIComponent(options.id)}` : '';
            const response = await fetch(`${apiPath}?resource=${encodeURIComponent(resource)}${id}`, requestOptions(options));
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || '요청에 실패했습니다.');
            }

            return payload;
        }

        function flattenExercises(categories) {
            const rows = [];

            for (const category of categories) {
                for (const section of category.sections || []) {
                    for (const item of section.items || []) {
                        rows.push({
                            id: item.id,
                            title: item.title,
                            sectionId: section.id,
                            category: category.title,
                            section: section.title,
                            problem: item.problem,
                            sortOrder: item.sort_order,
                        });
                    }
                }
            }

            return rows;
        }

        function currentItems() {
            return state.resource === 'examples' ? state.examples : flattenExercises(state.exercises);
        }

        async function loadClassifications() {
            const payload = await api('classifications');
            state.classifications = payload.classifications || [];
            updateContentClassificationOptions();
            updateClassificationCategoryOptions();
        }

        function optionFromValue(value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            return option;
        }

        function updateContentClassificationOptions(selectedCategory = categorySelect.value, selectedSection = sectionSelect.value) {
            categorySelect.innerHTML = '';

            for (const category of state.classifications) {
                categorySelect.append(optionFromValue(category.title));
            }

            if (
                selectedCategory &&
                !state.classifications.some((category) => category.title === selectedCategory)
            ) {
                categorySelect.append(optionFromValue(selectedCategory));
            }

            if (categorySelect.options.length > 0) {
                categorySelect.value = selectedCategory || categorySelect.options[0].value;
            }

            updateContentSectionOptions(selectedSection);
        }

        function updateContentSectionOptions(selectedSection = sectionSelect.value) {
            const category = state.classifications.find((candidate) => candidate.title === categorySelect.value);
            sectionSelect.innerHTML = '';

            for (const section of category?.sections || []) {
                sectionSelect.append(optionFromValue(section.title));
            }

            if (
                selectedSection &&
                !(category?.sections || []).some((section) => section.title === selectedSection)
            ) {
                sectionSelect.append(optionFromValue(selectedSection));
            }

            if (sectionSelect.options.length > 0) {
                sectionSelect.value = selectedSection || sectionSelect.options[0].value;
            }
        }

        function setResource(resource) {
            state.resource = resource;
            state.selectedId = null;
            state.selectedClassification = null;
            tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.resource === resource));
            contentLayout.classList.toggle('hidden', resource === 'classifications');
            classificationPanel.classList.toggle('hidden', resource !== 'classifications');
            document.querySelectorAll('[data-example-only]').forEach((element) => {
                element.classList.toggle('hidden', resource !== 'examples');
            });
            document.querySelectorAll('[data-exercise-only]').forEach((element) => {
                element.classList.toggle('hidden', resource !== 'exercises');
            });
            listTitle.textContent = resource === 'examples' ? '예제 목록' : '연습문제 목록';
            editorTitle.textContent = resource === 'examples' ? '예제 편집' : '연습문제 편집';
            resetForm();
            resetClassificationForm();
            renderList();
            renderClassifications();
        }

        function resetForm() {
            contentForm.reset();
            state.selectedId = null;
            editingId.textContent = '새 항목';
            contentForm.elements.kind.value = 'rule';
            contentForm.elements.variantIndex.value = '0';
            updateContentClassificationOptions();
        }

        function fillForm(item) {
            resetForm();
            state.selectedId = item.id;
            editingId.textContent = `ID ${item.id}`;
            contentForm.elements.title.value = item.title || '';
            updateContentClassificationOptions(item.category || '', item.section || '');
            contentForm.elements.problem.value = item.problem || '';

            if (state.resource === 'examples') {
                contentForm.elements.kind.value = item.kind || 'rule';
                contentForm.elements.ruleKey.value = item.ruleKey || '';
                contentForm.elements.guideKey.value = item.guideKey || '';
                contentForm.elements.variantIndex.value = String(item.variantIndex || 0);
                contentForm.elements.answer.value = item.answer || '';
            }
        }

        function resetClassificationForm() {
            classificationForm.reset();
            state.selectedClassification = null;
            classificationEditingId.textContent = '새 항목';
            updateClassificationCategoryOptions();
        }

        function updateClassificationCategoryOptions() {
            const select = classificationForm.elements.categoryId;
            select.innerHTML = '';

            for (const category of state.classifications) {
                const option = document.createElement('option');
                option.value = String(category.id);
                option.textContent = category.title;
                select.append(option);
            }
        }

        function fillClassificationForm(item) {
            state.selectedClassification = item;
            classificationEditingId.textContent = `ID ${item.id}`;
            classificationForm.elements.type.value = item.type;
            updateClassificationCategoryOptions();
            classificationForm.elements.categoryId.value = String(item.categoryId || state.classifications[0]?.id || '');
            classificationForm.elements.title.value = item.title || '';
        }

        function renderClassifications() {
            if (state.resource !== 'classifications') {
                return;
            }

            classificationList.innerHTML = '';
            updateClassificationCategoryOptions();

            if (state.classifications.length === 0) {
                classificationList.innerHTML = '<div class="item"><p class="meta">분류가 없습니다.</p></div>';
                return;
            }

            for (const category of state.classifications) {
                const categoryRow = document.createElement('article');
                const categoryCopy = document.createElement('div');
                const categoryTitle = document.createElement('h3');
                const categoryMeta = document.createElement('p');
                const categoryActions = document.createElement('div');
                const categoryEdit = document.createElement('button');

                categoryRow.className = 'item';
                categoryTitle.textContent = category.title;
                categoryMeta.className = 'meta';
                categoryMeta.textContent = '범주';
                categoryActions.className = 'item-actions';
                categoryEdit.type = 'button';
                categoryEdit.textContent = '편집';
                categoryEdit.addEventListener('click', () => fillClassificationForm({
                    type: 'category',
                    id: category.id,
                    title: category.title,
                }));
                categoryCopy.append(categoryTitle, categoryMeta);
                categoryActions.append(categoryEdit);
                categoryRow.append(categoryCopy, categoryActions);
                classificationList.append(categoryRow);

                for (const section of category.sections || []) {
                    const sectionRow = document.createElement('article');
                    const sectionCopy = document.createElement('div');
                    const sectionTitle = document.createElement('h3');
                    const sectionMeta = document.createElement('p');
                    const sectionActions = document.createElement('div');
                    const sectionEdit = document.createElement('button');

                    sectionRow.className = 'item';
                    sectionRow.draggable = true;
                    sectionRow.dataset.id = String(section.id);
                    sectionRow.dataset.categoryId = String(category.id);
                    sectionTitle.textContent = `- ${section.title}`;
                    sectionMeta.className = 'meta';
                    sectionMeta.textContent = `${category.title} / 섹션`;
                    sectionActions.className = 'item-actions';
                    sectionEdit.type = 'button';
                    sectionEdit.textContent = '편집';
                    sectionEdit.addEventListener('click', () => fillClassificationForm({
                        type: 'section',
                        id: section.id,
                        categoryId: category.id,
                        title: section.title,
                    }));
                    sectionRow.addEventListener('dragstart', () => {
                        state.draggedSectionId = section.id;
                        sectionRow.classList.add('is-dragging');
                    });
                    sectionRow.addEventListener('dragend', () => {
                        state.draggedSectionId = null;
                        sectionRow.classList.remove('is-dragging');
                    });
                    sectionRow.addEventListener('dragover', (event) => {
                        event.preventDefault();
                    });
                    sectionRow.addEventListener('drop', async (event) => {
                        event.preventDefault();

                        const draggedId = state.draggedSectionId;

                        if (!draggedId || draggedId === section.id) {
                            return;
                        }

                        const sectionItems = [...(category.sections || [])]
                            .sort((left, right) => left.sort_order - right.sort_order || left.id - right.id);
                        const draggedIndex = sectionItems.findIndex((candidate) => candidate.id === draggedId);
                        const targetIndex = sectionItems.findIndex((candidate) => candidate.id === section.id);

                        if (draggedIndex === -1 || targetIndex === -1) {
                            setStatus('같은 범주 안에서만 섹션 순서를 바꿀 수 있습니다.', true);
                            return;
                        }

                        const [moved] = sectionItems.splice(draggedIndex, 1);
                        sectionItems.splice(targetIndex, 0, moved);
                        const orderedIds = sectionItems.map((candidate) => candidate.id);
                        category.sections.sort((left, right) => (
                            orderedIds.indexOf(left.id) - orderedIds.indexOf(right.id)
                        ));
                        category.sections.forEach((candidate, index) => {
                            candidate.sort_order = (index + 1) * 100;
                        });

                        try {
                            renderClassifications();
                            await api('section-order', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    categoryId: category.id,
                                    ids: orderedIds,
                                }),
                            });
                            setStatus('섹션 순서를 저장했습니다.');
                        } catch (error) {
                            setStatus(error.message, true);
                            await loadClassifications();
                            renderClassifications();
                        }
                    });
                    sectionCopy.append(sectionTitle, sectionMeta);
                    sectionActions.append(sectionEdit);
                    sectionRow.append(sectionCopy, sectionActions);
                    classificationList.append(sectionRow);
                }
            }
        }

        function orderedExerciseIdsForSection(sectionId) {
            return currentItems()
                .filter((item) => item.sectionId === sectionId)
                .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id)
                .map((item) => item.id);
        }

        async function saveExerciseOrder(sectionId, ids = orderedExerciseIdsForSection(sectionId)) {
            await api('exercise-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sectionId,
                    ids,
                }),
            });
        }

        function createExerciseRow(item, items) {
            const row = document.createElement('article');
            row.className = 'item';
            row.draggable = true;
            row.dataset.id = String(item.id);
            row.dataset.sectionId = String(item.sectionId);

            const copy = document.createElement('div');
            const title = document.createElement('h3');
            const preview = document.createElement('p');
            const actions = document.createElement('div');
            const editButton = document.createElement('button');

            title.textContent = item.title || '(제목 없음)';
            preview.className = 'preview';
            preview.textContent = item.problem || '';
            actions.className = 'item-actions';
            editButton.type = 'button';
            editButton.textContent = 'edit';
            editButton.addEventListener('click', () => fillForm(item));

            row.addEventListener('dragstart', () => {
                state.draggedExerciseId = item.id;
                row.classList.add('is-dragging');
            });
            row.addEventListener('dragend', () => {
                state.draggedExerciseId = null;
                row.classList.remove('is-dragging');
            });
            row.addEventListener('dragover', (event) => {
                event.preventDefault();
            });
            row.addEventListener('drop', async (event) => {
                event.preventDefault();

                const draggedId = state.draggedExerciseId;

                if (!draggedId || draggedId === item.id) {
                    return;
                }

                const dragged = items.find((candidate) => candidate.id === draggedId);

                if (!dragged || dragged.sectionId !== item.sectionId) {
                    setStatus('같은 섹션 안에서만 순서를 바꿀 수 있습니다.', true);
                    return;
                }

                const sectionItems = items
                    .filter((candidate) => candidate.sectionId === item.sectionId)
                    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
                const draggedIndex = sectionItems.findIndex((candidate) => candidate.id === draggedId);
                const targetIndex = sectionItems.findIndex((candidate) => candidate.id === item.id);
                const [moved] = sectionItems.splice(draggedIndex, 1);
                sectionItems.splice(targetIndex, 0, moved);
                const orderedIds = sectionItems.map((candidate) => candidate.id);
                sectionItems.forEach((candidate, index) => {
                    candidate.sortOrder = (index + 1) * 100;
                });
                for (const category of state.exercises) {
                    for (const section of category.sections || []) {
                        if (section.id !== item.sectionId) {
                            continue;
                        }

                        section.items.sort((left, right) => (
                            orderedIds.indexOf(left.id) - orderedIds.indexOf(right.id)
                        ));
                        section.items.forEach((candidate, index) => {
                            candidate.sort_order = (index + 1) * 100;
                        });
                    }
                }

                try {
                    renderList();
                    await saveExerciseOrder(item.sectionId, orderedIds);
                    setStatus('문제 순서를 저장했습니다.');
                } catch (error) {
                    setStatus(error.message, true);
                    await loadContent();
                }
            });

            copy.append(title, preview);
            actions.append(editButton);
            row.append(copy, actions);

            return row;
        }

        function addExerciseToSection(categoryTitle, sectionTitle) {
            resetForm();
            updateContentClassificationOptions(categoryTitle || '', sectionTitle || '');
            contentForm.elements.title.focus();
            setStatus('새 연습문제의 범주와 섹션을 선택했습니다.', false, { temporary: true });
        }

        function renderExerciseTree() {
            const items = currentItems();
            contentList.innerHTML = '';

            if (items.length === 0) {
                contentList.innerHTML = '<div class="item"><p class="meta">항목이 없습니다.</p></div>';
                return;
            }

            for (const category of state.exercises) {
                const categorySection = document.createElement('section');
                const categoryHeader = document.createElement('div');
                const categoryTitle = document.createElement('h3');
                const categoryMeta = document.createElement('p');

                categorySection.className = 'exercise-category';
                categoryHeader.className = 'exercise-category-header';
                categoryTitle.textContent = category.title || '(범주 없음)';
                categoryMeta.className = 'meta';
                categoryMeta.textContent = `${(category.sections || []).length}개 섹션`;
                categoryHeader.append(categoryTitle, categoryMeta);
                categorySection.append(categoryHeader);

                for (const section of category.sections || []) {
                    const sectionBlock = document.createElement('section');
                    const sectionHeader = document.createElement('div');
                    const summary = document.createElement('button');
                    const addButton = document.createElement('button');
                    const title = document.createElement('span');
                    const count = document.createElement('span');
                    const sectionItems = document.createElement('div');
                    const rows = (section.items || [])
                        .map((item) => ({
                            id: item.id,
                            title: item.title,
                            sectionId: section.id,
                            category: category.title,
                            section: section.title,
                            problem: item.problem,
                            sortOrder: item.sort_order,
                        }))
                        .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);

                    sectionBlock.className = 'exercise-section';
                    sectionHeader.className = 'exercise-section-header';
                    summary.className = 'exercise-section-summary';
                    summary.type = 'button';
                    summary.setAttribute('aria-expanded', 'false');
                    addButton.className = 'exercise-section-add';
                    addButton.type = 'button';
                    addButton.textContent = '+';
                    title.className = 'exercise-section-title';
                    title.textContent = section.title || '(섹션 없음)';
                    count.className = 'meta';
                    count.textContent = `${rows.length}개`;
                    sectionItems.className = 'exercise-section-items';

                    for (const item of rows) {
                        sectionItems.append(createExerciseRow(item, items));
                    }

                    summary.addEventListener('click', () => {
                        const isOpen = sectionBlock.classList.toggle('is-open');
                        summary.setAttribute('aria-expanded', String(isOpen));
                    });
                    addButton.addEventListener('click', (event) => {
                        event.stopPropagation();
                        addExerciseToSection(category.title, section.title);
                    });

                    summary.append(title, count, addButton);
                    sectionHeader.append(summary);
                    sectionBlock.append(sectionHeader, sectionItems);
                    categorySection.append(sectionBlock);
                }

                contentList.append(categorySection);
            }
        }

        function renderList() {
            if (state.resource === 'classifications') {
                return;
            }

            if (state.resource === 'exercises') {
                renderExerciseTree();
                return;
            }

            const items = currentItems();
            contentList.innerHTML = '';

            if (items.length === 0) {
                contentList.innerHTML = '<div class="item"><p class="meta">항목이 없습니다.</p></div>';
                return;
            }

            for (const item of items) {
                const row = document.createElement('article');
                row.className = 'item';
                const copy = document.createElement('div');
                const title = document.createElement('h3');
                const meta = document.createElement('p');
                const preview = document.createElement('p');
                const actions = document.createElement('div');
                const editButton = document.createElement('button');

                title.textContent = item.title || '(제목 없음)';
                meta.className = 'meta';
                meta.textContent = state.resource === 'examples'
                    ? `${item.kind || 'rule'} / ${item.category || '-'} / ${item.section || '-'} / ${item.ruleKey || item.guideKey || '-'}`
                    : `${item.category || '-'} / ${item.section || '-'}`;
                preview.className = 'preview';
                preview.textContent = item.problem || '';
                actions.className = 'item-actions';
                editButton.type = 'button';
                editButton.textContent = '편집';
                editButton.addEventListener('click', () => fillForm(item));

                if (state.resource === 'exercises') {
                    row.draggable = true;
                    row.dataset.id = String(item.id);
                    row.dataset.sectionId = String(item.sectionId);
                    row.addEventListener('dragstart', () => {
                        state.draggedExerciseId = item.id;
                        row.classList.add('is-dragging');
                    });
                    row.addEventListener('dragend', () => {
                        state.draggedExerciseId = null;
                        row.classList.remove('is-dragging');
                    });
                    row.addEventListener('dragover', (event) => {
                        event.preventDefault();
                    });
                    row.addEventListener('drop', async (event) => {
                        event.preventDefault();

                        const draggedId = state.draggedExerciseId;

                        if (!draggedId || draggedId === item.id) {
                            return;
                        }

                        const dragged = items.find((candidate) => candidate.id === draggedId);

                        if (!dragged || dragged.sectionId !== item.sectionId) {
                            setStatus('같은 섹션 안에서만 순서를 바꿀 수 있습니다.', true);
                            return;
                        }

                        const sectionItems = items
                            .filter((candidate) => candidate.sectionId === item.sectionId)
                            .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
                        const draggedIndex = sectionItems.findIndex((candidate) => candidate.id === draggedId);
                        const targetIndex = sectionItems.findIndex((candidate) => candidate.id === item.id);
                        const [moved] = sectionItems.splice(draggedIndex, 1);
                        sectionItems.splice(targetIndex, 0, moved);
                        const orderedIds = sectionItems.map((candidate) => candidate.id);
                        sectionItems.forEach((candidate, index) => {
                            candidate.sortOrder = (index + 1) * 100;
                        });
                        for (const category of state.exercises) {
                            for (const section of category.sections || []) {
                                if (section.id !== item.sectionId) {
                                    continue;
                                }

                                section.items.sort((left, right) => (
                                    orderedIds.indexOf(left.id) - orderedIds.indexOf(right.id)
                                ));
                                section.items.forEach((candidate, index) => {
                                    candidate.sort_order = (index + 1) * 100;
                                });
                            }
                        }

                        try {
                            renderList();
                            await saveExerciseOrder(item.sectionId, orderedIds);
                            setStatus('문제 순서를 저장했습니다.');
                        } catch (error) {
                            setStatus(error.message, true);
                            await loadContent();
                        }
                    });
                }

                copy.append(title, meta, preview);
                actions.append(editButton);
                row.append(copy, actions);
                contentList.append(row);
            }
        }

        async function loadContent() {
            setStatus('불러오는 중...');
            try {
                const payload = await api(state.resource);

                if (state.resource === 'classifications') {
                    state.classifications = payload.classifications || [];
                    renderClassifications();
                    setStatus('연습문제 분류를 불러왔습니다.');
                    return;
                }

                state[state.resource] = payload[state.resource] || [];
                renderList();
                setStatus(`${currentItems().length}개 항목을 불러왔습니다.`);
            } catch (error) {
                setStatus(error.message, true);
            }
        }

        function formPayload() {
            const data = Object.fromEntries(new FormData(contentForm).entries());

            if (state.resource === 'examples') {
                data.variantIndex = Number(data.variantIndex || 0);
                return data;
            }

            return {
                title: data.title,
                category: data.category,
                section: data.section,
                problem: data.problem,
            };
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                setResource(tab.dataset.resource);
                loadContent();
            });
        });

        accountToggleButton.addEventListener('click', () => {
            const isOpen = passwordForm.classList.toggle('hidden') === false;
            accountPanel.classList.toggle('is-open', isOpen);
            accountToggleButton.setAttribute('aria-expanded', String(isOpen));
        });

        document.querySelector('#newButton').addEventListener('click', resetForm);
        document.querySelector('#newClassificationButton').addEventListener('click', resetClassificationForm);
        categorySelect.addEventListener('change', () => updateContentSectionOptions(''));

        classificationForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const data = Object.fromEntries(new FormData(classificationForm).entries());

            if (data.type === 'category') {
                delete data.categoryId;
            } else {
                data.categoryId = Number(data.categoryId || 0);
            }

            try {
                const isNewClassification = !state.selectedClassification;
                await api('classifications', {
                    id: state.selectedClassification?.id,
                    method: state.selectedClassification ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                await loadContent();
                await loadClassifications();
                resetClassificationForm();
                setStatus(
                    isNewClassification ? '새 분류가 추가되었습니다.' : '분류가 수정되었습니다.',
                    false,
                    { temporary: true },
                );
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        document.querySelector('#deleteClassificationButton').addEventListener('click', async () => {
            if (!state.selectedClassification) {
                setStatus('삭제할 분류를 먼저 선택하세요.', true);
                return;
            }

            if (!confirm('선택한 분류를 삭제할까요? 연결된 하위 항목이 있으면 삭제되지 않습니다.')) {
                return;
            }

            try {
                await api('classifications', {
                    id: state.selectedClassification.id,
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: state.selectedClassification.type }),
                });
                await loadContent();
                await loadClassifications();
                resetClassificationForm();
                setStatus('분류를 삭제했습니다.');
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const data = Object.fromEntries(new FormData(passwordForm).entries());

            try {
                await api('password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                passwordForm.reset();
                setStatus('암호를 변경했습니다.');
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        document.querySelector('#seedImportButton').addEventListener('click', async () => {
            if (!confirm('현재 DB 내용을 지우고 JSON 시드를 다시 넣습니다. 계속할까요?')) {
                return;
            }

            try {
                setStatus('시드 파일을 DB로 가져오는 중...');
                await api('seed-import', { method: 'POST' });
                await loadContent();
                await loadClassifications();
                setStatus('시드 파일을 DB로 가져왔습니다.');
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        document.querySelector('#seedExportButton').addEventListener('click', async () => {
            if (!confirm('현재 DB 내용을 JSON 시드 파일에 저장합니다. 계속할까요?')) {
                return;
            }

            try {
                setStatus('DB 내용을 시드 파일로 저장하는 중...');
                const payload = await api('seed-export', { method: 'POST' });
                const counts = payload.counts || {};
                setStatus(
                    `시드 파일로 저장했습니다. 규칙 예제 ${counts.ruleExamples || 0}개, ` +
                    `가이드 ${counts.guideExamples || 0}개, 연습문제 ${counts.exerciseEntries || 0}개.`,
                );
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        contentForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                const isNewItem = !state.selectedId;
                const method = state.selectedId ? 'PUT' : 'POST';
                const payload = await api(state.resource, {
                    id: state.selectedId,
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formPayload()),
                });
                state.selectedId = payload.id;
                await loadContent();
                setStatus(
                    isNewItem ? '새 항목이 추가되었습니다.' : '항목이 수정되었습니다.',
                    false,
                    { temporary: true },
                );
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        document.querySelector('#deleteSelectedButton').addEventListener('click', async () => {
            if (!state.selectedId) {
                setStatus('삭제할 항목을 먼저 선택하세요.', true);
                return;
            }

            if (!confirm(`ID ${state.selectedId} 항목을 삭제할까요?`)) {
                return;
            }

            try {
                await api(state.resource, { id: state.selectedId, method: 'DELETE' });
                resetForm();
                await loadContent();
                setStatus('삭제했습니다.');
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        loadClassifications()
            .then(loadContent)
            .catch((error) => setStatus(error.message, true));
    </script>
<?php endif; ?>
</body>
</html>
