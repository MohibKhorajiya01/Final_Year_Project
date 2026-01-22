-- ============================================
-- FEEDBACK TABLE FOR EVENT EASE
-- ============================================
-- This creates the feedback table for user ratings
-- Copy paste this entire file in phpMyAdmin SQL tab
-- ============================================

CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking_feedback` (`booking_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Success message
SELECT 'Feedback table created successfully! You can now use the feedback system.' AS message;
