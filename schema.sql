-- schema.sql
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

START TRANSACTION;

-- ---------------------------------------------------------------------
-- Drop tables (safe order)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- users (teachers)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name  VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- quizzes
-- ---------------------------------------------------------------------
CREATE TABLE quizzes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  subject VARCHAR(80) NOT NULL,
  time_limit_minutes SMALLINT UNSIGNED NOT NULL,
  total_questions INT UNSIGNED NOT NULL DEFAULT 0,
  total_points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  quiz_code CHAR(6) NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quizzes_quiz_code (quiz_code),
  KEY idx_quizzes_teacher (teacher_id),
  KEY idx_quizzes_status (status),
  CONSTRAINT fk_quizzes_teacher
    FOREIGN KEY (teacher_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- questions
-- ---------------------------------------------------------------------
CREATE TABLE questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id BIGINT UNSIGNED NOT NULL,
  type ENUM('mcq','identification','matching','enumeration') NOT NULL,
  prompt TEXT NOT NULL,
  points DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  choices_json LONGTEXT NULL,
  answer TEXT NULL,
  settings_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_questions_quiz (quiz_id),
  KEY idx_questions_quiz_position (quiz_id, position),
  CONSTRAINT fk_questions_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- students (no password; MVP)
-- ---------------------------------------------------------------------
CREATE TABLE students (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name  VARCHAR(80) NOT NULL,
  full_name VARCHAR(170) NOT NULL,
  name_key VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_students_name_key (name_key),
  KEY idx_students_full_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- attempts (one attempt per student per quiz)
-- Also enforces "one attempt per name per quiz" via student_name_key.
-- ---------------------------------------------------------------------
CREATE TABLE attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_uuid CHAR(36) NOT NULL,
  quiz_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  student_name_key VARCHAR(190) NOT NULL,
  total_score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  scores_json LONGTEXT NULL, -- per-type breakdown, etc.
  time_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  submitted TINYINT(1) NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  compliment VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attempts_uuid (attempt_uuid),
  UNIQUE KEY uq_attempts_one_per_student (quiz_id, student_id),
  UNIQUE KEY uq_attempts_one_per_name (quiz_id, student_name_key),
  KEY idx_attempts_quiz (quiz_id),
  KEY idx_attempts_quiz_rank (quiz_id, total_score, time_seconds),
  KEY idx_attempts_student (student_id),
  CONSTRAINT fk_attempts_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_attempts_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- attempt_answers
-- ---------------------------------------------------------------------
CREATE TABLE attempt_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  answer_text LONGTEXT NULL,
  answer_json LONGTEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  points_awarded DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attempt_answers_once (attempt_id, question_id),
  KEY idx_attempt_answers_attempt (attempt_id),
  KEY idx_attempt_answers_question (question_id),
  CONSTRAINT fk_attempt_answers_attempt
    FOREIGN KEY (attempt_id) REFERENCES attempts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_attempt_answers_question
    FOREIGN KEY (question_id) REFERENCES questions(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QUIZ SETTINGS (add to your existing quizzes table)
ALTER TABLE quizzes
  ADD COLUMN instructions TEXT NULL AFTER subject,
 
  ADD COLUMN due_at DATETIME NULL AFTER time_limit_minutes,
  ADD COLUMN shuffle_questions TINYINT(1) NOT NULL DEFAULT 0 AFTER due_at,
  ADD COLUMN allow_retake TINYINT(1) NOT NULL DEFAULT 0 AFTER shuffle_questions,
  ADD COLUMN show_score_after_submission TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_retake,
  ADD COLUMN show_correct_answers_mode ENUM('never','immediate','after_due') NOT NULL DEFAULT 'never' AFTER show_score_after_submission,
  ADD COLUMN require_login TINYINT(1) NOT NULL DEFAULT 1 AFTER show_correct_answers_mode,
  ADD COLUMN access_via_link TINYINT(1) NOT NULL DEFAULT 0 AFTER require_login,
  ADD COLUMN case_sensitive_default TINYINT(1) NOT NULL DEFAULT 0 AFTER access_via_link;

-- QUESTIONS (one row per question)
CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  type ENUM('mcq','identification','matching','enumeration','truefalse','essay') NOT NULL,
  prompt TEXT NOT NULL,
  points INT NOT NULL DEFAULT 1,
  position INT NOT NULL DEFAULT 1,
  case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (quiz_id),
  INDEX (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MCQ choices
CREATE TABLE IF NOT EXISTS question_choices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  choice_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 1,
  INDEX (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Identification answer
CREATE TABLE IF NOT EXISTS question_identification (
  question_id INT PRIMARY KEY,
  answer_text TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- True/False answer
CREATE TABLE IF NOT EXISTS question_truefalse (
  question_id INT PRIMARY KEY,
  correct_value TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Matching pairs
CREATE TABLE IF NOT EXISTS matching_pairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  left_text TEXT NOT NULL,
  right_text TEXT NOT NULL,
  position INT NOT NULL DEFAULT 1,
  INDEX (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enumeration answers (each line is an accepted answer)
CREATE TABLE IF NOT EXISTS enumeration_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  answer_text TEXT NOT NULL,
  INDEX (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Essay metadata (optional)
CREATE TABLE IF NOT EXISTS question_essay (
  question_id INT PRIMARY KEY,
  rubric TEXT NULL,
  max_words INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

