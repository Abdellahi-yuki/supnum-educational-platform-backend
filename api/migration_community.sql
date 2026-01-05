-- Migration script for community enhancements
-- Run this script to add missing columns to existing tables

-- Add profile_path to users table if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_path VARCHAR(255) DEFAULT NULL;

-- Add reply_to_id to community_messages table if it doesn't exist
ALTER TABLE community_messages 
ADD COLUMN IF NOT EXISTS reply_to_id INT DEFAULT NULL,
ADD CONSTRAINT fk_reply_to FOREIGN KEY (reply_to_id) REFERENCES community_messages(id) ON DELETE SET NULL;

-- Verify the changes
SELECT 'Migration completed successfully' as status;
