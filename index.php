<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentPath = (string) ($_SERVER['REQUEST_URI'] ?? './');
$encodedCurrentPath = htmlspecialchars(urlencode($currentPath), ENT_QUOTES, 'UTF-8');
$currentUser = currentLogicUser();
$isAdmin = currentLogicUserIsAdmin();
$isLoggedIn = $currentUser !== null;
$currentView = (string) ($_GET['view'] ?? '');
$isExercisesView = $currentView === 'exercises';
$isAboutView = $currentView === 'about';
$exerciseCategories = $isExercisesView
    ? getExerciseCatalog(getLogicDataStore())
    : [];
$siteTitle = '기호논리학 실습실';
$pagePrefix = $isExercisesView ? '연습문제 - ' : ($isAboutView ? '일러두기 - ' : '');

?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pagePrefix ?><?= $siteTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=STIX+Two+Math&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css?v=gray-tagline-20260730">
</head>
<body>
    <main class="app-shell">
        <header class="topbar">
            <div class="header-copy">
                <h1><a class="site-title-link" href="./"><?= $siteTitle ?></a></h1>
                <p class="site-tagline">Symbolic Logic Lab</p>
                <nav class="site-nav" aria-label="주요 메뉴">
                    <div class="site-nav-links">
                        <a class="<?= (!$isExercisesView && !$isAboutView) ? 'is-active' : '' ?>" href="./">자연 연역 검증기</a>
                        <a class="<?= $isExercisesView ? 'is-active' : '' ?>" href="?view=exercises">연습문제</a>
                        <a class="<?= $isAboutView ? 'is-active' : '' ?>" href="?view=about">일러두기</a>
                    </div>
                    <div class="auth-box" aria-label="로그인 상태">
                        <?php if ($isLoggedIn): ?>
                            <span class="auth-user"><?= htmlspecialchars((string) ($currentUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($isAdmin): ?>
                                <a href="admin.php">관리자</a>
                            <?php endif; ?>
                            <a href="admin.php?action=logout&amp;return=<?= $encodedCurrentPath ?>">로그아웃</a>
                        <?php else: ?>
                            <a href="admin.php?return=<?= $encodedCurrentPath ?>">로그인</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </header>

        <?php if ($isExercisesView): ?>
        <div class="example-group-list" id="examples">
            <?php foreach ($exerciseCategories as $category): ?>
                <article class="example-category-card">
                    <h2><?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php foreach ($category['sections'] as $section): ?>
                    <section class="example-section">
                        <h3><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="example-list">
                            <?php foreach ($section['items'] as $example): ?>
                                <a class="example-card" href="?problem=<?= (int) $example['id'] ?>">
                                    <h4><?= htmlspecialchars($example['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <p><?= htmlspecialchars($example['problem'], ENT_QUOTES, 'UTF-8') ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php elseif ($isAboutView): ?>
        <article class="about-page">
            <section class="about-card">
                <h2>일러두기</h2>
                <p>기호논리학 실습실은 기호논리학을 배우는 과정에서 증명 줄을 직접 써 보고, 각 줄이 주어진 추론 규칙에 부합하는지 즉시 확인해 볼 수 있도록 만든 작은 웹앱입니다. 종이에 증명을 쓰는 연습을 대신하기보다는, 규칙 적용의 감각을 더 자주 시험해 보고 스스로 오류를 찾는 데 도움을 주는 보조 도구로 생각해 주세요.</p>
                <p>이 앱은 이병덕의 『코어논리학 : 논리적 추론과 증명 테크닉』의 문법과 추론 규칙을 기본 기준으로 삼습니다. 다만 웹에서 입력하고 자동 검증하는 환경에 맞추기 위해 일부 표기와 처리 방식은 교재의 서술과 완전히 같지 않을 수 있습니다.</p>
            </section>

            <section class="about-card">
                <h2>교재와의 차이</h2>
                <p>이 웹앱은 기본적으로 『코어 논리학』의 문법과 추론 규칙을 최대한 따르려고 했지만, 사용성과 확장성을 위해 두 가지는 교재를 따르지 않았습니다.</p>
                <ul>
                    <li><strong>~~ 제거 규칙</strong>: 이 규칙은 교재에서는 "~ 제거" 규칙으로 소개되어 있으나, 그에 따른 자연스러운 약식 표기 "~E"가 "존재 양화사의 부정" 규칙의 약식 표기와 혼동될 여지가 있어, 아예 "~~ 제거" 규칙으로 이름을 바꾸고 약식 표기도 "~~E"로 결정했습니다.</li>
                    <li><strong>⊥ 도입 규칙</strong>: 교재에서는 모순(⊥, bottom) 기호를 전혀 도입하지 않고 있으나, 존재 양화사 제거 규칙의 엄격하면서도 제대로 된 활용을 위해 모순 기호가 필요하다는 점을 인지하고 "⊥ 도입" 규칙을 신설했습니다.</li>
                </ul>
            </section>

            <section class="about-card">
                <h2>개발자 정보와 피드백 안내</h2>
                <ul>
                    <li>개발자 홈페이지: <a href="https://zolaist.gnu.ac.kr/" target="_blank" rel="noopener">zolaist.gnu.ac.kr</a> | <a href="https://zolaist.org/wiki" target="_blank" rel="noopener">zolaist.org/wiki</a></li>
                    <li>오류 신고 및 기능 요청: <a href="mailto:zolaist@gnu.ac.kr">zolaist@gnu.ac.kr</a></li>
                </ul>
            </section>
        </article>
        <?php else: ?>
        <div class="workspace-layout">
            <div class="workspace-main">
            <section class="proof-panel" id="proof-editor" aria-label="증명 줄 입력">
                <div class="proof-list is-empty" id="proofList" aria-live="polite">
                </div>

                <article class="proof-line editor-line is-active">
                    <span class="line-number" id="activeLineNumber">1</span>
                    <div class="line-body">
                        <div class="formula-and-tools">
                            <div class="formula-stack">
                                <div class="entry-row">
                                    <label class="visually-hidden" for="formulaInput">논리식</label>
                                    <input class="formula-input" id="formulaInput" type="text" placeholder="논리식" autocomplete="off">
                                    <label class="visually-hidden" for="ruleTextInput">정당화</label>
                                    <input id="ruleTextInput" class="rule-command-input" type="text" placeholder="정당화" autocomplete="off">
                                    <input id="ruleSelect" type="hidden">
                                    <input id="refsInput" type="hidden">
                                    <button class="delete-button" type="button" id="deleteButton" hidden>✖︎</button>
                                    <button class="complete-button" type="button" id="completeButton">✔︎</button>
                                </div>
                                <p class="formula-warning" id="formulaWarning" hidden></p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

        <section class="rules-panel" id="sentential-rules" aria-labelledby="sententialRulesTitle">
            <h2 id="sententialRulesTitle">문장 논리의 추론 규칙</h2>
            <details class="rule-section">
                <summary class="input-guide-section-title rule-section-title">기본 규칙</summary>
                <div class="rule-list">
                <article class="rule-card">
                    <h3>반복<span class="rule-alias">R</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>A</strong></p>
                        <p class="rule-conclusion"><strong>A</strong><em><span>i</span>, 반복</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>&amp; 도입<span class="rule-alias">&amp;I</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>A</strong></p>
                        <p><span>j</span><strong>B</strong></p>
                        <p class="rule-conclusion"><strong>A &amp; B</strong><em><span>i, j</span>, &amp; 도입</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>&amp; 제거<span class="rule-alias">&amp;E</span></h3>
                    <div class="rule-variants">
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A &amp; B</strong></p>
                            <p class="rule-conclusion"><strong>A</strong><em><span>i</span>, &amp; 제거</em></p>
                        </div>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A &amp; B</strong></p>
                            <p class="rule-conclusion"><strong>B</strong><em><span>i</span>, &amp; 제거</em></p>
                        </div>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>∨ 도입<span class="rule-alias">vI</span></h3>
                    <div class="rule-variants">
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A</strong></p>
                            <p class="rule-conclusion"><strong>A ∨ B</strong><em><span>i</span>, ∨ 도입</em></p>
                        </div>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A</strong></p>
                            <p class="rule-conclusion"><strong>B ∨ A</strong><em><span>i</span>, ∨ 도입</em></p>
                        </div>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>∨ 제거<span class="rule-alias">vE</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>A ∨ B</strong></p>
                        <p><span>j</span><strong>~A</strong></p>
                        <p class="rule-conclusion"><strong>B</strong><em><span>i, j</span>, ∨ 제거</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>→ 도입<span class="rule-alias">&gt;I</span></h3>
                    <div class="rule-scheme rule-scheme-subproof-intro">
                        <div class="subproof-box">
                            <p><span>i</span><strong>A</strong><em>가정</em></p>
                            <p><span>j</span><strong>B</strong></p>
                        </div>
                        <p class="rule-conclusion"><strong>A → B</strong><em><span>i-j</span>, → 도입</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>→ 제거<span class="rule-alias">&gt;E</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>A → B</strong></p>
                        <p><span>j</span><strong>A</strong></p>
                        <p class="rule-conclusion"><strong>B</strong><em><span>i, j</span>, → 제거</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>↔ 도입<span class="rule-alias">&lt;&gt;I</span></h3>
                    <div class="rule-variants">
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A → B</strong></p>
                            <p><span>j</span><strong>B → A</strong></p>
                            <p class="rule-conclusion"><strong>A ↔ B</strong><em><span>i, j</span>, ↔ 도입</em></p>
                        </div>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A → B</strong></p>
                            <p><span>j</span><strong>B → A</strong></p>
                            <p class="rule-conclusion"><strong>B ↔ A</strong><em><span>i, j</span>, ↔ 도입</em></p>
                        </div>
                        <div class="rule-scheme rule-scheme-subproof-intro">
                            <div class="subproof-box">
                                <p><span>i</span><strong>A</strong><em>가정</em></p>
                                <p><span>j</span><strong>B</strong></p>
                            </div>
                            <div class="subproof-box">
                                <p><span>k</span><strong>B</strong><em>가정</em></p>
                                <p><span>l</span><strong>A</strong></p>
                            </div>
                            <p class="rule-conclusion"><strong>A ↔ B</strong><em><span>i-j, k-l</span>, ↔ 도입</em></p>
                        </div>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>↔ 제거<span class="rule-alias">&lt;&gt;E</span></h3>
                    <div class="rule-variants">
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A ↔ B</strong></p>
                            <p class="rule-conclusion"><strong>A → B</strong><em><span>i</span>, ↔ 제거</em></p>
                        </div>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A ↔ B</strong></p>
                            <p class="rule-conclusion"><strong>B → A</strong><em><span>i</span>, ↔ 제거</em></p>
                        </div>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>~ 도입<span class="rule-alias">~I</span></h3>
                    <div class="rule-variants">
                        <div class="rule-scheme rule-scheme-subproof-intro">
                            <div class="subproof-box">
                                <p><span>i</span><strong>A</strong><em>가정</em></p>
                                <p><span>j</span><strong>⊥</strong></p>
                            </div>
                            <p class="rule-conclusion"><strong>~A</strong><em><span>i-j</span>, ~ 도입</em></p>
                        </div>
                        <div class="rule-scheme rule-scheme-subproof-intro">
                            <div class="subproof-box">
                                <p><span>i</span><strong>A</strong><em>가정</em></p>
                                <p><span></span><strong>B</strong></p>
                                <p><span>j</span><strong>~B</strong></p>
                            </div>
                            <p class="rule-conclusion"><strong>~A</strong><em><span>i-j</span>, ~ 도입</em></p>
                        </div>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>~~ 제거<span class="rule-alias">~~E</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>~~A</strong></p>
                        <p class="rule-conclusion"><strong>A</strong><em><span>i</span>, ~~ 제거</em></p>
                    </div>
                </article>

                <article class="rule-card">
                    <h3>⊥ 도입<span class="rule-alias">_I</span></h3>
                    <div class="rule-scheme">
                        <p><span>i</span><strong>A</strong></p>
                        <p><span>j</span><strong>~A</strong></p>
                        <p class="rule-conclusion"><strong>⊥</strong><em><span>i, j</span>, ⊥ 도입</em></p>
                    </div>
                </article>
                </div>
            </details>
            <details class="rule-section">
                <summary class="input-guide-section-title rule-section-title">파생 규칙</summary>
                <div class="rule-list derived-rule-list">
                    <article class="rule-card">
                        <h3>~~ 도입<span class="rule-alias">~~I</span></h3>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A</strong></p>
                            <p class="rule-conclusion"><strong>~~A</strong><em><span>i</span>, ~~ 도입</em></p>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>후건 부정<span class="rule-alias">MT</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A → B</strong></p>
                                <p><span>j</span><strong>~B</strong></p>
                                <p class="rule-conclusion"><strong>~A</strong><em><span>i, j</span>, MT</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A → ~B</strong></p>
                                <p><span>j</span><strong>B</strong></p>
                                <p class="rule-conclusion"><strong>~A</strong><em><span>i, j</span>, MT</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>연쇄 논법<span class="rule-alias">HS</span></h3>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A → B</strong></p>
                            <p><span>j</span><strong>B → C</strong></p>
                            <p class="rule-conclusion"><strong>A → C</strong><em><span>i, j</span>, HS</em></p>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>대우 규칙<span class="rule-alias">CP</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A → B</strong></p>
                                <p class="rule-conclusion"><strong>~B → ~A</strong><em><span>i</span>, CP</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A → ~B</strong></p>
                                <p class="rule-conclusion"><strong>B → ~A</strong><em><span>i</span>, CP</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>약화<span class="rule-alias">W</span></h3>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A</strong></p>
                            <p class="rule-conclusion"><strong>B → A</strong><em><span>i</span>, W</em></p>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>경우 논증<span class="rule-alias">AC</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A ∨ B</strong></p>
                                <p><span>j</span><strong>A → C</strong></p>
                                <p><span>k</span><strong>B → C</strong></p>
                                <p class="rule-conclusion"><strong>C</strong><em><span>i, j, k</span>, AC</em></p>
                            </div>
                            <div class="rule-scheme rule-scheme-subproof-intro">
                                <p><span>i</span><strong>A ∨ B</strong></p>
                                <div class="subproof-box">
                                    <p><span>j</span><strong>A</strong><em>가정</em></p>
                                    <p><span>k</span><strong>C</strong></p>
                                </div>
                                <div class="subproof-box">
                                    <p><span>m</span><strong>B</strong><em>가정</em></p>
                                    <p><span>n</span><strong>C</strong></p>
                                </div>
                                <p class="rule-conclusion"><strong>C</strong><em><span>i, j-k, m-n</span>, AC</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>교환 규칙<span class="rule-alias">Com</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A ∨ B</strong></p>
                                <p class="rule-conclusion"><strong>B ∨ A</strong><em><span>i</span>, Com</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A &amp; B</strong></p>
                                <p class="rule-conclusion"><strong>B &amp; A</strong><em><span>i</span>, Com</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>결합 규칙<span class="rule-alias">Asso</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A ∨ (B ∨ C)</strong></p>
                                <p class="rule-conclusion"><strong>(A ∨ B) ∨ C</strong><em><span>i</span>, Asso</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(A ∨ B) ∨ C</strong></p>
                                <p class="rule-conclusion"><strong>A ∨ (B ∨ C)</strong><em><span>i</span>, Asso</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A &amp; (B &amp; C)</strong></p>
                                <p class="rule-conclusion"><strong>(A &amp; B) &amp; C</strong><em><span>i</span>, Asso</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(A &amp; B) &amp; C</strong></p>
                                <p class="rule-conclusion"><strong>A &amp; (B &amp; C)</strong><em><span>i</span>, Asso</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>분배 규칙<span class="rule-alias">Dist</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A &amp; (B ∨ C)</strong></p>
                                <p class="rule-conclusion"><strong>(A &amp; B) ∨ (A &amp; C)</strong><em><span>i</span>, Dist</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(A &amp; B) ∨ (A &amp; C)</strong></p>
                                <p class="rule-conclusion"><strong>A &amp; (B ∨ C)</strong><em><span>i</span>, Dist</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A ∨ (B &amp; C)</strong></p>
                                <p class="rule-conclusion"><strong>(A ∨ B) &amp; (A ∨ C)</strong><em><span>i</span>, Dist</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(A ∨ B) &amp; (A ∨ C)</strong></p>
                                <p class="rule-conclusion"><strong>A ∨ (B &amp; C)</strong><em><span>i</span>, Dist</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>드 모르간의 규칙<span class="rule-alias">DeM</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~(A ∨ B)</strong></p>
                                <p class="rule-conclusion"><strong>~A &amp; ~B</strong><em><span>i</span>, DeM</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~A &amp; ~B</strong></p>
                                <p class="rule-conclusion"><strong>~(A ∨ B)</strong><em><span>i</span>, DeM</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~(A &amp; B)</strong></p>
                                <p class="rule-conclusion"><strong>~A ∨ ~B</strong><em><span>i</span>, DeM</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~A ∨ ~B</strong></p>
                                <p class="rule-conclusion"><strong>~(A &amp; B)</strong><em><span>i</span>, DeM</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>조건문 규칙<span class="rule-alias">Cond</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A → B</strong></p>
                                <p class="rule-conclusion"><strong>~A ∨ B</strong><em><span>i</span>, Cond</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~A ∨ B</strong></p>
                                <p class="rule-conclusion"><strong>A → B</strong><em><span>i</span>, Cond</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~(A → B)</strong></p>
                                <p class="rule-conclusion"><strong>A &amp; ~B</strong><em><span>i</span>, Cond</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>A &amp; ~B</strong></p>
                                <p class="rule-conclusion"><strong>~(A → B)</strong><em><span>i</span>, Cond</em></p>
                            </div>
                        </div>
                    </article>
                    <article class="rule-card">
                        <h3>배중률<span class="rule-alias">LEM</span></h3>
                        <div class="rule-scheme">
                            <p class="rule-conclusion"><strong>A ∨ ~A</strong><em>LEM</em></p>
                        </div>
                    </article>
                </div>
            </details>
        </section>

        <section class="rules-panel" id="predicate-rules" aria-labelledby="predicateRulesTitle">
            <h2 id="predicateRulesTitle">술어 논리의 추론 규칙</h2>
            <details class="rule-section">
                <summary class="input-guide-section-title rule-section-title">기본 규칙</summary>
                <div class="rule-list">
                    <article class="rule-card">
                        <h3>∀ 제거<span class="rule-alias">AE</span></h3>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>(∀x)A(x)</strong></p>
                            <p class="rule-conclusion"><strong>A(t)</strong><em><span>i</span>, ∀ 제거</em></p>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>∀ 도입<span class="rule-alias">AI</span></h3>
                        <div class="rule-scheme rule-scheme-subproof-intro rule-scheme-universal-intro">
                            <div class="subproof-box">
                                <p class="rule-arbitrary-name-row"><span>i</span><strong class="arbitrary-name-marker">u</strong></p>
                                <p class="rule-ellipsis-row"><span></span><strong>⋮</strong></p>
                                <p><span>j</span><strong>A(u)</strong></p>
                            </div>
                            <p class="rule-conclusion"><strong>(∀x)A(x)</strong><em><span>i-j</span>, ∀ 도입</em></p>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>∃ 도입<span class="rule-alias">EI</span></h3>
                        <div class="rule-scheme">
                            <p><span>i</span><strong>A(t)</strong></p>
                            <p class="rule-conclusion"><strong>(∃x)A(x)</strong><em><span>i</span>, ∃ 도입</em></p>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>∃ 제거<span class="rule-alias">EE</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(∃x)A(x)</strong></p>
                                <p><span>j</span><strong>(∀x)(A(x) → C)</strong></p>
                                <p class="rule-conclusion"><strong>C</strong><em><span>i, j</span>, ∃ 제거</em></p>
                            </div>
                            <div class="rule-scheme rule-scheme-subproof-intro rule-scheme-universal-intro rule-scheme-existential-elim">
                                <p><span>i</span><strong>(∃x)A(x)</strong></p>
                                <div class="subproof-box">
                                    <p class="rule-arbitrary-name-row"><span>j</span><strong class="arbitrary-name-marker">d</strong><strong>A(d)</strong><em>가정 (<span>i</span>, ∃ 제거용)</em></p>
                                    <p class="rule-ellipsis-row"><span></span><strong>⋮</strong></p>
                                    <p><span>k</span><strong>C</strong></p>
                                </div>
                                <p class="rule-conclusion"><strong>C</strong><em><span>i, j-k</span>, ∃ 제거</em></p>
                            </div>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>= 도입<span class="rule-alias">=I</span></h3>
                        <div class="rule-scheme">
                            <p class="rule-conclusion"><strong>s=s</strong><em>= 도입</em></p>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>= 제거<span class="rule-alias">=E</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>s=t</strong></p>
                                <p><span>j</span><strong>P(s)</strong></p>
                                <p class="rule-conclusion"><strong>P(t)</strong><em><span>i, j</span>, = 제거</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>s=t</strong></p>
                                <p><span>j</span><strong>P(t)</strong></p>
                                <p class="rule-conclusion"><strong>P(s)</strong><em><span>i, j</span>, = 제거</em></p>
                            </div>
                        </div>
                    </article>
                </div>
            </details>
            <details class="rule-section">
                <summary class="input-guide-section-title rule-section-title">파생 규칙</summary>
                <div class="rule-list">
                    <article class="rule-card">
                        <h3>존재 양화사의 부정<span class="rule-alias">~E</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~(∃x)A(x)</strong></p>
                                <p class="rule-conclusion"><strong>(∀x)~A(x)</strong><em><span>i</span>, ~∃</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(∀x)~A(x)</strong></p>
                                <p class="rule-conclusion"><strong>~(∃x)A(x)</strong><em><span>i</span>, ~∃</em></p>
                            </div>
                        </div>
                    </article>

                    <article class="rule-card">
                        <h3>보편 양화사의 부정<span class="rule-alias">~A</span></h3>
                        <div class="rule-variants">
                            <div class="rule-scheme">
                                <p><span>i</span><strong>~(∀x)A(x)</strong></p>
                                <p class="rule-conclusion"><strong>(∃x)~A(x)</strong><em><span>i</span>, ~∀</em></p>
                            </div>
                            <div class="rule-scheme">
                                <p><span>i</span><strong>(∃x)~A(x)</strong></p>
                                <p class="rule-conclusion"><strong>~(∀x)A(x)</strong><em><span>i</span>, ~∀</em></p>
                            </div>
                        </div>
                    </article>
                </div>
            </details>
        </section>
            </div>

            <aside class="input-guide" aria-label="입력 가이드">
                <h2>입력 가이드</h2>
                <details class="guide-section">
                    <summary class="input-guide-section-title guide-section-title">예제</summary>
                    <a class="guide-example-proof" href="#" data-guide-key="propositional-proof" aria-label="초보자용 대표 예제 불러오기">
                        <div class="guide-example-target">
                            <span>문제</span>
                            <strong>A → ~B / B ∨ C // A → C</strong>
                        </div>
                        <div class="guide-example-lines">
                            <div class="guide-example-line">
                                <span>1</span>
                                <strong>A → ~B</strong>
                                <em>전제</em>
                            </div>
                            <div class="guide-example-line">
                                <span>2</span>
                                <strong>B ∨ C</strong>
                                <em>전제</em>
                            </div>
                            <div class="guide-example-line is-subproof is-subproof-start">
                                <span>3</span>
                                <strong>A</strong>
                                <em>가정</em>
                            </div>
                            <div class="guide-example-line is-subproof">
                                <span>4</span>
                                <strong>~B</strong>
                                <em>1, 3, → 제거</em>
                            </div>
                            <div class="guide-example-line is-subproof is-subproof-end">
                                <span>5</span>
                                <strong>C</strong>
                                <em>2, 4, ∨ 제거</em>
                            </div>
                            <div class="guide-example-line">
                                <span>6</span>
                                <strong>A → C</strong>
                                <em>3-5, → 도입</em>
                            </div>
                        </div>
                    </a>
                    <a class="guide-example-proof" href="#" data-guide-key="predicate-proof" aria-label="술어 논리 예제 불러오기">
                        <div class="guide-example-target">
                            <span>문제</span>
                            <strong>~(∃x)Lxe // (∀x)~Lxe</strong>
                        </div>
                        <div class="guide-example-lines guide-example-lines-predicate">
                            <div class="guide-example-line">
                                <span>1</span>
                                <strong>~(∃x)Lxe</strong>
                                <em>전제</em>
                            </div>
                            <div class="guide-example-line is-predicate-name is-predicate-name-start is-predicate-assumption is-predicate-assumption-start">
                                <span>2</span>
                                <strong><span class="guide-example-name-marker">u</span>Lue</strong>
                                <em>가정</em>
                            </div>
                            <div class="guide-example-line is-predicate-name is-predicate-assumption">
                                <span>3</span>
                                <strong>(∃x)Lxe</strong>
                                <em>2, ∃ 도입</em>
                            </div>
                            <div class="guide-example-line is-predicate-name is-predicate-assumption is-predicate-assumption-end">
                                <span>4</span>
                                <strong>⊥</strong>
                                <em>1, 3, ⊥ 도입</em>
                            </div>
                            <div class="guide-example-line is-predicate-name is-predicate-name-end">
                                <span>5</span>
                                <strong>~Lue</strong>
                                <em>2-4, ~ 도입</em>
                            </div>
                            <div class="guide-example-line">
                                <span>6</span>
                                <strong>(∀x)~Lxe</strong>
                                <em>2-5, ∀ 도입</em>
                            </div>
                        </div>
                    </a>
                </details>
                <details class="guide-section">
                    <summary class="input-guide-section-title guide-section-title">논리식</summary>
                    <dl class="input-rule-list">
                        <div>
                            <dt>단순 문장 or 술어</dt>
                            <dd class="has-example">
                                <span>알파벳 대문자+[숫자]</span>
                                <small class="input-rule-example">예: P, Q, X<sub>1</sub>, F<sub>2</sub></small>
                            </dd>
                        </div>
                        <div class="input-rule-term-group">
                            <dt>
                                <span class="term-group-label">항</span>
                                <span class="term-kind">이름</span>
                                <span class="term-kind">변항</span>
                            </dt>
                            <dd>
                                <span class="term-entry">
                                    <span>알파벳 소문자(a~v)+[숫자]</span>
                                    <small class="input-rule-example">예: a, d, u, b<sub>1</sub>, v<sub>2</sub></small>
                                </span>
                                <span class="term-entry">
                                    <span>알파벳 소문자(w~z)+[숫자]</span>
                                    <small class="input-rule-example">예: x, y, z, w, x<sub>1</sub>, y<sub>2</sub></small>
                                </span>
                            </dd>
                        </div>
                        <div class="input-rule-atomic-group">
                            <dt>
                            	<span class="atomic-group-label">원자식</span>
                            	<span class="atomic-kind">단순 문장</span>
                            	<span class="atomic-kind">술어+항</span>
                            	<span class="atomic-kind">동일성</span>
                            </dt>
                            <dd>
                            	<span class="atomic-entry">
                                	<small class="input-rule-example">예: A, B<sub>1</sub>, P<sub>2</sub>, Q<sub>10</sub>, X<sub>2</sub>, Y, ⊥(모순)</small>
                                </span>
                            	<span class="atomic-entry">
                                	<small class="input-rule-example">예: Fa, G<sub>1</sub>ax, Lxe, Bx<sub>1</sub>x<sub>2</sub>x<sub>3</sub></small>
                                </span>
                            	<span class="atomic-entry">
                                	<small class="input-rule-example">예: a=b, c≠x, y=z, u<sub>1</sub>≠u<sub>2</sub></small>
                                </span>
                                <span clsss="atomic-entry">
                                	
                            </dd>
                        </div>
                        <div>
                            <dt>부정</dt>
                            <dd>not &nbsp;~ &nbsp;¬ &nbsp;∼ &nbsp;- &nbsp;−</dd>
                        </div>
                        <div>
                            <dt>연언</dt>
                            <dd>and &nbsp;&amp; &nbsp;∧ &nbsp;^ &nbsp;. &nbsp;· &nbsp;*</dd>
                        </div>
                        <div>
                            <dt>선언</dt>
                            <dd>or &nbsp;∨ &nbsp;v &nbsp;| &nbsp;+</dd>
                        </div>
                        <div>
                            <dt>조건</dt>
                            <dd>imp &nbsp;→ &nbsp;⇒ &nbsp;⊃ &nbsp;-&gt; &nbsp;=&gt; &nbsp;&gt;</dd>
                        </div>
                        <div>
                            <dt>쌍조건</dt>
                            <dd>iff &nbsp;↔ &nbsp;⇔ &nbsp;≡ &nbsp;&lt;-&gt; &nbsp;&lt;=&gt; &nbsp;&lt;&gt;</dd>
                        </div>
                        <div>
                            <dt>모순</dt>
                            <dd>bot &nbsp;⊥ &nbsp;XX &nbsp;# &nbsp;_</dd>
                        </div>
                        <div>
                            <dt>보편 양화</dt>
                            <dd>(forall x) &nbsp;(∀x) &nbsp;(Ay) &nbsp;(!z)</dd>
                        </div>
                        <div>
                            <dt>존재 양화</dt>
                            <dd>(exists x) &nbsp;(∃x) &nbsp;(Ey) &nbsp;(?z)</dd>
                        </div>
                        <div>
                            <dt>동일성</dt>
                            <dd>=</dd>
                        </div>
                        <div>
                            <dt>동일성의 부정</dt>
                            <dd>≠ &nbsp;neq</dd>
                        </div>
                    </dl>
                </details>
                <details class="guide-section">
                    <summary class="input-guide-section-title guide-section-title">보조증명</summary>
                    <dl class="input-rule-list">
                        <div>
                            <dt>개시</dt>
                            <dd>
                                [정당화]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-guide-key="subproof-start"
                                    aria-label="AS 보조증명 개시 예제 불러오기"
                                >AS</button>
                            </dd>
                        </div>
                        <div>
                            <dt>종료</dt>
                            <dd>
                                [논리식 끝]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-guide-key="subproof-end"
                                    aria-label="보조증명 종료 예제 불러오기"
                                >/</button>
                            </dd>
                        </div>
                        <div>
                            <dt>임의 이름 도입</dt>
                            <dd>
                                [논리식 앞] 이름&nbsp;
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-guide-key="arbitrary-name"
                                    aria-label="임의 이름 u 예제 불러오기"
                                >u</button>
                                ,&nbsp; 
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-guide-key="multiple-arbitrary-names"
                                    aria-label="여러 임의 이름 예제 불러오기"
                                >u s t</button>
                            </dd>
                        </div>
                        <div>
                            <dt>∃ 제거용 가정</dt>
                            <dd>
                                [정당화]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-guide-key="existential-elim-assumption"
                                    aria-label="존재 양화사 제거용 가정 예제 불러오기"
                                >AS (for i, EE)</button>
                            </dd>
                        </div>
                    </dl>
                </details>
            </aside>
        </div>
        <?php endif; ?>

        <footer class="site-footer">
            made by <a href="https://zolaist.org/wiki">zolaist</a>
        </footer>
    </main>
    <?php if (!$isExercisesView): ?>
    <script src="assets/app.js?v=unified-examples-20260726"></script>
    <?php endif; ?>
</body>
</html>
