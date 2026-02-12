-- Performance Indexes for Bag Doorprize Application
-- Run this script in production database after deployment
-- These indexes optimize queries for tables with millions of records

-- ============================================
-- PARTICIPANTS TABLE INDEXES
-- ============================================

-- Composite index for event_id and account_id (common filter combination)
CREATE INDEX IF NOT EXISTS participants_event_id_account_id_index 
ON participants (event_id, account_id);

-- Index on account_id for joins with accounts table
CREATE INDEX IF NOT EXISTS participants_account_id_index 
ON participants (account_id);

-- ============================================
-- LOTTERY TICKETS TABLE INDEXES
-- ============================================

-- Composite index for participant_id and status (for active points calculation)
-- This dramatically speeds up queries like: WHERE participant_id = X AND status = 'ACTIVE'
CREATE INDEX IF NOT EXISTS lottery_tickets_participant_id_status_index 
ON lottery_tickets (participant_id, status);

-- ============================================
-- EVENT_PARTICIPANT PIVOT TABLE INDEXES
-- ============================================

-- Composite index for pivot table queries
-- Speeds up queries for inactive events that use the pivot table
CREATE INDEX IF NOT EXISTS event_participant_event_id_participant_id_index 
ON event_participant (event_id, participant_id);

-- ============================================
-- ACCOUNTS TABLE INDEXES
-- ============================================

-- Index on branch_id for branch filtering
-- Used when filtering participants by user's assigned branches
CREATE INDEX IF NOT EXISTS accounts_branch_id_index 
ON accounts (branch_id);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Run these queries to verify indexes were created successfully:

-- List all indexes on participants table
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'participants' 
ORDER BY indexname;

-- List all indexes on lottery_tickets table
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'lottery_tickets' 
ORDER BY indexname;

-- List all indexes on event_participant table
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'event_participant' 
ORDER BY indexname;

-- List all indexes on accounts table
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'accounts' 
ORDER BY indexname;

-- ============================================
-- PERFORMANCE ANALYSIS (Optional)
-- ============================================

-- Analyze table statistics after creating indexes
ANALYZE participants;
ANALYZE lottery_tickets;
ANALYZE event_participant;
ANALYZE accounts;

-- Check table sizes and index usage
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS indexes_size
FROM pg_tables
WHERE tablename IN ('participants', 'lottery_tickets', 'event_participant', 'accounts')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
