<?php
$isExercisesView = ($_GET['view'] ?? '') === 'exercises';

function normalizeExerciseProblem(string $problem): string
{
    $normalized = str_replace(
        ['<=>', '<->', '=>', '->', '≡'],
        ['↔', '↔', '→', '→', '↔'],
        $problem,
    );
    $normalized = preg_replace_callback(
        '/\(([AE])([w-z]\d*)\)/u',
        static fn(array $match): string => sprintf(
            '(%s%s)',
            $match[1] === 'A' ? '∀' : '∃',
            $match[2],
        ),
        $normalized,
    );
    $normalized = preg_replace('/\s+v\s+/u', ' ∨ ', $normalized);

    return $normalized;
}

$examples = [
    [
        'title' => '~~ 도입',
        'problem' => 'X // ~~X',
    ],
    [
        'title' => '후건 부정 a',
        'problem' => 'X → Y / ~Y // ~X',
    ],
    [
        'title' => '후건 부정 b',
        'problem' => 'X → ~Y / Y // ~X',
    ],
    [
        'title' => '연쇄 논법',
        'problem' => 'X → Y / Y → Z // X → Z',
    ],
    [
        'title' => '대우 규칙 a',
        'problem' => 'X → Y // ~Y → ~X',
    ],
    [
        'title' => '대우 규칙 b',
        'problem' => 'X → ~Y // Y → ~X',
    ],
    [
        'title' => '약화',
        'problem' => 'Y // X → Y',
    ],
    [
        'title' => '경우 논증',
        'problem' => 'X ∨ Y / X → Z / Y → Z // Z',
    ],
    [
        'title' => '교환 규칙 a',
        'problem' => '// (X ∨ Y) ≡ (Y ∨ X)',
    ],
    [
        'title' => '교환 규칙 b',
        'problem' => '// (X & Y) ≡ (Y & X)',
    ],
    [
        'title' => '결합 규칙 a',
        'problem' => '// (X ∨ (Y ∨ Z)) ≡ ((X ∨ Y) ∨ Z)',
    ],
    [
        'title' => '결합 규칙 b',
        'problem' => '// (X & (Y & Z)) ≡ ((X & Y) & Z)',
    ],
    [
        'title' => '분배 규칙 a1',
        'problem' => 'X & (Y ∨ Z) // (X & Y) ∨ (X & Z)',
    ],
    [
        'title' => '분배 규칙 a2',
        'problem' => '(X & Y) ∨ (X & Z) // X & (Y ∨ Z)',
    ],
    [
        'title' => '분배 규칙 b1',
        'problem' => 'X ∨ (Y & Z) // (X ∨ Y) & (X ∨ Z)',
    ],
    [
        'title' => '분배 규칙 b2',
        'problem' => '(X ∨ Y) & (X ∨ Z) // X ∨ (Y & Z)',
    ],
    [
        'title' => '드 모르간의 규칙 a1',
        'problem' => '~(X ∨ Y) // ~X & ~Y',
    ],
    [
        'title' => '드 모르간의 규칙 a2',
        'problem' => '~X & ~Y // ~(X ∨ Y)',
    ],
    [
        'title' => '드 모르간의 규칙 b1',
        'problem' => '~(X & Y) // ~X ∨ ~Y',
    ],
    [
        'title' => '드 모르간의 규칙 b2',
        'problem' => '~X ∨ ~Y // ~(X & Y)',
    ],
    [
        'title' => '조건문 규칙 a1',
        'problem' => 'X → Y // ~X ∨ Y',
    ],
    [
        'title' => '조건문 규칙 a2',
        'problem' => '~X ∨ Y // X → Y',
    ],
    [
        'title' => '조건문 규칙 b1',
        'problem' => '~(X → Y) // X & ~Y',
    ],
    [
        'title' => '조건문 규칙 b2',
        'problem' => 'X & ~Y // ~(X → Y)',
    ],
    [
        'title' => '폭발의 원리',
        'problem' => 'X & ~X // Y',
    ],
    [
        'title' => '배중률',
        'problem' => '// X v ~X',
    ],
    [
        'title' => '동일률',
        'problem' => '// X -> X',
    ],
];

$basicRuleProofExercises = [
    [
        'title' => '기본 규칙 활용 1',
        'problem' => 'A → B / A & C / (B & C) → D // D',
    ],
    [
        'title' => '기본 규칙 활용 2',
        'problem' => '~A ∨ B / ~B / A ∨ C // ~B & C',
    ],
    [
        'title' => '기본 규칙 활용 3',
        'problem' => 'D / (D ∨ A) → (B ∨ C) / ~B / (C ∨ ~F) → (G & H) // G',
    ],
    [
        'title' => '기본 규칙 활용 4',
        'problem' => '~(A & G) ∨ C / ~C / (A & G) ∨ (~D ∨ F) / E → B / E ∨ D / ~F // B',
    ],
    [
        'title' => '기본 규칙 활용 5',
        'problem' => 'B & D / (B ∨ F) → (A & G) / (G → E) & ~C // ~C & E',
    ],
    [
        'title' => '기본 규칙 활용 6',
        'problem' => '(B → D) & (C → E) / B ∨ C / (D ∨ E) → (A ∨ F) / ~A & ~C // F & B',
    ],
    [
        'title' => '기본 규칙 활용 7',
        'problem' => '(~A ∨ B) → (C → D) / (~F ∨ B) → (D → E) / ~A & ~F // C → E',
    ],
    [
        'title' => '기본 규칙 활용 8',
        'problem' => '~(A → ~C) / (D → E) ∨ (A → ~C) / E → ~B // D → ~B',
    ],
    [
        'title' => '기본 규칙 활용 9',
        'problem' => '~A ∨ ~B / A ∨ (C → ~D) / ~~B / ~D → E // C → E',
    ],
    [
        'title' => '기본 규칙 활용 10',
        'problem' => 'A → ~B / C ∨ A / B // C',
    ],
    [
        'title' => '기본 규칙 활용 11',
        'problem' => 'A ∨ (B & C) / B → D / ~D // A',
    ],
    [
        'title' => '기본 규칙 활용 12',
        'problem' => 'A ∨ ~(D ∨ C) / ~(A ∨ ~D) ∨ B // B',
    ],
];

$derivedRuleProofExercises = [
    [
        'title' => '파생 규칙 활용 1',
        'problem' => '~A → B / B → ~C // C → A',
    ],
    [
        'title' => '파생 규칙 활용 2',
        'problem' => 'A ↔ ~B / A ↔ C // ~B ↔ C',
    ],
    [
        'title' => '파생 규칙 활용 3',
        'problem' => '~A → ((B → D) → (A ∨ ~C)) / (B → C) → ~A / B → D / D → C // ~D',
    ],
    [
        'title' => '파생 규칙 활용 4',
        'problem' => '~A → (C & B) / ~D → (B & E) / ~A ∨ ~D // B',
    ],
    [
        'title' => '파생 규칙 활용 5',
        'problem' => '(B → A) & (A → (D → ~C)) / (D ∨ E) ↔ (A ∨ B) / ((E → ~C) ∨ ~A) & B // ~C',
    ],
    [
        'title' => '파생 규칙 활용 6',
        'problem' => '(A ∨ D) ∨ C / (A ∨ C) → ~B / ~D // ~(D ∨ B)',
    ],
    [
        'title' => '파생 규칙 활용 7',
        'problem' => 'B → (C → A) / C → (A → ~C) // ~B ∨ ~C',
    ],
    [
        'title' => '파생 규칙 활용 8',
        'problem' => 'C ∨ D / ~D ∨ ~(A & B) // ~C → (A → ~B)',
    ],
    [
        'title' => '파생 규칙 활용 9',
        'problem' => '(C & B) → D / (B → D) → ~A / ~(E ∨ ~C) // ~A',
    ],
    [
        'title' => '파생 규칙 활용 10',
        'problem' => 'A → ~B / ~(A & B) → (~C ∨ (D & E)) // ~C ∨ E',
    ],
    [
        'title' => '파생 규칙 활용 11',
        'problem' => '~C → (C ∨ (A → D)) / C → (C & B) / ~C ∨ ~B / ~D // ~A',
    ],
    [
        'title' => '파생 규칙 활용 12',
        'problem' => 'C → (B → ~A) / B → (~A → ~D) // C → (D → ~B)',
    ],
    [
        'title' => '파생 규칙 활용 13',
        'problem' => 'A → (B → C) / B → (C → ~D) // A → (~D ∨ ~B)',
    ],
    [
        'title' => '파생 규칙 활용 14',
        'problem' => '(A ∨ (B ∨ C)) → ~(D & ~C) / E → (B ∨ (C ∨ A)) // (E & D) → C',
    ],
];

$sentenceLogicTruthExercises = [
    [
        'title' => '논리적 참 1',
        'problem' => '// (A → B) → (~B → ~(A & C))',
    ],
    [
        'title' => '논리적 참 2',
        'problem' => '// (A & ~B) ∨ ((B & C) ∨ ~(C & A))',
    ],
    [
        'title' => '논리적 참 3',
        'problem' => '// ((A → B) & (A → ~B)) → ~A',
    ],
    [
        'title' => '논리적 참 4',
        'problem' => '// ((A → C) & (B → C)) → ((A ∨ B) → C)',
    ],
    [
        'title' => '논리적 참 5',
        'problem' => '// A ↔ ((A & B) ∨ (A & ~B))',
    ],
    [
        'title' => '논리적 참 6',
        'problem' => '// ((A ∨ B) & (~B ∨ ~(C & D))) → (~A → (C → ~D))',
    ],
    [
        'title' => '논리적 참 7',
        'problem' => '// ~(A & ~B) ∨ (C → (~A ∨ (C ∨ B)))',
    ],
];

$exampleGroups = [
    [
        'title' => '문장 논리의 기본 규칙 활용 증명',
        'items' => $basicRuleProofExercises,
    ],
    [
        'title' => '파생 규칙 1',
        'items' => array_slice($examples, 0, 8),
    ],
    [
        'title' => '파생 규칙 2',
        'items' => array_slice($examples, 8, 16),
    ],
    [
        'title' => '문장 논리의 파생 규칙 활용 증명',
        'items' => $derivedRuleProofExercises,
    ],
    [
        'title' => '유명한 원리들',
        'items' => array_merge(
            array_slice($examples, 24),
            [[
                'title' => '수출 규칙',
                'problem' => '(X & Y) → Z // X → (Y → Z)',
            ]],
        ),
    ],
    [
        'title' => '문장 논리의 논리적 참',
        'items' => $sentenceLogicTruthExercises,
    ],
    [
        'title' => '양화사의 부정 규칙들',
        'items' => [
            [
                'title' => '존재 양화사의 부정 a',
                'problem' => '~(∃x)Fx // (∀x)~Fx',
            ],
            [
                'title' => '존재 양화사의 부정 b',
                'problem' => '(∀x)~Fx // ~(∃x)Fx',
            ],
            [
                'title' => '보편 양화사의 부정 a',
                'problem' => '~(∀x)Fx // (∃x)~Fx',
            ],
            [
                'title' => '보편 양화사의 부정 b',
                'problem' => '(∃x)~Fx // ~(∀x)Fx',
            ],
        ],
    ],
    [
        'title' => '동일성 관계의 특징들',
        'items' => [
            [
                'title' => '동일성의 재귀성',
                'problem' => '// (Ax)(x=x)',
            ],
            [
                'title' => '동일성의 대칭성',
                'problem' => '// (Ax)(Ay)(x=y -> y=x)',
            ],
            [
                'title' => '동일성의 이행성',
                'problem' => '// (Ax)(Ay)(Az)((x=y & y=z) -> x=z)',
            ],
            [
                'title' => '동일자 구별불가능성 원리',
                'problem' => '// (Ax)(Ay)(x=y -> (Fx <-> Fy))',
            ],
        ],
    ],
    [
        'title' => '술어 논리의 다양한 문제들',
        'items' => [
            [
                'title' => '술어 논리 문제 1',
                'problem' => '~(Ex)((Nx & Px) & Sx) // (Ax)((Nx & Px) -> ~Sx)',
            ],
            [
                'title' => '술어 논리 문제 2',
                'problem' => '~(Ax)(Px -> (Nx & Gx)) // (Ex)(Px & (~Nx v ~Gx))',
            ],
            [
                'title' => '술어 논리 문제 3',
                'problem' => 'Dd / (Ex)(Qx & Rxd) // (Ex)(Dx & (Ey)(Qy & Ryx))',
            ],
            [
                'title' => '술어 논리 문제 4',
                'problem' => '(Ex)(Dx & (Ay)(Py -> Cyx)) / (Ax)(Px -> (Ey)(Dy & Cxy))',
            ],
            [
                'title' => '술어 논리 문제 5',
                'problem' => '~(Ax)(Px -> (Ay)(Py -> Dxy)) // (Ex)(Px & (Ey)(Py & ~Dxy))',
            ],
            [
                'title' => '술어 논리 문제 6',
                'problem' => '(Ax)(Px -> (Ey)(Py & Lyx)) // ~(Ex)(Px & (Ay)(Py -> ~Lyx))',
            ],
            [
                'title' => '술어 논리 문제 7',
                'problem' => '(Ex)(Sx & (Ay)(My -> Pyx)) // (Ax)(Mx -> (Ey)(Sy & Pxy))',
            ],
            [
                'title' => '술어 논리 문제 8',
                'problem' => '~(Ex)(Px & (Ay)(Py -> ~Ryx)) // (Ax)(Px -> (Ey)(Py & Ryx))',
            ],
            [
                'title' => '술어 논리 문제 9',
                'problem' => '(Ax)(~(Px & Lxa) -> ~Lxe) // (Ax)((Px & Lxe) -> Lxa)',
            ],
        ],
    ],
];

foreach ($exampleGroups as &$exampleGroup) {
    foreach ($exampleGroup['items'] as &$example) {
        $example['problem'] = normalizeExerciseProblem($example['problem']);
    }
    unset($example);
}
unset($exampleGroup);

$exampleCategories = [
    [
        'title' => '문장 논리',
        'sections' => array_slice($exampleGroups, 0, 6),
    ],
    [
        'title' => '술어 논리',
        'sections' => [
            $exampleGroups[6],
            $exampleGroups[8],
            $exampleGroups[7],
        ],
    ],
];

$beginnerExample = [
    'problem' => 'A -> ~B / B v C // A -> C',
    'answer' => "A :: AS\n~B :: 1,3,>E\nC / :: 2,4,vE\nA -> C :: 3-5,>I",
];

$predicateExample = [
    'problem' => '~(exists x)Lxe // (forall x)~Lxe',
    'answer' => "[u] Lue :: AS\n(exists x)Lxe :: 2,EI\n_ / :: 1,3,_I\n~Lue / :: 2-4,~I\n(forall x)~Lxe :: 2-5,AI",
];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isExercisesView ? '연습문제 - ' : '' ?>자연 연역 실험실</title>
    <link rel="stylesheet" href="assets/app.css?v=subproof-guide-examples-20260723">
</head>
<body>
    <main class="app-shell">
        <header class="topbar">
            <div class="header-copy">
                <p class="eyebrow">기호논리학</p>
                <h1><a class="site-title-link" href="./">자연 연역 실험실</a></h1>
                <p class="site-description">이 곳에서는 이병덕의 『코어논리학 : 논리적 추론과 증명 테크닉』(성균관대학교출판부, 2019)의 문법과 추론 규칙에 기반하여 자연 연역을 구성하고 검증할 수 있습니다.</p>
                <nav class="site-nav" aria-label="주요 메뉴">
                    <a class="<?= $isExercisesView ? '' : 'is-active' ?>" href="./">증명 편집기</a>
                    <a class="<?= $isExercisesView ? 'is-active' : '' ?>" href="?view=exercises">연습문제</a>
                </nav>
            </div>
        </header>

        <?php if ($isExercisesView): ?>
        <div class="example-group-list" id="examples">
            <?php foreach ($exampleCategories as $category): ?>
                <article class="example-category-card">
                    <h2><?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php foreach ($category['sections'] as $section): ?>
                    <section class="example-section">
                        <h3><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="example-list">
                            <?php foreach ($section['items'] as $example): ?>
                                <a class="example-card" href="?problem=<?= rawurlencode($example['problem']) ?>">
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
                    <a class="guide-example-proof" href="?problem=<?= rawurlencode($beginnerExample['problem']) ?>&amp;answer=<?= rawurlencode($beginnerExample['answer']) ?>" aria-label="초보자용 대표 예제 불러오기">
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
                    <a class="guide-example-proof" href="?problem=<?= rawurlencode($predicateExample['problem']) ?>&amp;answer=<?= rawurlencode($predicateExample['answer']) ?>" aria-label="술어 논리 예제 불러오기">
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
                            <dt>원자식</dt>
                            <dd class="has-example">
                                <span>단순 문장 or 술어+항(들) or 항=항 or 항≠항</span>
                                <small class="input-rule-example">예: P, Qa, Fx, Gy<sub>1</sub>y<sub>2</sub>, a=b, x≠y</small>
                            </dd>
                        </div>
                        <div>
                            <dt>단순 문장 or 술어</dt>
                            <dd class="has-example">
                                <span>알파벳 대문자+선택적 숫자</span>
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
                                    <span>알파벳 소문자(a~v)+선택적 숫자</span>
                                    <small class="input-rule-example">예: a, d, u, b<sub>1</sub>, v<sub>2</sub></small>
                                </span>
                                <span class="term-entry">
                                    <span>알파벳 소문자(w~z)+선택적 숫자</span>
                                    <small class="input-rule-example">예: x, y, z, w, x<sub>1</sub>, y<sub>2</sub></small>
                                </span>
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
                                [정당화에]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-example-problem="// P -&gt; P"
                                    data-example-answer="P :: AS"
                                    aria-label="AS 보조증명 개시 예제 불러오기"
                                >AS</button>
                            </dd>
                        </div>
                        <div>
                            <dt>임의 이름</dt>
                            <dd>
                                [논리식 앞에]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-example-problem="(Ax)Fx / (Ax)(Fx -&gt; Gx) // (Ax)Gx"
                                    data-example-answer="[u] Fu :: 1, AE"
                                    aria-label="임의 이름 u 예제 불러오기"
                                >[u]</button>
                                또는
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-example-problem="// (∀x)(∀y)(∀z)((x=y &amp; y=z) → x=z)"
                                    data-example-answer="[u] [s] [t] u=s &amp; s=t :: AS"
                                    aria-label="여러 임의 이름 예제 불러오기"
                                >[u] [s] [t]</button>
                            </dd>
                        </div>
                        <div>
                            <dt>∃ 제거용 가정</dt>
                            <dd>
                                [정당화에]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-example-problem="(Ex)Fx / (Ax)(Fx -&gt; C) // C"
                                    data-example-answer="[d] Fd :: AS (for 1, EE)"
                                    aria-label="존재 양화사 제거용 가정 예제 불러오기"
                                >AS (for 1, EE)</button>
                            </dd>
                        </div>
                        <div>
                            <dt>종료</dt>
                            <dd>
                                [논리식 끝에]
                                <button
                                    class="input-guide-example-trigger"
                                    type="button"
                                    data-example-problem="// P -&gt; P"
                                    data-example-answer="P :: AS&#10;P / :: 1, R"
                                    aria-label="보조증명 종료 예제 불러오기"
                                >/</button>
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
    <script src="assets/app.js?v=optional-bare-name-prefix-20260723"></script>
    <?php endif; ?>
</body>
</html>
