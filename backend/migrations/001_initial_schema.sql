-- Staten Academy V2 - PostgreSQL Database Schema
-- Wallet + Unified Calendar + Earnings System
-- Migration: 001_initial_schema.sql

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- =====================================================
-- WALLETS TABLE
-- Track student plan entitlements (wrapper for entitlements system)
-- =====================================================
CREATE TABLE IF NOT EXISTS wallets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id INTEGER NOT NULL,
    plan_id INTEGER,
    credits_balance DECIMAL(10,2) DEFAULT 0.00, -- Deprecated, kept for compatibility
    currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

CREATE INDEX idx_wallets_user_id ON wallets(user_id);
CREATE INDEX idx_wallets_plan_id ON wallets(plan_id);

-- =====================================================
-- ENTITLEMENTS TABLE
-- Track what students can access (PRIMARY system - not credits)
-- =====================================================
CREATE TABLE IF NOT EXISTS entitlements (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('one_on_one_class', 'group_class', 'video_course_access', 'practice_session')),
    quantity_total INTEGER NOT NULL DEFAULT 0,
    quantity_remaining INTEGER NOT NULL DEFAULT 0,
    period_start DATE,
    period_end DATE,
    expires_at TIMESTAMP WITH TIME ZONE,
    meta JSONB DEFAULT '{}'::jsonb, -- plan_id, recurring info, etc.
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_entitlements_student_id ON entitlements(student_id);
CREATE INDEX idx_entitlements_type ON entitlements(type);
CREATE INDEX idx_entitlements_expires_at ON entitlements(expires_at);
CREATE INDEX idx_entitlements_active ON entitlements(student_id, type, expires_at) WHERE expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP;

-- =====================================================
-- WALLET_ITEMS TABLE
-- Transaction ledger for audit trail
-- =====================================================
CREATE TABLE IF NOT EXISTS wallet_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    wallet_id UUID REFERENCES wallets(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL CHECK (type IN ('entitlement_purchase', 'entitlement_used', 'entitlement_refund', 'plan_subscription', 'adjustment')),
    reference_id UUID, -- Links to classes/plans/entitlements
    credits_delta DECIMAL(10,2) DEFAULT 0.00, -- For compatibility, but entitlements are primary
    amount DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'failed')),
    meta JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_wallet_items_wallet_id ON wallet_items(wallet_id);
CREATE INDEX idx_wallet_items_type ON wallet_items(type);
CREATE INDEX idx_wallet_items_status ON wallet_items(status);
CREATE INDEX idx_wallet_items_reference_id ON wallet_items(reference_id);

-- =====================================================
-- LESSON_TYPES TABLE
-- Define class types and what entitlements they require
-- =====================================================
CREATE TABLE IF NOT EXISTS lesson_types (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    duration_minutes INTEGER NOT NULL,
    cost_in_entitlements JSONB NOT NULL DEFAULT '{}'::jsonb, -- {"type": "one_on_one_class", "quantity": 1}
    refundable BOOLEAN DEFAULT TRUE,
    applicable_to VARCHAR(50)[] DEFAULT ARRAY['kids', 'adults', 'coding'],
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_lesson_types_slug ON lesson_types(slug);
CREATE INDEX idx_lesson_types_applicable_to ON lesson_types USING GIN(applicable_to);

-- =====================================================
-- TEACHER_PROFILES TABLE
-- Teacher pay configuration (admin-only, NOT visible to students)
-- =====================================================
CREATE TABLE IF NOT EXISTS teacher_profiles (
    teacher_id INTEGER PRIMARY KEY,
    default_rate DECIMAL(10,2) NOT NULL DEFAULT 15.00, -- $15/hour base rate
    group_class_rate DECIMAL(10,2), -- Optional: different rate for group classes
    bonus_rate DECIMAL(10,2), -- Optional: bonus rates for special sessions
    payout_info JSONB DEFAULT '{}'::jsonb, -- Payment method, account details, etc.
    availability_settings JSONB DEFAULT '{}'::jsonb, -- Default buffer, booking notice, etc.
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_teacher_profiles_teacher_id ON teacher_profiles(teacher_id);

-- =====================================================
-- AVAILABILITY_SLOTS TABLE
-- Teacher available times
-- =====================================================
CREATE TABLE IF NOT EXISTS availability_slots (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    teacher_id INTEGER NOT NULL,
    start_at_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    end_at_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    is_open BOOLEAN DEFAULT TRUE,
    source VARCHAR(50) NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'recurring', 'admin')),
    meta JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_availability_slots_teacher_id ON availability_slots(teacher_id);
CREATE INDEX idx_availability_slots_time_range ON availability_slots(start_at_utc, end_at_utc);
CREATE INDEX idx_availability_slots_open ON availability_slots(teacher_id, is_open, start_at_utc) WHERE is_open = TRUE;

-- =====================================================
-- CLASSES TABLE
-- Unified booking table (replaces lessons table)
-- =====================================================
CREATE TABLE IF NOT EXISTS classes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    type VARCHAR(50) NOT NULL CHECK (type IN ('one_on_one', 'group', 'practice', 'time_off', 'video_session')),
    title VARCHAR(255),
    start_at_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    end_at_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'requested' CHECK (status IN ('requested', 'confirmed', 'cancelled', 'completed', 'no_show')),
    teacher_id INTEGER NOT NULL,
    student_id INTEGER, -- Nullable for group classes
    slot_request_id UUID, -- Links to slot_requests
    entitlement_id UUID REFERENCES entitlements(id) ON DELETE SET NULL, -- Which entitlement was used
    earnings_record_id UUID, -- Links to earnings (nullable initially)
    recurrence_group_id UUID, -- Links recurring classes together
    meta JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_classes_teacher_id ON classes(teacher_id);
CREATE INDEX idx_classes_student_id ON classes(student_id);
CREATE INDEX idx_classes_status ON classes(status);
CREATE INDEX idx_classes_time_range ON classes(start_at_utc, end_at_utc);
CREATE INDEX idx_classes_recurrence_group ON classes(recurrence_group_id);
CREATE INDEX idx_classes_entitlement_id ON classes(entitlement_id);

-- =====================================================
-- SLOT_REQUESTS TABLE
-- Student booking requests
-- =====================================================
CREATE TABLE IF NOT EXISTS slot_requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id INTEGER NOT NULL,
    teacher_id INTEGER NOT NULL,
    requested_start_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    requested_end_utc TIMESTAMP WITH TIME ZONE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined', 'expired')),
    entitlement_hold_id UUID, -- Links to held entitlement
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITH TIME ZONE -- TTL for holds (e.g., 15 minutes)
);

CREATE INDEX idx_slot_requests_student_id ON slot_requests(student_id);
CREATE INDEX idx_slot_requests_teacher_id ON slot_requests(teacher_id);
CREATE INDEX idx_slot_requests_status ON slot_requests(status);
CREATE INDEX idx_slot_requests_expires_at ON slot_requests(expires_at) WHERE status = 'pending';

-- =====================================================
-- EARNINGS TABLE
-- Teacher pay tracking (admin/teacher only - NOT visible to students)
-- =====================================================
CREATE TABLE IF NOT EXISTS earnings (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    class_id UUID REFERENCES classes(id) ON DELETE SET NULL,
    teacher_id INTEGER NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    platform_fee DECIMAL(10,2) DEFAULT 0.00, -- Default 0 per requirements (no platform fees)
    payout_status VARCHAR(50) NOT NULL DEFAULT 'pending' CHECK (payout_status IN ('pending', 'paid')),
    paid_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_earnings_teacher_id ON earnings(teacher_id);
CREATE INDEX idx_earnings_class_id ON earnings(class_id);
CREATE INDEX idx_earnings_payout_status ON earnings(payout_status);
CREATE INDEX idx_earnings_created_at ON earnings(created_at);

-- =====================================================
-- AUDIT_LOGS TABLE
-- Track all changes for accountability
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_id INTEGER NOT NULL, -- User who performed the action
    action VARCHAR(100) NOT NULL, -- e.g., 'entitlement_used', 'class_cancelled', 'teacher_rate_updated'
    target_type VARCHAR(50) NOT NULL, -- e.g., 'entitlement', 'class', 'teacher_profile'
    target_id UUID, -- ID of the affected record
    changes JSONB DEFAULT '{}'::jsonb, -- Before/after values
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_logs_actor_id ON audit_logs(actor_id);
CREATE INDEX idx_audit_logs_target ON audit_logs(target_type, target_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);

-- =====================================================
-- RECURRENCE_GROUPS TABLE
-- Track recurring lesson series
-- =====================================================
CREATE TABLE IF NOT EXISTS recurrence_groups (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id INTEGER NOT NULL,
    teacher_id INTEGER NOT NULL,
    day_of_week INTEGER NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Sunday, 6=Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE, -- NULL = ongoing
    frequency_weeks INTEGER DEFAULT 1, -- Weekly by default
    status VARCHAR(50) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'paused', 'cancelled')),
    payment_failures INTEGER DEFAULT 0, -- Track consecutive payment failures
    meta JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_recurrence_groups_student_id ON recurrence_groups(student_id);
CREATE INDEX idx_recurrence_groups_teacher_id ON recurrence_groups(teacher_id);
CREATE INDEX idx_recurrence_groups_status ON recurrence_groups(status);

-- =====================================================
-- UPDATE TIMESTAMPS TRIGGER FUNCTION
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply triggers to tables with updated_at
CREATE TRIGGER update_wallets_updated_at BEFORE UPDATE ON wallets FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_entitlements_updated_at BEFORE UPDATE ON entitlements FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_lesson_types_updated_at BEFORE UPDATE ON lesson_types FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_teacher_profiles_updated_at BEFORE UPDATE ON teacher_profiles FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_availability_slots_updated_at BEFORE UPDATE ON availability_slots FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_classes_updated_at BEFORE UPDATE ON classes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_slot_requests_updated_at BEFORE UPDATE ON slot_requests FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_recurrence_groups_updated_at BEFORE UPDATE ON recurrence_groups FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();








