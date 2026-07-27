PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS problems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    problem_text TEXT NOT NULL,
    logic_type TEXT NOT NULL DEFAULT 'propositional',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS problem_examples (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    category_title TEXT NOT NULL DEFAULT '',
    section_title TEXT NOT NULL DEFAULT '',
    example_kind TEXT NOT NULL DEFAULT 'rule',
    guide_key TEXT DEFAULT NULL,
    rule_key TEXT NOT NULL,
    variant_index INTEGER NOT NULL,
    answer_text TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (rule_key, variant_index),
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_problem_examples_problem_id
    ON problem_examples(problem_id);

CREATE TABLE IF NOT EXISTS exercise_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL UNIQUE,
    logic_type TEXT NOT NULL,
    sort_order INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS exercise_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    UNIQUE (category_id, title),
    FOREIGN KEY (category_id) REFERENCES exercise_categories(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_exercise_sections_category_id
    ON exercise_sections(category_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_exercise_sections_category_sort_order
    ON exercise_sections(category_id, sort_order);

CREATE TABLE IF NOT EXISTS exercise_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL,
    section_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    UNIQUE (section_id, title),
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES exercise_sections(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_exercise_entries_problem_id
    ON exercise_entries(problem_id);

CREATE INDEX IF NOT EXISTS idx_exercise_entries_section_id
    ON exercise_entries(section_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_exercise_entries_section_sort_order
    ON exercise_entries(section_id, sort_order);
