CREATE DATABASE quiz_system;

USE quiz_system;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  role ENUM('Instructor', 'Student') NOT NULL
);

CREATE TABLE quiz_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL
);

-- Pre-insert some categories for filtering
INSERT INTO quiz_categories (name) VALUES ('Math'), ('Science'), ('History'), ('General'), ('Web Programming'), ('HTML'), ('CSS'), ('JS'), ('C'), ('C++');

CREATE TABLE quizzes (
  quiz_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  time_limit INT NOT NULL, -- in seconds
  is_published BOOLEAN DEFAULT FALSE,
  access_code VARCHAR(50) UNIQUE,
  category_id INT,
  created_by INT,
  FOREIGN KEY (category_id) REFERENCES quiz_categories(category_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE questions (
  question_id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT,
  text TEXT NOT NULL,
  type ENUM('MCQ', 'TrueFalse') NOT NULL,
  options JSON, -- for MCQ: {"a": "opt1", "b": "opt2", ...}, for TrueFalse: {"True": "True", "False": "False"}
  correct_answer VARCHAR(255) NOT NULL,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id)
);

CREATE TABLE leaderboards (
  entry_id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT,
  user_id INT,
  score INT NOT NULL,
  attempted INT NOT NULL DEFAULT 0,
  completion_time INT NOT NULL, -- in seconds
  FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE app_settings (
  name VARCHAR(191) PRIMARY KEY,
  value TEXT NOT NULL
);

-- Table to store in-progress quiz attempts so students can resume later
CREATE TABLE in_progress_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  user_id INT NOT NULL,
  access_code VARCHAR(50) DEFAULT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  time_left INT DEFAULT NULL,
  answers JSON DEFAULT NULL,
  UNIQUE KEY uq_quiz_user (quiz_id, user_id),
  FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert Teachers
INSERT INTO users (email, password_hash, name, role) VALUES
('ntn@ewubd.edu', '#Pass123', 'ntn', 'Instructor'),
('tba@ewubd.edu', '#Pass123', 'tba', 'Instructor');

-- Insert Students
INSERT INTO users (email, password_hash, name, role) VALUES
('sheikh@ewubd.edu', '#Pass1234', 'sheikh', 'Student'),
('ohin@ewubd.edu', '#Pass1234', 'ohin', 'Student'),
('salman@ewubd.edu', '#Pass1234', 'salman', 'Student');

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_read_created (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);