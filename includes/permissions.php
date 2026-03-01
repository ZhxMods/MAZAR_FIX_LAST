<?php
// ============================================================
//  MAZAR — includes/permissions.php
//  Single source of truth for all role-based permissions
// ============================================================

function isSuperAdmin(): bool {
    return ($_SESSION[SESS_ROLE] ?? '') === 'super_admin';
}

function isAtLeastAdmin(): bool {
    return in_array($_SESSION[SESS_ROLE] ?? '', ['admin', 'super_admin']);
}

function isAtLeastStaff(): bool {
    return in_array($_SESSION[SESS_ROLE] ?? '', ['staff', 'admin', 'super_admin']);
}

// ── Lesson permissions ────────────────────────────────────────
// staff / admin / super_admin  → add & edit
// admin / super_admin          → delete
function canAddLesson(): bool    { return isAtLeastStaff(); }
function canEditLesson(): bool   { return isAtLeastStaff(); }
function canDeleteLesson(): bool { return isAtLeastAdmin(); }

// ── Quiz permissions ──────────────────────────────────────────
// staff / admin / super_admin  → add
// admin / super_admin          → edit & delete
function canAddQuiz(): bool    { return isAtLeastStaff(); }
function canEditQuiz(): bool   { return isAtLeastAdmin(); }
function canDeleteQuiz(): bool { return isAtLeastAdmin(); }

// ── Site-structure permissions (super_admin only) ─────────────
function canManageLevels(): bool   { return isSuperAdmin(); }
function canManageSubjects(): bool { return isSuperAdmin(); }
function canManageUsers(): bool    { return isSuperAdmin(); }

// ── Hard gates (redirect if not allowed) ─────────────────────
function requireSuperAdmin(): void {
    if (!isSuperAdmin()) {
        header('Location: dashboard.php?msg=unauthorized');
        exit;
    }
}

function requireAtLeastAdmin(): void {
    if (!isAtLeastAdmin()) {
        header('Location: dashboard.php?msg=unauthorized');
        exit;
    }
}

function requireAtLeastStaff(): void {
    if (!isAtLeastStaff()) {
        header('Location: dashboard.php?msg=unauthorized');
        exit;
    }
}
