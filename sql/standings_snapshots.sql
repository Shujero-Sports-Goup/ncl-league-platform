-- Add standings snapshots table for weekly archiving
CREATE TABLE IF NOT EXISTS `standings_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `league_id` (`league_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX idx_league_created ON standings_snapshots(league_id, created_at);
