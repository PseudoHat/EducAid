# Email Notification System - Option 3: Frequency Control Only

**Implementation Date:** November 12, 2025  
**Decision:** Frequency-based preference system with critical type override

---

## 🎯 System Overview

The notification preference system allows students to control **when** they receive emails, but not **what** they receive. All notification types are always enabled to ensure students don't miss critical scholarship application updates.

### Key Principles

1. ✅ **All emails are enabled** - Students cannot disable specific notification types
2. ✅ **Frequency choice** - Students can choose immediate or daily digest
3. ✅ **Critical override** - Errors and warnings ALWAYS sent immediately regardless of preference
4. ✅ **In-app backup** - Bell icon notifications always work regardless of email settings

---

## 📊 Notification Types

All students receive notifications for:

| Type | Description | Examples | Frequency Respect |
|------|-------------|----------|-------------------|
| **error** | Critical failures | Document rejections | ❌ Always immediate |
| **warning** | Urgent alerts | Re-upload requirements, deadline warnings | ❌ Always immediate |
| **success** | Positive updates | Application approvals, document approvals | ✅ Respects preference |
| **announcement** | System-wide messages | Policy changes, important notices | ✅ Respects preference |
| **document** | Document status | Under review notifications | ✅ Respects preference |
| **schedule** | Schedule updates | Distribution dates, appointment changes | ✅ Respects preference |
| **system** | System notifications | Account updates, system maintenance | ✅ Respects preference |
| **info** | General information | Tips, reminders, general updates | ✅ Respects preference |

---

## 🔐 Critical Type Override Logic

```php
// From student_notification_helper.php
$critical_types = ['error', 'warning'];

if (in_array(strtolower($type), $critical_types)) {
    // ALWAYS send immediately - ignore frequency preference
    $svc->sendImmediateEmail($student_id, $title, $message, $type, $action_url);
} else {
    // Respect student's frequency preference
    if ($pref['email_frequency'] === 'immediate') {
        $svc->sendImmediateEmail(...);
    } else {
        // Queue for daily digest
    }
}
```

### Why This Is Safe

**Scenario 1: Student selects "Daily Digest"**
- ✅ Gets document rejection email **immediately** (critical override)
- ✅ Gets success/announcement emails in **daily summary**
- ✅ Reduced email volume for non-urgent items
- ✅ Never misses critical application issues

**Scenario 2: Student selects "Immediate"**
- ✅ Gets all emails as they happen
- ✅ Maximum awareness of application status
- ✅ Fast response to any issues

---

## 💾 Database Schema

### Current Table Structure
```sql
student_notification_preferences:
- student_id (primary key)
- email_enabled (boolean) -- Always TRUE in new system
- email_frequency (varchar) -- 'immediate' or 'daily'
- email_announcement (boolean) -- Always TRUE
- email_document (boolean) -- Always TRUE
- email_schedule (boolean) -- Always TRUE
- email_warning (boolean) -- Always TRUE
- email_error (boolean) -- Always TRUE
- email_success (boolean) -- Always TRUE
- email_system (boolean) -- Always TRUE
- email_info (boolean) -- Always TRUE
- created_at (timestamp)
- updated_at (timestamp)
```

### Migration Strategy

**No schema changes needed!** Type columns remain in table but are:
- Always set to TRUE by save API
- Ignored by notification helper function
- Kept for potential future use

This allows easy rollback if needed.

---

## 🖥️ User Interface

### Student Settings Page

**Before (Old System - Risky):**
```
☑ Enable Email Notifications
☐ Announcements
☐ Documents
☐ Schedule
☐ Warnings        ← Student could disable critical alerts!
☐ Errors          ← Student could disable critical alerts!
☐ Success
```

**After (New System - Safe):**
```
Email Delivery Frequency:
● Immediate (Recommended) - Get emails as updates happen
○ Daily Digest - One email per day summarizing all updates

⚠️ Important: Critical alerts (document rejections, errors, warnings) 
are always sent immediately regardless of your preference.

What You'll Receive:
✓ Document Updates (approvals, rejections, re-upload requests)
✓ Application Status (verification, approval)
✓ Announcements (important messages)
✓ Schedule Changes (distribution dates, deadlines)
```

---

## 🔄 API Endpoints

### GET `/api/student/get_notification_preferences.php`

**Response:**
```json
{
  "success": true,
  "preferences": {
    "student_id": "GENERALTRIAS-2025-3-ABC123",
    "email_enabled": true,
    "email_frequency": "immediate",
    "email_announcement": true,
    "email_document": true,
    "email_schedule": true,
    "email_warning": true,
    "email_error": true,
    "email_success": true,
    "email_system": true,
    "email_info": true
  }
}
```

### POST `/api/student/save_notification_preferences.php`

**Request:**
```json
{
  "email_enabled": true,
  "email_frequency": "daily"
}
```

**Backend Behavior:**
```php
// Ignores any type-specific flags in request
// Forces all types to TRUE
UPDATE student_notification_preferences 
SET email_enabled = true,
    email_frequency = 'daily',
    email_announcement = true,
    email_document = true,
    email_schedule = true,
    email_warning = true,
    email_error = true,
    email_success = true,
    email_system = true,
    email_info = true
WHERE student_id = $1
```

---

## 📝 Implementation Files

| File | Changes Made |
|------|--------------|
| `modules/student/student_settings.php` | Removed type checkboxes, added frequency radio buttons, added informational alerts |
| `api/student/save_notification_preferences.php` | Force all types to TRUE, only save frequency preference |
| `includes/student_notification_helper.php` | Added critical type override logic (error/warning always immediate) |
| `00005 ensure_notification_preferences_all_enabled.php` | Migration script to enable all preferences for existing students |

---

## ✅ Testing Checklist

### Critical Type Override Test
- [ ] Student sets frequency to "Daily Digest"
- [ ] Admin rejects student's document (type: 'warning')
- [ ] **Expected:** Student receives email **immediately** (not in digest)
- [ ] **Verify:** Check student's inbox for immediate email

### Non-Critical Type Test
- [ ] Student sets frequency to "Daily Digest"
- [ ] Admin approves student's application (type: 'success')
- [ ] **Expected:** Email queued for daily digest (or sent immediately if digest not implemented)
- [ ] **Verify:** Student receives success notification

### Preference Save Test
- [ ] Student changes frequency from "Immediate" to "Daily Digest"
- [ ] Click "Save Preferences"
- [ ] **Expected:** Success message shown
- [ ] **Verify:** Reload page, "Daily Digest" still selected

### Database Verification Test
- [ ] Check student's row in `student_notification_preferences`
- [ ] **Expected:** All type columns are TRUE
- [ ] **Expected:** `email_enabled` is TRUE
- [ ] **Expected:** `email_frequency` matches student's selection

---

## 🎓 Benefits of This Approach

| Benefit | Explanation |
|---------|-------------|
| **Safety** | Students cannot disable critical notifications |
| **Flexibility** | Students can reduce email volume with daily digest |
| **Transparency** | UI clearly explains what emails they'll receive |
| **Compliance** | System can prove critical notifications were sent |
| **Simplicity** | Only one choice (frequency), not 8+ checkboxes |
| **Rollback-friendly** | Database schema unchanged, easy to revert |

---

## 🚨 Important Notes

### For Developers

1. **Never check type preferences** in notification code - they're always TRUE
2. **Always check critical types** before respecting frequency preference
3. **Test document rejection flow** thoroughly - this is the most critical path
4. **Daily digest** not yet implemented - currently all non-critical emails also send immediately

### For Administrators

1. **All students get all emails** - they cannot opt out of any notification type
2. **Critical alerts bypass preferences** - errors/warnings always send immediately
3. **Students can only choose frequency** - immediate or daily digest
4. **In-app notifications always work** - bell icon shows everything regardless of email settings

### For Students (User Education)

1. "Immediate" = Get emails right away
2. "Daily Digest" = One summary email per day (but critical alerts still immediate)
3. Bell icon notifications always work
4. Cannot disable specific notification types (by design, for your benefit)

---

## 🔮 Future Enhancements

### Daily Digest Implementation (Not Yet Built)

If implementing daily digest:

```php
// Add to student_notification_helper.php
if ($pref['email_frequency'] === 'daily') {
    // Queue notification for digest
    $queue_sql = "INSERT INTO notification_digest_queue 
                  (student_id, notification_id, queued_at) 
                  VALUES ($1, $2, NOW())";
    pg_query_params($connection, $queue_sql, [$student_id, $notification_id]);
}

// Separate cron job to send digests
// Run daily at 8 AM local time
// SELECT all queued notifications per student
// Build HTML digest email
// Send via StudentEmailNotificationService->sendDigestEmail()
// Clear queue for sent notifications
```

### Admin Override

Add ability for admins to force-send critical emails regardless of any setting:

```php
function sendCriticalNotificationOverride($student_id, $title, $message) {
    // Bypass ALL preferences, send immediately
    $svc = new StudentEmailNotificationService($connection);
    $svc->sendImmediateEmail($student_id, $title, $message, 'warning', null);
}
```

---

## 📞 Support

**Decision Rationale:** This system balances user autonomy (frequency choice) with system reliability (critical alerts always sent). Students cannot accidentally harm their scholarship prospects by disabling important notifications.

**Maintenance:** No ongoing maintenance required. System is self-contained and safe by design.
