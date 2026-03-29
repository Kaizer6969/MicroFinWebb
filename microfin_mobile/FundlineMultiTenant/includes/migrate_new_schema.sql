-- ============================================================
-- Migration: Add missing columns to MultiTenant schema
-- Run this AFTER importing the main MultiTenant schema SQL
-- ============================================================

USE MultiTenant;

-- Add has_seen_tour column to clients (used by dashboard.php onboarding modal)
ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS has_seen_tour BOOLEAN DEFAULT FALSE;

-- Add seen_rejection_modal column to clients (used by mark_rejection_seen.php)
ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS seen_rejection_modal BOOLEAN DEFAULT FALSE;
