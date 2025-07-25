-- Nukta League Platform - InfinityFree Database Setup
-- Run this script in your InfinityFree phpMyAdmin

-- Create the enhanced match_results table structure
CREATE TABLE IF NOT EXISTS leagues (
  league_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  abbreviation VARCHAR(10) NOT NULL,
  logo_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100),
  role ENUM('admin', 'manager', 'referee') NOT NULL,
  league_id INT,
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

CREATE TABLE IF NOT EXISTS teams (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  league_id INT,
  name VARCHAR(100) NOT NULL,
  coach_name VARCHAR(100),
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

CREATE TABLE IF NOT EXISTS players (
  player_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT,
  name VARCHAR(100) NOT NULL,
  position VARCHAR(50),
  FOREIGN KEY (team_id) REFERENCES teams(team_id)
);

CREATE TABLE IF NOT EXISTS fixtures (
  fixture_id INT AUTO_INCREMENT PRIMARY KEY,
  league_id INT,
  home_team INT,
  away_team INT,
  match_date DATE,
  match_time TIME,
  venue VARCHAR(100),
  status ENUM('upcoming', 'played') DEFAULT 'upcoming',
  FOREIGN KEY (league_id) REFERENCES leagues(league_id),
  FOREIGN KEY (home_team) REFERENCES teams(team_id),
  FOREIGN KEY (away_team) REFERENCES teams(team_id)
);

-- Enhanced match_results table (main table for scoring)
CREATE TABLE IF NOT EXISTS match_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  fixture_id INT NOT NULL,
  score_home INT NOT NULL,
  score_away INT NOT NULL,
  submitted_by INT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  cancelled_by_referee TINYINT(1) DEFAULT 0,
  cancelled_at TIMESTAMP NULL DEFAULT NULL,
  cancelled_reason TEXT,
  UNIQUE KEY unique_fixture (fixture_id),
  FOREIGN KEY (fixture_id) REFERENCES fixtures(fixture_id),
  FOREIGN KEY (submitted_by) REFERENCES users(user_id)
);

-- Additional tables
CREATE TABLE IF NOT EXISTS referee_assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  league_id INT,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

CREATE TABLE IF NOT EXISTS standings (
  standing_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT,
  league_id INT,
  games_played INT DEFAULT 0,
  wins INT DEFAULT 0,
  draws INT DEFAULT 0,
  losses INT DEFAULT 0,
  goals_for INT DEFAULT 0,
  goals_against INT DEFAULT 0,
  points INT DEFAULT 0,
  FOREIGN KEY (team_id) REFERENCES teams(team_id),
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

-- Insert sample data
INSERT INTO leagues (name, abbreviation) VALUES
('NCL Volt Cup', 'NCL'),
('Women Empowerment League', 'WEL');

-- Insert admin user (password: admin123)
INSERT INTO users (username, password, name, role, league_id) VALUES
('admin', SHA2('admin123', 256), 'Super Admin', 'admin', 1);

-- Insert sample referee (password: referee123)
INSERT INTO users (username, password, name, role, league_id) VALUES
('referee', SHA2('referee123', 256), 'Test Referee', 'referee', 1);

-- Sample teams for testing
INSERT INTO teams (league_id, name, coach_name) VALUES
(1, 'Test Team A', 'Coach A'),
(1, 'Test Team B', 'Coach B'),
(2, 'Test Team C', 'Coach C'),
(2, 'Test Team D', 'Coach D');

-- Referee assignments
INSERT INTO referee_assignments (user_id, league_id) VALUES
(2, 1), (2, 2);
