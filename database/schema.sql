CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS problems (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    problem_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_problems_problem_lookup (problem_text(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS problem_examples (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    problem_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    category_title VARCHAR(255) NOT NULL DEFAULT '',
    section_title VARCHAR(255) NOT NULL DEFAULT '',
    example_kind VARCHAR(32) NOT NULL DEFAULT 'rule',
    guide_key VARCHAR(191) DEFAULT NULL,
    rule_key VARCHAR(191) NOT NULL,
    variant_index INT NOT NULL,
    answer_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_problem_examples_rule_variant (rule_key, variant_index),
    UNIQUE KEY uq_problem_examples_guide_key (guide_key),
    INDEX idx_problem_examples_problem_id (problem_id),
    INDEX idx_problem_examples_example_kind (example_kind),
    CONSTRAINT fk_problem_examples_problem
        FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exercise_categories_title (title),
    INDEX idx_exercise_categories_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_sections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exercise_sections_category_title (category_id, title),
    UNIQUE KEY uq_exercise_sections_category_sort_order (category_id, sort_order),
    INDEX idx_exercise_sections_category_id (category_id),
    CONSTRAINT fk_exercise_sections_category
        FOREIGN KEY (category_id) REFERENCES exercise_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    problem_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exercise_entries_section_title (section_id, title),
    UNIQUE KEY uq_exercise_entries_section_sort_order (section_id, sort_order),
    INDEX idx_exercise_entries_problem_id (problem_id),
    INDEX idx_exercise_entries_section_id (section_id),
    CONSTRAINT fk_exercise_entries_problem
        FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    CONSTRAINT fk_exercise_entries_section
        FOREIGN KEY (section_id) REFERENCES exercise_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
