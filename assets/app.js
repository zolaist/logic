const formulaInput = document.querySelector('#formulaInput');
const proofList = document.querySelector('#proofList');
const proofPanel = document.querySelector('#proof-editor');
const editorLine = document.querySelector('.editor-line');
const activeLineNumber = document.querySelector('#activeLineNumber');
const formulaWarning = document.querySelector('#formulaWarning');
const ruleTextInput = document.querySelector('#ruleTextInput');
const ruleSelect = document.querySelector('#ruleSelect');
const refsInput = document.querySelector('#refsInput');
const completeButton = document.querySelector('#completeButton');
const deleteButton = document.querySelector('#deleteButton');

let nextLineNumber = 1;
let editingLineNumber = null;
let isEditingConclusion = false;
let isTargetEditorOpen = true;
let targetConclusionError = '';
let isEditorVisible = true;
let targetConclusion = null;
let loadedExampleTitle = '';
let swipeGesture = null;
let swipedLineNumber = null;
const proofLines = [];
window.proofLines = proofLines;

const SUBPROOF_GUIDE_LEFT_START = 40;
const SUBPROOF_GUIDE_STEP = 16;
const ARBITRARY_NAME_SUBPROOF_GUIDE_STEP = 42;
const SUBPROOF_FORMULA_INDENT_FROM_FIRST_GUIDE = 2;
const SWIPE_DELETE_THRESHOLD = 42;
const PROOF_FORMULA_BASE_RATIO = 0.6;
const PROOF_FORMULA_MAX_RATIO = 0.8;
const PROOF_FORMULA_WIDTH_ALLOWANCE = 8;

let proofLayoutFrame = null;
let proofFormulaMeasurer = null;

const referenceModeByRule = {
    '': 'unselected',
    '전제': 'none',
    '가정': 'none',
    '반복': 'single',
    '& 제거': 'single',
    '∨ 도입': 'single',
    '↔ 제거': 'single',
    '~~ 제거': 'single',
    '¬ 제거': 'single',
    '~ 제거': 'single',
    '∀ 제거': 'single',
    '∀ 도입': 'range',
    '∃ 도입': 'single',
    '∃ 제거': 'pair',
    '∃ 제거용 가정': 'single',
    '= 도입': 'none',
    '= 제거': 'pair',
    '~~ 도입': 'single',
    '& 도입': 'pair',
    '∨ 제거': 'pair',
    '→ 제거': 'pair',
    '↔ 도입': 'pair',
    '⊥ 도입': 'pair',
    'MT': 'pair',
    'HS': 'pair',
    'AC': 'triple',
    'CP': 'single',
    'W': 'single',
    'Com': 'single',
    'Asso': 'single',
    'Dist': 'single',
    'DeM': 'single',
    'Cond': 'single',
    'LEM': 'none',
    '~E': 'single',
    '~A': 'single',
    '→ 도입': 'range',
    '¬ 도입': 'range',
    '~ 도입': 'range',
};

const referenceModeHints = {
    unselected: '규칙을 먼저 선택하세요.',
    none: '이 규칙은 참조줄을 사용하지 않습니다.',
    single: '참조줄 하나를 숫자로 입력하세요.',
    pair: '참조줄 두 개를 숫자로 입력하세요.',
    triple: '참조줄 세 개 또는 한 줄과 두 보조증명 범위를 입력하세요.',
    range: '보조증명 범위를 숫자로 입력하세요.',
};

const ruleCategories = {
    basic: new Set([
        '전제',
        '가정',
        '반복',
        '& 도입',
        '& 제거',
        '∨ 도입',
        'v 도입',
        '∨ 제거',
        'v 제거',
        '→ 도입',
        '→ 제거',
        '↔ 도입',
        '<-> 도입',
        '↔ 제거',
        '<-> 제거',
        '~~ 제거',
        '~ 도입',
        '¬ 도입',
        '~ 제거',
        '¬ 제거',
        '⊥ 도입',
        '∀ 제거',
        '∀ 도입',
        '∃ 도입',
        '∃ 제거',
        '∃ 제거용 가정',
        '= 도입',
        '= 제거',
    ]),
    derived: new Set([
        '~~ 도입',
        'MT',
        'HS',
        'AC',
        'CP',
        'W',
        'Com',
        'Asso',
        'Dist',
        'DeM',
        'Cond',
        'LEM',
        '~E',
        '~A',
    ]),
};

const tokenTypes = {
    ATOM: 'ATOM',
    PREDICATE: 'PREDICATE',
    FALSUM: 'FALSUM',
    NOT: 'NOT',
    AND: 'AND',
    OR: 'OR',
    IF: 'IF',
    IFF: 'IFF',
    QUANTIFIER: 'QUANTIFIER',
    TERM: 'TERM',
    EQUAL: 'EQUAL',
    NOT_EQUAL: 'NOT_EQUAL',
    LPAREN: 'LPAREN',
    RPAREN: 'RPAREN',
};

const NAME_TERM_PATTERN = /^[a-v]\d*$/;
const VARIABLE_TERM_PATTERN = /^[w-z]\d*$/;
const TERM_PATTERN = /^[a-z]\d*/;
const PREDICATE_SYMBOL_PATTERN = /^[A-Z]\d*/;

function isNameTerm(term) {
    return NAME_TERM_PATTERN.test(term);
}

function isVariableTerm(term) {
    return VARIABLE_TERM_PATTERN.test(term);
}

function parseArbitraryNamePrefix(formulaSource) {
    const arbitraryNames = [];
    let remainingSource = formulaSource.trim();
    let match = remainingSource.match(/^\[([a-v]\d*)\]\s+(.+)$/);

    while (match) {
        arbitraryNames.push(match[1]);
        remainingSource = match[2].trim();
        match = remainingSource.match(/^\[([a-v]\d*)\]\s+(.+)$/);
    }

    match = remainingSource.match(/^([a-v]\d*)\s+(.+)$/);

    while (match) {
        const nextSource = match[2].trim();

        if (/^(?:=|≠|neq(?:\s|$))/i.test(nextSource)) {
            break;
        }

        arbitraryNames.push(match[1]);
        remainingSource = nextSource;
        match = remainingSource.match(/^([a-v]\d*)\s+(.+)$/);
    }

    return {
        arbitraryNames,
        arbitraryName: arbitraryNames[arbitraryNames.length - 1] || '',
        formulaSource: remainingSource,
    };
}

function getProofLineArbitraryNames(proofLine) {
    if (Array.isArray(proofLine.arbitraryNames)) {
        return proofLine.arbitraryNames;
    }

    return proofLine.arbitraryName ? [proofLine.arbitraryName] : [];
}

function getOpenedArbitraryNameSubproof(proofLine, subproofId = null) {
    const subproofs = proofLine.openedArbitraryNameSubproofs || [];

    if (subproofId !== null) {
        return subproofs.find((subproof) => subproof.id === subproofId) || null;
    }

    return subproofs[subproofs.length - 1] || null;
}

function getDuplicateArbitraryNameIntro(proofLine) {
    const activeNames = new Set(proofLine.parentArbitraryNames || []);

    return getProofLineArbitraryNames(proofLine).find((arbitraryName) => {
        if (activeNames.has(arbitraryName)) {
            return true;
        }

        activeNames.add(arbitraryName);
        return false;
    }) || '';
}

function parseTermSequence(source) {
    const terms = [];
    let rest = source;

    while (rest.length > 0) {
        const termMatch = rest.match(TERM_PATTERN);

        if (!termMatch) {
            return null;
        }

        terms.push(termMatch[0]);
        rest = rest.slice(termMatch[0].length);
    }

    return terms;
}

function getAtomicFormulaParts(value) {
    const predicateMatch = value.match(PREDICATE_SYMBOL_PATTERN);

    if (!predicateMatch || predicateMatch.index !== 0) {
        return null;
    }

    const predicate = predicateMatch[0];
    const termsSource = value.slice(predicate.length);
    const terms = termsSource.length > 0
        ? parseTermSequence(termsSource)
        : [];

    if (!terms) {
        return null;
    }

    return { predicate, terms };
}

function renderTextWithSubscripts(element, text) {
    element.replaceChildren();

    const source = String(text);
    const symbolNumberPattern = /([A-Za-z])(\d+)/g;
    let cursor = 0;
    let match = symbolNumberPattern.exec(source);

    while (match) {
        const [token, symbol, digits] = match;

        if (match.index > cursor) {
            element.append(document.createTextNode(source.slice(cursor, match.index)));
        }

        element.append(document.createTextNode(symbol));

        const subscript = document.createElement('sub');
        subscript.textContent = digits;
        element.append(subscript);

        cursor = match.index + token.length;
        match = symbolNumberPattern.exec(source);
    }

    if (cursor < source.length) {
        element.append(document.createTextNode(source.slice(cursor)));
    }
}

function getProofFormulaMeasurer() {
    if (proofFormulaMeasurer?.isConnected) {
        return proofFormulaMeasurer;
    }

    proofFormulaMeasurer = document.createElement('span');
    proofFormulaMeasurer.className = 'proof-formula-measurer';
    proofFormulaMeasurer.setAttribute('aria-hidden', 'true');
    proofPanel.append(proofFormulaMeasurer);
    return proofFormulaMeasurer;
}

function measureProofFormulaWidth(text) {
    const measurer = getProofFormulaMeasurer();
    renderTextWithSubscripts(measurer, text);
    return measurer.getBoundingClientRect().width;
}

function getRequiredProofFormulaWidth() {
    let requiredWidth = 0;

    proofLines.forEach((proofLine) => {
        requiredWidth = Math.max(
            requiredWidth,
            (proofLine.contentIndent || 0) + measureProofFormulaWidth(proofLine.formula),
        );
    });

    const inlineFormulaInput = proofList.querySelector('.inline-formula-input');

    if (inlineFormulaInput) {
        const inlineEditor = inlineFormulaInput.closest('.inline-editor-line');
        const contentIndent = Number.parseFloat(
            getComputedStyle(inlineEditor).getPropertyValue('--content-indent'),
        ) || 0;
        requiredWidth = Math.max(
            requiredWidth,
            contentIndent + measureProofFormulaWidth(getEditableText(inlineFormulaInput)),
        );
    } else if (!editorLine.hidden && formulaInput.value.trim()) {
        const contentIndent = Number.parseFloat(
            getComputedStyle(editorLine).getPropertyValue('--content-indent'),
        ) || 0;
        requiredWidth = Math.max(
            requiredWidth,
            contentIndent + measureProofFormulaWidth(formulaInput.value.trim()),
        );
    }

    return requiredWidth + PROOF_FORMULA_WIDTH_ALLOWANCE;
}

function getFlexibleProofColumnWidth(row) {
    const resolvedTracks = getComputedStyle(row).gridTemplateColumns
        .match(/[\d.]+px/g)
        ?.map((track) => Number.parseFloat(track));

    if (!resolvedTracks || resolvedTracks.length < 4) {
        return 0;
    }

    return resolvedTracks[1] + resolvedTracks[2];
}

function applyProofColumnRatio(formulaRatio) {
    const formulaShare = `${(formulaRatio * 5).toFixed(4)}fr`;
    const justificationShare = `${((1 - formulaRatio) * 5).toFixed(4)}fr`;
    const formulaColumn = `minmax(0, ${formulaShare})`;
    const justificationColumn = `minmax(7ch, ${justificationShare})`;

    proofPanel.style.setProperty('--proof-formula-share', formulaShare);
    proofPanel.style.setProperty('--proof-justification-share', justificationShare);
    proofPanel.style.setProperty(
        '--proof-line-columns',
        `34px ${formulaColumn} ${justificationColumn} var(--tail-width)`,
    );
    proofPanel.style.setProperty(
        '--proof-entry-columns',
        `${formulaColumn} ${justificationColumn} var(--tail-width)`,
    );
}

function updateProofColumnLayout() {
    proofLayoutFrame = null;

    const layoutRow = proofList.querySelector('.saved-line') || editorLine;

    if (!layoutRow || layoutRow.clientWidth === 0) {
        return;
    }

    applyProofColumnRatio(PROOF_FORMULA_BASE_RATIO);

    const flexibleWidth = getFlexibleProofColumnWidth(layoutRow);
    const requiredWidth = getRequiredProofFormulaWidth();
    const formulaRatio = flexibleWidth > 0
        ? Math.min(
            PROOF_FORMULA_MAX_RATIO,
            Math.max(PROOF_FORMULA_BASE_RATIO, requiredWidth / flexibleWidth),
        )
        : PROOF_FORMULA_BASE_RATIO;

    applyProofColumnRatio(formulaRatio);

    const geometryRow = proofList.querySelector('.saved-line');
    const formulaBody = geometryRow?.querySelector(':scope > .line-body');

    if (geometryRow && formulaBody) {
        const rowRect = geometryRow.getBoundingClientRect();
        const formulaRect = formulaBody.getBoundingClientRect();
        const rightInset = Math.max(0, rowRect.right - formulaRect.right);
        proofPanel.style.setProperty('--subproof-right-inset', `${rightInset}px`);
    }
}

function scheduleProofColumnLayout() {
    if (proofLayoutFrame !== null) {
        window.cancelAnimationFrame(proofLayoutFrame);
    }

    proofLayoutFrame = window.requestAnimationFrame(updateProofColumnLayout);
}

function updateEntryState() {
    const value = formulaInput.value.trim();
    completeButton.disabled = value.length === 0;
}

function isPremiseEditorMode() {
    return false;
}

function isFixedWffOnlyInputMode() {
    return isEditingConclusion;
}

function clearFormulaWarning() {
    formulaWarning.replaceChildren();
    formulaWarning.hidden = true;
}

function showFormulaWarning(message) {
    renderTextWithSubscripts(formulaWarning, message);
    formulaWarning.hidden = false;
}

function showInlineFormulaWarning(editor, message) {
    const warning = editor.querySelector('.inline-formula-warning');

    if (!warning) {
        window.alert(message);
        return;
    }

    renderTextWithSubscripts(warning, message);
    warning.hidden = false;
}

function clearInlineFormulaWarning(editor) {
    const warning = editor.querySelector('.inline-formula-warning');

    if (!warning) {
        return;
    }

    warning.replaceChildren();
    warning.hidden = true;
}

function hasNonPremiseBeforeLine(lineNumber) {
    return proofLines.some((line) => line.lineNumber < lineNumber && line.rule !== '전제');
}

function shouldBlockPremiseAtLine(rule, lineNumber) {
    return rule === '전제' && hasNonPremiseBeforeLine(lineNumber);
}

function warnInvalidFixedFormula(rawFormula) {
    const formula = normalizeFormula(rawFormula);
    const syntax = validatePropositionalFormula(formula);

    if (syntax.ok) {
        return false;
    }

    const detail = syntax.error && syntax.error !== '논리식의 WFF 문법에 맞지 않습니다.'
        ? ` ${syntax.error}`
        : ' 논리식을 수정해 주세요.';

    showFormulaWarning(`논리식의 WFF 문법에 맞지 않습니다.${detail}`);
    formulaInput.value = formula;
    formulaInput.focus();
    formulaInput.selectionStart = formulaInput.value.length;
    formulaInput.selectionEnd = formulaInput.value.length;
    updateEntryState();
    return true;
}

function focusFormulaInput() {
    window.requestAnimationFrame(() => {
        if (editorLine.hidden) {
            return;
        }

        formulaInput.focus();
        formulaInput.selectionStart = formulaInput.value.length;
        formulaInput.selectionEnd = formulaInput.value.length;
    });
}

function updateActiveLineMarker() {
    const marker = nextLineNumber;
    const isInlineEditing = editingLineNumber !== null;
    const shouldHideCompletedProofEditor = Boolean(getQedLineNumber());
    const nextLayout = getOpenSubproofLayoutAfterLines(proofLines);

    activeLineNumber.textContent = String(marker);
    editorLine.style.setProperty('--depth', nextLayout.depth);
    editorLine.style.setProperty('--content-indent', `${nextLayout.contentIndent}px`);
    updateActiveEditorSubproofGuides(nextLayout);
    editorLine.hidden = !isEditorVisible || shouldHideCompletedProofEditor;
    proofList.classList.toggle('has-inline-editor', isInlineEditing);
    editorLine.classList.remove('is-target-editing', 'is-fixed-rule-editing');
    scheduleProofColumnLayout();
}

function updateRuleSelection(rule, options = {}) {
    ruleSelect.value = rule;
    refsInput.value = normalizeReferences(options.refs ?? '');
    ruleTextInput.value = formatRuleCommand(rule, refsInput.value, 'edit');
    updateActiveLineMarker();
    updateEntryState();
}

function getReferenceMode(rule = ruleSelect.value) {
    return referenceModeByRule[rule] || 'unselected';
}

function getOpenSubproofDepthAfterLines(lines) {
    return getOpenSubproofLayoutAfterLines(lines).depth;
}

function getSubproofGuideStep(kind) {
    return kind === 'arbitraryName'
        ? ARBITRARY_NAME_SUBPROOF_GUIDE_STEP
        : SUBPROOF_GUIDE_STEP;
}

function getSubproofGuideEntries(openSubproofs) {
    let left = SUBPROOF_GUIDE_LEFT_START;

    return openSubproofs.map((subproof) => {
        left += getSubproofGuideStep(subproof.kind);
        const entry = {
            id: subproof.id,
            kind: subproof.kind,
            left,
            name: subproof.name || '',
        };
        return entry;
    });
}

function getSubproofContentIndentFromEntries(entries) {
    if (entries.length === 0) {
        return 0;
    }

    const currentEntry = entries[entries.length - 1];
    return currentEntry.left
        - SUBPROOF_GUIDE_LEFT_START
        + SUBPROOF_FORMULA_INDENT_FROM_FIRST_GUIDE;
}

function getCurrentSubproofLeft(entries) {
    return entries.length > 0
        ? entries[entries.length - 1].left
        : SUBPROOF_GUIDE_LEFT_START;
}

function getOpenedAssumptionSubproofLeft(entries, openedSubproofIds = []) {
    const openedIds = new Set(openedSubproofIds);
    const openedAssumption = [...entries]
        .reverse()
        .find((entry) => openedIds.has(entry.id) && entry.kind === 'assumption');

    return openedAssumption?.left ?? null;
}

function getOpenSubproofLayoutAfterLines(lines) {
    const openSubproofs = [];
    let nextVirtualSubproofId = 1;

    lines.forEach((line) => {
        getProofLineArbitraryNames(line).forEach((arbitraryName) => {
            openSubproofs.push({
                id: nextVirtualSubproofId,
                kind: 'arbitraryName',
                name: arbitraryName,
            });
            nextVirtualSubproofId += 1;
        });

        if (line.rule === '가정') {
            openSubproofs.push({
                id: nextVirtualSubproofId,
                kind: 'assumption',
            });
            nextVirtualSubproofId += 1;
        }

        if (line.hasSubproofEndMarker && openSubproofs.length > 0) {
            openSubproofs.pop();
        }
    });

    const guideEntries = getSubproofGuideEntries(openSubproofs);

    return {
        depth: openSubproofs.length,
        guideEntries,
        contentIndent: getSubproofContentIndentFromEntries(guideEntries),
        currentSubproofLeft: getCurrentSubproofLeft(guideEntries),
    };
}

function tokenizeFormula(input) {
    const tokens = [];
    let index = 0;

    const previousNonSpaceChar = (position) => {
        for (let cursor = position - 1; cursor >= 0; cursor -= 1) {
            if (!/\s/.test(input[cursor])) {
                return input[cursor];
            }
        }

        return '';
    };

    const nextNonSpaceChar = (position) => {
        for (let cursor = position; cursor < input.length; cursor += 1) {
            if (!/\s/.test(input[cursor])) {
                return input[cursor];
            }
        }

        return '';
    };

    const isInsideQuantifierPrefixParens = (position) => previousNonSpaceChar(position) === '(';
    const hasQuantifierPrefixClosingParen = (position) => nextNonSpaceChar(position) === ')';

    const readVariableAfter = (position) => {
        let cursor = position;

        while (cursor < input.length && /\s/.test(input[cursor])) {
            cursor += 1;
        }

        const match = input.slice(cursor).match(/^[w-z]\d*/);

        return match
            ? { variable: match[0], nextIndex: cursor + match[0].length }
            : null;
    };

    const readVariableImmediatelyAfter = (position) => {
        const match = input.slice(position).match(/^[w-z]\d*/);

        return match
            ? { variable: match[0], nextIndex: position + match[0].length }
            : null;
    };

    while (index < input.length) {
        const char = input[index];
        const rest = input.slice(index);

        if (/\s/.test(char)) {
            index += 1;
            continue;
        }

        const textTokenAliases = [
            { text: '<->', type: tokenTypes.IFF },
            { text: '<=>', type: tokenTypes.IFF },
            { text: '<>', type: tokenTypes.IFF },
            { text: 'forall', type: tokenTypes.QUANTIFIER, quantifier: '∀', caseInsensitive: true },
            { text: 'exists', type: tokenTypes.QUANTIFIER, quantifier: '∃', caseInsensitive: true },
            { text: 'neq', type: tokenTypes.NOT_EQUAL, caseInsensitive: true },
            { text: 'not', type: tokenTypes.NOT, caseInsensitive: true },
            { text: 'and', type: tokenTypes.AND, caseInsensitive: true },
            { text: 'bot', type: tokenTypes.FALSUM, caseInsensitive: true },
            { text: 'imp', type: tokenTypes.IF, caseInsensitive: true },
            { text: 'iff', type: tokenTypes.IFF, caseInsensitive: true },
            { text: 'XX', type: tokenTypes.FALSUM },
            { text: '->', type: tokenTypes.IF },
            { text: '=>', type: tokenTypes.IF },
            { text: 'or', type: tokenTypes.OR, caseInsensitive: true },
        ];
        const matchedTextToken = textTokenAliases.find((token) => (
            token.caseInsensitive
                ? rest.toLowerCase().startsWith(token.text)
                : rest.startsWith(token.text)
        ));

        if (matchedTextToken) {
            if (matchedTextToken.type === tokenTypes.QUANTIFIER) {
                if (!isInsideQuantifierPrefixParens(index)) {
                    return {
                        ok: false,
                        error: '양화 접두식은 (∀x), (∃x)처럼 괄호 안에 써야 합니다.',
                    };
                }

                const variableResult = readVariableAfter(index + matchedTextToken.text.length);

                if (!variableResult) {
                    return {
                        ok: false,
                        error: `${matchedTextToken.text} 뒤에는 w~z의 변항이 와야 합니다.`,
                    };
                }

                if (!hasQuantifierPrefixClosingParen(variableResult.nextIndex)) {
                    return {
                        ok: false,
                        error: '양화 접두식은 (∀x), (∃x)처럼 괄호 안에 써야 합니다.',
                    };
                }

                tokens.push({
                    type: tokenTypes.QUANTIFIER,
                    quantifier: matchedTextToken.quantifier,
                    variable: variableResult.variable,
                    value: `${matchedTextToken.quantifier}${variableResult.variable}`,
                });
                index = variableResult.nextIndex;
                continue;
            }

            tokens.push({ type: matchedTextToken.type, value: matchedTextToken.text });
            index += matchedTextToken.text.length;
            continue;
        }

        if (char === '∀' || char === '!' || char === '∃' || char === '?') {
            if (!isInsideQuantifierPrefixParens(index)) {
                return {
                    ok: false,
                    error: '양화 접두식은 (∀x), (∃x)처럼 괄호 안에 써야 합니다.',
                };
            }

            const quantifier = (char === '∀' || char === '!') ? '∀' : '∃';
            const variableResult = readVariableAfter(index + 1);

            if (!variableResult) {
                return {
                    ok: false,
                    error: `${char} 뒤에는 w~z의 변항이 와야 합니다.`,
                };
            }

            if (!hasQuantifierPrefixClosingParen(variableResult.nextIndex)) {
                return {
                    ok: false,
                    error: '양화 접두식은 (∀x), (∃x)처럼 괄호 안에 써야 합니다.',
                };
            }

            tokens.push({
                type: tokenTypes.QUANTIFIER,
                quantifier,
                variable: variableResult.variable,
                value: `${quantifier}${variableResult.variable}`,
            });
            index = variableResult.nextIndex;
            continue;
        }

        const shorthandQuantifierVariableResult = (
            (char === 'A' || char === 'E') &&
            previousNonSpaceChar(index) === '('
        )
            ? readVariableImmediatelyAfter(index + 1)
            : null;

        if (
            shorthandQuantifierVariableResult &&
            hasQuantifierPrefixClosingParen(shorthandQuantifierVariableResult.nextIndex)
        ) {
            const quantifier = char === 'A' ? '∀' : '∃';
            const variable = shorthandQuantifierVariableResult.variable;
            tokens.push({
                type: tokenTypes.QUANTIFIER,
                quantifier,
                variable,
                value: `${quantifier}${variable}`,
            });
            index = shorthandQuantifierVariableResult.nextIndex;
            continue;
        }

        const predicateSymbolMatch = rest.match(PREDICATE_SYMBOL_PATTERN);

        if (predicateSymbolMatch) {
            let atomLength = predicateSymbolMatch[0].length;
            const terms = [];

            while (true) {
                const termMatch = rest.slice(atomLength).match(TERM_PATTERN);

                if (!termMatch) {
                    break;
                }

                terms.push(termMatch[0]);
                atomLength += termMatch[0].length;
            }

            const value = rest.slice(0, atomLength);
            tokens.push({
                type: terms.length > 0 ? tokenTypes.PREDICATE : tokenTypes.ATOM,
                value,
            });
            index += atomLength;
            continue;
        }

        const termMatch = rest.match(TERM_PATTERN);
        const followingNonSpaceText = (() => {
            let cursor = index + (termMatch?.[0].length || 0);

            while (cursor < input.length && /\s/.test(input[cursor])) {
                cursor += 1;
            }

            return input.slice(cursor);
        })();
        const isEqualityTerm = termMatch && (
            followingNonSpaceText.startsWith('=') ||
            followingNonSpaceText.startsWith('≠') ||
            followingNonSpaceText.toLowerCase().startsWith('neq') ||
            [tokenTypes.EQUAL, tokenTypes.NOT_EQUAL].includes(tokens[tokens.length - 1]?.type)
        );

        if (isEqualityTerm) {
            tokens.push({ type: tokenTypes.TERM, value: termMatch[0] });
            index += termMatch[0].length;
            continue;
        }

        const singleTokenTypes = {
            '⊥': tokenTypes.FALSUM,
            '#': tokenTypes.FALSUM,
            '_': tokenTypes.FALSUM,
            '¬': tokenTypes.NOT,
            '~': tokenTypes.NOT,
            '∼': tokenTypes.NOT,
            '-': tokenTypes.NOT,
            '−': tokenTypes.NOT,
            '&': tokenTypes.AND,
            '∧': tokenTypes.AND,
            '^': tokenTypes.AND,
            '⋀': tokenTypes.AND,
            '.': tokenTypes.AND,
            '·': tokenTypes.AND,
            '*': tokenTypes.AND,
            '∨': tokenTypes.OR,
            '⋁': tokenTypes.OR,
            'v': tokenTypes.OR,
            '|': tokenTypes.OR,
            '+': tokenTypes.OR,
            '→': tokenTypes.IF,
            '⇒': tokenTypes.IF,
            '⊃': tokenTypes.IF,
            '>': tokenTypes.IF,
            '↔': tokenTypes.IFF,
            '⇔': tokenTypes.IFF,
            '≡': tokenTypes.IFF,
            '=': tokenTypes.EQUAL,
            '≠': tokenTypes.NOT_EQUAL,
            '(': tokenTypes.LPAREN,
            ')': tokenTypes.RPAREN,
        };

        if (singleTokenTypes[char]) {
            tokens.push({ type: singleTokenTypes[char], value: char });
            index += 1;
            continue;
        }

        return {
            ok: false,
            error: `"${char}"는 논리식 기호로 읽을 수 없습니다.`,
        };
    }

    return { ok: true, tokens };
}

function canonicalTokenValue(token) {
    const values = {
        [tokenTypes.NOT]: '~',
        [tokenTypes.FALSUM]: '⊥',
        [tokenTypes.AND]: '&',
        [tokenTypes.OR]: '∨',
        [tokenTypes.IF]: '→',
        [tokenTypes.IFF]: '↔',
        [tokenTypes.EQUAL]: '=',
        [tokenTypes.NOT_EQUAL]: '≠',
        [tokenTypes.LPAREN]: '(',
        [tokenTypes.RPAREN]: ')',
    };

    if (token.type === tokenTypes.QUANTIFIER) {
        return token.value;
    }

    return values[token.type] || token.value;
}

function isBinaryOperator(token) {
    return [tokenTypes.AND, tokenTypes.OR, tokenTypes.IF, tokenTypes.IFF].includes(token.type);
}

function shouldSeparateTokens(previousToken, currentToken) {
    if (!previousToken) {
        return false;
    }

    if (previousToken.type === tokenTypes.LPAREN || currentToken.type === tokenTypes.RPAREN) {
        return false;
    }

    if (isBinaryOperator(previousToken) || isBinaryOperator(currentToken)) {
        return true;
    }

    if (previousToken.type === tokenTypes.NOT) {
        return false;
    }

    if (previousToken.type === tokenTypes.QUANTIFIER) {
        return false;
    }

    if (currentToken.type === tokenTypes.NOT) {
        return previousToken.type !== tokenTypes.LPAREN;
    }

    if (currentToken.type === tokenTypes.QUANTIFIER) {
        return previousToken.type !== tokenTypes.LPAREN;
    }

    if (currentToken.type === tokenTypes.LPAREN) {
        return isBinaryOperator(previousToken);
    }

    if (
        previousToken.type === tokenTypes.RPAREN &&
        [tokenTypes.ATOM, tokenTypes.PREDICATE].includes(currentToken.type)
    ) {
        return true;
    }

    return false;
}

function hasRemovableOuterParens(tokens) {
    if (
        tokens.length < 3 ||
        tokens[0].type !== tokenTypes.LPAREN ||
        tokens[tokens.length - 1].type !== tokenTypes.RPAREN
    ) {
        return false;
    }

    let depth = 0;

    for (let index = 0; index < tokens.length; index += 1) {
        if (tokens[index].type === tokenTypes.LPAREN) {
            depth += 1;
        }

        if (tokens[index].type === tokenTypes.RPAREN) {
            depth -= 1;
        }

        if (depth === 0 && index < tokens.length - 1) {
            return false;
        }
    }

    return depth === 0;
}

function removeOutermostParens(tokens) {
    let normalizedTokens = [...tokens];

    while (hasRemovableOuterParens(normalizedTokens)) {
        normalizedTokens = normalizedTokens.slice(1, -1);
    }

    return normalizedTokens;
}

function findMatchingRightParen(tokens, leftIndex) {
    let depth = 0;

    for (let index = leftIndex; index < tokens.length; index += 1) {
        if (tokens[index].type === tokenTypes.LPAREN) {
            depth += 1;
        }

        if (tokens[index].type === tokenTypes.RPAREN) {
            depth -= 1;
        }

        if (depth === 0) {
            return index;
        }
    }

    return -1;
}

function hasTopLevelBinaryOperator(tokens) {
    let depth = 0;

    for (const token of tokens) {
        if (token.type === tokenTypes.LPAREN) {
            depth += 1;
            continue;
        }

        if (token.type === tokenTypes.RPAREN) {
            depth -= 1;
            continue;
        }

        if (depth === 0 && isBinaryOperator(token)) {
            return true;
        }
    }

    return false;
}

function getTopLevelBinaryOperatorIndexes(tokens) {
    const indexes = [];
    let depth = 0;

    tokens.forEach((token, index) => {
        if (token.type === tokenTypes.LPAREN) {
            depth += 1;
            return;
        }

        if (token.type === tokenTypes.RPAREN) {
            depth -= 1;
            return;
        }

        if (depth === 0 && isBinaryOperator(token)) {
            indexes.push(index);
        }
    });

    return indexes;
}

function parseQuantifiedFormulaTokens(tokens) {
    if (
        tokens.length < 4 ||
        tokens[0].type !== tokenTypes.LPAREN ||
        tokens[1]?.type !== tokenTypes.QUANTIFIER ||
        tokens[2]?.type !== tokenTypes.RPAREN
    ) {
        return null;
    }

    const operand = parseStrictFormulaTokens(tokens.slice(3), { allowUnwrappedBinary: true });

    return operand
        ? {
            type: tokenTypes.QUANTIFIER,
            quantifier: tokens[1].quantifier,
            variable: tokens[1].variable,
            operand,
        }
        : null;
}

function parseStrictFormulaTokens(tokens, options = {}) {
    const allowUnwrappedBinary = options.allowUnwrappedBinary ?? true;
    const normalizedTokens = tokens;

    if (normalizedTokens.length === 0) {
        return null;
    }

    if (hasRemovableOuterParens(normalizedTokens)) {
        const innerTokens = normalizedTokens.slice(1, -1);
        const coreInnerTokens = removeOutermostParens(innerTokens);
        const parenthesizedNegation = coreInnerTokens[0]?.type === tokenTypes.NOT &&
            parseStrictFormulaTokens(coreInnerTokens.slice(1), { allowUnwrappedBinary: true });
        const parenthesizedQuantified = parseQuantifiedFormulaTokens(coreInnerTokens);
        const parenthesizedBinary = getTopLevelBinaryOperatorIndexes(coreInnerTokens).length === 1;
        const parenthesizedEquality = (
            coreInnerTokens.length === 3 &&
            coreInnerTokens[0].type === tokenTypes.TERM &&
            [tokenTypes.EQUAL, tokenTypes.NOT_EQUAL].includes(coreInnerTokens[1].type) &&
            coreInnerTokens[2].type === tokenTypes.TERM
        );

        if (
            !parenthesizedNegation &&
            !parenthesizedQuantified &&
            !parenthesizedBinary &&
            !parenthesizedEquality
        ) {
            return null;
        }

        return parseStrictFormulaTokens(innerTokens, { allowUnwrappedBinary: true });
    }

    const topLevelBinaryOperatorIndexes = getTopLevelBinaryOperatorIndexes(normalizedTokens);

    if (topLevelBinaryOperatorIndexes.length > 0) {
        if (!allowUnwrappedBinary || topLevelBinaryOperatorIndexes.length !== 1) {
            return null;
        }

        const [operatorIndex] = topLevelBinaryOperatorIndexes;
        const left = parseStrictFormulaTokens(normalizedTokens.slice(0, operatorIndex), { allowUnwrappedBinary: false });
        const right = parseStrictFormulaTokens(normalizedTokens.slice(operatorIndex + 1), { allowUnwrappedBinary: false });

        return left && right
            ? { type: normalizedTokens[operatorIndex].type, left, right }
            : null;
    }

    if (normalizedTokens[0].type === tokenTypes.NOT) {
        const operand = parseStrictFormulaTokens(normalizedTokens.slice(1), { allowUnwrappedBinary: true });
        return operand ? { type: tokenTypes.NOT, operand } : null;
    }

    const quantified = parseQuantifiedFormulaTokens(normalizedTokens);

    if (quantified) {
        return quantified;
    }

    if (normalizedTokens.length === 1 && normalizedTokens[0].type === tokenTypes.ATOM) {
        return { type: tokenTypes.ATOM, value: normalizedTokens[0].value };
    }

    if (normalizedTokens.length === 1 && normalizedTokens[0].type === tokenTypes.PREDICATE) {
        return { type: tokenTypes.PREDICATE, value: normalizedTokens[0].value };
    }

    if (normalizedTokens.length === 1 && normalizedTokens[0].type === tokenTypes.FALSUM) {
        return { type: tokenTypes.FALSUM };
    }

    if (
        normalizedTokens.length === 3 &&
        normalizedTokens[0].type === tokenTypes.TERM &&
        [tokenTypes.EQUAL, tokenTypes.NOT_EQUAL].includes(normalizedTokens[1].type) &&
        normalizedTokens[2].type === tokenTypes.TERM
    ) {
        const equality = {
            type: tokenTypes.EQUAL,
            leftTerm: normalizedTokens[0].value,
            rightTerm: normalizedTokens[2].value,
        };

        return normalizedTokens[1].type === tokenTypes.NOT_EQUAL
            ? { type: tokenTypes.NOT, operand: equality }
            : equality;
    }

    return null;
}

function removeNegationOperandParens(tokens) {
    let normalizedTokens = [...tokens];
    let changed = true;

    while (changed) {
        changed = false;

        for (let index = 0; index < normalizedTokens.length - 2; index += 1) {
            if (
                normalizedTokens[index].type !== tokenTypes.NOT ||
                normalizedTokens[index + 1].type !== tokenTypes.LPAREN
            ) {
                continue;
            }

            const rightIndex = findMatchingRightParen(normalizedTokens, index + 1);

            if (rightIndex === -1) {
                continue;
            }

            const innerTokens = normalizedTokens.slice(index + 2, rightIndex);
            const isQuantifierPrefixParens = innerTokens.length === 1 &&
                innerTokens[0].type === tokenTypes.QUANTIFIER;

            if (isQuantifierPrefixParens) {
                continue;
            }

            if (hasTopLevelBinaryOperator(removeOutermostParens(innerTokens))) {
                continue;
            }

            normalizedTokens = [
                ...normalizedTokens.slice(0, index + 1),
                ...innerTokens,
                ...normalizedTokens.slice(rightIndex + 1),
            ];
            changed = true;
            break;
        }
    }

    return normalizedTokens;
}

function normalizeFormula(input) {
    const tokenized = tokenizeFormula(input);

    if (!tokenized.ok) {
        return input.trim().replace(/\s+/g, ' ');
    }

    const isWff = Boolean(parseStrictFormulaTokens(tokenized.tokens, { allowUnwrappedBinary: true }));
    const tokens = isWff
        ? removeNegationOperandParens(removeOutermostParens(tokenized.tokens))
        : tokenized.tokens;

    return tokens.reduce((formula, token, index) => {
        const previousToken = tokens[index - 1];
        const closesQuantifierPrefix = previousToken?.type === tokenTypes.RPAREN &&
            tokens[index - 2]?.type === tokenTypes.QUANTIFIER &&
            tokens[index - 3]?.type === tokenTypes.LPAREN;
        const separator = !closesQuantifierPrefix && shouldSeparateTokens(previousToken, token) ? ' ' : '';
        return `${formula}${separator}${canonicalTokenValue(token)}`;
    }, '');
}

function parseFormulaAst(input) {
    const tokenized = tokenizeFormula(input);

    if (!tokenized.ok) {
        return tokenized;
    }

    const ast = parseStrictFormulaTokens(tokenized.tokens, { allowUnwrappedBinary: true });

    if (!ast) {
        return {
            ok: false,
            error: '논리식의 WFF 구조로 읽을 수 없습니다.',
        };
    }

    return { ok: true, ast };
}

function unionVariableSets(...sets) {
    const combined = new Set();

    sets.forEach((set) => {
        set.forEach((variable) => combined.add(variable));
    });

    return combined;
}

function getFreeVariablesFromAst(ast, boundVariables = new Set()) {
    if (!ast) {
        return new Set();
    }

    if (ast.type === tokenTypes.ATOM || ast.type === tokenTypes.FALSUM) {
        return new Set();
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);

        return new Set((parts?.terms || []).filter((term) => (
            isVariableTerm(term) && !boundVariables.has(term)
        )));
    }

    if (ast.type === tokenTypes.EQUAL) {
        return new Set([ast.leftTerm, ast.rightTerm].filter((term) => (
            isVariableTerm(term) && !boundVariables.has(term)
        )));
    }

    if (ast.type === tokenTypes.NOT) {
        return getFreeVariablesFromAst(ast.operand, boundVariables);
    }

    if (ast.type === tokenTypes.QUANTIFIER) {
        const nextBoundVariables = new Set(boundVariables);
        nextBoundVariables.add(ast.variable);
        return getFreeVariablesFromAst(ast.operand, nextBoundVariables);
    }

    return unionVariableSets(
        getFreeVariablesFromAst(ast.left, boundVariables),
        getFreeVariablesFromAst(ast.right, boundVariables),
    );
}

function describePredicateArity(arity) {
    return arity === 0
        ? '단순 문장'
        : `${arity}항 술어`;
}

function formatPredicateArityWarning(predicate, arity) {
    return arity === 0
        ? `${predicate}는 단순 문장입니다.`
        : `술어 ${predicate}는 ${describePredicateArity(arity)}입니다.`;
}

function recordPredicateArity(predicateArities, predicate, arity) {
    if (!predicateArities.has(predicate)) {
        predicateArities.set(predicate, arity);
        return { ok: true };
    }

    const previousArity = predicateArities.get(predicate);

    if (previousArity === arity) {
        return { ok: true };
    }

    return {
        ok: false,
        error: formatPredicateArityWarning(predicate, previousArity),
    };
}

function collectPredicateAritiesFromAst(ast, predicateArities) {
    if (!ast || ast.type === tokenTypes.FALSUM || ast.type === tokenTypes.EQUAL) {
        return { ok: true };
    }

    if (ast.type === tokenTypes.ATOM) {
        return recordPredicateArity(predicateArities, ast.value, 0);
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);

        return recordPredicateArity(predicateArities, parts.predicate, parts.terms.length);
    }

    if (ast.type === tokenTypes.NOT) {
        return collectPredicateAritiesFromAst(ast.operand, predicateArities);
    }

    if (ast.type === tokenTypes.QUANTIFIER) {
        return collectPredicateAritiesFromAst(ast.operand, predicateArities);
    }

    const left = collectPredicateAritiesFromAst(ast.left, predicateArities);

    if (!left.ok) {
        return left;
    }

    return collectPredicateAritiesFromAst(ast.right, predicateArities);
}

function validatePredicateArityConsistency(formulas) {
    const predicateArities = new Map();

    for (const formula of formulas) {
        const parsed = parseFormulaAst(formula);

        if (!parsed.ok) {
            continue;
        }

        const result = collectPredicateAritiesFromAst(parsed.ast, predicateArities);

        if (!result.ok) {
            return result;
        }
    }

    return { ok: true };
}

function validatePropositionalFormula(input) {
    const tokenized = tokenizeFormula(input);

    if (!tokenized.ok) {
        return {
            ...tokenized,
            isSentence: false,
            freeVariables: [],
        };
    }

    const ast = parseStrictFormulaTokens(tokenized.tokens, { allowUnwrappedBinary: true });
    const ok = Boolean(ast);
    const freeVariables = ok
        ? [...getFreeVariablesFromAst(ast)].sort()
        : [];

    return {
        ok,
        error: ok ? '' : '논리식의 WFF 문법에 맞지 않습니다.',
        isSentence: ok && freeVariables.length === 0,
        freeVariables,
    };
}

function sameFormulaAst(left, right) {
    if (!left || !right || left.type !== right.type) {
        return false;
    }

    if (left.type === tokenTypes.ATOM || left.type === tokenTypes.PREDICATE) {
        return left.value === right.value;
    }

    if (left.type === tokenTypes.EQUAL) {
        return left.leftTerm === right.leftTerm && left.rightTerm === right.rightTerm;
    }

    if (left.type === tokenTypes.FALSUM) {
        return true;
    }

    if (left.type === tokenTypes.NOT) {
        return sameFormulaAst(left.operand, right.operand);
    }

    if (left.type === tokenTypes.QUANTIFIER) {
        return (
            left.quantifier === right.quantifier &&
            left.variable === right.variable &&
            sameFormulaAst(left.operand, right.operand)
        );
    }

    return sameFormulaAst(left.left, right.left) && sameFormulaAst(left.right, right.right);
}

function areContradictoryFormulaAsts(left, right) {
    return (
        (left?.type === tokenTypes.NOT && sameFormulaAst(left.operand, right)) ||
        (right?.type === tokenTypes.NOT && sameFormulaAst(right.operand, left))
    );
}

function substituteTermInAst(ast, variable, replacementName) {
    if (!ast) {
        return ast;
    }

    if (ast.type === tokenTypes.ATOM || ast.type === tokenTypes.FALSUM) {
        return { ...ast };
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);
        const terms = parts.terms
            .map((term) => (term === variable ? replacementName : term))
            .join('');
        return {
            ...ast,
            value: `${parts.predicate}${terms}`,
        };
    }

    if (ast.type === tokenTypes.EQUAL) {
        return {
            ...ast,
            leftTerm: ast.leftTerm === variable ? replacementName : ast.leftTerm,
            rightTerm: ast.rightTerm === variable ? replacementName : ast.rightTerm,
        };
    }

    if (ast.type === tokenTypes.NOT) {
        return {
            ...ast,
            operand: substituteTermInAst(ast.operand, variable, replacementName),
        };
    }

    if (ast.type === tokenTypes.QUANTIFIER) {
        if (ast.variable === variable) {
            return { ...ast };
        }

        return {
            ...ast,
            operand: substituteTermInAst(ast.operand, variable, replacementName),
        };
    }

    return {
        ...ast,
        left: substituteTermInAst(ast.left, variable, replacementName),
        right: substituteTermInAst(ast.right, variable, replacementName),
    };
}

function matchPartialFreeTermSubstitution(
    sourceAst,
    targetAst,
    sourceTerm,
    replacementTerm,
    boundVariables = new Set(),
) {
    if (!sourceAst || !targetAst || sourceAst.type !== targetAst.type) {
        return { matches: false, changed: false };
    }

    const matchTerm = (source, target) => {
        if (source === target) {
            return { matches: true, changed: false };
        }

        const sourceIsBound = isVariableTerm(sourceTerm) && boundVariables.has(sourceTerm);
        const replacementWouldBeCaptured = (
            isVariableTerm(replacementTerm) &&
            boundVariables.has(replacementTerm)
        );
        const changed = (
            source === sourceTerm &&
            target === replacementTerm &&
            !sourceIsBound &&
            !replacementWouldBeCaptured
        );

        return { matches: changed, changed };
    };

    const combineMatches = (...matches) => ({
        matches: matches.every((match) => match.matches),
        changed: matches.some((match) => match.changed),
    });

    if (sourceAst.type === tokenTypes.ATOM || sourceAst.type === tokenTypes.PREDICATE) {
        if (sourceAst.type === tokenTypes.ATOM) {
            return {
                matches: sourceAst.value === targetAst.value,
                changed: false,
            };
        }

        const sourceParts = getAtomicFormulaParts(sourceAst.value);
        const targetParts = getAtomicFormulaParts(targetAst.value);

        if (
            !sourceParts ||
            !targetParts ||
            sourceParts.predicate !== targetParts.predicate ||
            sourceParts.terms.length !== targetParts.terms.length
        ) {
            return { matches: false, changed: false };
        }

        return combineMatches(...sourceParts.terms.map((term, index) => (
            matchTerm(term, targetParts.terms[index])
        )));
    }

    if (sourceAst.type === tokenTypes.FALSUM) {
        return { matches: true, changed: false };
    }

    if (sourceAst.type === tokenTypes.EQUAL) {
        return combineMatches(
            matchTerm(sourceAst.leftTerm, targetAst.leftTerm),
            matchTerm(sourceAst.rightTerm, targetAst.rightTerm),
        );
    }

    if (sourceAst.type === tokenTypes.NOT) {
        return matchPartialFreeTermSubstitution(
            sourceAst.operand,
            targetAst.operand,
            sourceTerm,
            replacementTerm,
            boundVariables,
        );
    }

    if (sourceAst.type === tokenTypes.QUANTIFIER) {
        if (
            sourceAst.quantifier !== targetAst.quantifier ||
            sourceAst.variable !== targetAst.variable
        ) {
            return { matches: false, changed: false };
        }

        const nextBoundVariables = new Set(boundVariables);
        nextBoundVariables.add(sourceAst.variable);
        return matchPartialFreeTermSubstitution(
            sourceAst.operand,
            targetAst.operand,
            sourceTerm,
            replacementTerm,
            nextBoundVariables,
        );
    }

    return combineMatches(
        matchPartialFreeTermSubstitution(
            sourceAst.left,
            targetAst.left,
            sourceTerm,
            replacementTerm,
            boundVariables,
        ),
        matchPartialFreeTermSubstitution(
            sourceAst.right,
            targetAst.right,
            sourceTerm,
            replacementTerm,
            boundVariables,
        ),
    );
}

function collectNameTermsFromAst(ast) {
    if (!ast) {
        return [];
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);
        return (parts?.terms || []).filter(isNameTerm);
    }

    if (ast.type === tokenTypes.EQUAL) {
        return [ast.leftTerm, ast.rightTerm].filter(isNameTerm);
    }

    if (ast.type === tokenTypes.ATOM || ast.type === tokenTypes.FALSUM) {
        return [];
    }

    if (ast.type === tokenTypes.NOT || ast.type === tokenTypes.QUANTIFIER) {
        return collectNameTermsFromAst(ast.operand);
    }

    return [
        ...collectNameTermsFromAst(ast.left),
        ...collectNameTermsFromAst(ast.right),
    ];
}

function containsTermInAst(ast, term) {
    if (!ast) {
        return false;
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);
        return (parts?.terms || []).includes(term);
    }

    if (ast.type === tokenTypes.EQUAL) {
        return ast.leftTerm === term || ast.rightTerm === term;
    }

    if (ast.type === tokenTypes.ATOM || ast.type === tokenTypes.FALSUM) {
        return false;
    }

    if (ast.type === tokenTypes.NOT || ast.type === tokenTypes.QUANTIFIER) {
        return containsTermInAst(ast.operand, term);
    }

    return containsTermInAst(ast.left, term) || containsTermInAst(ast.right, term);
}

function canInstantiateWithAnyName(schemaAst, variable, targetAst) {
    const candidateNames = new Set([
        ...collectNameTermsFromAst(targetAst),
        ...'abcdefghijklmnopqrstuv'.split(''),
    ]);

    return [...candidateNames]
        .some((name) => sameFormulaAst(
            substituteTermInAst(schemaAst, variable, name),
            targetAst,
        ));
}

function replaceNameWithVariableInAst(ast, name, variable) {
    if (!ast) {
        return ast;
    }

    if (ast.type === tokenTypes.ATOM || ast.type === tokenTypes.FALSUM) {
        return { ...ast };
    }

    if (ast.type === tokenTypes.PREDICATE) {
        const parts = getAtomicFormulaParts(ast.value);
        const terms = parts.terms
            .map((term) => (term === name ? variable : term))
            .join('');
        return {
            ...ast,
            value: `${parts.predicate}${terms}`,
        };
    }

    if (ast.type === tokenTypes.EQUAL) {
        return {
            ...ast,
            leftTerm: ast.leftTerm === name ? variable : ast.leftTerm,
            rightTerm: ast.rightTerm === name ? variable : ast.rightTerm,
        };
    }

    if (ast.type === tokenTypes.NOT) {
        return {
            ...ast,
            operand: replaceNameWithVariableInAst(ast.operand, name, variable),
        };
    }

    if (ast.type === tokenTypes.QUANTIFIER) {
        if (ast.variable === variable) {
            return { ...ast };
        }

        return {
            ...ast,
            operand: replaceNameWithVariableInAst(ast.operand, name, variable),
        };
    }

    return {
        ...ast,
        left: replaceNameWithVariableInAst(ast.left, name, variable),
        right: replaceNameWithVariableInAst(ast.right, name, variable),
    };
}

function canGeneralizeAnyNameAsExistential(schemaAst, variable, targetAst) {
    const candidateNames = new Set(collectNameTermsFromAst(targetAst));

    return [...candidateNames]
        .some((name) => sameFormulaAst(
            replaceNameWithVariableInAst(targetAst, name, variable),
            schemaAst,
        ));
}

function parseReferenceNumbers(refs) {
    const matches = refs.match(/\d+/g);
    return matches ? matches.map(Number) : [];
}

function parseReferenceRange(refs) {
    const match = refs.match(/^\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (!match) {
        return null;
    }

    return {
        start: Number(match[1]),
        end: Number(match[2]),
    };
}

function parseBiconditionalIntroRangeReferences(refs) {
    const match = refs.match(/^\s*(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (!match) {
        return null;
    }

    return {
        firstRange: {
            start: Number(match[1]),
            end: Number(match[2]),
        },
        secondRange: {
            start: Number(match[3]),
            end: Number(match[4]),
        },
    };
}

function parseArgumentByCasesRangeReferences(refs) {
    const match = refs.match(/^\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (!match) {
        return null;
    }

    return {
        disjunctionLineNumber: Number(match[1]),
        firstRange: {
            start: Number(match[2]),
            end: Number(match[3]),
        },
        secondRange: {
            start: Number(match[4]),
            end: Number(match[5]),
        },
    };
}

function parseExistentialElimRangeReferences(refs) {
    const match = refs.match(/^\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (!match) {
        return null;
    }

    return {
        existentialLineNumber: Number(match[1]),
        subproofRange: {
            start: Number(match[2]),
            end: Number(match[3]),
        },
    };
}

function normalizeReferences(refs) {
    const trimmed = refs.trim();

    if (!trimmed) {
        return '';
    }

    const range = trimmed.match(/^(\d+)\s*-\s*(\d+)$/);

    if (range) {
        return `${range[1]}-${range[2]}`;
    }

    if (trimmed.includes(',')) {
        const parts = trimmed.split(',')
            .map((part) => part.trim())
            .filter(Boolean);
        const normalizedParts = parts.map((part) => {
            const partRange = part.match(/^(\d+)\s*-\s*(\d+)$/);

            if (partRange) {
                return `${partRange[1]}-${partRange[2]}`;
            }

            return /^\d+$/.test(part) ? part : '';
        });

        return normalizedParts.length === parts.length && normalizedParts.every(Boolean)
            ? normalizedParts.join(', ')
            : '';
    }

    return trimmed;
}

function formatRuleRefs(refs, mode = 'output') {
    const normalizedRefs = normalizeReferences(refs);
    return mode === 'edit'
        ? normalizedRefs.replace(/,\s*/g, ',')
        : normalizedRefs.replace(/,\s*/g, ', ');
}

function formatRuleLabel(rule, mode = 'output') {
    if (mode !== 'edit') {
        const directOutputLabels = new Map([
            ['후건 부정', 'MT'],
            ['연쇄 논법', 'HS'],
            ['경우 논증', 'AC'],
            ['대우 규칙', 'CP'],
            ['약화', 'W'],
            ['교환 규칙', 'Com'],
            ['결합 규칙', 'Asso'],
            ['분배 규칙', 'Dist'],
            ['드 모르간의 규칙', 'DeM'],
            ['조건문 규칙', 'Cond'],
            ['배중률', 'LEM'],
            ['~ 제거', '~~ 제거'],
            ['¬ 제거', '~~ 제거'],
            ['~E', '~∃'],
            ['~A', '~∀'],
        ]);

        return directOutputLabels.get(rule) || rule;
    }

    const directEditLabels = new Map([
        ['전제', 'PR'],
        ['가정', 'AS'],
        ['반복', 'R'],
        ['∀ 제거', 'AE'],
        ['∀ 도입', 'AI'],
        ['∃ 도입', 'EI'],
        ['∃ 제거', 'EE'],
        ['= 도입', '=I'],
        ['= 제거', '=E'],
        ['~~ 도입', '~~I'],
        ['~~ 제거', '~~E'],
        ['~ 제거', '~~E'],
        ['¬ 제거', '~~E'],
        ['~ 도입', '~I'],
        ['¬ 도입', '~I'],
        ['후건 부정', 'MT'],
        ['연쇄 논법', 'HS'],
        ['경우 논증', 'AC'],
        ['대우 규칙', 'CP'],
        ['약화', 'W'],
        ['교환 규칙', 'Com'],
        ['결합 규칙', 'Asso'],
        ['분배 규칙', 'Dist'],
        ['드 모르간의 규칙', 'DeM'],
        ['조건문 규칙', 'Cond'],
        ['배중률', 'LEM'],
    ]);
    const directLabel = directEditLabels.get(rule);

    if (directLabel) {
        return directLabel;
    }

    const actionEditLabels = new Map([
        ['도입', 'I'],
        ['제거', 'E'],
    ]);
    const ruleParts = rule.split(/\s+/);
    const action = ruleParts.pop();
    const actionLabel = actionEditLabels.get(action);

    if (!actionLabel || ruleParts.length === 0) {
        return rule;
    }

    const operatorEditLabels = new Map([
        ['∨', 'v'],
        ['~', '~'],
        ['¬', '~'],
        ['→', '>'],
        ['↔', '<>'],
        ['⊥', '_'],
    ]);
    const ruleBase = ruleParts.join(' ');
    const editRuleBase = operatorEditLabels.get(ruleBase) || ruleBase;

    return editRuleBase
        ? `${editRuleBase}${actionLabel}`
        : rule;
}

function formatRuleCommand(rule, refs = '', mode = 'output') {
    const normalizedRefs = formatRuleRefs(refs, mode);

    if (rule === '∃ 제거용 가정') {
        return mode === 'edit'
            ? `AS (for ${normalizedRefs.replace(/,\s*/g, ',')}, EE)`
            : `가정 (${normalizedRefs}, ∃ 제거용)`;
    }

    const label = formatRuleLabel(rule, mode);
    return normalizedRefs ? `${normalizedRefs}, ${label}` : label;
}

const directRuleAliases = new Map([
    ['전제', '전제'],
    ['p', '전제'],
    ['pr', '전제'],
    ['가정', '가정'],
    ['as', '가정'],
    ['반복', '반복'],
    ['r', '반복'],
    ['re', '반복'],
    ['ae', '∀ 제거'],
    ['foralle', '∀ 제거'],
    ['!e', '∀ 제거'],
    ['ai', '∀ 도입'],
    ['foralli', '∀ 도입'],
    ['!i', '∀ 도입'],
    ['ei', '∃ 도입'],
    ['existsi', '∃ 도입'],
    ['?e', '∃ 제거'],
    ['?i', '∃ 도입'],
    ['ee', '∃ 제거'],
    ['existse', '∃ 제거'],
    ['=도입', '= 도입'],
    ['=i', '= 도입'],
    ['=제거', '= 제거'],
    ['=e', '= 제거'],
    ['∃제거용가정', '∃ 제거용 가정'],
    ['~~도입', '~~ 도입'],
    ['~~i', '~~ 도입'],
    ['~~제거', '~~ 제거'],
    ['~~e', '~~ 제거'],
    ['note', '~~ 제거'],
    ['¬¬e', '~~ 제거'],
    ['--e', '~~ 제거'],
    ['∼∼e', '~~ 제거'],
    ['−−e', '~~ 제거'],
    ['후건부정', 'MT'],
    ['mt', 'MT'],
    ['연쇄논법', 'HS'],
    ['hs', 'HS'],
    ['경우논증', 'AC'],
    ['경우에의한논증', 'AC'],
    ['ac', 'AC'],
    ['대우규칙', 'CP'],
    ['cp', 'CP'],
    ['약화', 'W'],
    ['w', 'W'],
    ['교환규칙', 'Com'],
    ['교환', 'Com'],
    ['cr', 'Com'],
    ['com', 'Com'],
    ['결합규칙', 'Asso'],
    ['결합', 'Asso'],
    ['ar', 'Asso'],
    ['asso', 'Asso'],
    ['assoc', 'Asso'],
    ['분배규칙', 'Dist'],
    ['분배', 'Dist'],
    ['dr', 'Dist'],
    ['dist', 'Dist'],
    ['드모르간의규칙', 'DeM'],
    ['드모르간규칙', 'DeM'],
    ['드모르간', 'DeM'],
    ['dem', 'DeM'],
    ['demorgan', 'DeM'],
    ['조건문규칙', 'Cond'],
    ['조건문', 'Cond'],
    ['cond', 'Cond'],
    ['배중률', 'LEM'],
    ['lem', 'LEM'],
    ['~e', '~E'],
    ['~∃', '~E'],
    ['존재양화사의부정', '~E'],
    ['존재양화사부정', '~E'],
    ['~a', '~A'],
    ['~∀', '~A'],
    ['보편양화사의부정', '~A'],
    ['보편양화사부정', '~A'],
]);

const ruleOperatorAliases = [
    { aliases: ['<->', '<=>', '<>', 'iff', '↔', '⇔', '≡'], canonical: '↔' },
    { aliases: ['imp', '->', '=>', '→', '⇒', '⊃', '>'], canonical: '→' },
    { aliases: ['forall', '∀', '!'], canonical: '∀' },
    { aliases: ['exists', '∃', '?'], canonical: '∃' },
    { aliases: ['not', '¬', '~', '∼', '-', '−'], canonical: '~' },
    { aliases: ['and', '&', '∧', '^', '⋀', '.', '·', '*'], canonical: '&' },
    { aliases: ['or', '∨', '⋁', 'v', '|', '+'], canonical: '∨' },
    { aliases: ['bot', '⊥', 'XX', '#', '_'], canonical: '⊥' },
    { aliases: ['='], canonical: '=' },
];

const ruleActionAliases = [
    { aliases: ['도입', 'i'], canonical: '도입' },
    { aliases: ['제거', 'e'], canonical: '제거' },
];

function normalizeRuleCommandPart(text) {
    return text.trim().replace(/[,\s]+/g, '');
}

function parseRuleName(ruleText) {
    const compact = normalizeRuleCommandPart(ruleText);

    if (!compact) {
        return null;
    }

    const directRule = directRuleAliases.get(compact.toLowerCase()) || directRuleAliases.get(compact);

    if (directRule) {
        return directRule;
    }

    const compactLower = compact.toLowerCase();

    for (const operator of ruleOperatorAliases) {
        for (const operatorAlias of operator.aliases) {
            const operatorLower = operatorAlias.toLowerCase();

            for (const action of ruleActionAliases) {
                for (const actionAlias of action.aliases) {
                    if (compactLower === `${operatorLower}${actionAlias.toLowerCase()}`) {
                        const rule = `${operator.canonical} ${action.canonical}`;
                        return referenceModeByRule[rule] ? rule : null;
                    }
                }
            }
        }
    }

    return null;
}

function splitRuleCommand(rawCommand) {
    const command = rawCommand.trim().replace(/\s+/g, ' ');

    if (!command) {
        return { ruleText: '', refs: '' };
    }

    const existentialElimAssumption = command.match(/^AS\s*(?:\(\s*)?for\s+(\d+)\s*,?\s*(?:EE|\?E|exists\s*E|∃\s*E|∃\s*제거)\s*\)?\s*$/i);

    if (existentialElimAssumption) {
        return {
            ruleText: '∃ 제거용 가정',
            refs: existentialElimAssumption[1],
        };
    }

    if (/^\d+$/.test(command)) {
        return { ruleText: '반복', refs: command };
    }

    const leadingBiconditionalIntroRanges = command.match(/^(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingBiconditionalIntroRanges) {
        return {
            ruleText: leadingBiconditionalIntroRanges[5].replace(/^[,\s]+/g, ''),
            refs: `${leadingBiconditionalIntroRanges[1]}-${leadingBiconditionalIntroRanges[2]}, ${leadingBiconditionalIntroRanges[3]}-${leadingBiconditionalIntroRanges[4]}`,
        };
    }

    const leadingArgumentByCasesRanges = command.match(/^(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingArgumentByCasesRanges) {
        return {
            ruleText: leadingArgumentByCasesRanges[6].replace(/^[,\s]+/g, ''),
            refs: `${leadingArgumentByCasesRanges[1]}, ${leadingArgumentByCasesRanges[2]}-${leadingArgumentByCasesRanges[3]}, ${leadingArgumentByCasesRanges[4]}-${leadingArgumentByCasesRanges[5]}`,
        };
    }

    const leadingSingleRange = command.match(/^(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingSingleRange) {
        return {
            ruleText: leadingSingleRange[4].replace(/^[,\s]+/g, ''),
            refs: `${leadingSingleRange[1]}, ${leadingSingleRange[2]}-${leadingSingleRange[3]}`,
        };
    }

    const leadingRange = command.match(/^(\d+)\s*-\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingRange) {
        return {
            ruleText: leadingRange[3].replace(/^[,\s]+/g, ''),
            refs: `${leadingRange[1]}-${leadingRange[2]}`,
        };
    }

    const leadingCommaTriple = command.match(/^(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingCommaTriple) {
        return {
            ruleText: leadingCommaTriple[4].replace(/^[,\s]+/g, ''),
            refs: `${leadingCommaTriple[1]}, ${leadingCommaTriple[2]}, ${leadingCommaTriple[3]}`,
        };
    }

    const leadingSpaceTriple = command.match(/^(\d+)\s+(\d+)\s+(\d+)\s+(.+?)\s*$/);

    if (leadingSpaceTriple) {
        return {
            ruleText: leadingSpaceTriple[4].replace(/^[,\s]+/g, ''),
            refs: `${leadingSpaceTriple[1]}, ${leadingSpaceTriple[2]}, ${leadingSpaceTriple[3]}`,
        };
    }

    const leadingCommaPair = command.match(/^(\d+)\s*,\s*(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingCommaPair) {
        return {
            ruleText: leadingCommaPair[3].replace(/^[,\s]+/g, ''),
            refs: `${leadingCommaPair[1]}, ${leadingCommaPair[2]}`,
        };
    }

    const leadingSpacePair = command.match(/^(\d+)\s+(\d+)\s+(.+?)\s*$/);

    if (leadingSpacePair) {
        return {
            ruleText: leadingSpacePair[3].replace(/^[,\s]+/g, ''),
            refs: `${leadingSpacePair[1]}, ${leadingSpacePair[2]}`,
        };
    }

    const leadingSingle = command.match(/^(\d+)\s*(?:,|\s)\s*(.+?)\s*$/);

    if (leadingSingle) {
        return {
            ruleText: leadingSingle[2].replace(/^[,\s]+/g, ''),
            refs: leadingSingle[1],
        };
    }

    const biconditionalIntroRanges = command.match(/^(.*?)[,\s]+(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (biconditionalIntroRanges) {
        return {
            ruleText: biconditionalIntroRanges[1].replace(/[,\s]+$/g, ''),
            refs: `${biconditionalIntroRanges[2]}-${biconditionalIntroRanges[3]}, ${biconditionalIntroRanges[4]}-${biconditionalIntroRanges[5]}`,
        };
    }

    const argumentByCasesRanges = command.match(/^(.*?)[,\s]+(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (argumentByCasesRanges) {
        return {
            ruleText: argumentByCasesRanges[1].replace(/[,\s]+$/g, ''),
            refs: `${argumentByCasesRanges[2]}, ${argumentByCasesRanges[3]}-${argumentByCasesRanges[4]}, ${argumentByCasesRanges[5]}-${argumentByCasesRanges[6]}`,
        };
    }

    const singleRange = command.match(/^(.*?)[,\s]+(\d+)\s*,\s*(\d+)\s*-\s*(\d+)\s*$/);

    if (singleRange) {
        return {
            ruleText: singleRange[1].replace(/[,\s]+$/g, ''),
            refs: `${singleRange[2]}, ${singleRange[3]}-${singleRange[4]}`,
        };
    }

    const range = command.match(/^(.*?)[,\s]+(\d+)\s*-\s*(\d+)\s*$/);

    if (range) {
        return {
            ruleText: range[1].replace(/[,\s]+$/g, ''),
            refs: `${range[2]}-${range[3]}`,
        };
    }

    const commaTriple = command.match(/^(.*?)[,\s]+(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*$/);

    if (commaTriple) {
        return {
            ruleText: commaTriple[1].replace(/[,\s]+$/g, ''),
            refs: `${commaTriple[2]}, ${commaTriple[3]}, ${commaTriple[4]}`,
        };
    }

    const spaceTriple = command.match(/^(.*?)\s+(\d+)\s+(\d+)\s+(\d+)\s*$/);

    if (spaceTriple) {
        return {
            ruleText: spaceTriple[1].replace(/[,\s]+$/g, ''),
            refs: `${spaceTriple[2]}, ${spaceTriple[3]}, ${spaceTriple[4]}`,
        };
    }

    const commaPair = command.match(/^(.*?)[,\s]+(\d+)\s*,\s*(\d+)\s*$/);

    if (commaPair) {
        return {
            ruleText: commaPair[1].replace(/[,\s]+$/g, ''),
            refs: `${commaPair[2]}, ${commaPair[3]}`,
        };
    }

    const spacePair = command.match(/^(.*?)\s+(\d+)\s+(\d+)\s*$/);

    if (spacePair) {
        return {
            ruleText: spacePair[1].replace(/[,\s]+$/g, ''),
            refs: `${spacePair[2]}, ${spacePair[3]}`,
        };
    }

    const single = command.match(/^(.*?)[,\s]+(\d+)\s*$/);

    if (single) {
        return {
            ruleText: single[1].replace(/[,\s]+$/g, ''),
            refs: single[2],
        };
    }

    return { ruleText: command, refs: '' };
}

function validateRuleReferenceShape(rule, refs) {
    const mode = getReferenceMode(rule);
    const normalizedRefs = normalizeReferences(refs);
    const references = parseReferenceNumbers(normalizedRefs);
    const range = parseReferenceRange(normalizedRefs);

    if (mode === 'none') {
        return normalizedRefs
            ? { ok: false, error: '이 규칙은 참조줄을 사용할 수 없습니다.' }
            : { ok: true, refs: '' };
    }

    if (mode === 'single') {
        return references.length === 1 && !range && /^\d+$/.test(normalizedRefs)
            ? { ok: true, refs: normalizedRefs }
            : { ok: false, error: '이 규칙은 참조줄 하나를 n 형식으로 입력해야 합니다.' };
    }

    if (mode === 'pair') {
        if (rule === '↔ 도입') {
            const biconditionalIntroRanges = parseBiconditionalIntroRangeReferences(normalizedRefs);

            if (biconditionalIntroRanges) {
                return {
                    ok: true,
                    refs: `${biconditionalIntroRanges.firstRange.start}-${biconditionalIntroRanges.firstRange.end}, ${biconditionalIntroRanges.secondRange.start}-${biconditionalIntroRanges.secondRange.end}`,
                };
            }
        }

        if (rule === '∃ 제거') {
            const existentialElimRange = parseExistentialElimRangeReferences(normalizedRefs);

            if (existentialElimRange) {
                return {
                    ok: true,
                    refs: `${existentialElimRange.existentialLineNumber}, ${existentialElimRange.subproofRange.start}-${existentialElimRange.subproofRange.end}`,
                };
            }
        }

        if (references.length === 2 && !range) {
            return { ok: true, refs: normalizeReferences(references.join(', ')) };
        }

        return {
            ok: false,
            error: rule === '↔ 도입'
                ? '↔ 도입은 참조를 i,j 또는 i-j, k-l 형식으로 입력해야 합니다.'
                : '이 규칙은 참조줄 두 개를 m,n 형식으로 입력해야 합니다.',
        };
    }

    if (mode === 'triple') {
        const argumentByCasesRanges = parseArgumentByCasesRangeReferences(normalizedRefs);

        if (argumentByCasesRanges) {
            return {
                ok: true,
                refs: `${argumentByCasesRanges.disjunctionLineNumber}, ${argumentByCasesRanges.firstRange.start}-${argumentByCasesRanges.firstRange.end}, ${argumentByCasesRanges.secondRange.start}-${argumentByCasesRanges.secondRange.end}`,
            };
        }

        return references.length === 3 && !range
            ? { ok: true, refs: normalizeReferences(references.join(', ')) }
            : { ok: false, error: '이 규칙은 참조줄을 l,m,n 또는 i,j-k,m-n 형식으로 입력해야 합니다.' };
    }

    if (mode === 'range') {
        return range
            ? { ok: true, refs: `${range.start}-${range.end}` }
            : { ok: false, error: '이 규칙은 참조 범위를 m-n 형식으로 입력해야 합니다.' };
    }

    return { ok: false, error: '정의된 규칙으로 읽을 수 없습니다.' };
}

function parseRuleCommand(rawCommand) {
    const { ruleText, refs } = splitRuleCommand(rawCommand);

    if (!ruleText && !refs) {
        return {
            ok: false,
            error: '정당화를 입력해주세요',
        };
    }

    const rule = parseRuleName(ruleText);

    if (!rule) {
        return {
            ok: false,
            error: '규칙을 읽을 수 없습니다. 예: AS, 1-3, >I, 2, &E',
        };
    }

    const validatedRefs = validateRuleReferenceShape(rule, refs);

    if (!validatedRefs.ok) {
        return validatedRefs;
    }

    return {
        ok: true,
        rule,
        refs: validatedRefs.refs,
        display: formatRuleCommand(rule, validatedRefs.refs, 'edit'),
    };
}

function applyRuleCommandInput(input, showWarning) {
    const parsed = parseRuleCommand(input.value);

    if (!parsed.ok) {
        showWarning(parsed.error);
        input.focus();
        return null;
    }

    ruleSelect.value = parsed.rule;
    refsInput.value = parsed.refs;
    input.value = parsed.display;
    return parsed;
}

function isReferenceFreeRule(rule) {
    return rule === '전제' || rule === '가정' || rule === '문제';
}

function prepareReferencesForRule(rule, refs, warnOnDroppedRefs = false) {
    const normalizedRefs = normalizeReferences(refs);

    if (!isReferenceFreeRule(rule) || !normalizedRefs) {
        return normalizedRefs;
    }

    if (warnOnDroppedRefs && typeof window.alert === 'function') {
        const message = rule === '문제'
            ? '문제는 다른 문장을 참조할 수 없습니다'
            : '전제 또는 가정은 다른 문장을 참조할 수 없습니다';
        window.alert(message);
    }

    return '';
}

function remapReferences(refs, lineNumberMap) {
    const trimmed = refs.trim();

    if (!trimmed) {
        return '';
    }

    const argumentByCasesRanges = parseArgumentByCasesRangeReferences(trimmed);

    if (argumentByCasesRanges) {
        const disjunction = lineNumberMap.get(argumentByCasesRanges.disjunctionLineNumber);
        const firstStart = lineNumberMap.get(argumentByCasesRanges.firstRange.start);
        const firstEnd = lineNumberMap.get(argumentByCasesRanges.firstRange.end);
        const secondStart = lineNumberMap.get(argumentByCasesRanges.secondRange.start);
        const secondEnd = lineNumberMap.get(argumentByCasesRanges.secondRange.end);

        return disjunction && firstStart && firstEnd && secondStart && secondEnd
            ? normalizeReferences(`${disjunction}, ${firstStart}-${firstEnd}, ${secondStart}-${secondEnd}`)
            : '';
    }

    const existentialElimRange = parseExistentialElimRangeReferences(trimmed);

    if (existentialElimRange) {
        const existential = lineNumberMap.get(existentialElimRange.existentialLineNumber);
        const start = lineNumberMap.get(existentialElimRange.subproofRange.start);
        const end = lineNumberMap.get(existentialElimRange.subproofRange.end);

        return existential && start && end
            ? normalizeReferences(`${existential}, ${start}-${end}`)
            : '';
    }

    const biconditionalIntroRanges = parseBiconditionalIntroRangeReferences(trimmed);

    if (biconditionalIntroRanges) {
        const firstStart = lineNumberMap.get(biconditionalIntroRanges.firstRange.start);
        const firstEnd = lineNumberMap.get(biconditionalIntroRanges.firstRange.end);
        const secondStart = lineNumberMap.get(biconditionalIntroRanges.secondRange.start);
        const secondEnd = lineNumberMap.get(biconditionalIntroRanges.secondRange.end);

        return firstStart && firstEnd && secondStart && secondEnd
            ? normalizeReferences(`${firstStart}-${firstEnd}, ${secondStart}-${secondEnd}`)
            : '';
    }

    const range = trimmed.match(/^(\d+)\s*-\s*(\d+)$/);

    if (range) {
        const start = lineNumberMap.get(Number(range[1]));
        const end = lineNumberMap.get(Number(range[2]));
        return start && end ? normalizeReferences(`${start}-${end}`) : '';
    }

    const references = trimmed
        .match(/\d+/g)
        ?.map(Number)
        .map((lineNumber) => lineNumberMap.get(lineNumber))
        .filter(Boolean);

    return references?.length ? normalizeReferences(references.join(', ')) : '';
}

function shiftReferencesAfter(refs, threshold) {
    const trimmed = refs.trim();

    if (!trimmed) {
        return '';
    }

    const shift = (lineNumber) => lineNumber > threshold ? lineNumber + 1 : lineNumber;
    const argumentByCasesRanges = parseArgumentByCasesRangeReferences(trimmed);

    if (argumentByCasesRanges) {
        return normalizeReferences(
            `${shift(argumentByCasesRanges.disjunctionLineNumber)}, ` +
            `${shift(argumentByCasesRanges.firstRange.start)}-${shift(argumentByCasesRanges.firstRange.end)}, ` +
            `${shift(argumentByCasesRanges.secondRange.start)}-${shift(argumentByCasesRanges.secondRange.end)}`,
        );
    }

    const existentialElimRange = parseExistentialElimRangeReferences(trimmed);

    if (existentialElimRange) {
        return normalizeReferences(
            `${shift(existentialElimRange.existentialLineNumber)}, ` +
            `${shift(existentialElimRange.subproofRange.start)}-${shift(existentialElimRange.subproofRange.end)}`,
        );
    }

    const biconditionalIntroRanges = parseBiconditionalIntroRangeReferences(trimmed);

    if (biconditionalIntroRanges) {
        return normalizeReferences(
            `${shift(biconditionalIntroRanges.firstRange.start)}-${shift(biconditionalIntroRanges.firstRange.end)}, ` +
            `${shift(biconditionalIntroRanges.secondRange.start)}-${shift(biconditionalIntroRanges.secondRange.end)}`,
        );
    }

    const range = trimmed.match(/^(\d+)\s*-\s*(\d+)$/);

    if (range) {
        return normalizeReferences(`${shift(Number(range[1]))}-${shift(Number(range[2]))}`);
    }

    if (trimmed.includes(',')) {
        const references = trimmed.match(/\d+/g)?.map(Number).map(shift);
        return references?.length ? normalizeReferences(references.join(', ')) : '';
    }

    const singleReference = trimmed.match(/^\d+$/);
    return singleReference ? String(shift(Number(trimmed))) : trimmed;
}

function findProofLine(lineNumber, lines) {
    return lines.find((line) => line.lineNumber === lineNumber) || null;
}

function validRuleResult(message = '추론규칙에 부합합니다.') {
    return { status: 'valid', message };
}

function invalidRuleResult(message) {
    return { status: 'invalid', message };
}

function pendingRuleResult() {
    return { status: 'pending', message: '아직 제작중인 추론규칙입니다.' };
}

function skippedRuleResult() {
    return { status: 'skipped', message: 'WFF가 아니어서 추론규칙을 검사하지 않았습니다.' };
}

function ensureLinesAreWff(lines) {
    return lines.every((line) => line?.isWff);
}

function sameSubproof(leftLine, rightLine) {
    return (
        leftLine.depth === rightLine.depth &&
        leftLine.subproofPath === rightLine.subproofPath
    );
}

function validateReferenceAccessibility(proofLine, lines) {
    if (!proofLine.refs.trim()) {
        return validRuleResult();
    }

    if (isSubproofClosingRule(proofLine.rule)) {
        return validRuleResult();
    }

    if (
        proofLine.rule === 'AC' &&
        parseArgumentByCasesRangeReferences(proofLine.refs)
    ) {
        return validRuleResult();
    }

    if (
        proofLine.rule === '∃ 제거' &&
        parseExistentialElimRangeReferences(proofLine.refs)
    ) {
        return validRuleResult();
    }

    if (
        proofLine.rule === '↔ 도입' &&
        parseBiconditionalIntroRangeReferences(proofLine.refs)
    ) {
        return validRuleResult();
    }

    const references = parseReferenceNumbers(proofLine.refs);
    const inaccessibleLine = references
        .map((lineNumber) => findProofLine(lineNumber, lines))
        .find((referencedLine) => {
            if (!referencedLine) {
                return false;
            }

            if (referencedLine.depth < proofLine.depth) {
                return false;
            }

            return !sameSubproof(referencedLine, proofLine);
        });

    return inaccessibleLine
        ? invalidRuleResult('참조줄은 현재 줄보다 낮은 깊이에 있거나, 같은 깊이의 같은 보조증명 안에 있어야 합니다.')
        : validRuleResult();
}

function validateConditionalElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('→ 제거는 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const pairs = [
        [first.ast, second.ast],
        [second.ast, first.ast],
    ];

    const hasModusPonensPattern = pairs.some(([conditional, antecedent]) => (
        conditional.type === tokenTypes.IF &&
        sameFormulaAst(conditional.left, antecedent) &&
        sameFormulaAst(conditional.right, conclusion.ast)
    ));

    return hasModusPonensPattern
        ? validRuleResult()
        : invalidRuleResult('A → B와 A에서 B를 도출한 형태가 아닙니다.');
}

function validateAndElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('& 제거는 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conjunction = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!conjunction.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conjunction.ast.type !== tokenTypes.AND) {
        return invalidRuleResult('참조줄이 A & B 형태가 아닙니다.');
    }

    const isLeftOrRightSide = (
        sameFormulaAst(conclusion.ast, conjunction.ast.left) ||
        sameFormulaAst(conclusion.ast, conjunction.ast.right)
    );

    return isLeftOrRightSide
        ? validRuleResult()
        : invalidRuleResult('도출문이 참조된 연언문의 왼쪽 또는 오른쪽 항이 아닙니다.');
}

function validateRepetition(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('반복은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const repeatedFormula = parseFormulaAst(referencedLine.formula);

    if (!conclusion.ok || !repeatedFormula.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    return sameFormulaAst(conclusion.ast, repeatedFormula.ast)
        ? validRuleResult()
        : invalidRuleResult('반복의 도출문은 참조줄의 문장과 같아야 합니다.');
}

function validateAndIntro(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('& 도입은 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.AND) {
        return invalidRuleResult('도출문이 A & B 형태가 아닙니다.');
    }

    const matchesReferenceOrder = (
        sameFormulaAst(conclusion.ast.left, first.ast) &&
        sameFormulaAst(conclusion.ast.right, second.ast)
    );
    const matchesReverseOrder = (
        sameFormulaAst(conclusion.ast.left, second.ast) &&
        sameFormulaAst(conclusion.ast.right, first.ast)
    );

    return matchesReferenceOrder || matchesReverseOrder
        ? validRuleResult()
        : invalidRuleResult('두 참조줄 A, B에서 A & B 또는 B & A를 도출한 형태가 아닙니다.');
}

function validateOrIntro(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('∨ 도입은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const disjunct = parseFormulaAst(referencedLine.formula);

    if (!conclusion.ok || !disjunct.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.OR) {
        return invalidRuleResult('도출문이 A ∨ B 형태가 아닙니다.');
    }

    const containsReferencedDisjunct = (
        sameFormulaAst(conclusion.ast.left, disjunct.ast) ||
        sameFormulaAst(conclusion.ast.right, disjunct.ast)
    );

    return containsReferencedDisjunct
        ? validRuleResult()
        : invalidRuleResult('참조줄 A에서 A ∨ B 또는 B ∨ A를 도출한 형태가 아닙니다.');
}

function validateOrElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('∨ 제거는 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const pairs = [
        [first.ast, second.ast],
        [second.ast, first.ast],
    ];

    const hasDisjunctiveSyllogismPattern = pairs.some(([disjunction, negation]) => {
        if (disjunction.type !== tokenTypes.OR || negation.type !== tokenTypes.NOT) {
            return false;
        }

        if (sameFormulaAst(negation.operand, disjunction.left)) {
            return sameFormulaAst(conclusion.ast, disjunction.right);
        }

        if (sameFormulaAst(negation.operand, disjunction.right)) {
            return sameFormulaAst(conclusion.ast, disjunction.left);
        }

        return false;
    });

    return hasDisjunctiveSyllogismPattern
        ? validRuleResult()
        : invalidRuleResult('A ∨ B와 ~A 또는 ~B에서 남은 선언지를 도출한 형태가 아닙니다.');
}

function validateBiconditionalIntro(proofLine, lines) {
    const rangeReferences = parseBiconditionalIntroRangeReferences(proofLine.refs);

    if (rangeReferences) {
        const conclusion = parseFormulaAst(proofLine.formula);
        const firstConditional = getConditionalFromSubproofRange(
            rangeReferences.firstRange,
            proofLine,
            lines,
            '↔ 도입',
        );
        const secondConditional = getConditionalFromSubproofRange(
            rangeReferences.secondRange,
            proofLine,
            lines,
            '↔ 도입',
        );

        if (firstConditional.status === 'invalid') {
            return firstConditional;
        }

        if (secondConditional.status === 'invalid') {
            return secondConditional;
        }

        if (!conclusion.ok || conclusion.ast.type !== tokenTypes.IFF) {
            return invalidRuleResult('도출문은 A ↔ B 형태여야 합니다.');
        }

        return hasBiconditionalIntroPattern(
            firstConditional,
            secondConditional,
            conclusion.ast,
        )
            ? validRuleResult()
            : invalidRuleResult('두 보조증명에 → 도입을 적용한 결과가 A → B와 B → A가 되어야 합니다.');
    }

    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('↔ 도입은 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (
        conclusion.ast.type !== tokenTypes.IFF ||
        first.ast.type !== tokenTypes.IF ||
        second.ast.type !== tokenTypes.IF
    ) {
        return invalidRuleResult('참조줄은 A → B, B → A이고 도출문은 A ↔ B 형태여야 합니다.');
    }

    return hasBiconditionalIntroPattern(first.ast, second.ast, conclusion.ast)
        ? validRuleResult()
        : invalidRuleResult('A → B와 B → A에서 A ↔ B 또는 B ↔ A를 도출한 형태가 아닙니다.');
}

function hasBiconditionalIntroPattern(firstConditional, secondConditional, conclusion) {
    if (
        firstConditional.type !== tokenTypes.IF ||
        secondConditional.type !== tokenTypes.IF ||
        conclusion.type !== tokenTypes.IFF
    ) {
        return false;
    }

    const referencesMatchConclusion = (
        sameFormulaAst(firstConditional.left, conclusion.left) &&
        sameFormulaAst(firstConditional.right, conclusion.right) &&
        sameFormulaAst(secondConditional.left, conclusion.right) &&
        sameFormulaAst(secondConditional.right, conclusion.left)
    );
    const referencesMatchReverseConclusion = (
        sameFormulaAst(firstConditional.left, conclusion.right) &&
        sameFormulaAst(firstConditional.right, conclusion.left) &&
        sameFormulaAst(secondConditional.left, conclusion.left) &&
        sameFormulaAst(secondConditional.right, conclusion.right)
    );

    return referencesMatchConclusion || referencesMatchReverseConclusion;
}

function validateBiconditionalElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('↔ 제거는 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const biconditional = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!biconditional.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (biconditional.ast.type !== tokenTypes.IFF || conclusion.ast.type !== tokenTypes.IF) {
        return invalidRuleResult('참조줄은 A ↔ B이고 도출문은 A → B 또는 B → A 형태여야 합니다.');
    }

    const isForwardDirection = (
        sameFormulaAst(conclusion.ast.left, biconditional.ast.left) &&
        sameFormulaAst(conclusion.ast.right, biconditional.ast.right)
    );
    const isBackwardDirection = (
        sameFormulaAst(conclusion.ast.left, biconditional.ast.right) &&
        sameFormulaAst(conclusion.ast.right, biconditional.ast.left)
    );

    return isForwardDirection || isBackwardDirection
        ? validRuleResult()
        : invalidRuleResult('A ↔ B에서 A → B 또는 B → A를 도출한 형태가 아닙니다.');
}

function validateDoubleNegationElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('~~ 제거는 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const doubleNegation = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!doubleNegation.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (
        doubleNegation.ast.type !== tokenTypes.NOT ||
        doubleNegation.ast.operand.type !== tokenTypes.NOT
    ) {
        return invalidRuleResult('참조줄이 ~~A 형태가 아닙니다.');
    }

    return sameFormulaAst(conclusion.ast, doubleNegation.ast.operand.operand)
        ? validRuleResult()
        : invalidRuleResult('~~A에서 A를 도출한 형태가 아닙니다.');
}

function validateDoubleNegationIntroDerived(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('~~ 도입은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (
        conclusion.ast.type !== tokenTypes.NOT ||
        conclusion.ast.operand.type !== tokenTypes.NOT
    ) {
        return invalidRuleResult('도출문이 ~~A 형태가 아닙니다.');
    }

    return sameFormulaAst(conclusion.ast.operand.operand, referencedFormula.ast)
        ? validRuleResult()
        : invalidRuleResult('A에서 ~~A를 도출한 형태가 아닙니다.');
}

function validateModusTollens(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('후건 부정은 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.NOT) {
        return invalidRuleResult('후건 부정의 도출문은 ~A 형태여야 합니다.');
    }

    const pairs = [
        [first.ast, second.ast],
        [second.ast, first.ast],
    ];

    const hasModusTollensPattern = pairs.some(([conditional, consequentEvidence]) => {
        if (
            conditional.type !== tokenTypes.IF ||
            !sameFormulaAst(conclusion.ast.operand, conditional.left)
        ) {
            return false;
        }

        const hasNegatedConsequent = (
            consequentEvidence.type === tokenTypes.NOT &&
            sameFormulaAst(consequentEvidence.operand, conditional.right)
        );
        const hasPositiveContraryToNegatedConsequent = (
            conditional.right.type === tokenTypes.NOT &&
            sameFormulaAst(consequentEvidence, conditional.right.operand)
        );

        return hasNegatedConsequent || hasPositiveContraryToNegatedConsequent;
    });

    return hasModusTollensPattern
        ? validRuleResult()
        : invalidRuleResult('A → B와 ~B, 또는 A → ~B와 B에서 ~A를 도출한 형태가 아닙니다.');
}

function validateHypotheticalSyllogism(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('연쇄 논법은 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (
        conclusion.ast.type !== tokenTypes.IF ||
        first.ast.type !== tokenTypes.IF ||
        second.ast.type !== tokenTypes.IF
    ) {
        return invalidRuleResult('연쇄 논법은 A → B와 B → C에서 A → C를 도출해야 합니다.');
    }

    const hasHypotheticalSyllogismPattern = (
        sameFormulaAst(first.ast.right, second.ast.left) &&
        sameFormulaAst(conclusion.ast.left, first.ast.left) &&
        sameFormulaAst(conclusion.ast.right, second.ast.right)
    );

    return hasHypotheticalSyllogismPattern
        ? validRuleResult()
        : invalidRuleResult('A → B와 B → C에서 A → C를 도출한 형태가 아닙니다.');
}

function hasArgumentByCasesPattern(disjunctionAst, firstConditionalAst, secondConditionalAst, conclusionAst) {
    const conditionalPairs = [
        [firstConditionalAst, secondConditionalAst],
        [secondConditionalAst, firstConditionalAst],
    ];

    return conditionalPairs.some(([leftCase, rightCase]) => (
        leftCase.type === tokenTypes.IF &&
        rightCase.type === tokenTypes.IF &&
        sameFormulaAst(leftCase.left, disjunctionAst.left) &&
        sameFormulaAst(rightCase.left, disjunctionAst.right) &&
        sameFormulaAst(leftCase.right, conclusionAst) &&
        sameFormulaAst(rightCase.right, conclusionAst)
    ));
}

function getConditionalFromSubproofRange(range, proofLine, lines, ruleName = 'AC') {
    const context = getSubproofIntroContext({
        ...proofLine,
        refs: `${range.start}-${range.end}`,
    }, lines, ruleName);

    if (!context.ok) {
        return context.result;
    }

    if (context.conclusionLine.depth !== context.subproofDepth) {
        return invalidRuleResult(`n번 줄은 깊이 ${context.subproofDepth}이어야 합니다.`);
    }

    const assumption = parseFormulaAst(context.assumptionLine.formula);
    const subproofConclusion = parseFormulaAst(context.conclusionLine.formula);

    if (!assumption.ok || !subproofConclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    return {
        type: tokenTypes.IF,
        left: assumption.ast,
        right: subproofConclusion.ast,
    };
}

function validateArgumentByCasesWithRanges(proofLine, lines, rangeRefs) {
    const disjunctionLine = findProofLine(rangeRefs.disjunctionLineNumber, lines);

    if (!disjunctionLine) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, disjunctionLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const disjunction = parseFormulaAst(disjunctionLine.formula);
    const firstConditional = getConditionalFromSubproofRange(rangeRefs.firstRange, proofLine, lines);
    const secondConditional = getConditionalFromSubproofRange(rangeRefs.secondRange, proofLine, lines);

    if (firstConditional.status === 'invalid') {
        return firstConditional;
    }

    if (secondConditional.status === 'invalid') {
        return secondConditional;
    }

    if (!conclusion.ok || !disjunction.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (disjunction.ast.type !== tokenTypes.OR) {
        return invalidRuleResult('경우 논증은 A ∨ B에서 시작해야 합니다.');
    }

    return hasArgumentByCasesPattern(
        disjunction.ast,
        firstConditional,
        secondConditional,
        conclusion.ast,
    )
        ? validRuleResult()
        : invalidRuleResult('A ∨ B와 두 보조증명에서 얻은 A → C, B → C로 C를 도출한 형태가 아닙니다.');
}

function validateArgumentByCases(proofLine, lines) {
    const rangeRefs = parseArgumentByCasesRangeReferences(proofLine.refs);

    if (rangeRefs) {
        return validateArgumentByCasesWithRanges(proofLine, lines, rangeRefs);
    }

    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 3) {
        return invalidRuleResult('경우 논증은 참조줄을 l,m,n 또는 i,j-k,m-n 형식으로 입력해야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const disjunction = parseFormulaAst(referencedLines[0].formula);
    const firstConditional = parseFormulaAst(referencedLines[1].formula);
    const secondConditional = parseFormulaAst(referencedLines[2].formula);

    if (!conclusion.ok || !disjunction.ok || !firstConditional.ok || !secondConditional.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (
        disjunction.ast.type !== tokenTypes.OR ||
        firstConditional.ast.type !== tokenTypes.IF ||
        secondConditional.ast.type !== tokenTypes.IF
    ) {
        return invalidRuleResult('경우 논증은 A ∨ B, A → C, B → C에서 C를 도출해야 합니다.');
    }

    return hasArgumentByCasesPattern(
        disjunction.ast,
        firstConditional.ast,
        secondConditional.ast,
        conclusion.ast,
    )
        ? validRuleResult()
        : invalidRuleResult('A ∨ B, A → C, B → C에서 C를 도출한 형태가 아닙니다.');
}

function validateContraposition(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('대우 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conditional = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!conditional.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conditional.ast.type !== tokenTypes.IF || conclusion.ast.type !== tokenTypes.IF) {
        return invalidRuleResult('대우 규칙은 조건문에서 조건문을 도출해야 합니다.');
    }

    const hasNegatedAntecedentAsConsequent = (
        conclusion.ast.right.type === tokenTypes.NOT &&
        sameFormulaAst(conclusion.ast.right.operand, conditional.ast.left)
    );
    const hasContrapositiveAntecedent = conditional.ast.right.type === tokenTypes.NOT
        ? sameFormulaAst(conclusion.ast.left, conditional.ast.right.operand)
        : (
            conclusion.ast.left.type === tokenTypes.NOT &&
            sameFormulaAst(conclusion.ast.left.operand, conditional.ast.right)
        );

    return hasNegatedAntecedentAsConsequent && hasContrapositiveAntecedent
        ? validRuleResult()
        : invalidRuleResult('A → B에서 ~B → ~A, 또는 A → ~B에서 B → ~A를 도출한 형태가 아닙니다.');
}

function validateWeakening(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('약화는 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.IF) {
        return invalidRuleResult('약화의 도출문은 B → A 형태여야 합니다.');
    }

    return sameFormulaAst(conclusion.ast.right, referencedFormula.ast)
        ? validRuleResult()
        : invalidRuleResult('A에서 B → A를 도출한 형태가 아닙니다.');
}

function validateCommutation(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('교환 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const isCommutableOperator = [tokenTypes.OR, tokenTypes.AND].includes(referencedFormula.ast.type);
    const hasCommutationPattern = (
        isCommutableOperator &&
        conclusion.ast.type === referencedFormula.ast.type &&
        sameFormulaAst(conclusion.ast.left, referencedFormula.ast.right) &&
        sameFormulaAst(conclusion.ast.right, referencedFormula.ast.left)
    );

    return hasCommutationPattern
        ? validRuleResult()
        : invalidRuleResult('A ∨ B에서 B ∨ A, 또는 A & B에서 B & A를 도출한 형태가 아닙니다.');
}

function hasAssociationPattern(source, target, operatorType) {
    const sourceIsRightAssociated = (
        source.type === operatorType &&
        source.right?.type === operatorType
    );
    const targetIsLeftAssociated = (
        target.type === operatorType &&
        target.left?.type === operatorType
    );

    if (
        sourceIsRightAssociated &&
        targetIsLeftAssociated &&
        sameFormulaAst(source.left, target.left.left) &&
        sameFormulaAst(source.right.left, target.left.right) &&
        sameFormulaAst(source.right.right, target.right)
    ) {
        return true;
    }

    const sourceIsLeftAssociated = (
        source.type === operatorType &&
        source.left?.type === operatorType
    );
    const targetIsRightAssociated = (
        target.type === operatorType &&
        target.right?.type === operatorType
    );

    return (
        sourceIsLeftAssociated &&
        targetIsRightAssociated &&
        sameFormulaAst(source.left.left, target.left) &&
        sameFormulaAst(source.left.right, target.right.left) &&
        sameFormulaAst(source.right, target.right.right)
    );
}

function validateAssociation(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('결합 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const hasOrAssociation = hasAssociationPattern(referencedFormula.ast, conclusion.ast, tokenTypes.OR);
    const hasAndAssociation = hasAssociationPattern(referencedFormula.ast, conclusion.ast, tokenTypes.AND);

    return hasOrAssociation || hasAndAssociation
        ? validRuleResult()
        : invalidRuleResult('A ∨ (B ∨ C)와 (A ∨ B) ∨ C, 또는 A & (B & C)와 (A & B) & C 사이의 결합 변환이 아닙니다.');
}

function hasDistributionPattern(source, target, outerType, innerType) {
    const sourceIsUndistributed = (
        source.type === outerType &&
        source.right?.type === innerType
    );
    const targetIsDistributed = (
        target.type === innerType &&
        target.left?.type === outerType &&
        target.right?.type === outerType
    );

    if (
        sourceIsUndistributed &&
        targetIsDistributed &&
        sameFormulaAst(source.left, target.left.left) &&
        sameFormulaAst(source.left, target.right.left) &&
        sameFormulaAst(source.right.left, target.left.right) &&
        sameFormulaAst(source.right.right, target.right.right)
    ) {
        return true;
    }

    const sourceIsDistributed = (
        source.type === innerType &&
        source.left?.type === outerType &&
        source.right?.type === outerType
    );
    const targetIsUndistributed = (
        target.type === outerType &&
        target.right?.type === innerType
    );

    return (
        sourceIsDistributed &&
        targetIsUndistributed &&
        sameFormulaAst(source.left.left, target.left) &&
        sameFormulaAst(source.right.left, target.left) &&
        sameFormulaAst(source.left.right, target.right.left) &&
        sameFormulaAst(source.right.right, target.right.right)
    );
}

function validateDistribution(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('분배 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const hasAndOverOrDistribution = hasDistributionPattern(
        referencedFormula.ast,
        conclusion.ast,
        tokenTypes.AND,
        tokenTypes.OR,
    );
    const hasOrOverAndDistribution = hasDistributionPattern(
        referencedFormula.ast,
        conclusion.ast,
        tokenTypes.OR,
        tokenTypes.AND,
    );

    return hasAndOverOrDistribution || hasOrOverAndDistribution
        ? validRuleResult()
        : invalidRuleResult('A & (B ∨ C)와 (A & B) ∨ (A & C), 또는 A ∨ (B & C)와 (A ∨ B) & (A ∨ C) 사이의 분배 변환이 아닙니다.');
}

function isNegationOf(formula, operand) {
    return formula?.type === tokenTypes.NOT && sameFormulaAst(formula.operand, operand);
}

function hasDeMorganPattern(source, target, negatedOperatorType, distributedOperatorType) {
    const sourceIsNegatedBinary = (
        source.type === tokenTypes.NOT &&
        source.operand?.type === negatedOperatorType
    );
    const targetIsDistributedNegation = (
        target.type === distributedOperatorType &&
        isNegationOf(target.left, source.operand?.left) &&
        isNegationOf(target.right, source.operand?.right)
    );

    if (sourceIsNegatedBinary && targetIsDistributedNegation) {
        return true;
    }

    const sourceIsDistributedNegation = (
        source.type === distributedOperatorType &&
        source.left?.type === tokenTypes.NOT &&
        source.right?.type === tokenTypes.NOT
    );
    const targetIsNegatedBinary = (
        target.type === tokenTypes.NOT &&
        target.operand?.type === negatedOperatorType
    );

    return (
        sourceIsDistributedNegation &&
        targetIsNegatedBinary &&
        sameFormulaAst(source.left.operand, target.operand.left) &&
        sameFormulaAst(source.right.operand, target.operand.right)
    );
}

function validateDeMorgan(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('드 모르간의 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const hasNegatedOrPattern = hasDeMorganPattern(
        referencedFormula.ast,
        conclusion.ast,
        tokenTypes.OR,
        tokenTypes.AND,
    );
    const hasNegatedAndPattern = hasDeMorganPattern(
        referencedFormula.ast,
        conclusion.ast,
        tokenTypes.AND,
        tokenTypes.OR,
    );

    return hasNegatedOrPattern || hasNegatedAndPattern
        ? validRuleResult()
        : invalidRuleResult('~(A ∨ B)와 ~A & ~B, 또는 ~(A & B)와 ~A ∨ ~B 사이의 드 모르간 변환이 아닙니다.');
}

function hasConditionalRulePattern(source, target) {
    const sourceIsConditional = source.type === tokenTypes.IF;
    const targetIsMaterialConditional = (
        target.type === tokenTypes.OR &&
        isNegationOf(target.left, source.left) &&
        sameFormulaAst(target.right, source.right)
    );

    if (sourceIsConditional && targetIsMaterialConditional) {
        return true;
    }

    const sourceIsMaterialConditional = (
        source.type === tokenTypes.OR &&
        source.left?.type === tokenTypes.NOT
    );
    const targetIsConditional = target.type === tokenTypes.IF;

    if (
        sourceIsMaterialConditional &&
        targetIsConditional &&
        sameFormulaAst(source.left.operand, target.left) &&
        sameFormulaAst(source.right, target.right)
    ) {
        return true;
    }

    const sourceIsNegatedConditional = (
        source.type === tokenTypes.NOT &&
        source.operand?.type === tokenTypes.IF
    );
    const targetIsConjunctiveNegatedConsequent = (
        target.type === tokenTypes.AND &&
        sourceIsNegatedConditional &&
        sameFormulaAst(target.left, source.operand.left) &&
        isNegationOf(target.right, source.operand.right)
    );

    if (sourceIsNegatedConditional && targetIsConjunctiveNegatedConsequent) {
        return true;
    }

    const sourceIsConjunctiveNegatedConsequent = (
        source.type === tokenTypes.AND &&
        source.right?.type === tokenTypes.NOT
    );
    const targetIsNegatedConditional = (
        target.type === tokenTypes.NOT &&
        target.operand?.type === tokenTypes.IF
    );

    return (
        sourceIsConjunctiveNegatedConsequent &&
        targetIsNegatedConditional &&
        sameFormulaAst(source.left, target.operand.left) &&
        sameFormulaAst(source.right.operand, target.operand.right)
    );
}

function validateConditionalRule(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('조건문 규칙은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    return hasConditionalRulePattern(referencedFormula.ast, conclusion.ast)
        ? validRuleResult()
        : invalidRuleResult('A → B와 ~A ∨ B, 또는 ~(A → B)와 A & ~B 사이의 조건문 변환이 아닙니다.');
}

function hasNegatedExistentialPattern(source, target) {
    const sourceIsNegatedExistential = (
        source.type === tokenTypes.NOT &&
        source.operand?.type === tokenTypes.QUANTIFIER &&
        source.operand.quantifier === '∃'
    );
    const targetIsUniversalNegation = (
        target.type === tokenTypes.QUANTIFIER &&
        target.quantifier === '∀' &&
        target.operand?.type === tokenTypes.NOT
    );

    if (
        sourceIsNegatedExistential &&
        targetIsUniversalNegation &&
        source.operand.variable === target.variable &&
        sameFormulaAst(source.operand.operand, target.operand.operand)
    ) {
        return true;
    }

    return (
        target.type === tokenTypes.NOT &&
        target.operand?.type === tokenTypes.QUANTIFIER &&
        target.operand.quantifier === '∃' &&
        source.type === tokenTypes.QUANTIFIER &&
        source.quantifier === '∀' &&
        source.operand?.type === tokenTypes.NOT &&
        target.operand.variable === source.variable &&
        sameFormulaAst(target.operand.operand, source.operand.operand)
    );
}

function hasNegatedUniversalPattern(source, target) {
    const sourceIsNegatedUniversal = (
        source.type === tokenTypes.NOT &&
        source.operand?.type === tokenTypes.QUANTIFIER &&
        source.operand.quantifier === '∀'
    );
    const targetIsExistentialNegation = (
        target.type === tokenTypes.QUANTIFIER &&
        target.quantifier === '∃' &&
        target.operand?.type === tokenTypes.NOT
    );

    if (
        sourceIsNegatedUniversal &&
        targetIsExistentialNegation &&
        source.operand.variable === target.variable &&
        sameFormulaAst(source.operand.operand, target.operand.operand)
    ) {
        return true;
    }

    return (
        target.type === tokenTypes.NOT &&
        target.operand?.type === tokenTypes.QUANTIFIER &&
        target.operand.quantifier === '∀' &&
        source.type === tokenTypes.QUANTIFIER &&
        source.quantifier === '∃' &&
        source.operand?.type === tokenTypes.NOT &&
        target.operand.variable === source.variable &&
        sameFormulaAst(target.operand.operand, source.operand.operand)
    );
}

function validateQuantifierNegationRule(proofLine, lines, ruleName, matcher, message) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult(`${ruleName}은 참조줄이 정확히 1개여야 합니다.`);
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    return matcher(referencedFormula.ast, conclusion.ast)
        ? validRuleResult()
        : invalidRuleResult(message);
}

function validateLawOfExcludedMiddle(proofLine) {
    if (proofLine.refs.trim()) {
        return invalidRuleResult('배중률은 참조줄 없이 사용해야 합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);

    if (!conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.OR) {
        return invalidRuleResult('배중률의 도출문은 A ∨ ~A 형태여야 합니다.');
    }

    const hasExcludedMiddlePattern = areContradictoryFormulaAsts(
        conclusion.ast.left,
        conclusion.ast.right,
    );

    return hasExcludedMiddlePattern
        ? validRuleResult()
        : invalidRuleResult('배중률은 A ∨ ~A 또는 ~A ∨ A 형태여야 합니다.');
}

function validateBottomIntro(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('⊥ 도입은 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((lineNumber) => findProofLine(lineNumber, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const first = parseFormulaAst(referencedLines[0].formula);
    const second = parseFormulaAst(referencedLines[1].formula);

    if (!conclusion.ok || !first.ok || !second.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.FALSUM) {
        return invalidRuleResult('⊥ 도입의 도출문은 ⊥이어야 합니다.');
    }

    const hasContradiction = areContradictoryFormulaAsts(first.ast, second.ast);

    return hasContradiction
        ? validRuleResult()
        : invalidRuleResult('참조줄 두 개가 A와 ~A 형태가 아닙니다.');
}

function getProofLinesInRange(range, lines) {
    const rangeLines = lines.filter((line) => (
        line.lineNumber >= range.start &&
        line.lineNumber <= range.end
    ));

    return rangeLines.length === range.end - range.start + 1
        ? rangeLines
        : null;
}

function isLineInsideSubproof(line, rootSubproofPath) {
    return line.subproofPath === rootSubproofPath ||
        line.subproofPath.startsWith(`${rootSubproofPath}/`);
}

function getSubproofIntroContext(proofLine, lines, ruleName, options = {}) {
    const range = parseReferenceRange(proofLine.refs);

    if (!range) {
        return {
            ok: false,
            result: invalidRuleResult(`${ruleName}은 참조를 m-n 형식으로 입력해야 합니다.`),
        };
    }

    if (range.start > range.end) {
        return {
            ok: false,
            result: invalidRuleResult(`${ruleName}의 참조 범위는 m <= n이어야 합니다.`),
        };
    }

    const assumptionLine = findProofLine(range.start, lines);
    const conclusionLine = findProofLine(range.end, lines);
    const rangeLines = getProofLinesInRange(range, lines);

    if (!assumptionLine || !conclusionLine || !rangeLines) {
        return {
            ok: false,
            result: invalidRuleResult('존재하지 않는 참조줄이 있습니다.'),
        };
    }

    if (!ensureLinesAreWff([proofLine, ...rangeLines])) {
        return {
            ok: false,
            result: invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.'),
        };
    }

    const currentDepth = proofLine.depth || 0;
    const subproofDepth = currentDepth + 1;

    if (assumptionLine.rule !== '가정' || assumptionLine.depth !== subproofDepth) {
        return {
            ok: false,
            result: invalidRuleResult(`m번 참조줄은 깊이 ${subproofDepth}의 가정이어야 합니다.`),
        };
    }

    const hasLineOutsideSubproof = rangeLines.some((line) => (
        line.depth <= currentDepth ||
        !isLineInsideSubproof(line, assumptionLine.subproofPath)
    ));

    if (hasLineOutsideSubproof) {
        return {
            ok: false,
            result: invalidRuleResult(`m-n 범위는 현재 깊이 ${currentDepth}보다 깊은 하나의 보조증명이어야 합니다.`),
        };
    }

    if (options.requireEndMarker && !conclusionLine.hasSubproofEndMarker) {
        return {
            ok: false,
            result: invalidRuleResult(`n번 줄은 /로 닫힌 보조증명의 마지막 줄이어야 합니다.`),
        };
    }

    return {
        ok: true,
        range,
        rangeLines,
        assumptionLine,
        conclusionLine,
        currentDepth,
        subproofDepth,
    };
}

function validateConditionalIntro(proofLine, lines) {
    const context = getSubproofIntroContext(proofLine, lines, '→ 도입');

    if (!context.ok) {
        return context.result;
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const assumption = parseFormulaAst(context.assumptionLine.formula);
    const subproofConclusion = parseFormulaAst(context.conclusionLine.formula);

    if (!conclusion.ok || !assumption.ok || !subproofConclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.IF) {
        return invalidRuleResult('→ 도입의 도출문은 A → B 형태여야 합니다.');
    }

    if (context.conclusionLine.depth !== context.subproofDepth) {
        return invalidRuleResult(`n번 줄은 깊이 ${context.subproofDepth}이어야 합니다.`);
    }

    const matchesConditionalIntro = (
        sameFormulaAst(conclusion.ast.left, assumption.ast) &&
        sameFormulaAst(conclusion.ast.right, subproofConclusion.ast)
    );

    return matchesConditionalIntro
        ? validRuleResult()
        : invalidRuleResult('m번 가정 A와 n번 결론 B에서 A → B를 도출한 형태가 아닙니다.');
}

function validateNegationIntro(proofLine, lines) {
    const context = getSubproofIntroContext(proofLine, lines, '~ 도입');

    if (!context.ok) {
        return context.result;
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const assumption = parseFormulaAst(context.assumptionLine.formula);

    if (!conclusion.ok || !assumption.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.NOT) {
        return invalidRuleResult('~ 도입의 도출문은 ~A 형태여야 합니다.');
    }

    const hasBottomAtSubproofDepth = context.rangeLines
        .filter((line) => line.lineNumber > context.assumptionLine.lineNumber)
        .some((line) => {
            if (line.depth !== context.subproofDepth) {
                return false;
            }

            const formula = parseFormulaAst(line.formula);
            return formula.ok && formula.ast.type === tokenTypes.FALSUM;
        });

    const subproofDepthFormulas = context.rangeLines
        .filter((line) => line.depth === context.subproofDepth)
        .map((line) => parseFormulaAst(line.formula))
        .filter((formula) => formula.ok)
        .map((formula) => formula.ast);

    const hasContradictoryPairAtSubproofDepth = subproofDepthFormulas.some((formula, index) => (
        subproofDepthFormulas
            .slice(index + 1)
            .some((otherFormula) => areContradictoryFormulaAsts(formula, otherFormula))
    ));

    if (!hasBottomAtSubproofDepth && !hasContradictoryPairAtSubproofDepth) {
        return invalidRuleResult(
            `m+1번부터 n번 줄 사이에 깊이 ${context.subproofDepth}의 ⊥이 있거나, ` +
            `m번부터 n번 줄 사이의 깊이 ${context.subproofDepth} 문장들 중 A와 ~A 형태의 두 문장이 있어야 합니다.`,
        );
    }

    return sameFormulaAst(conclusion.ast.operand, assumption.ast)
        ? validRuleResult()
        : invalidRuleResult('m번 가정 A에서 ~A를 도출한 형태가 아닙니다.');
}

function validateUniversalElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('∀ 제거는 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const universal = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!universal.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (universal.ast.type !== tokenTypes.QUANTIFIER || universal.ast.quantifier !== '∀') {
        return invalidRuleResult('∀ 제거의 참조줄은 (∀x)A 형태여야 합니다.');
    }

    return canInstantiateWithAnyName(
        universal.ast.operand,
        universal.ast.variable,
        conclusion.ast,
    )
        ? validRuleResult()
        : invalidRuleResult('구속 변항을 하나의 이름으로 대치한 결과가 아닙니다.');
}

function validateUniversalIntro(proofLine, lines) {
    const range = parseReferenceRange(proofLine.refs);

    if (!range) {
        return invalidRuleResult('∀ 도입은 참조를 m-n 형식으로 입력해야 합니다.');
    }

    if (range.start > range.end) {
        return invalidRuleResult('∀ 도입의 참조 범위는 m <= n이어야 합니다.');
    }

    const arbitraryNameLine = findProofLine(range.start, lines);
    const subproofConclusionLine = findProofLine(range.end, lines);
    const rangeLines = getProofLinesInRange(range, lines);

    if (!arbitraryNameLine || !subproofConclusionLine || !rangeLines) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...rangeLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const currentDepth = proofLine.depth || 0;
    const subproofDepth = currentDepth + 1;

    if (getProofLineArbitraryNames(arbitraryNameLine).length === 0) {
        return invalidRuleResult('m번 참조줄은 임의 이름으로 시작하는 보조증명이어야 합니다.');
    }

    const arbitraryNameSubproof = getOpenedArbitraryNameSubproof(
        arbitraryNameLine,
        subproofConclusionLine.closedSubproofId,
    );

    if (!arbitraryNameSubproof) {
        return invalidRuleResult('n번 줄은 m번 줄에서 시작한 임의 이름 보조증명을 닫아야 합니다.');
    }

    if (arbitraryNameSubproof.depth !== subproofDepth) {
        return invalidRuleResult(`m번 참조줄의 임의 이름 보조증명은 깊이 ${subproofDepth}에서 시작되어야 합니다.`);
    }

    const hasLineOutsideArbitraryNameSubproof = rangeLines.some((line) => (
        line.depth <= currentDepth ||
        !isLineInsideSubproof(line, arbitraryNameSubproof.path)
    ));

    if (hasLineOutsideArbitraryNameSubproof) {
        return invalidRuleResult(`m-n 범위는 현재 깊이 ${currentDepth}보다 깊은 하나의 임의 이름 보조증명이어야 합니다.`);
    }

    if (!subproofConclusionLine.hasSubproofEndMarker) {
        return invalidRuleResult('n번 줄은 /로 닫힌 임의 이름 보조증명의 마지막 줄이어야 합니다.');
    }

    if (subproofConclusionLine.depth !== subproofDepth) {
        return invalidRuleResult(`n번 줄의 A(u)는 깊이 ${subproofDepth}이어야 합니다.`);
    }

    const conclusion = parseFormulaAst(proofLine.formula);
    const subproofConclusion = parseFormulaAst(subproofConclusionLine.formula);

    if (!conclusion.ok || !subproofConclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.QUANTIFIER || conclusion.ast.quantifier !== '∀') {
        return invalidRuleResult('∀ 도입의 도출문은 (∀x)A 형태여야 합니다.');
    }

    const instantiatedUniversalBody = substituteTermInAst(
        conclusion.ast.operand,
        conclusion.ast.variable,
        arbitraryNameSubproof.name,
    );

    return sameFormulaAst(instantiatedUniversalBody, subproofConclusion.ast)
        ? validRuleResult()
        : invalidRuleResult('보조증명 안의 A(u)에서 임의 이름 u를 변항으로 바꾸어 (∀x)A(x)를 도출한 형태가 아닙니다.');
}

function validateExistentialIntro(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('∃ 도입은 참조줄이 정확히 1개여야 합니다.');
    }

    const referencedLine = findProofLine(references[0], lines);

    if (!referencedLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!ensureLinesAreWff([proofLine, referencedLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormula = parseFormulaAst(referencedLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!referencedFormula.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (conclusion.ast.type !== tokenTypes.QUANTIFIER || conclusion.ast.quantifier !== '∃') {
        return invalidRuleResult('∃ 도입의 도출문은 (∃x)A 형태여야 합니다.');
    }

    return canGeneralizeAnyNameAsExistential(
        conclusion.ast.operand,
        conclusion.ast.variable,
        referencedFormula.ast,
    )
        ? validRuleResult()
        : invalidRuleResult('참조줄의 이름을 하나의 변항으로 바꾸어 존재 양화한 결과가 아닙니다.');
}

function validateExistentialElim(proofLine, lines) {
    const rangeReferences = parseExistentialElimRangeReferences(proofLine.refs);

    if (rangeReferences) {
        const existentialLine = findProofLine(rangeReferences.existentialLineNumber, lines);
        const assumptionLine = findProofLine(rangeReferences.subproofRange.start, lines);
        const subproofConclusionLine = findProofLine(rangeReferences.subproofRange.end, lines);
        const rangeLines = getProofLinesInRange(rangeReferences.subproofRange, lines);

        if (!existentialLine || !assumptionLine || !subproofConclusionLine || !rangeLines) {
            return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
        }

        if (!ensureLinesAreWff([proofLine, existentialLine, ...rangeLines])) {
            return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
        }

        if (assumptionLine.rule !== '∃ 제거용 가정') {
            return invalidRuleResult('j번 줄은 ∃ 제거용 가정이어야 합니다.');
        }

        if (Number(assumptionLine.refs) !== existentialLine.lineNumber) {
            return invalidRuleResult('j번 줄의 ∃ 제거용 가정은 i번 줄을 참조해야 합니다.');
        }

        if (getProofLineArbitraryNames(assumptionLine).length === 0) {
            return invalidRuleResult('j번 줄은 [d]처럼 임의 이름으로 시작해야 합니다.');
        }

        const currentDepth = proofLine.depth || 0;
        const subproofDepth = currentDepth + 1;
        const arbitraryNameSubproof = getOpenedArbitraryNameSubproof(
            assumptionLine,
            subproofConclusionLine.closedSubproofId,
        );

        if (!arbitraryNameSubproof) {
            return invalidRuleResult('k번 줄은 j번 줄에서 시작한 임의 이름 보조증명을 닫아야 합니다.');
        }

        if (arbitraryNameSubproof.depth !== subproofDepth) {
            return invalidRuleResult(`j번 줄의 임의 이름 보조증명은 깊이 ${subproofDepth}에서 시작되어야 합니다.`);
        }

        const hasLineOutsideArbitraryNameSubproof = rangeLines.some((line) => (
            line.depth <= currentDepth ||
            !isLineInsideSubproof(line, arbitraryNameSubproof.path)
        ));

        if (hasLineOutsideArbitraryNameSubproof) {
            return invalidRuleResult(`j-k 범위는 현재 깊이 ${currentDepth}보다 깊은 하나의 임의 이름 보조증명이어야 합니다.`);
        }

        if (!subproofConclusionLine.hasSubproofEndMarker) {
            return invalidRuleResult('k번 줄은 /로 닫힌 임의 이름 보조증명의 마지막 줄이어야 합니다.');
        }

        if (subproofConclusionLine.depth !== subproofDepth) {
            return invalidRuleResult(`k번 줄의 C는 깊이 ${subproofDepth}이어야 합니다.`);
        }

        if (!proofLine.isSentence || !subproofConclusionLine.isSentence) {
            return invalidRuleResult('∃ 제거의 도출문 C는 자유 변항이 없는 완전한 문장이어야 합니다.');
        }

        const conclusion = parseFormulaAst(proofLine.formula);
        const subproofConclusion = parseFormulaAst(subproofConclusionLine.formula);

        if (!conclusion.ok || !subproofConclusion.ok) {
            return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
        }

        if (!sameFormulaAst(conclusion.ast, subproofConclusion.ast)) {
            return invalidRuleResult('∃ 제거의 도출문은 j-k 보조증명의 마지막 문장 C와 같아야 합니다.');
        }

        if (containsTermInAst(subproofConclusion.ast, arbitraryNameSubproof.name)) {
            return invalidRuleResult('보조증명의 마지막 문장 C에는 도입된 임의 이름이 등장하지 않아야 합니다.');
        }

        return validRuleResult();
    }

    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('∃ 제거는 참조줄이 정확히 2개여야 합니다.');
    }

    const existentialLine = findProofLine(references[0], lines);
    const universalConditionalLine = findProofLine(references[1], lines);

    if (!existentialLine || !universalConditionalLine) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, existentialLine, universalConditionalLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    if (!proofLine.isSentence) {
        return invalidRuleResult('∃ 제거의 도출문 C는 자유 변항이 없는 완전한 문장이어야 합니다.');
    }

    const existential = parseFormulaAst(existentialLine.formula);
    const universalConditional = parseFormulaAst(universalConditionalLine.formula);
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!existential.ok || !universalConditional.ok || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (existential.ast.type !== tokenTypes.QUANTIFIER || existential.ast.quantifier !== '∃') {
        return invalidRuleResult('∃ 제거의 첫 번째 참조줄은 (∃x)A(x) 형태여야 합니다.');
    }

    if (
        universalConditional.ast.type !== tokenTypes.QUANTIFIER ||
        universalConditional.ast.quantifier !== '∀'
    ) {
        return invalidRuleResult('∃ 제거의 두 번째 참조줄은 (∀x)(A(x) → C) 형태여야 합니다.');
    }

    if (existential.ast.variable !== universalConditional.ast.variable) {
        return invalidRuleResult('두 참조줄의 양화 변항이 같아야 합니다.');
    }

    const conditional = universalConditional.ast.operand;

    if (conditional.type !== tokenTypes.IF) {
        return invalidRuleResult('보편 양화된 문장의 본문은 A(x) → C 형태여야 합니다.');
    }

    if (!sameFormulaAst(existential.ast.operand, conditional.left)) {
        return invalidRuleResult('두 번째 참조줄의 조건문 전건은 첫 번째 참조줄의 A(x)와 같아야 합니다.');
    }

    if (!sameFormulaAst(conclusion.ast, conditional.right)) {
        return invalidRuleResult('∃ 제거의 도출문은 두 번째 참조줄 조건문의 후건 C와 같아야 합니다.');
    }

    return validRuleResult();
}

function validateExistentialElimAssumption(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 1) {
        return invalidRuleResult('∃ 제거용 가정은 참조줄이 정확히 1개여야 합니다.');
    }

    const existentialLine = findProofLine(references[0], lines);

    if (!existentialLine) {
        return invalidRuleResult('존재하지 않는 참조줄입니다.');
    }

    if (!proofLine.arbitraryName) {
        return invalidRuleResult('∃ 제거용 가정은 [d]처럼 임의 이름으로 시작해야 합니다.');
    }

    if (!ensureLinesAreWff([proofLine, existentialLine])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const existential = parseFormulaAst(existentialLine.formula);
    const assumption = parseFormulaAst(proofLine.formula);

    if (!existential.ok || !assumption.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    if (existential.ast.type !== tokenTypes.QUANTIFIER || existential.ast.quantifier !== '∃') {
        return invalidRuleResult('∃ 제거용 가정의 참조줄은 (∃x)A(x) 형태여야 합니다.');
    }

    const expectedAssumption = substituteTermInAst(
        existential.ast.operand,
        existential.ast.variable,
        proofLine.arbitraryName,
    );

    return sameFormulaAst(expectedAssumption, assumption.ast)
        ? validRuleResult('∃ 제거를 위한 임의 이름 가정입니다.')
        : invalidRuleResult('가정식은 참조줄의 A(x)에서 x를 임의 이름으로 대치한 A(d)여야 합니다.');
}

function validateEqualityIntro(proofLine) {
    const conclusion = parseFormulaAst(proofLine.formula);

    if (!conclusion.ok || conclusion.ast.type !== tokenTypes.EQUAL) {
        return invalidRuleResult('= 도입의 도출문은 a=a 형태의 동일성 문장이어야 합니다.');
    }

    if (conclusion.ast.leftTerm !== conclusion.ast.rightTerm) {
        return invalidRuleResult('= 도입은 같은 이름을 등호 양쪽에 쓴 a=a 형태에만 적용할 수 있습니다.');
    }

    return isNameTerm(conclusion.ast.leftTerm)
        ? validRuleResult()
        : invalidRuleResult('= 도입에는 변항이 아닌 이름만 사용할 수 있습니다.');
}

function validateEqualityElim(proofLine, lines) {
    const references = parseReferenceNumbers(proofLine.refs);

    if (references.length !== 2) {
        return invalidRuleResult('= 제거는 참조줄이 정확히 2개여야 합니다.');
    }

    const referencedLines = references.map((reference) => findProofLine(reference, lines));

    if (referencedLines.some((line) => !line)) {
        return invalidRuleResult('존재하지 않는 참조줄이 있습니다.');
    }

    if (!ensureLinesAreWff([proofLine, ...referencedLines])) {
        return invalidRuleResult('WFF인 줄에 대해서만 추론규칙을 검사합니다.');
    }

    const referencedFormulas = referencedLines.map((line) => parseFormulaAst(line.formula));
    const conclusion = parseFormulaAst(proofLine.formula);

    if (referencedFormulas.some((formula) => !formula.ok) || !conclusion.ok) {
        return invalidRuleResult('문장 구조를 WFF로 읽을 수 없습니다.');
    }

    const equalityCandidates = referencedFormulas.flatMap((equality, index) => (
        equality.ast.type === tokenTypes.EQUAL
            ? [{ equality: equality.ast, source: referencedFormulas[1 - index].ast }]
            : []
    ));

    if (equalityCandidates.length === 0) {
        return invalidRuleResult('= 제거의 두 참조줄 중 하나는 s=t 형태의 동일성 문장이어야 합니다.');
    }

    const matchesEqualityCandidate = ({ equality, source }) => {
        const leftToRight = matchPartialFreeTermSubstitution(
            source,
            conclusion.ast,
            equality.leftTerm,
            equality.rightTerm,
        );
        const rightToLeft = matchPartialFreeTermSubstitution(
            source,
            conclusion.ast,
            equality.rightTerm,
            equality.leftTerm,
        );

        return (
            (leftToRight.matches && leftToRight.changed) ||
            (rightToLeft.matches && rightToLeft.changed)
        );
    };

    return equalityCandidates.some(matchesEqualityCandidate)
        ? validRuleResult()
        : invalidRuleResult('참조한 식에서 동일한 항의 하나 이상을 등치된 항으로 대치한 결과가 아닙니다.');
}

function validateProofLineRule(proofLine, lines) {
    if (!proofLine.isWff) {
        return skippedRuleResult();
    }

    const duplicateArbitraryName = getDuplicateArbitraryNameIntro(proofLine);

    if (duplicateArbitraryName) {
        return invalidRuleResult(`임의 이름 ${duplicateArbitraryName}은 이미 상위 보조증명에서 사용 중입니다.`);
    }

    if (proofLine.rule === '전제') {
        return validRuleResult('전제는 별도 검사가 필요하지 않습니다.');
    }

    if (proofLine.rule === '가정') {
        return proofLine.arbitraryName
            ? validRuleResult('임의 이름 보조증명 안에서 가정 보조증명을 함께 시작합니다.')
            : validRuleResult('가정은 WFF이면 타당한 줄로 처리합니다.');
    }

    const referenceAccessibility = validateReferenceAccessibility(proofLine, lines);

    if (referenceAccessibility.status === 'invalid') {
        return referenceAccessibility;
    }

    if (proofLine.rule === '& 도입') {
        return validateAndIntro(proofLine, lines);
    }

    if (proofLine.rule === '반복') {
        return validateRepetition(proofLine, lines);
    }

    if (proofLine.rule === '→ 도입') {
        return validateConditionalIntro(proofLine, lines);
    }

    if (proofLine.rule === '→ 제거') {
        return validateConditionalElim(proofLine, lines);
    }

    if (proofLine.rule === '& 제거') {
        return validateAndElim(proofLine, lines);
    }

    if (proofLine.rule === '∨ 도입' || proofLine.rule === 'v 도입') {
        return validateOrIntro(proofLine, lines);
    }

    if (proofLine.rule === '∨ 제거' || proofLine.rule === 'v 제거') {
        return validateOrElim(proofLine, lines);
    }

    if (proofLine.rule === '↔ 도입' || proofLine.rule === '<-> 도입') {
        return validateBiconditionalIntro(proofLine, lines);
    }

    if (proofLine.rule === '↔ 제거' || proofLine.rule === '<-> 제거') {
        return validateBiconditionalElim(proofLine, lines);
    }

    if (proofLine.rule === '⊥ 도입') {
        return validateBottomIntro(proofLine, lines);
    }

    if (proofLine.rule === '¬ 도입' || proofLine.rule === '~ 도입') {
        return validateNegationIntro(proofLine, lines);
    }

    if (proofLine.rule === '~~ 제거' || proofLine.rule === '¬ 제거' || proofLine.rule === '~ 제거') {
        return validateDoubleNegationElim(proofLine, lines);
    }

    if (proofLine.rule === '∀ 제거') {
        return validateUniversalElim(proofLine, lines);
    }

    if (proofLine.rule === '∀ 도입') {
        return validateUniversalIntro(proofLine, lines);
    }

    if (proofLine.rule === '∃ 도입') {
        return validateExistentialIntro(proofLine, lines);
    }

    if (proofLine.rule === '∃ 제거') {
        return validateExistentialElim(proofLine, lines);
    }

    if (proofLine.rule === '∃ 제거용 가정') {
        return validateExistentialElimAssumption(proofLine, lines);
    }

    if (proofLine.rule === '= 도입') {
        return validateEqualityIntro(proofLine);
    }

    if (proofLine.rule === '= 제거') {
        return validateEqualityElim(proofLine, lines);
    }

    if (proofLine.rule === '~~ 도입') {
        return validateDoubleNegationIntroDerived(proofLine, lines);
    }

    if (proofLine.rule === 'MT') {
        return validateModusTollens(proofLine, lines);
    }

    if (proofLine.rule === 'HS') {
        return validateHypotheticalSyllogism(proofLine, lines);
    }

    if (proofLine.rule === 'AC') {
        return validateArgumentByCases(proofLine, lines);
    }

    if (proofLine.rule === 'CP') {
        return validateContraposition(proofLine, lines);
    }

    if (proofLine.rule === 'W') {
        return validateWeakening(proofLine, lines);
    }

    if (proofLine.rule === 'Com') {
        return validateCommutation(proofLine, lines);
    }

    if (proofLine.rule === 'Asso') {
        return validateAssociation(proofLine, lines);
    }

    if (proofLine.rule === 'Dist') {
        return validateDistribution(proofLine, lines);
    }

    if (proofLine.rule === 'DeM') {
        return validateDeMorgan(proofLine, lines);
    }

    if (proofLine.rule === 'Cond') {
        return validateConditionalRule(proofLine, lines);
    }

    if (proofLine.rule === 'LEM') {
        return validateLawOfExcludedMiddle(proofLine);
    }

    if (proofLine.rule === '~E') {
        return validateQuantifierNegationRule(
            proofLine,
            lines,
            '~E',
            hasNegatedExistentialPattern,
            '~(∃x)A(x)와 (∀x)~A(x) 사이의 변환이 아닙니다.',
        );
    }

    if (proofLine.rule === '~A') {
        return validateQuantifierNegationRule(
            proofLine,
            lines,
            '~A',
            hasNegatedUniversalPattern,
            '~(∀x)A(x)와 (∃x)~A(x) 사이의 변환이 아닙니다.',
        );
    }

    return pendingRuleResult();
}

function isSubproofClosingRule(rule) {
    return ['→ 도입', '¬ 도입', '~ 도입', '∀ 도입'].includes(rule);
}

function refreshSubproofStructure(lines) {
    const openAssumptions = [];
    let nextSubproofId = 1;

    const openSubproof = (proofLine, kind, data = {}) => {
        const id = nextSubproofId;
        nextSubproofId += 1;

        const entry = {
            id,
            kind,
            lineNumber: proofLine.lineNumber,
            formula: proofLine.formula,
            ...data,
        };

        openAssumptions.push(entry);
        proofLine.opensSubproof = true;
        proofLine.openedSubproofId = id;
        proofLine.openedSubproofIds.push(id);

        return entry;
    };

    const closeSubproof = (proofLine) => {
        if (openAssumptions.length === 0) {
            return;
        }

        const closedAssumption = openAssumptions.pop();
        proofLine.endsSubproof = true;
        proofLine.closesSubproof = true;
        proofLine.closedAssumptionLine = closedAssumption.lineNumber;
        proofLine.closedSubproofId = closedAssumption.id;
    };

    lines.forEach((proofLine) => {
        proofLine.opensSubproof = false;
        proofLine.closesSubproof = false;
        proofLine.endsSubproof = false;
        proofLine.closedAssumptionLine = null;
        proofLine.openedSubproofId = null;
        proofLine.closedSubproofId = null;
        proofLine.openedSubproofIds = [];
        proofLine.openedArbitraryNameSubproofId = null;
        proofLine.openedArbitraryNameSubproofs = [];
        proofLine.arbitraryNameSubproofPath = '';
        proofLine.arbitraryNameDepth = 0;
        proofLine.parentArbitraryNames = openAssumptions
            .filter((assumption) => assumption.kind === 'arbitraryName')
            .map((assumption) => assumption.name);
        proofLine.subproofGuideEntries = [];
        proofLine.contentIndent = 0;
        proofLine.currentSubproofLeft = SUBPROOF_GUIDE_LEFT_START;
        proofLine.openedAssumptionSubproofLeft = null;

        getProofLineArbitraryNames(proofLine).forEach((arbitraryName) => {
            const arbitraryNameSubproof = openSubproof(proofLine, 'arbitraryName', {
                name: arbitraryName,
            });
            const arbitraryNameMetadata = {
                id: arbitraryNameSubproof.id,
                name: arbitraryName,
                depth: openAssumptions.length,
                path: openAssumptions
                    .map((assumption) => assumption.id)
                    .join('/'),
            };

            proofLine.openedArbitraryNameSubproofs.push(arbitraryNameMetadata);
            proofLine.openedArbitraryNameSubproofId = arbitraryNameSubproof.id;
            proofLine.arbitraryNameDepth = arbitraryNameMetadata.depth;
            proofLine.arbitraryNameSubproofPath = arbitraryNameMetadata.path;
        });

        if (proofLine.rule === '가정') {
            openSubproof(proofLine, 'assumption');
        }

        proofLine.depth = openAssumptions.length;
        proofLine.subproofPath = openAssumptions.map((assumption) => assumption.id).join('/');
        proofLine.subproofGuideEntries = getSubproofGuideEntries(openAssumptions);
        proofLine.contentIndent = getSubproofContentIndentFromEntries(proofLine.subproofGuideEntries);
        proofLine.currentSubproofLeft = getCurrentSubproofLeft(proofLine.subproofGuideEntries);
        proofLine.openedAssumptionSubproofLeft = getOpenedAssumptionSubproofLeft(
            proofLine.subproofGuideEntries,
            proofLine.openedSubproofIds,
        );

        if (proofLine.hasSubproofEndMarker) {
            closeSubproof(proofLine);
        }
    });

    lines.forEach((proofLine, index) => {
        const nextLine = lines[index + 1];

        if (!nextLine) {
            return;
        }

        if ((proofLine.depth || 0) > (nextLine.depth || 0)) {
            proofLine.endsSubproof = true;
        }
    });
}

function refreshRuleValidity(lines) {
    refreshSubproofStructure(lines);

    lines.forEach((proofLine) => {
        proofLine.ruleCheck = validateProofLineRule(proofLine, lines);
    });
}

function getInferenceRuleCategory(rule) {
    if (ruleCategories.basic.has(rule)) {
        return 'basic';
    }

    if (ruleCategories.derived.has(rule)) {
        return 'derived';
    }

    return 'unknown';
}

function proofUsesOnlyBasicInferenceRules(lines = proofLines) {
    return lines.length > 0 && lines.every((proofLine) => (
        getInferenceRuleCategory(proofLine.rule) === 'basic'
    ));
}

function proofPremisesMatchProblemPremises(lines = proofLines) {
    if (!targetConclusion) {
        return false;
    }

    const problemPremises = targetConclusion.premises || [];
    const proofPremises = lines.filter((proofLine) => proofLine.rule === '전제');

    if (problemPremises.length !== proofPremises.length) {
        return false;
    }

    const unmatchedProblemPremises = problemPremises.map((premise) => parseFormulaAst(premise));

    if (unmatchedProblemPremises.some((premise) => !premise.ok)) {
        return false;
    }

    for (const proofPremise of proofPremises) {
        const parsedProofPremise = parseFormulaAst(proofPremise.formula);

        if (!parsedProofPremise.ok) {
            return false;
        }

        const matchIndex = unmatchedProblemPremises.findIndex((problemPremise) => (
            sameFormulaAst(problemPremise.ast, parsedProofPremise.ast)
        ));

        if (matchIndex === -1) {
            return false;
        }

        unmatchedProblemPremises.splice(matchIndex, 1);
    }

    return unmatchedProblemPremises.length === 0;
}

function getQedLineNumber() {
    if (!targetConclusion?.isWff || proofLines.length === 0) {
        return null;
    }

    const target = parseFormulaAst(targetConclusion.formula);

    if (!target.ok) {
        return null;
    }

    if (!proofPremisesMatchProblemPremises()) {
        return null;
    }

    for (const proofLine of proofLines) {
        if (!proofLine.isWff || proofLine.ruleCheck?.status !== 'valid') {
            return null;
        }
    }

    const lastLine = proofLines[proofLines.length - 1];

    if ((lastLine.depth || 0) !== 0) {
        return null;
    }

    const formula = parseFormulaAst(lastLine.formula);

    return formula.ok && sameFormulaAst(formula.ast, target.ast)
        ? lastLine.lineNumber
        : null;
}

function isConclusionProved() {
    return getQedLineNumber() !== null;
}

function createLineChip(text, variant = '') {
    const chip = document.createElement('span');
    chip.className = variant ? `line-chip ${variant}` : 'line-chip';
    chip.textContent = text;
    return chip;
}

function createRuleText(text) {
    const ruleText = document.createElement('span');
    ruleText.className = 'line-rule-text';
    ruleText.textContent = text;
    return ruleText;
}

function renderProofLineFormula(element, proofLine) {
    element.replaceChildren();

    const formulaText = document.createElement('span');
    formulaText.className = 'line-formula-text';
    renderTextWithSubscripts(formulaText, proofLine.formula);
    element.append(formulaText);
}

function createArbitraryNameMarker(proofLine) {
    const marker = document.createElement('span');
    marker.className = 'line-arbitrary-name-marker';
    renderTextWithSubscripts(marker, proofLine.name);
    marker.style.setProperty('--arbitrary-name-left', `${proofLine.left}px`);
    return marker;
}

function getArbitraryNameMarkerEntries(proofLine) {
    const openedIds = new Set(proofLine.openedSubproofIds || []);

    return (proofLine.subproofGuideEntries || []).filter((entry) => (
        entry.kind === 'arbitraryName' && openedIds.has(entry.id)
    ));
}

function createStatusMark(variant, title) {
    const mark = document.createElement('span');
    mark.className = `status-mark ${variant}`;
    mark.title = title;
    mark.setAttribute('aria-label', title);
    return mark;
}

function getEditableText(control) {
    if (!control) {
        return '';
    }

    return typeof control.value === 'string'
        ? control.value
        : control.textContent;
}

function setEditableText(control, text) {
    if (!control) {
        return;
    }

    if (typeof control.value === 'string') {
        control.value = text;
        return;
    }

    control.textContent = text;
}

function moveCaretToEnd(control) {
    if (!control) {
        return;
    }

    control.focus();

    if (typeof control.selectionStart === 'number') {
        control.selectionStart = getEditableText(control).length;
        control.selectionEnd = getEditableText(control).length;
        return;
    }

    const range = document.createRange();
    range.selectNodeContents(control);
    range.collapse(false);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

function appendLoadedExampleTitle(label) {
    if (!targetConclusion || !loadedExampleTitle) {
        return;
    }

    const title = document.createElement('span');
    title.className = 'target-example-title';
    title.textContent = loadedExampleTitle;
    label.append(title);
}

function createTargetConclusionElement() {
    const isEditorOpen = isTargetEditorOpen || !targetConclusion;
    const conclusion = document.createElement('article');
    conclusion.className = [
        'target-conclusion',
        targetConclusion ? 'has-target' : 'is-empty-target',
        isEditorOpen ? 'is-editing' : '',
    ].filter(Boolean).join(' ');
    conclusion.role = isEditorOpen ? 'group' : 'button';
    conclusion.tabIndex = isEditorOpen ? -1 : 0;
    conclusion.title = targetConclusion ? '문제 수정' : '문제 입력';

    if (isEditorOpen) {
        const body = document.createElement('div');
        body.className = 'target-body target-editor-body';

        const label = document.createElement('span');
        label.className = 'target-label';
        label.textContent = '문제';
        appendLoadedExampleTitle(label);

        const editor = document.createElement('div');
        editor.className = 'target-editor';

        const input = document.createElement('input');
        input.className = 'target-input';
        input.id = 'targetConclusionInput';
        input.type = 'text';
        input.value = targetConclusion?.displayFormula || '';
        input.autocomplete = 'off';
        input.placeholder = '전제1 / 전제2 / 전제3 ... // 결론';
        input.setAttribute('aria-label', '문제');

        const button = document.createElement('button');
        button.className = 'target-complete-button';
        button.type = 'button';
        button.textContent = '완료';

        editor.append(input, button);
        body.append(label, editor);
        conclusion.append(body);

        if (targetConclusionError) {
            const warning = document.createElement('p');
            warning.className = 'target-warning';
            renderTextWithSubscripts(warning, targetConclusionError);
            conclusion.append(warning);
        }

        return conclusion;
    }

    const body = document.createElement('div');
    body.className = 'target-body';

    const label = document.createElement('span');
    label.className = 'target-label';
    label.textContent = targetConclusion ? '문제' : '';
    appendLoadedExampleTitle(label);

    const formula = document.createElement('p');
    formula.className = targetConclusion
        ? 'target-formula'
        : 'target-formula target-placeholder';
    if (targetConclusion) {
        renderTextWithSubscripts(formula, targetConclusion.displayFormula);
    } else {
        formula.textContent = '문제 입력';
    }

    body.append(label, formula);

    const chips = document.createElement('aside');
    chips.className = 'target-chips';

    if (targetConclusion) {
        const clearTargetButton = document.createElement('button');
        clearTargetButton.className = 'icon-button target-clear-button';
        clearTargetButton.type = 'button';
        clearTargetButton.dataset.action = 'clear';
        clearTargetButton.textContent = '↺';
        clearTargetButton.setAttribute('aria-label', '전체 지우기');
        conclusion.append(clearTargetButton);

        if (isConclusionProved()) {
            chips.append(createLineChip('Q.E.D.', 'qed-chip'));
        }
    }

    conclusion.append(body, chips);
    return conclusion;
}

function clearProofEditor() {
    proofLines.length = 0;
    targetConclusion = null;
    loadedExampleTitle = '';
    isTargetEditorOpen = true;
    targetConclusionError = '';
    nextLineNumber = 1;
    resetEditorForNewLine();
}

function createSetupPanelElement() {
    const setupPanel = document.createElement('section');
    setupPanel.className = 'setup-panel';
    setupPanel.append(createTargetConclusionElement());

    return setupPanel;
}

function createQedCompletionElement(usesOnlyBasicRules = false) {
    const completion = document.createElement('div');
    completion.className = 'qed-completion';

    const label = document.createElement('span');
    label.className = 'qed-label';
    label.textContent = 'Q.E.D.';
    completion.append(label);

    if (usesOnlyBasicRules) {
        const basis = document.createElement('span');
        basis.className = 'qed-basis';
        basis.textContent = 'by only basic rules';
        completion.append(basis);
    }

    return completion;
}

function createSubproofGuides(guideEntries = [], openedSubproofIds = []) {
    const guides = document.createElement('div');
    guides.className = 'subproof-guides';
    guides.setAttribute('aria-hidden', 'true');
    const openedIds = new Set(openedSubproofIds);

    guideEntries.forEach((entry) => {
        const guide = document.createElement('span');
        const classes = [];

        if (openedIds.has(entry.id)) {
            classes.push('starts-current-subproof');
        }

        if (openedIds.has(entry.id) && entry.kind === 'arbitraryName') {
            classes.push('starts-arbitrary-name-guide');
        }

        guide.className = classes.join(' ');
        guide.style.left = `${entry.left}px`;
        guides.append(guide);
    });

    return guides;
}

function updateActiveEditorSubproofGuides(layout) {
    editorLine
        .querySelectorAll('.active-editor-subproof-guides')
        .forEach((guides) => guides.remove());

    editorLine.classList.toggle('awaiting-subproof-line', layout.depth > 0);
    editorLine.style.setProperty(
        '--current-subproof-left',
        `${layout.currentSubproofLeft}px`,
    );

    if (layout.depth <= 0) {
        return;
    }

    const guides = createSubproofGuides(layout.guideEntries);
    guides.classList.add('active-editor-subproof-guides');
    editorLine.prepend(guides);
}

function createInlineRuleCommandInput(rule, refs) {
    const input = document.createElement('input');
    input.className = 'rule-command-input inline-rule-command-input';
    input.type = 'text';
    input.value = formatRuleCommand(rule, refs, 'edit');
    input.autocomplete = 'off';
    input.dataset.inlineRuleCommand = 'true';
    return input;
}

function createInlineProofLineEditorElement(proofLine) {
    const depth = proofLine.depth || 0;
    const line = document.createElement('article');
    line.className = 'proof-line saved-line inline-editor-line is-editing';
    line.dataset.lineNumber = proofLine.lineNumber;
    line.style.setProperty('--depth', depth);
    line.style.setProperty('--content-indent', `${proofLine.contentIndent || 0}px`);
    line.style.setProperty('--current-subproof-left', `${proofLine.currentSubproofLeft || SUBPROOF_GUIDE_LEFT_START}px`);

    if (getProofLineArbitraryNames(proofLine).length > 0) {
        line.classList.add('starts-arbitrary-name-subproof');
    }

    if (proofLine.rule === '∃ 제거용 가정') {
        line.classList.add('is-existential-elim-assumption');
    }

    if (proofLine.openedAssumptionSubproofLeft !== null) {
        line.classList.add('starts-assumption-subproof');
        line.style.setProperty(
            '--assumption-subproof-left',
            `${proofLine.openedAssumptionSubproofLeft}px`,
        );
    }

    if (depth > 0) {
        line.classList.add('is-subproof-line');
        line.append(createSubproofGuides(
            proofLine.subproofGuideEntries,
            proofLine.openedSubproofIds,
        ));
    }

    if (proofLine.opensSubproof) {
        line.classList.add('opens-subproof');
    }

    if (proofLine.closesSubproof) {
        line.classList.add('closes-subproof');
    }

    if (proofLine.endsSubproof) {
        line.classList.add('ends-subproof');
    }

    if (proofLine.lineNumber === swipedLineNumber) {
        line.classList.add('is-swiped');
    }

    const numberEl = document.createElement('span');
    numberEl.className = 'line-number';

    const numberText = document.createElement('span');
    numberText.className = 'line-number-text';
    numberText.textContent = proofLine.lineNumber;

    numberEl.append(numberText);

    const body = document.createElement('div');
    body.className = 'line-body inline-editor-body';

    const formulaInputEl = document.createElement('div');
    formulaInputEl.className = 'line-formula inline-formula-input';
    formulaInputEl.contentEditable = 'true';
    formulaInputEl.role = 'textbox';
    formulaInputEl.spellcheck = false;
    formulaInputEl.textContent = formatProofFormulaForEditing(proofLine);
    formulaInputEl.dataset.inlineFormula = 'true';

    const warningEl = document.createElement('p');
    warningEl.className = 'formula-warning inline-formula-warning';
    warningEl.hidden = true;

    const ruleCommandInputEl = createInlineRuleCommandInput(proofLine.rule, proofLine.refs);

    const completeButtonEl = document.createElement('button');
    completeButtonEl.className = 'complete-button inline-complete-button';
    completeButtonEl.type = 'button';
    completeButtonEl.textContent = '✔︎';

    const meta = document.createElement('aside');
    meta.className = 'line-meta inline-line-meta';
    meta.append(ruleCommandInputEl);

    const actions = document.createElement('div');
    actions.className = 'inline-editor-actions';
    actions.append(completeButtonEl);
    meta.append(actions);

    body.append(formulaInputEl, warningEl);
    line.append(numberEl, body, meta);
    return line;
}

function createProofLineElement(proofLine, qedLineNumber = null) {
    if (proofLine.lineNumber === editingLineNumber) {
        return createInlineProofLineEditorElement(proofLine);
    }

    const line = document.createElement('article');
    const depth = proofLine.depth || 0;
    line.className = [
        'proof-line saved-line',
    ].filter(Boolean).join(' ');
    line.dataset.lineNumber = proofLine.lineNumber;
    line.role = 'button';
    line.tabIndex = 0;
    line.title = '이 줄 수정';
    line.style.setProperty('--depth', depth);
    line.style.setProperty('--content-indent', `${proofLine.contentIndent || 0}px`);
    line.style.setProperty('--current-subproof-left', `${proofLine.currentSubproofLeft || SUBPROOF_GUIDE_LEFT_START}px`);

    if (getProofLineArbitraryNames(proofLine).length > 0) {
        line.classList.add('starts-arbitrary-name-subproof');
    }

    if (proofLine.rule === '∃ 제거용 가정') {
        line.classList.add('is-existential-elim-assumption');
    }

    if (proofLine.openedAssumptionSubproofLeft !== null) {
        line.classList.add('starts-assumption-subproof');
        line.style.setProperty(
            '--assumption-subproof-left',
            `${proofLine.openedAssumptionSubproofLeft}px`,
        );
    }

    if (depth > 0) {
        line.classList.add('is-subproof-line');
        line.append(createSubproofGuides(
            proofLine.subproofGuideEntries,
            proofLine.openedSubproofIds,
        ));
    }

    if (proofLine.opensSubproof) {
        line.classList.add('opens-subproof');
    }

    if (proofLine.closesSubproof) {
        line.classList.add('closes-subproof');
        line.title = `${line.title}: ${proofLine.closedAssumptionLine}번 가정 닫기`;
    }

    if (proofLine.endsSubproof) {
        line.classList.add('ends-subproof');
    }

    const numberEl = document.createElement('span');
    numberEl.className = 'line-number deletable-line-number';

    const numberText = document.createElement('span');
    numberText.className = 'line-number-text';
    numberText.textContent = proofLine.lineNumber;

    const deleteButtonEl = document.createElement('button');
    deleteButtonEl.className = 'delete-button saved-delete-button line-number-delete-button';
    deleteButtonEl.type = 'button';
    deleteButtonEl.textContent = '✖︎';
    deleteButtonEl.setAttribute('aria-label', `${proofLine.lineNumber}번 줄 삭제`);

    numberEl.append(numberText, deleteButtonEl);

    const ruleCheck = proofLine.ruleCheck || pendingRuleResult();
    const status = document.createElement('div');
    status.className = 'line-status';

    const syntaxStatus = proofLine.isWff
        ? (proofLine.isSentence ? 'is-ok' : 'is-open')
        : 'is-bad';
    const syntaxMessage = proofLine.isWff
        ? (
            proofLine.isSentence
                ? 'WFF 문법 검증됨: 문장'
                : `WFF이나 문장은 아닙니다. 자유 변항: ${proofLine.freeVariables.join(', ')}`
        )
        : proofLine.syntaxError;

    status.append(createStatusMark(syntaxStatus, syntaxMessage));

    if (proofLine.isWff && ruleCheck.status !== 'skipped') {
        const ruleStatus = {
            valid: 'is-ok',
            invalid: 'is-bad',
            pending: 'is-pending',
        }[ruleCheck.status];

        if (ruleStatus) {
            status.append(createStatusMark(ruleStatus, ruleCheck.message));
        }
    }

    const body = document.createElement('div');
    body.className = 'line-body';

    const formulaEl = document.createElement('p');
    formulaEl.className = 'line-formula';
    renderProofLineFormula(formulaEl, proofLine);

    const meta = document.createElement('aside');
    meta.className = 'line-meta';

    meta.append(createRuleText(formatRuleCommand(proofLine.rule, proofLine.refs)));
    meta.append(status);

    const swipeDeleteButtonEl = document.createElement('button');
    swipeDeleteButtonEl.className = 'delete-button saved-delete-button swipe-delete-button';
    swipeDeleteButtonEl.type = 'button';
    swipeDeleteButtonEl.textContent = '✖︎';
    swipeDeleteButtonEl.setAttribute('aria-label', `${proofLine.lineNumber}번 줄 삭제`);

    body.append(formulaEl);
    line.append(numberEl);

    getArbitraryNameMarkerEntries(proofLine).forEach((entry) => {
        line.append(createArbitraryNameMarker(entry));
    });

    line.append(body, meta, swipeDeleteButtonEl);
    return line;
}

function renderProofLines() {
    proofList.replaceChildren();
    refreshRuleValidity(proofLines);
    const qedLineNumber = getQedLineNumber();
    const qedUsesOnlyBasicRules = qedLineNumber !== null && proofUsesOnlyBasicInferenceRules();

    proofList.append(createSetupPanelElement());

    if (proofLines.length === 0) {
        proofList.classList.add('is-empty');
        updateActiveLineMarker();
        scheduleProofColumnLayout();
        return;
    }

    proofList.classList.remove('is-empty');

    proofLines.forEach((proofLine) => {
        proofList.append(createProofLineElement(proofLine, qedLineNumber));

        if (proofLine.lineNumber === qedLineNumber) {
            proofList.append(createQedCompletionElement(qedUsesOnlyBasicRules));
        }
    });

    updateActiveLineMarker();
    scheduleProofColumnLayout();
}

function formatProblemDisplay(premises, conclusion) {
    return premises.length > 0
        ? `${premises.join(' / ')} // ${conclusion}`
        : `// ${conclusion}`;
}

function parseProblemInput(rawInput) {
    const trimmedInput = rawInput.trim();
    const parts = trimmedInput.split('//');

    if (parts.length !== 2) {
        return {
            ok: false,
            error: '문제는 전제들 // 결론 형식으로 입력해 주세요.',
            normalizedInput: normalizeFormula(trimmedInput),
        };
    }

    const premisePart = parts[0].trim();
    const conclusionPart = parts[1].trim();

    if (!conclusionPart) {
        return {
            ok: false,
            error: '증명할 결론을 // 뒤에 입력해 주세요.',
            normalizedInput: trimmedInput,
        };
    }

    const rawPremises = premisePart
        ? premisePart.split('/').map((premise) => premise.trim())
        : [];

    if (rawPremises.some((premise) => !premise)) {
        return {
            ok: false,
            error: '전제 사이에는 빈 항목이 없도록 입력해 주세요.',
            normalizedInput: trimmedInput,
        };
    }

    const normalizedPremises = [];

    for (let index = 0; index < rawPremises.length; index += 1) {
        const normalizedPremise = normalizeFormula(rawPremises[index]);
        const syntax = validatePropositionalFormula(normalizedPremise);

        if (!syntax.ok) {
            return {
                ok: false,
                error: `${index + 1}번째 전제가 WFF가 아닙니다. ${syntax.error}`,
                normalizedInput: formatProblemDisplay(
                    [
                        ...normalizedPremises,
                        normalizedPremise,
                        ...rawPremises.slice(index + 1),
                    ],
                    normalizeFormula(conclusionPart),
                ),
            };
        }

        if (!syntax.isSentence) {
            return {
                ok: false,
                error: `${index + 1}번째 전제가 문장이 아닙니다.`,
                normalizedInput: formatProblemDisplay(
                    [
                        ...normalizedPremises,
                        normalizedPremise,
                        ...rawPremises.slice(index + 1),
                    ],
                    normalizeFormula(conclusionPart),
                ),
            };
        }

        normalizedPremises.push(normalizedPremise);
    }

    const normalizedConclusion = normalizeFormula(conclusionPart);
    const conclusionSyntax = validatePropositionalFormula(normalizedConclusion);

    if (!conclusionSyntax.ok) {
        return {
            ok: false,
            error: `결론이 WFF가 아닙니다. ${conclusionSyntax.error}`,
            normalizedInput: formatProblemDisplay(normalizedPremises, normalizedConclusion),
        };
    }

    if (!conclusionSyntax.isSentence) {
        return {
            ok: false,
            error: '결론이 문장이 아닙니다.',
            normalizedInput: formatProblemDisplay(normalizedPremises, normalizedConclusion),
        };
    }

    const arityConsistency = validatePredicateArityConsistency([
        ...normalizedPremises,
        normalizedConclusion,
    ]);

    if (!arityConsistency.ok) {
        return {
            ok: false,
            error: arityConsistency.error,
            normalizedInput: formatProblemDisplay(normalizedPremises, normalizedConclusion),
        };
    }

    return {
        ok: true,
        premises: normalizedPremises,
        conclusion: normalizedConclusion,
        displayFormula: formatProblemDisplay(normalizedPremises, normalizedConclusion),
    };
}

function buildTargetConclusion(conclusionFormula, premises = []) {
    const formula = normalizeFormula(conclusionFormula);
    const normalizedPremises = premises.map((premise) => normalizeFormula(premise));
    const syntax = validatePropositionalFormula(formula);

    return {
        formula,
        displayFormula: formatProblemDisplay(normalizedPremises, formula),
        premises: normalizedPremises,
        rule: '문제',
        refs: '',
        isWff: syntax.ok,
        isSentence: syntax.isSentence,
        freeVariables: syntax.freeVariables,
        syntaxError: syntax.error,
    };
}

function applyParsedProblem(parsedProblem) {
    targetConclusion = buildTargetConclusion(parsedProblem.conclusion, parsedProblem.premises);
    proofLines.length = 0;
    parsedProblem.premises.forEach((premise, index) => {
        proofLines.push(buildProofLine(index + 1, premise, '전제', ''));
    });
    nextLineNumber = proofLines.length + 1;
    targetConclusionError = '';
    isTargetEditorOpen = false;
    isEditingConclusion = false;
    editingLineNumber = null;
    isEditorVisible = true;
    formulaInput.value = '';
    ruleTextInput.value = '';
    ruleSelect.value = '';
    refsInput.value = '';
    clearFormulaWarning();
    updateEntryState();
}

function parseAnswerInput(rawInput) {
    const normalizedInput = rawInput.replace(/\r\n?/g, '\n').trim();

    if (!normalizedInput) {
        return { ok: true, lines: [] };
    }

    const rawLines = normalizedInput
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
    const answerLines = [];

    for (let index = 0; index < rawLines.length; index += 1) {
        const rawLine = rawLines[index];
        const separatorIndex = rawLine.indexOf('::');

        if (separatorIndex === -1) {
            return {
                ok: false,
                error: `${index + 1}번째 정답 줄은 "논리식 :: 정당화" 형식이어야 합니다.`,
            };
        }

        const formulaPart = rawLine.slice(0, separatorIndex).trim();
        const rulePart = rawLine.slice(separatorIndex + 2).trim();

        if (!formulaPart) {
            return {
                ok: false,
                error: `${index + 1}번째 정답 줄에는 논리식이 필요합니다.`,
            };
        }

        const parsedRule = parseRuleCommand(rulePart);
        const rule = parsedRule.ok ? parsedRule.rule : rulePart;
        const refs = parsedRule.ok ? parsedRule.refs : '';
        const parsedFormula = parseProofFormulaInput(formulaPart);
        const syntax = validatePropositionalFormula(parsedFormula.formula);

        if (!syntax.ok) {
            return {
                ok: false,
                error: `${index + 1}번째 정답 줄의 논리식이 WFF가 아닙니다. ${syntax.error}`,
            };
        }

        answerLines.push({
            rawFormula: formulaPart,
            rule,
            refs,
        });
    }

    return {
        ok: true,
        lines: answerLines,
    };
}

function applyParsedAnswer(parsedAnswer) {
    parsedAnswer.lines.forEach((answerLine) => {
        proofLines.push(buildProofLine(
            proofLines.length + 1,
            answerLine.rawFormula,
            answerLine.rule,
            answerLine.refs,
        ));
    });

    nextLineNumber = proofLines.length + 1;
}

function getPredicateArityContextFormulas(options = {}) {
    const excludeLineNumber = options.excludeLineNumber ?? null;
    const candidateFormula = options.candidateFormula ?? '';
    const formulas = [];

    if (targetConclusion) {
        formulas.push(...(targetConclusion.premises || []), targetConclusion.formula);
    }

    proofLines.forEach((proofLine) => {
        if (proofLine.lineNumber === excludeLineNumber) {
            return;
        }

        formulas.push(proofLine.formula);
    });

    if (candidateFormula) {
        formulas.push(candidateFormula);
    }

    return formulas;
}

function validateProofLinePredicateArity(rawFormula, options = {}) {
    const parsedFormula = parseProofFormulaInput(rawFormula);
    const syntax = validatePropositionalFormula(parsedFormula.formula);

    if (!syntax.ok) {
        return {
            ok: true,
            formula: parsedFormula.formula,
        };
    }

    const arityConsistency = validatePredicateArityConsistency(
        getPredicateArityContextFormulas({
            excludeLineNumber: options.excludeLineNumber ?? null,
            candidateFormula: parsedFormula.formula,
        }),
    );

    return {
        ...arityConsistency,
        formula: parsedFormula.formula,
    };
}

function loadProblemFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const rawProblem = params.get('');
    const rawAnswer = params.get('answer');

    if (!rawProblem) {
        return false;
    }

    const parsedProblem = parseProblemInput(rawProblem);

    if (!parsedProblem.ok) {
        targetConclusionError = parsedProblem.error;
        isTargetEditorOpen = true;
        return false;
    }

    applyParsedProblem(parsedProblem);

    if (rawAnswer) {
        const parsedAnswer = parseAnswerInput(rawAnswer);

        if (parsedAnswer.ok) {
            applyParsedAnswer(parsedAnswer);
        } else {
            showFormulaWarning(`정답 예제를 읽을 수 없습니다. ${parsedAnswer.error}`);
        }
    }

    return true;
}

function updateExampleUrl(problem, answer) {
    const params = new URLSearchParams();
    params.set('', problem);

    if (answer) {
        params.set('answer', answer);
    }

    window.history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
}

function updateRuleExampleUrl(exampleId) {
    const params = new URLSearchParams();
    params.set('example', String(exampleId));
    window.history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
}

function loadRuleExample(problem, answer, options = {}) {
    const parsedProblem = parseProblemInput(problem);

    if (!parsedProblem.ok) {
        window.alert(`예제 문제를 읽을 수 없습니다. ${parsedProblem.error}`);
        return false;
    }

    const parsedAnswer = answer
        ? parseAnswerInput(answer)
        : { ok: true, lines: [] };

    if (!parsedAnswer.ok) {
        window.alert(`예제 정답을 읽을 수 없습니다. ${parsedAnswer.error}`);
        return false;
    }

    applyParsedProblem(parsedProblem);
    applyParsedAnswer(parsedAnswer);
    loadedExampleTitle = options.title || '';

    if (options.updateUrl !== false) {
        updateExampleUrl(problem, answer);
    }

    renderProofLines();
    updateActiveLineMarker();
    updateEntryState();
    return true;
}


function getRuleCardName(ruleCard) {
    const heading = ruleCard.querySelector('h3');

    if (!heading) {
        return '';
    }

    const alias = heading.querySelector('.rule-alias')?.textContent || '';
    return heading.textContent.replace(alias, '').trim();
}

const ruleExampleApiUrl = new URL('api/rule-examples.php', document.baseURI);
const ruleExampleCache = new Map();
const guideExampleApiUrl = new URL('api/guide-examples.php', document.baseURI);
const exerciseApiUrl = new URL('api/exercises.php', document.baseURI);
const exerciseCache = new Map();

async function requestRuleExampleData(exampleId = null) {
    const requestUrl = new URL(ruleExampleApiUrl);

    if (exampleId !== null) {
        requestUrl.searchParams.set('id', String(exampleId));
    }

    const response = await fetch(requestUrl, {
        headers: {
            Accept: 'application/json',
        },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.error || '예제 데이터를 불러오지 못했습니다.');
    }

    return payload;
}

async function loadRuleExampleById(exampleId, options = {}) {
    let example = ruleExampleCache.get(exampleId);

    if (!example) {
        example = await requestRuleExampleData(exampleId);
        ruleExampleCache.set(exampleId, example);
    }

    const didLoad = loadRuleExample(example.problem, example.answer, {
        title: [
            example.category,
            example.section,
            example.title,
        ].filter(Boolean).join(' / '),
        updateUrl: false,
    });

    if (didLoad && options.updateUrl !== false) {
        updateRuleExampleUrl(exampleId);
    }

    return didLoad;
}

async function loadRuleExampleFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const rawExampleId = params.get('example');

    if (rawExampleId === null) {
        return false;
    }

    const exampleId = Number(rawExampleId);

    if (!Number.isInteger(exampleId) || exampleId < 1) {
        window.alert('올바른 example id가 필요합니다.');
        return false;
    }

    try {
        return await loadRuleExampleById(exampleId, {
            updateUrl: false,
        });
    } catch (error) {
        console.error(error);
        window.alert(error.message || '예제를 불러오지 못했습니다.');
        return false;
    }
}

async function requestGuideExampleData(exampleId = null) {
    const requestUrl = new URL(guideExampleApiUrl);

    if (exampleId !== null) {
        requestUrl.searchParams.set('id', String(exampleId));
    }

    const response = await fetch(requestUrl, {
        headers: {
            Accept: 'application/json',
        },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.error || '입력 가이드 예제를 불러오지 못했습니다.');
    }

    return payload;
}

async function requestExerciseData(exerciseId) {
    const requestUrl = new URL(exerciseApiUrl);
    requestUrl.searchParams.set('id', String(exerciseId));

    const response = await fetch(requestUrl, {
        headers: {
            Accept: 'application/json',
        },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.error || '연습문제를 불러오지 못했습니다.');
    }

    return payload;
}

async function loadExerciseById(exerciseId) {
    let exercise = exerciseCache.get(exerciseId);

    if (!exercise) {
        exercise = await requestExerciseData(exerciseId);
        exerciseCache.set(exerciseId, exercise);
    }

    return loadRuleExample(exercise.problem, '', {
        title: [
            exercise.category,
            exercise.section,
            exercise.title,
        ].filter(Boolean).join(' / '),
        updateUrl: false,
    });
}

async function loadExerciseFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const rawExerciseId = params.get('problem');

    if (rawExerciseId === null) {
        return false;
    }

    const exerciseId = Number(rawExerciseId);

    if (!Number.isInteger(exerciseId) || exerciseId < 1) {
        window.alert('올바른 problem id가 필요합니다.');
        return false;
    }

    try {
        return await loadExerciseById(exerciseId);
    } catch (error) {
        console.error(error);
        window.alert(error.message || '연습문제를 불러오지 못했습니다.');
        return false;
    }
}

async function setupGuideExamples() {
    const guide = document.querySelector('.input-guide');

    if (!guide) {
        return;
    }

    let payload;

    try {
        payload = await requestGuideExampleData();
    } catch (error) {
        console.error(error);
        return;
    }

    const examplesByKey = new Map(
        payload.examples.map((example) => [example.key, example]),
    );

    guide.querySelectorAll('[data-guide-key]').forEach((trigger) => {
        const example = examplesByKey.get(trigger.dataset.guideKey);

        if (!example) {
            return;
        }

        trigger.dataset.guideExampleId = String(example.id);

        if (trigger.matches('a')) {
            trigger.href = `${window.location.pathname}?example=${example.id}`;
        }
    });
}

async function activateGuideExample(trigger) {
    const exampleId = Number(trigger.dataset.guideExampleId);

    if (!Number.isInteger(exampleId) || exampleId < 1 || trigger.dataset.exampleLoading === 'true') {
        return;
    }

    trigger.dataset.exampleLoading = 'true';
    trigger.setAttribute('aria-busy', 'true');

    try {
        const didLoad = await loadRuleExampleById(exampleId);

        if (didLoad) {
            document.querySelector('#proof-editor')?.scrollIntoView({ block: 'start' });
        }
    } catch (error) {
        console.error(error);
        window.alert(error.message || '입력 가이드 예제를 불러오지 못했습니다.');
    } finally {
        delete trigger.dataset.exampleLoading;
        trigger.removeAttribute('aria-busy');
    }
}

async function setupRuleSchemeExamples() {
    const rulesPanels = document.querySelectorAll('.rules-panel');

    if (rulesPanels.length === 0) {
        return;
    }

    let payload;

    try {
        payload = await requestRuleExampleData();
    } catch (error) {
        console.error(error);
        return;
    }

    const examplesByRule = new Map();

    payload.examples.forEach((example) => {
        if (!examplesByRule.has(example.rule)) {
            examplesByRule.set(example.rule, new Map());
        }

        examplesByRule.get(example.rule).set(example.variantIndex, example);
    });

    rulesPanels.forEach((rulesPanel) => {
        rulesPanel.querySelectorAll('.rule-card').forEach((ruleCard) => {
            const ruleName = getRuleCardName(ruleCard);
            const examples = examplesByRule.get(ruleName);

            if (!examples) {
                return;
            }

            ruleCard.querySelectorAll('.rule-scheme').forEach((scheme, index) => {
                const example = examples.get(index);

                if (!example) {
                    return;
                }

                scheme.classList.add('is-example-link');
                scheme.tabIndex = 0;
                scheme.setAttribute('role', 'button');
                scheme.setAttribute('aria-label', `${ruleName} 예제 불러오기`);
                scheme.dataset.exampleId = String(example.id);
            });
        });
    });
}

async function activateRuleSchemeExample(scheme) {
    const exampleId = Number(scheme.dataset.exampleId);

    if (!Number.isInteger(exampleId) || exampleId < 1 || scheme.dataset.exampleLoading === 'true') {
        return;
    }

    scheme.dataset.exampleLoading = 'true';
    scheme.setAttribute('aria-busy', 'true');

    try {
        const didLoad = await loadRuleExampleById(exampleId);

        if (didLoad) {
            document.querySelector('#proof-editor')?.scrollIntoView({ block: 'start' });
        }
    } catch (error) {
        console.error(error);
        window.alert(error.message || '예제를 불러오지 못했습니다.');
    } finally {
        delete scheme.dataset.exampleLoading;
        scheme.removeAttribute('aria-busy');
    }
}

function parseProofFormulaInput(rawFormula) {
    const trimmed = rawFormula.trim();
    const hasSubproofEndMarker = trimmed.endsWith('/');
    const formulaSource = hasSubproofEndMarker
        ? trimmed.slice(0, -1).trim()
        : trimmed;
    const arbitraryNamePrefix = parseArbitraryNamePrefix(formulaSource);

    return {
        formula: normalizeFormula(arbitraryNamePrefix.formulaSource),
        arbitraryNames: arbitraryNamePrefix.arbitraryNames,
        arbitraryName: arbitraryNamePrefix.arbitraryName,
        hasSubproofEndMarker,
    };
}

function formatProofFormulaForEditing(proofLine) {
    const arbitraryNamePrefix = getProofLineArbitraryNames(proofLine)
        .map((arbitraryName) => `[${arbitraryName}]`)
        .join(' ');
    const formula = arbitraryNamePrefix
        ? `${arbitraryNamePrefix} ${proofLine.formula}`
        : proofLine.formula;

    return proofLine.hasSubproofEndMarker ? `${formula} /` : formula;
}

function buildProofLine(lineNumber, rawFormula, rule, refs, options = {}) {
    const parsedFormula = parseProofFormulaInput(rawFormula);
    const formula = parsedFormula.formula;
    const normalizedRefs = prepareReferencesForRule(rule, refs, options.warnOnDroppedRefs);
    const syntax = validatePropositionalFormula(formula);

    return {
        lineNumber,
        formula,
        arbitraryNames: parsedFormula.arbitraryNames,
        arbitraryName: parsedFormula.arbitraryName,
        rule,
        refs: normalizedRefs,
        hasSubproofEndMarker: parsedFormula.hasSubproofEndMarker,
        depth: 0,
        subproofPath: '',
        opensSubproof: false,
        closesSubproof: false,
        endsSubproof: false,
        openedSubproofId: null,
        closedSubproofId: null,
        closedAssumptionLine: null,
        openedSubproofIds: [],
        openedArbitraryNameSubproofId: null,
        openedArbitraryNameSubproofs: [],
        arbitraryNameSubproofPath: '',
        arbitraryNameDepth: 0,
        parentArbitraryNames: [],
        subproofGuideEntries: [],
        contentIndent: 0,
        currentSubproofLeft: SUBPROOF_GUIDE_LEFT_START,
        openedAssumptionSubproofLeft: null,
        isWff: syntax.ok,
        isSentence: syntax.isSentence,
        freeVariables: syntax.freeVariables,
        syntaxError: syntax.error,
    };
}

function renumberProofLinesAfterDelete(deletedLineNumber) {
    const lineNumberMap = new Map();
    let nextNumber = 1;

    proofLines.forEach((proofLine) => {
        if (proofLine.lineNumber !== deletedLineNumber) {
            lineNumberMap.set(proofLine.lineNumber, nextNumber);
            nextNumber += 1;
        }
    });

    const remainingLines = proofLines
        .filter((proofLine) => proofLine.lineNumber !== deletedLineNumber)
        .map((proofLine) => ({
            ...proofLine,
            lineNumber: lineNumberMap.get(proofLine.lineNumber),
            refs: remapReferences(proofLine.refs, lineNumberMap),
        }));

    proofLines.length = 0;
    proofLines.push(...remainingLines);
    nextLineNumber = proofLines.length + 1;
}

function resetEditorForNewLine() {
    editingLineNumber = null;
    isEditingConclusion = false;
    isEditorVisible = true;
    formulaInput.value = '';
    clearFormulaWarning();
    updateRuleSelection('');
    completeButton.textContent = '✔︎';
    deleteButton.textContent = '✖︎';
    deleteButton.hidden = true;
    renderProofLines();
    formulaInput.focus();
    updateEntryState();
}

function hasActiveProofDraft() {
    return formulaInput.value.trim().length > 0
        || ruleTextInput.value.trim().length > 0;
}

function clearActiveProofDraft() {
    if (!hasActiveProofDraft()) {
        return;
    }

    formulaInput.value = '';
    clearFormulaWarning();
    updateRuleSelection('');
    updateEntryState();
}

function cancelTargetEditing() {
    if (!isTargetEditorOpen) {
        return;
    }

    isTargetEditorOpen = false;
    targetConclusionError = '';
    renderProofLines();
}

function cancelInlineProofEditing() {
    if (editingLineNumber === null) {
        return;
    }

    editingLineNumber = null;
    clearFormulaWarning();
    renderProofLines();
    updateActiveLineMarker();
}

function activateNewLineInput() {
    if (editingLineNumber === null) {
        return;
    }

    editingLineNumber = null;
    clearFormulaWarning();
    renderProofLines();
    updateActiveLineMarker();
}

function startEditingConclusion() {
    isEditingConclusion = false;
    editingLineNumber = null;
    isTargetEditorOpen = true;
    targetConclusionError = '';
    renderProofLines();

    const input = document.querySelector('#targetConclusionInput');

    if (input) {
        input.focus();
        input.selectionStart = input.value.length;
        input.selectionEnd = input.value.length;
    }
}

function completeTargetConclusion() {
    const input = document.querySelector('#targetConclusionInput');
    const rawProblem = input?.value.trim() || '';

    if (!rawProblem) {
        input?.focus();
        return;
    }

    const parsedProblem = parseProblemInput(rawProblem);

    if (!parsedProblem.ok) {
        targetConclusionError = parsedProblem.error;
        renderProofLines();

        const nextInput = document.querySelector('#targetConclusionInput');

        if (nextInput) {
            nextInput.value = parsedProblem.normalizedInput || rawProblem;
            nextInput.focus();
            nextInput.selectionStart = nextInput.value.length;
            nextInput.selectionEnd = nextInput.value.length;
        }

        return;
    }

    applyParsedProblem(parsedProblem);
    renderProofLines();
    updateActiveLineMarker();
    focusFormulaInput();
}

function startEditingLine(lineNumber, focusTarget = 'formula') {
    const proofLine = proofLines.find((line) => line.lineNumber === lineNumber);

    if (!proofLine) {
        return;
    }

    isEditingConclusion = false;
    isTargetEditorOpen = false;
    targetConclusionError = '';
    editingLineNumber = lineNumber;
    clearFormulaWarning();
    renderProofLines();

    const input = focusTarget === 'justification'
        ? document.querySelector('.inline-rule-command-input')
        : document.querySelector('.inline-formula-input');

    if (input) {
        moveCaretToEnd(input);
    }
}

function switchEditingLine(lineNumber, focusTarget = 'formula') {
    if (editingLineNumber !== null && editingLineNumber !== lineNumber) {
        editingLineNumber = null;
        clearFormulaWarning();
    }

    startEditingLine(lineNumber, focusTarget);
}

function completeInlineProofLine(editor) {
    const lineNumber = Number(editor.dataset.lineNumber);
    const formulaControl = editor.querySelector('[data-inline-formula]');
    const rawFormula = getEditableText(formulaControl).trim();
    const ruleCommandInput = editor.querySelector('[data-inline-rule-command]');

    if (!rawFormula) {
        formulaControl?.focus();
        return;
    }

    const parsedRuleCommand = applyRuleCommandInput(
        ruleCommandInput,
        (message) => showInlineFormulaWarning(editor, message),
    );

    if (!parsedRuleCommand) {
        return;
    }

    const { rule, refs } = parsedRuleCommand;

    if (shouldBlockPremiseAtLine(rule, lineNumber)) {
        showInlineFormulaWarning(editor, '더이상 전제를 추가할 수 없습니다.');
        ruleCommandInput?.focus();
        return;
    }

    const arityCheck = validateProofLinePredicateArity(rawFormula, {
        excludeLineNumber: lineNumber,
    });

    if (!arityCheck.ok) {
        formulaControl.textContent = arityCheck.formula;
        showInlineFormulaWarning(editor, arityCheck.error);
        moveCaretToEnd(formulaControl);
        return;
    }

    const index = proofLines.findIndex((line) => line.lineNumber === lineNumber);

    if (index !== -1) {
        proofLines[index] = buildProofLine(
            lineNumber,
            rawFormula,
            rule,
            refs,
            { warnOnDroppedRefs: true },
        );
    }

    resetEditorForNewLine();
}

function completeCurrentLine() {
    activateNewLineInput();

    const rawFormula = formulaInput.value.trim();

    if (!rawFormula) {
        formulaInput.focus();
        return;
    }

    const parsedRuleCommand = applyRuleCommandInput(ruleTextInput, showFormulaWarning);

    if (!parsedRuleCommand) {
        return;
    }

    const { rule, refs } = parsedRuleCommand;

    if (shouldBlockPremiseAtLine(rule, editingLineNumber ?? nextLineNumber)) {
        showFormulaWarning('더이상 전제를 추가할 수 없습니다.');
        ruleTextInput.focus();
        return;
    }

    if (isFixedWffOnlyInputMode() && warnInvalidFixedFormula(rawFormula)) {
        return;
    }

    if (isEditingConclusion) {
        const arityCheck = validateProofLinePredicateArity(rawFormula);

        if (!arityCheck.ok) {
            showFormulaWarning(arityCheck.error);
            formulaInput.value = arityCheck.formula;
            formulaInput.focus();
            formulaInput.selectionStart = formulaInput.value.length;
            formulaInput.selectionEnd = formulaInput.value.length;
            return;
        }

        targetConclusion = buildTargetConclusion(rawFormula, []);
        resetEditorForNewLine();
        return;
    }

    if (editingLineNumber !== null) {
        const index = proofLines.findIndex((line) => line.lineNumber === editingLineNumber);

        if (index !== -1) {
            const arityCheck = validateProofLinePredicateArity(rawFormula, {
                excludeLineNumber: editingLineNumber,
            });

            if (!arityCheck.ok) {
                showFormulaWarning(arityCheck.error);
                formulaInput.value = arityCheck.formula;
                formulaInput.focus();
                formulaInput.selectionStart = formulaInput.value.length;
                formulaInput.selectionEnd = formulaInput.value.length;
                return;
            }

            proofLines[index] = buildProofLine(
                editingLineNumber,
                rawFormula,
                rule,
                refs,
                { warnOnDroppedRefs: true },
            );
        }

        resetEditorForNewLine();
        return;
    }

    const arityCheck = validateProofLinePredicateArity(rawFormula);

    if (!arityCheck.ok) {
        showFormulaWarning(arityCheck.error);
        formulaInput.value = arityCheck.formula;
        formulaInput.focus();
        formulaInput.selectionStart = formulaInput.value.length;
        formulaInput.selectionEnd = formulaInput.value.length;
        return;
    }

    proofLines.push(buildProofLine(
        nextLineNumber,
        rawFormula,
        rule,
        refs,
        { warnOnDroppedRefs: true },
    ));
    nextLineNumber += 1;
    resetEditorForNewLine();
}

function deleteCurrentLine() {
    if (isEditingConclusion) {
        if (!targetConclusion) {
            resetEditorForNewLine();
            return;
        }

        targetConclusion = null;
        resetEditorForNewLine();
        return;
    }

    if (editingLineNumber === null) {
        return;
    }

    renumberProofLinesAfterDelete(editingLineNumber);
    resetEditorForNewLine();
}

function deleteProofLine(lineNumber) {
    if (!Number.isInteger(lineNumber)) {
        return;
    }

    swipedLineNumber = null;
    renumberProofLinesAfterDelete(lineNumber);
    resetEditorForNewLine();
}

function isSwipeDeleteEnabled() {
    return window.matchMedia('(max-width: 699px)').matches;
}

function closeSwipedLine() {
    swipedLineNumber = null;
    proofList
        .querySelectorAll('.saved-line.is-swiped')
        .forEach((line) => line.classList.remove('is-swiped'));
}

function openSwipedLine(line) {
    if (!line) {
        return;
    }

    closeSwipedLine();
    swipedLineNumber = Number(line.dataset.lineNumber);
    line.classList.add('is-swiped');
    line.dataset.swipeClickGuard = 'true';
}

function completeOnEnter(event, allowShiftNewline = false) {
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }

    if (allowShiftNewline && event.shiftKey) {
        return;
    }

    event.preventDefault();
    completeCurrentLine();
}

completeButton.addEventListener('click', completeCurrentLine);
deleteButton.addEventListener('click', deleteCurrentLine);

editorLine.addEventListener('pointerdown', (event) => {
    if (event.target.closest('#formulaInput, #ruleTextInput, #completeButton')) {
        activateNewLineInput();
    }
});

proofList.addEventListener('pointerdown', (event) => {
    if (!isSwipeDeleteEnabled() || event.pointerType === 'mouse') {
        return;
    }

    const line = event.target.closest('.saved-line:not(.inline-editor-line)');

    if (
        !line ||
        event.target.closest('button, input, textarea, [contenteditable="true"], .target-conclusion')
    ) {
        return;
    }

    swipeGesture = {
        line,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        isHorizontal: false,
    };
});

proofList.addEventListener('pointermove', (event) => {
    if (!swipeGesture || event.pointerId !== swipeGesture.pointerId) {
        return;
    }

    const deltaX = event.clientX - swipeGesture.startX;
    const deltaY = event.clientY - swipeGesture.startY;

    if (!swipeGesture.isHorizontal) {
        swipeGesture.isHorizontal = Math.abs(deltaX) > 12 && Math.abs(deltaX) > Math.abs(deltaY) * 1.25;
    }

    if (swipeGesture.isHorizontal) {
        event.preventDefault();
    }
});

proofList.addEventListener('pointerup', (event) => {
    if (!swipeGesture || event.pointerId !== swipeGesture.pointerId) {
        return;
    }

    const { line, startX, startY, isHorizontal } = swipeGesture;
    const deltaX = event.clientX - startX;
    const deltaY = event.clientY - startY;
    swipeGesture = null;

    if (!isHorizontal || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) {
        return;
    }

    if (deltaX <= -SWIPE_DELETE_THRESHOLD) {
        event.preventDefault();
        openSwipedLine(line);
        return;
    }

    if (deltaX >= SWIPE_DELETE_THRESHOLD / 2) {
        closeSwipedLine();
    }
});

proofList.addEventListener('pointercancel', () => {
    swipeGesture = null;
});

proofList.addEventListener('click', (event) => {
    const inlineEditor = event.target.closest('.inline-editor-line');

    if (event.target.closest('.inline-complete-button')) {
        event.preventDefault();
        event.stopPropagation();
        completeInlineProofLine(inlineEditor);
        return;
    }

    const savedDeleteButton = event.target.closest('.saved-delete-button');

    if (savedDeleteButton) {
        event.preventDefault();
        event.stopPropagation();
        const line = savedDeleteButton.closest('.saved-line');
        deleteProofLine(Number(line?.dataset.lineNumber));
        return;
    }

    if (inlineEditor) {
        return;
    }

    if (event.target.closest('[data-action="clear"]')) {
        event.preventDefault();
        event.stopPropagation();
        clearProofEditor();
        return;
    }

    const swipedLine = event.target.closest('.saved-line.is-swiped');

    if (swipedLine) {
        event.preventDefault();
        event.stopPropagation();

        if (swipedLine.dataset.swipeClickGuard === 'true') {
            delete swipedLine.dataset.swipeClickGuard;
            return;
        }

        closeSwipedLine();
        return;
    }

    if (event.target.closest('.target-complete-button')) {
        event.preventDefault();
        event.stopPropagation();
        completeTargetConclusion();
        return;
    }

    if (event.target.closest('.target-input')) {
        return;
    }

    const conclusion = event.target.closest('.target-conclusion');

    if (conclusion) {
        startEditingConclusion();
        return;
    }

    if (event.target.closest('.deletable-line-number')) {
        return;
    }

    const line = event.target.closest('.saved-line');

    if (line) {
        closeSwipedLine();
        event.preventDefault();
        event.stopPropagation();
        const focusTarget = event.target.closest('.line-meta') ? 'justification' : 'formula';
        switchEditingLine(Number(line.dataset.lineNumber), focusTarget);
    }
});

proofList.addEventListener('keydown', (event) => {
    const inlineEditor = event.target.closest('.inline-editor-line');

    if (inlineEditor) {
        if (event.key === 'Enter' && !event.isComposing) {
            event.preventDefault();
            completeInlineProofLine(inlineEditor);
        }

        return;
    }

    if (event.target.closest('.target-input')) {
        if (event.key === 'Enter' && !event.isComposing) {
            event.preventDefault();
            completeTargetConclusion();
        }

        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const conclusion = event.target.closest('.target-conclusion');

    if (conclusion) {
        event.preventDefault();
        startEditingConclusion();
        return;
    }

    const line = event.target.closest('.saved-line');

    if (line) {
        event.preventDefault();
        switchEditingLine(Number(line.dataset.lineNumber));
    }
});

proofList.addEventListener('input', (event) => {
    const inlineEditor = event.target.closest('.inline-editor-line');

    if (inlineEditor) {
        clearInlineFormulaWarning(inlineEditor);
        scheduleProofColumnLayout();
    }
});

document.addEventListener('click', (event) => {
    const target = event.target;

    if (swipedLineNumber !== null && !target.closest('.saved-line')) {
        closeSwipedLine();
    }

    if (target.closest([
        '#formulaInput',
        '#ruleTextInput',
        '#completeButton',
        '#deleteButton',
        '[data-action="clear"]',
        '.target-input',
        '.target-complete-button',
        '.inline-formula-input',
        '.inline-rule-command-input',
        '.inline-complete-button',
    ].join(','))) {
        return;
    }

    if (target.closest('.target-conclusion.is-editing')) {
        cancelTargetEditing();
        return;
    }

    if (target.closest('.target-conclusion')) {
        return;
    }

    if (target.closest('.inline-editor-line')) {
        cancelInlineProofEditing();
        return;
    }

    if (target.closest('.saved-line')) {
        return;
    }

    cancelTargetEditing();
    cancelInlineProofEditing();
    clearActiveProofDraft();
});

document.querySelectorAll('.rules-panel').forEach((rulesPanel) => {
    rulesPanel.addEventListener('click', (event) => {
        const scheme = event.target.closest('.rule-scheme.is-example-link');

        if (!scheme) {
            return;
        }

        event.preventDefault();
        void activateRuleSchemeExample(scheme);
    });

    rulesPanel.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const scheme = event.target.closest('.rule-scheme.is-example-link');

        if (!scheme) {
            return;
        }

        event.preventDefault();
        void activateRuleSchemeExample(scheme);
    });
});

document.querySelector('.input-guide')?.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-guide-key]');

    if (!trigger) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    void activateGuideExample(trigger);
});

ruleTextInput.addEventListener('input', () => {
    clearFormulaWarning();
    updateEntryState();
});
ruleTextInput.addEventListener('focus', activateNewLineInput);
formulaInput.addEventListener('input', () => {
    clearFormulaWarning();
    updateEntryState();
    scheduleProofColumnLayout();
});
formulaInput.addEventListener('focus', activateNewLineInput);
formulaInput.addEventListener('keydown', (event) => completeOnEnter(event, true));
ruleTextInput.addEventListener('keydown', completeOnEnter);

if ('ResizeObserver' in window) {
    const proofLayoutObserver = new ResizeObserver(scheduleProofColumnLayout);
    proofLayoutObserver.observe(proofPanel);
}

document.fonts?.ready.then(scheduleProofColumnLayout);

void setupRuleSchemeExamples();
void setupGuideExamples();
updateRuleSelection('');
const didLoadProblemFromUrl = loadProblemFromUrl();
renderProofLines();
updateActiveLineMarker();
updateEntryState();
void loadRuleExampleFromUrl();
void loadExerciseFromUrl();

if (didLoadProblemFromUrl) {
    focusFormulaInput();
}
