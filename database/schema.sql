CREATE DATABASE IF NOT EXISTS mufaddhol_portfolio
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mufaddhol_portfolio;

-- 1. Administrative Users
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Main Portfolio Entries (Front Grid & Core Metadata)
CREATE TABLE portfolios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category ENUM(
    'Graphic Design',
    'Video Editing',
    'Presentation Design',
    'Software Development'
  ) NOT NULL,
  title VARCHAR(150) NOT NULL,
  media_mode ENUM(
    'image_only',
    'hover_video',
    'autoplay_video'
  ) NOT NULL DEFAULT 'image_only',
  main_image VARCHAR(255) NOT NULL COMMENT 'Required cover image or video poster thumbnail',
  main_video VARCHAR(255) NULL COMMENT 'Local preview/video file when media_mode is hover_video or autoplay_video',
  video_url VARCHAR(255) NULL COMMENT 'Optional external video URL (YouTube, Vimeo, Reels, TikTok, Drive) shown on detail page',
  aspect_ratio DECIMAL(6,4) NOT NULL DEFAULT 1.0000 COMMENT 'Computed height / width of main_image',
  description TEXT NULL,
  tech_tags VARCHAR(255) NULL COMMENT 'Comma-separated tags for Software Development, e.g. React, PostgreSQL, Next.js',
  demo_url VARCHAR(255) NULL COMMENT 'Optional live deployment URL',
  github_url VARCHAR(255) NULL COMMENT 'Optional GitHub repository URL',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_sort_created (sort_order ASC, created_at DESC, id DESC)
) ENGINE=InnoDB;

-- 3. Portfolio Detail Attachments (Variants, Brand Guidelines, App Screenshots)
CREATE TABLE portfolio_details (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  portfolio_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
  caption VARCHAR(150) NULL COMMENT 'Optional variant label e.g. Ratio 16:9, Brand Guideline, Dashboard UI',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_portfolio_sort (portfolio_id, sort_order ASC),
  CONSTRAINT fk_detail_portfolio
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;