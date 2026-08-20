<?php

namespace Kanboard\Plugin\CompanyAgile\Schema;

use PDO;

const VERSION = 3;

function version_3(PDO $pdo)
{
    $pdo->exec("CREATE TABLE company_agile_task_estimates (
        task_id INT NOT NULL,
        story_points DECIMAL(6,2) NULL,
        created_at INT NOT NULL,
        updated_at INT NOT NULL,
        updated_by INT NOT NULL,
        PRIMARY KEY (task_id),
        KEY company_agile_task_estimates_points_idx (story_points),
        CONSTRAINT company_agile_task_estimates_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_task_estimates_user_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE company_agile_issue_links (
        id BIGINT NOT NULL AUTO_INCREMENT,
        parent_task_id INT NOT NULL,
        child_task_id INT NOT NULL,
        relationship_type VARCHAR(32) NOT NULL,
        created_by INT NOT NULL,
        created_at INT NOT NULL,
        removed_at INT NULL,
        PRIMARY KEY (id),
        KEY company_agile_issue_links_parent_active_idx (parent_task_id, relationship_type, removed_at),
        KEY company_agile_issue_links_child_active_idx (child_task_id, relationship_type, removed_at),
        KEY company_agile_issue_links_history_idx (child_task_id, created_at, removed_at),
        CONSTRAINT company_agile_issue_links_parent_fk FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_issue_links_child_fk FOREIGN KEY (child_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_issue_links_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("ALTER TABLE company_agile_sprint_tasks ADD COLUMN story_points_snapshot DECIMAL(6,2) NULL AFTER completed_in_sprint");
}

function version_2(PDO $pdo)
{
    $pdo->exec("CREATE TABLE company_agile_sprints (
        id INT NOT NULL AUTO_INCREMENT,
        project_id INT NOT NULL,
        name VARCHAR(191) NOT NULL,
        goal TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'planned',
        planned_start_at INT NULL,
        planned_end_at INT NULL,
        started_at INT NULL,
        completed_at INT NULL,
        created_by INT NOT NULL,
        created_at INT NOT NULL,
        updated_at INT NOT NULL,
        PRIMARY KEY (id),
        KEY company_agile_sprints_project_status_idx (project_id, status),
        KEY company_agile_sprints_project_dates_idx (project_id, planned_start_at, planned_end_at),
        CONSTRAINT company_agile_sprints_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_sprints_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE company_agile_sprint_tasks (
        id BIGINT NOT NULL AUTO_INCREMENT,
        sprint_id INT NOT NULL,
        task_id INT NOT NULL,
        added_at INT NOT NULL,
        removed_at INT NULL,
        completed_in_sprint TINYINT(1) NULL,
        position INT NOT NULL DEFAULT 0,
        original_position INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY company_agile_sprint_tasks_sprint_current_idx (sprint_id, removed_at, position),
        KEY company_agile_sprint_tasks_task_current_idx (task_id, removed_at),
        KEY company_agile_sprint_tasks_history_idx (task_id, sprint_id, added_at),
        CONSTRAINT company_agile_sprint_tasks_sprint_fk FOREIGN KEY (sprint_id) REFERENCES company_agile_sprints(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_sprint_tasks_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function version_1(PDO $pdo)
{
    $pdo->exec("CREATE TABLE company_agile_issue_types (
        id INT NOT NULL AUTO_INCREMENT,
        code VARCHAR(32) NOT NULL,
        name VARCHAR(64) NOT NULL,
        icon VARCHAR(32) NOT NULL,
        color VARCHAR(16) NOT NULL,
        hierarchy_level INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        position INT NOT NULL DEFAULT 0,
        created_at INT NOT NULL,
        updated_at INT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY company_agile_issue_types_code_uq (code),
        KEY company_agile_issue_types_active_position_idx (is_active, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE company_agile_task_issue_types (
        task_id INT NOT NULL,
        issue_type_id INT NOT NULL,
        PRIMARY KEY (task_id),
        KEY company_agile_task_issue_types_type_idx (issue_type_id),
        CONSTRAINT company_agile_task_issue_types_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT company_agile_task_issue_types_type_fk FOREIGN KEY (issue_type_id) REFERENCES company_agile_issue_types(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $now = time();
    $statement = $pdo->prepare('INSERT INTO company_agile_issue_types (code, name, icon, color, hierarchy_level, is_active, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)');
    $types = array(
        array('task', 'Task', 'check', '#4c6ef5', 0, 1),
        array('bug', 'Bug', 'bug', '#e03131', 0, 2),
        array('story', 'Story', 'bookmark', '#2f9e44', 0, 3),
        array('epic', 'Epic', 'diamond', '#9c36b5', 1, 4),
        array('improvement', 'Improvement', 'arrow-up', '#f08c00', 0, 5),
    );

    foreach ($types as $type) {
        $statement->execute(array($type[0], $type[1], $type[2], $type[3], $type[4], $type[5], $now, $now));
    }
}
