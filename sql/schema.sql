-- Reset and create database
DROP DATABASE IF EXISTS ncl_league_system;
CREATE DATABASE ncl_league_system;
USE ncl_league_system;

-- 1. Leagues Table
CREATE TABLE leagues (
  league_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  abbreviation VARCHAR(10) NOT NULL,
  logo_url VARCHAR(255)
);

-- 2. Users Table
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100),
  role ENUM('admin', 'manager', 'referee') NOT NULL,
  league_id INT,
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

-- 3. Teams Table
CREATE TABLE teams (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  league_id INT,
  name VARCHAR(100) NOT NULL,
  coach_name VARCHAR(100),
  FOREIGN KEY (league_id) REFERENCES leagues(league_id)
);

-- 4. Players Table
CREATE TABLE players (
  player_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT,
  player_name VARCHAR(100) NOT NULL,
  position VARCHAR(50),
  points_per_game DECIMAL(5,2) DEFAULT 0.00,
  FOREIGN KEY (team_id) REFERENCES teams(team_id)
);

-- 5. Fixtures Table
CREATE TABLE fixtures (
  fixture_id INT AUTO_INCREMENT PRIMARY KEY,
  league_id INT,
  home_team_id INT,
  away_team_id INT,
  match_date DATE,
  match_time TIME,
  venue VARCHAR(100),
  status ENUM('upcoming', 'played') DEFAULT 'upcoming',
  FOREIGN KEY (league_id) REFERENCES leagues(league_id),
  FOREIGN KEY (home_team_id) REFERENCES teams(team_id),
  FOREIGN KEY (away_team_id) REFERENCES teams(team_id)
);

-- 6. Enhanced Match Results Table
CREATE TABLE match_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  fixture_id INT UNIQUE NOT NULL,
  home_score INT,
  away_score INT,
  submitted_by INT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  match_date DATE,
  notes TEXT,
  cancelled_by_referee BOOLEAN DEFAULT FALSE,
  cancelled_at TIMESTAMP NULL,
  cancelled_reason TEXT,
  FOREIGN KEY (fixture_id) REFERENCES fixtures(fixture_id),
  FOREIGN KEY (submitted_by) REFERENCES users(user_id)
);

-- Optional: Seed Leagues
INSERT INTO leagues (name, abbreviation) VALUES
  ('NCL Volt Cup', 'NCL'),
  ('Women Empowerment League', 'WEL');

-- Optional: Seed Admin User (SHA2 hash of 'admin123')
INSERT INTO users (username, password, name, role, league_id)
VALUES ('admin', SHA2('admin123', 256), 'Super Admin', 'admin', 1);
