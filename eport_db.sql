
CREATE DATABASE IF NOT EXISTS eport_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE eport_db;

CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id       VARCHAR(30)  NOT NULL UNIQUE,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin','coordinator','dept_head','faculty','student') NOT NULL DEFAULT 'student',
    department_id   INT UNSIGNED NULL,
    year_level      VARCHAR(10)  NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE academic_years (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(20)  NOT NULL UNIQUE,
    is_active   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE semesters (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT UNSIGNED NOT NULL,
    name             ENUM('1st Semester','2nd Semester','Summer') NOT NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 0,
    is_locked        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sem_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE curriculum (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   INT UNSIGNED NOT NULL,
    subject_code    VARCHAR(20)  NOT NULL,
    subject_name    VARCHAR(120) NOT NULL,
    course          VARCHAR(50)  NOT NULL,
    units           TINYINT UNSIGNED NOT NULL DEFAULT 3,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_curr_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subjects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id   INT UNSIGNED NULL,
    semester_id     INT UNSIGNED NULL,
    faculty_id      INT UNSIGNED NOT NULL,
    coordinator_id  INT UNSIGNED NULL,
    code            VARCHAR(20)  NOT NULL,
    name            VARCHAR(120) NOT NULL,
    course          VARCHAR(50)  NOT NULL,
    join_code       VARCHAR(10)  NOT NULL UNIQUE,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    is_archived     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subject_faculty     FOREIGN KEY (faculty_id)    REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_subject_coordinator FOREIGN KEY (coordinator_id) REFERENCES users(id)     ON DELETE SET NULL,
    CONSTRAINT fk_subject_curriculum  FOREIGN KEY (curriculum_id) REFERENCES curriculum(id) ON DELETE SET NULL,
    CONSTRAINT fk_subject_semester    FOREIGN KEY (semester_id)   REFERENCES semesters(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    batch_year  VARCHAR(10)  NULL,
    is_archived TINYINT(1)   NOT NULL DEFAULT 0,
    joined_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (subject_id, student_id),
    CONSTRAINT fk_enroll_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE weeks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT UNSIGNED NOT NULL,
    week_number TINYINT UNSIGNED NOT NULL,
    title       VARCHAR(100) NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_week (subject_id, week_number),
    CONSTRAINT fk_week_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT UNSIGNED NOT NULL,
    week_id     INT UNSIGNED NULL,
    title       VARCHAR(150) NOT NULL,
    category    ENUM('Quiz','Activity','Exam','Project','Assignment') NOT NULL DEFAULT 'Activity',
    max_score   DECIMAL(6,2) NOT NULL DEFAULT 100,
    deadline    DATETIME     NULL,
    is_locked   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_week    FOREIGN KEY (week_id)    REFERENCES weeks(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE submissions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id    INT UNSIGNED NOT NULL,
    student_id     INT UNSIGNED NOT NULL,
    attempt_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status         ENUM('pending','submitted','graded') NOT NULL DEFAULT 'pending',
    overall_score  DECIMAL(6,2) NULL,
    feedback       TEXT         NULL,
    submitted_at   DATETIME     NULL,
    graded_at      DATETIME     NULL,
    CONSTRAINT fk_sub_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_student  FOREIGN KEY (student_id)  REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED  NOT NULL,
    original_name VARCHAR(255)  NOT NULL,
    stored_name   VARCHAR(255)  NOT NULL DEFAULT '',
    file_path     VARCHAR(500)  NOT NULL DEFAULT '',
    file_type     VARCHAR(10)   NOT NULL,
    file_url      VARCHAR(1000) NULL,
    is_link       TINYINT(1)    NOT NULL DEFAULT 0,
    file_size_kb  INT UNSIGNED  NOT NULL DEFAULT 0,
    file_feedback TEXT          NULL,
    uploaded_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_file_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE resubmission_log (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id    INT UNSIGNED     NOT NULL,
    student_id     INT UNSIGNED     NOT NULL,
    attempt_number TINYINT UNSIGNED NOT NULL,
    submitted_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    overall_score  DECIMAL(6,2)     NULL,
    graded_at      DATETIME         NULL,
    CONSTRAINT fk_resub_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_resub_student  FOREIGN KEY (student_id)  REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB;