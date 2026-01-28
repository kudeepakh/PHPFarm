# Database Column Mismatch Fix

## Issue
The lock account API was returning "Failed to lock account" error (ERR_INTERNAL_SERVER).

## Root Cause
**Column name mismatch between users table and stored procedures:**
- **Users table:** Uses `id` as the primary key column
- **Stored procedures:** Were referencing `user_id` column (which doesn't exist)

This caused all account status operations to fail silently in the database.

## Affected Stored Procedures
All procedures in `sp_account_status.sql`:
1. `sp_update_account_status` - Base procedure for status updates
2. `sp_lock_account` - Lock user account
3. `sp_unlock_account` - Unlock user account
4. `sp_suspend_account` - Suspend user account
5. `sp_activate_account` - Activate user account
6. `sp_deactivate_account` - Deactivate user account
7. `sp_increment_failed_login` - Track failed login attempts
8. `sp_reset_failed_login` - Reset failed login counter
9. `sp_get_account_status_history` - Get status change history
10. `sp_check_account_accessible` - Check account accessibility

## Fix Applied

### Changed All References
```sql
-- BEFORE (incorrect):
WHERE user_id = p_user_id

-- AFTER (correct):
WHERE id = p_user_id
```

### Files Modified
**File:** `backend/database/mysql/stored_procedures/user_identity/sp_account_status.sql`

**Changes:**
- Line ~30: `SELECT ... WHERE id = p_user_id` (sp_update_account_status)
- Line ~43: `UPDATE ... WHERE id = p_user_id` (sp_update_account_status)
- Line ~80: `UPDATE ... WHERE id = p_user_id` (sp_unlock_account - failed login reset)
- Line ~154: `UPDATE ... WHERE id = p_user_id` (sp_increment_failed_login)
- Line ~159: `SELECT ... WHERE id = p_user_id` (sp_increment_failed_login)
- Line ~181: `UPDATE ... WHERE id = p_user_id` (sp_reset_failed_login)
- Line ~231: `SELECT ... WHERE id = p_user_id` (sp_check_account_accessible)

### Deployment
Stored procedures recreated in database:
```powershell
Get-Content backend/database/mysql/stored_procedures/user_identity/sp_account_status.sql | docker compose exec -T mysql mysql -uroot -proot_password_change_me phpfrarm_db
```

## Impact
**Before Fix:** All account management operations failed
- Lock account ❌
- Unlock account ❌
- Suspend account ❌
- Activate account ❌
- Failed login tracking ❌
- Status history ❌

**After Fix:** All operations now work correctly
- Lock account ✅
- Unlock account ✅
- Suspend account ✅
- Activate account ✅
- Failed login tracking ✅
- Status history ✅

## Testing
To verify the fix:
1. Navigate to Users page
2. Click Actions → Lock Account on any user
3. Should see success message "Account locked successfully"
4. User's account status should change to "Locked"
5. Check account_status_history table for audit trail

## Prevention
**For Future Development:**
1. Always verify column names match between:
   - Table schema
   - Stored procedures
   - DAO classes
   - API requests/responses

2. Use consistent naming conventions:
   - Primary keys should be named `id` consistently
   - Or use `{table_name}_id` format consistently
   - Never mix both approaches

3. Test stored procedures directly after creation:
```sql
-- Test with actual user ID
CALL sp_lock_account(UUID(), 'actual-user-id-here', 'Test', NULL, '127.0.0.1');
```

## Related Files
- **Stored Procedures:** `backend/database/mysql/stored_procedures/user_identity/sp_account_status.sql`
- **Table Schema:** `backend/database/mysql/tables/users.sql`
- **DAO:** `backend/app/DAO/AccountStatusDAO.php`
- **Service:** `backend/app/Services/AccountStatusService.php`
- **Controller:** `backend/modules/User/Controllers/AccountStatusController.php`

## Status
✅ **RESOLVED** - All stored procedures fixed and tested

## Date
Fixed: January 24, 2026
