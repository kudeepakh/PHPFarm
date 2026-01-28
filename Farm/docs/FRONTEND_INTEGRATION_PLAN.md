# Frontend Integration Plan - Non-Integrated APIs

**Project:** PHPFrarm Framework  
**Created:** January 24, 2026  
**Purpose:** Detailed implementation plan for integrating backend APIs into React frontend

---

## 🎯 OVERVIEW

This document provides a complete implementation plan for integrating 23 non-integrated backend APIs into the frontend with proper UI components, forms, and admin features.

### Implementation Phases
- **Phase 1:** User Verification & Account Management (High Priority)
- **Phase 2:** Social Authentication (Medium Priority)
- **Phase 3:** OTP Administration (Admin Features)

---

## 📋 PHASE 1: USER VERIFICATION & ACCOUNT MANAGEMENT

### Priority: HIGH - Security & Core Features
**Timeline:** 1-2 weeks  
**APIs to Integrate:** 13 endpoints

---

## 1️⃣ USER VERIFICATION MODULE

### Backend APIs (5 endpoints)
```
POST   /api/v1/users/verify-email
POST   /api/v1/users/resend-verification
GET    /api/v1/users/verification-status
POST   /api/v1/users/verify-phone/send-otp
POST   /api/v1/users/verify-phone
```

### Frontend Implementation

#### A. Service Layer
**File:** `frontend/src/services/verificationService.js`

```javascript
import apiClient from '../utils/apiClient';

class VerificationService {
  // Email verification
  async verifyEmail(token) {
    return apiClient.post('/api/v1/users/verify-email', { token });
  }

  async resendEmailVerification() {
    return apiClient.post('/api/v1/users/resend-verification');
  }

  async getVerificationStatus() {
    return apiClient.get('/api/v1/users/verification-status');
  }

  // Phone verification
  async sendPhoneOTP(phone) {
    return apiClient.post('/api/v1/users/verify-phone/send-otp', { phone });
  }

  async verifyPhone(phone, otp) {
    return apiClient.post('/api/v1/users/verify-phone', { phone, otp });
  }
}

export default new VerificationService();
```

#### B. Email Verification Page
**File:** `frontend/src/pages/VerifyEmail.js`

**Route:** `/verify-email/:token`

**UI Components:**
```
┌─────────────────────────────────────────┐
│         PHPFrarm Logo                   │
│                                         │
│    Email Verification                   │
│                                         │
│    [Loading Spinner]                    │
│    Verifying your email address...      │
│                                         │
│    OR (if success)                      │
│                                         │
│    ✅ Email Verified Successfully!      │
│    Your account is now active.          │
│                                         │
│    [Go to Dashboard] Button             │
│                                         │
│    OR (if failed)                       │
│                                         │
│    ❌ Verification Failed               │
│    Link expired or invalid              │
│                                         │
│    [Resend Verification] Button         │
└─────────────────────────────────────────┘
```

**Features:**
- Auto-verify on page load using token from URL
- Show loading state during verification
- Success message with redirect to dashboard
- Error handling with resend option
- Toast notifications

#### C. Phone Verification Component
**File:** `frontend/src/components/PhoneVerification.js`

**Usage:** Embedded in Profile page or Settings

**UI Components:**
```
┌─────────────────────────────────────────┐
│  Phone Verification                     │
│  ─────────────────────────────────────  │
│                                         │
│  Phone Number: +1 (555) 123-4567        │
│  Status: ⚠️ Not Verified                │
│                                         │
│  [Send Verification Code] Button        │
│                                         │
│  OR (after OTP sent)                    │
│                                         │
│  Enter 6-digit code sent to your phone  │
│                                         │
│  ┌───┬───┬───┬───┬───┬───┐             │
│  │ 1 │ 2 │ 3 │ 4 │ 5 │ 6 │ OTP Input   │
│  └───┴───┴───┴───┴───┴───┘             │
│                                         │
│  [Verify] Button   [Resend] Link        │
│                                         │
│  Code expires in: 4:32                  │
└─────────────────────────────────────────┘
```

**Features:**
- Send OTP button
- 6-digit OTP input with auto-focus
- Countdown timer (5 minutes)
- Resend option after 60 seconds
- Auto-submit on 6 digits entered
- Success/error feedback

#### D. Verification Status Widget
**File:** `frontend/src/components/VerificationStatus.js`

**Usage:** Dashboard, Profile page header

**UI Components:**
```
┌─────────────────────────────────────────┐
│  Account Verification Status            │
│  ─────────────────────────────────────  │
│                                         │
│  ✅ Email: verified@example.com         │
│     Verified on: Jan 20, 2026           │
│                                         │
│  ⚠️ Phone: +1 (555) 123-4567            │
│     [Verify Now] Button                 │
│                                         │
│  Complete verification to unlock:       │
│  • Two-factor authentication            │
│  • Password recovery via SMS            │
│  • Enhanced security features           │
└─────────────────────────────────────────┘
```

---

## 2️⃣ ACCOUNT STATUS MANAGEMENT MODULE

### Backend APIs (8 endpoints)
```
POST   /api/v1/system/users/{userId}/lock
POST   /api/v1/system/users/{userId}/unlock
POST   /api/v1/system/users/{userId}/suspend
POST   /api/v1/system/users/{userId}/activate
POST   /api/v1/users/account/deactivate
GET    /api/v1/system/users/{userId}/status-history
GET    /api/v1/system/users/{userId}/check-access
GET    /api/v1/system/users/{userId}/identifiers
```

### Frontend Implementation

#### A. Service Layer
**File:** `frontend/src/services/accountStatusService.js`

```javascript
import apiClient from '../utils/apiClient';

class AccountStatusService {
  // Admin actions
  async lockAccount(userId) {
    return apiClient.post(`/api/v1/system/users/${userId}/lock`);
  }

  async unlockAccount(userId) {
    return apiClient.post(`/api/v1/system/users/${userId}/unlock`);
  }

  async suspendAccount(userId, reason) {
    return apiClient.post(`/api/v1/system/users/${userId}/suspend`, { reason });
  }

  async activateAccount(userId) {
    return apiClient.post(`/api/v1/system/users/${userId}/activate`);
  }

  async getStatusHistory(userId) {
    return apiClient.get(`/api/v1/system/users/${userId}/status-history`);
  }

  async checkAccess(userId) {
    return apiClient.get(`/api/v1/system/users/${userId}/check-access`);
  }

  async getIdentifiers(userId) {
    return apiClient.get(`/api/v1/system/users/${userId}/identifiers`);
  }

  // User self-action
  async deactivateOwnAccount(reason, password) {
    return apiClient.post('/api/v1/users/account/deactivate', { reason, password });
  }
}

export default new AccountStatusService();
```

#### B. Admin User Management Page (Enhanced)
**File:** `frontend/src/pages/admin/UserManagementPage.js`

**Route:** `/admin/users`

**Enhanced Users Table:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  User Management                                    [+ Add User] Button   │
│  ────────────────────────────────────────────────────────────────────    │
│                                                                            │
│  Search: [_______________________] 🔍  Filter: [All Status ▼]            │
│                                                                            │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ Name        Email            Status    Verified  Actions           │  │
│  ├────────────────────────────────────────────────────────────────────┤  │
│  │ John Doe    john@test.com    🟢 Active   ✅✅     [⋮ Actions ▼]    │  │
│  │ Jane Smith  jane@test.com    🔴 Locked   ✅❌     [⋮ Actions ▼]    │  │
│  │ Bob Wilson  bob@test.com     🟡 Suspend  ❌❌     [⋮ Actions ▼]    │  │
│  │ Alice Brown alice@test.com   🟢 Active   ✅✅     [⋮ Actions ▼]    │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                            │
│  Showing 1-10 of 45 users     [1] 2 3 4 5 Next >                         │
└──────────────────────────────────────────────────────────────────────────┘

Action Menu (⋮):
├─ 👤 View Profile
├─ 📝 Edit Details
├─ 🔒 Lock Account
├─ 🔓 Unlock Account
├─ ⏸️ Suspend Account
├─ ✅ Activate Account
├─ 📊 View Status History
├─ 🔑 View Identifiers
└─ 🗑️ Delete User
```

**Features:**
- Status badges with color coding
- Verification status icons (email/phone)
- Dropdown action menu per user
- Bulk actions checkbox
- Real-time status updates
- Confirmation modals

#### C. User Detail Page (Enhanced)
**File:** `frontend/src/pages/admin/UserDetailPage.js`

**Route:** `/admin/users/:userId`

**UI Layout:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  ← Back to Users                                                          │
│                                                                            │
│  User Profile: John Doe                   [⋮ Actions Menu]               │
│  ════════════════════════════════════════════════════════════════════    │
│                                                                            │
│  ┌─── Overview ───────────────────┐  ┌─── Quick Actions ─────────────┐  │
│  │                                 │  │                                │  │
│  │  📧 john.doe@example.com        │  │  Account Status: 🟢 Active     │  │
│  │  📱 +1 (555) 123-4567          │  │                                │  │
│  │  🆔 User ID: 01KFQB2CY5...     │  │  [🔒 Lock Account] Button      │  │
│  │  📅 Joined: Jan 15, 2026       │  │  [⏸️ Suspend Account] Button   │  │
│  │                                 │  │  [✅ Activate Account] Button  │  │
│  │  Verification:                  │  │  [🗑️ Delete Account] Button   │  │
│  │  ✅ Email Verified              │  │                                │  │
│  │  ✅ Phone Verified              │  └────────────────────────────────┘  │
│  │                                 │                                      │
│  │  Roles: Admin, Editor           │  ┌─── Access Status ─────────────┐  │
│  │                                 │  │                                │  │
│  └─────────────────────────────────┘  │  Login Enabled: ✅ Yes         │  │
│                                        │  2FA Status: ❌ Disabled        │  │
│  ┌─── All Identifiers ─────────────┐  │  Account Locked: ❌ No         │  │
│  │                                  │  │  Last Login: 2 hours ago       │  │
│  │  Primary Email: john@example.com│  │  Failed Attempts: 0            │  │
│  │  Phone: +1 (555) 123-4567       │  │                                │  │
│  │  Username: johndoe              │  └────────────────────────────────┘  │
│  │  Social: Google (linked)        │                                      │
│  │                                  │                                      │
│  └──────────────────────────────────┘                                     │
│                                                                            │
│  ┌─── Status History ─────────────────────────────────────────────────┐  │
│  │                                                                      │  │
│  │  Timeline:                                                           │  │
│  │                                                                      │  │
│  │  ● Jan 24, 2026 10:30 AM - Account Activated                       │  │
│  │    By: admin@system.com                                             │  │
│  │    Reason: Manual activation after review                           │  │
│  │                                                                      │  │
│  │  ● Jan 20, 2026 03:15 PM - Account Suspended                       │  │
│  │    By: admin@system.com                                             │  │
│  │    Reason: Violation of terms - spam activity                       │  │
│  │                                                                      │  │
│  │  ● Jan 15, 2026 09:00 AM - Account Created                         │  │
│  │    By: System                                                        │  │
│  │                                                                      │  │
│  │  [Load More History]                                                │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Comprehensive user overview
- Quick action buttons
- Access status card
- All user identifiers display
- Status history timeline
- Audit trail

#### D. Account Action Modals

**1. Suspend Account Modal**
```
┌─────────────────────────────────────────┐
│  ⏸️ Suspend User Account                │
│  ═══════════════════════════════════    │
│                                         │
│  User: john.doe@example.com             │
│                                         │
│  This action will:                      │
│  • Prevent user from logging in         │
│  • Keep account data intact             │
│  • Can be reversed by activation        │
│                                         │
│  Reason for suspension: (required)      │
│  ┌─────────────────────────────────┐   │
│  │ Violation of terms - spam       │   │
│  │                                 │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Notify user via email? ☑               │
│                                         │
│  [Cancel]          [Suspend Account]    │
└─────────────────────────────────────────┘
```

**2. Lock Account Modal**
```
┌─────────────────────────────────────────┐
│  🔒 Lock User Account                   │
│  ═══════════════════════════════════    │
│                                         │
│  User: john.doe@example.com             │
│                                         │
│  ⚠️ Warning:                            │
│  This will immediately lock the account │
│  and terminate active sessions.         │
│                                         │
│  Reason: (optional)                     │
│  ┌─────────────────────────────────┐   │
│  │ Suspicious activity detected    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Duration:                              │
│  ○ Until manually unlocked              │
│  ○ Temporary (24 hours)                 │
│  ○ Custom: [____] hours                 │
│                                         │
│  [Cancel]          [Lock Now]           │
└─────────────────────────────────────────┘
```

#### E. User Self-Deactivation Page
**File:** `frontend/src/pages/settings/DeactivateAccount.js`

**Route:** `/settings/deactivate-account`

**UI Components:**
```
┌─────────────────────────────────────────┐
│  ⚠️ Deactivate Your Account             │
│  ═══════════════════════════════════    │
│                                         │
│  We're sorry to see you go!             │
│                                         │
│  Before you deactivate:                 │
│  • Your profile will be hidden          │
│  • You can reactivate within 30 days   │
│  • After 30 days, data will be deleted │
│                                         │
│  Tell us why you're leaving:            │
│  ┌─────────────────────────────────┐   │
│  │ ○ No longer need the service    │   │
│  │ ○ Privacy concerns              │   │
│  │ ○ Found a better alternative    │   │
│  │ ○ Too expensive                 │   │
│  │ ○ Technical issues              │   │
│  │ ● Other: ___________________    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Additional feedback: (optional)        │
│  ┌─────────────────────────────────┐   │
│  │                                 │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Confirm your password:                 │
│  ┌─────────────────────────────────┐   │
│  │ ••••••••••••                    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  [Cancel]      [Deactivate Account]     │
└─────────────────────────────────────────┘
```

---

## 📋 PHASE 2: SOCIAL AUTHENTICATION

### Priority: MEDIUM - Enhanced User Experience
**Timeline:** 1 week  
**APIs to Integrate:** 4 endpoints

---

## 3️⃣ SOCIAL AUTHENTICATION MODULE

### Backend APIs (4 endpoints)
```
GET    /api/auth/social/{provider}
GET    /api/auth/social/{provider}/callback
POST   /api/auth/social/{provider}/unlink
GET    /api/auth/social/providers
```

### Frontend Implementation

#### A. Service Layer
**File:** `frontend/src/services/socialAuthService.js`

```javascript
import apiClient from '../utils/apiClient';

class SocialAuthService {
  async startOAuth(provider) {
    // Redirect to backend OAuth endpoint
    const baseURL = apiClient.defaults.baseURL;
    window.location.href = `${baseURL}/api/auth/social/${provider}`;
  }

  async getLinkedProviders() {
    return apiClient.get('/api/auth/social/providers');
  }

  async unlinkProvider(provider) {
    return apiClient.post(`/api/auth/social/${provider}/unlink`);
  }
}

export default new SocialAuthService();
```

#### B. Enhanced Login Page
**File:** `frontend/src/pages/Login.js` (Update existing)

**Add Social Login Section:**
```
┌─────────────────────────────────────────┐
│         PHPFrarm Logo                   │
│                                         │
│         Login to Your Account           │
│                                         │
│  Email:                                 │
│  ┌─────────────────────────────────┐   │
│  │ user@example.com                │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Password:                              │
│  ┌─────────────────────────────────┐   │
│  │ ••••••••••••                    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  [Login] Button                         │
│                                         │
│  ──────── OR ────────                   │
│                                         │
│  [🔵 Continue with Google]              │
│  [📘 Continue with Facebook]            │
│  [⚫ Continue with GitHub]               │
│                                         │
│  Don't have an account? Sign up         │
│  Forgot password?                       │
└─────────────────────────────────────────┘
```

#### C. OAuth Callback Handler
**File:** `frontend/src/pages/OAuthCallback.js`

**Route:** `/auth/callback`

**UI Components:**
```
┌─────────────────────────────────────────┐
│         PHPFrarm Logo                   │
│                                         │
│    [Loading Spinner]                    │
│    Completing authentication...         │
│                                         │
│    Please wait...                       │
└─────────────────────────────────────────┘
```

**Features:**
- Parse URL parameters (code, state, error)
- Handle OAuth success/failure
- Store tokens
- Redirect to dashboard or original destination
- Show error messages if OAuth failed

#### D. Social Connections Settings Page
**File:** `frontend/src/pages/settings/SocialConnections.js`

**Route:** `/settings/social-connections`

**UI Components:**
```
┌─────────────────────────────────────────────────────────────────────┐
│  Social Connections                                                  │
│  ════════════════════════════════════════════════════════════       │
│                                                                      │
│  Link your social accounts for quick login                          │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │  🔵 Google                                                  │    │
│  │                                                             │    │
│  │  Status: ✅ Connected                                       │    │
│  │  Account: john.doe@gmail.com                               │    │
│  │  Connected on: Jan 15, 2026                                │    │
│  │                                                             │    │
│  │  [Disconnect] Button                                       │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │  📘 Facebook                                                │    │
│  │                                                             │    │
│  │  Status: ❌ Not Connected                                   │    │
│  │                                                             │    │
│  │  [Connect Facebook] Button                                 │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │  ⚫ GitHub                                                   │    │
│  │                                                             │    │
│  │  Status: ✅ Connected                                       │    │
│  │  Username: @johndoe                                        │    │
│  │  Connected on: Jan 10, 2026                                │    │
│  │                                                             │    │
│  │  [Disconnect] Button                                       │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  ℹ️ Info: You can log in using any connected social account        │
└─────────────────────────────────────────────────────────────────────┘
```

**Features:**
- List all available providers
- Show connection status
- Connect/disconnect buttons
- Account info display
- Confirmation modals for disconnect

---

## 📋 PHASE 3: OTP ADMINISTRATION

### Priority: LOW - Admin Monitoring
**Timeline:** 3-5 days  
**APIs to Integrate:** 7 endpoints

---

## 4️⃣ OTP ADMINISTRATION MODULE

### Backend APIs (7 endpoints)
```
GET    /api/v1/system/otp/history
GET    /api/v1/system/otp/statistics
GET    /api/v1/system/otp/blacklist
POST   /api/v1/system/otp/blacklist
DELETE /api/v1/system/otp/blacklist/{blacklistId}
POST   /api/v1/system/otp/check-status
POST   /api/v1/system/otp/cleanup
```

### Frontend Implementation

#### A. Service Layer
**File:** `frontend/src/services/otpAdminService.js`

```javascript
import apiClient from '../utils/apiClient';

class OTPAdminService {
  async getHistory(params) {
    return apiClient.get('/api/v1/system/otp/history', { params });
  }

  async getStatistics(params) {
    return apiClient.get('/api/v1/system/otp/statistics', { params });
  }

  async getBlacklist(params) {
    return apiClient.get('/api/v1/system/otp/blacklist', { params });
  }

  async addToBlacklist(identifier, type, reason) {
    return apiClient.post('/api/v1/system/otp/blacklist', {
      identifier,
      type,
      reason
    });
  }

  async removeFromBlacklist(blacklistId) {
    return apiClient.delete(`/api/v1/system/otp/blacklist/${blacklistId}`);
  }

  async checkStatus(identifier, type) {
    return apiClient.post('/api/v1/system/otp/check-status', {
      identifier,
      type
    });
  }

  async cleanup() {
    return apiClient.post('/api/v1/system/otp/cleanup');
  }
}

export default new OTPAdminService();
```

#### B. OTP Monitoring Dashboard
**File:** `frontend/src/pages/admin/OTPDashboard.js`

**Route:** `/admin/otp-monitoring`

**UI Layout:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  OTP Monitoring Dashboard                                                 │
│  ════════════════════════════════════════════════════════════════════    │
│                                                                            │
│  ┌─── Statistics (Last 24h) ─────────────────────────────────────────┐   │
│  │                                                                     │   │
│  │  📧 Total Sent      🎯 Success Rate    ⏱️ Avg Time    ❌ Failed   │   │
│  │     1,234             94.5%             2.3s            68         │   │
│  │                                                                     │   │
│  │  📊 Chart: OTP Requests (Last 7 Days)                             │   │
│  │  ┌─────────────────────────────────────────────────────────────┐ │   │
│  │  │        ▄                                                      │ │   │
│  │  │    ▄  ███  ▄                                                 │ │   │
│  │  │  ▄███████████▄   ▄  ▄                                        │ │   │
│  │  │ ████████████████████████▄                                    │ │   │
│  │  └─────────────────────────────────────────────────────────────┘ │   │
│  │    Mon  Tue  Wed  Thu  Fri  Sat  Sun                            │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                            │
│  Tabs: [History] [Blacklist] [Check Status]                              │
│                                                                            │
│  ┌─── OTP History ───────────────────────────────────────────────────┐   │
│  │                                                                     │   │
│  │  Filter: [Last 24h ▼]  Type: [All ▼]  Status: [All ▼]           │   │
│  │  Search: [_______________________] 🔍                             │   │
│  │                                                                     │   │
│  │  Timestamp         Identifier         Type   Status    Purpose    │   │
│  │  ─────────────────────────────────────────────────────────────    │   │
│  │  Jan 24, 10:30 AM  john@test.com      Email  ✅ Verified  Login   │   │
│  │  Jan 24, 10:28 AM  +15551234567       Phone  ✅ Verified  2FA     │   │
│  │  Jan 24, 10:25 AM  jane@test.com      Email  ❌ Expired   Reset   │   │
│  │  Jan 24, 10:20 AM  bob@test.com       Email  ⏳ Pending   Login   │   │
│  │  Jan 24, 10:15 AM  +15559876543       Phone  ❌ Failed    Verify  │   │
│  │                                                                     │   │
│  │  Showing 1-20 of 1,234    [1] 2 3 ... 62 Next >                   │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────┘
```

#### C. OTP Blacklist Management
**File:** `frontend/src/pages/admin/OTPBlacklist.js`

**Route:** `/admin/otp-monitoring` (Tab: Blacklist)

**UI Layout:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  OTP Blacklist Management                      [+ Add to Blacklist]       │
│  ════════════════════════════════════════════════════════════════════    │
│                                                                            │
│  Search: [_______________________] 🔍  Type: [All ▼]                     │
│                                                                            │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ Identifier        Type   Reason              Added       Actions    │  │
│  ├────────────────────────────────────────────────────────────────────┤  │
│  │ spam@test.com     Email  Spam/abuse         Jan 20      [Remove]   │  │
│  │ +15551234567      Phone  Excessive attempts Jan 19      [Remove]   │  │
│  │ abuse@test.com    Email  Blacklisted email  Jan 18      [Remove]   │  │
│  │ +15559876543      Phone  Fraud detection    Jan 15      [Remove]   │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                            │
│  Showing 1-10 of 23    [1] 2 3 Next >                                    │
└──────────────────────────────────────────────────────────────────────────┘
```

**Add to Blacklist Modal:**
```
┌─────────────────────────────────────────┐
│  🚫 Add to OTP Blacklist                │
│  ═══════════════════════════════════    │
│                                         │
│  Type:                                  │
│  ○ Email    ● Phone                     │
│                                         │
│  Identifier:                            │
│  ┌─────────────────────────────────┐   │
│  │ spam@example.com                │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Reason:                                │
│  ┌─────────────────────────────────┐   │
│  │ Repeated spam/abuse attempts    │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  [Cancel]          [Add to Blacklist]   │
└─────────────────────────────────────────┘
```

#### D. OTP Status Checker
**File:** `frontend/src/components/OTPStatusChecker.js`

**Route:** `/admin/otp-monitoring` (Tab: Check Status)

**UI Layout:**
```
┌──────────────────────────────────────────────────────────────────┐
│  Check OTP Status                                                 │
│  ════════════════════════════════════════════════════════        │
│                                                                   │
│  Type:                                                            │
│  ● Email    ○ Phone                                               │
│                                                                   │
│  Identifier:                                                      │
│  ┌─────────────────────────────────┐                             │
│  │ user@example.com                │ [Check Status] Button       │
│  └─────────────────────────────────┘                             │
│                                                                   │
│  ┌─── Results ─────────────────────────────────────────────┐    │
│  │                                                           │    │
│  │  Status: ✅ Active                                       │    │
│  │  Blacklisted: ❌ No                                      │    │
│  │  Rate Limit: 2/5 attempts used                          │    │
│  │  Last OTP: 5 minutes ago                                │    │
│  │  Last Verification: 3 hours ago                         │    │
│  │  Success Rate: 85%                                      │    │
│  │                                                           │    │
│  │  Recent Activity (Last 24h):                            │    │
│  │  • 10:30 AM - OTP sent (Login)                          │    │
│  │  • 10:31 AM - OTP verified successfully                 │    │
│  │  • 2:15 PM - OTP sent (Password Reset)                  │    │
│  │  • 2:16 PM - OTP verified successfully                  │    │
│  └───────────────────────────────────────────────────────────┘    │
│                                                                   │
│  Quick Actions:                                                   │
│  [Add to Blacklist] [View Full History] [Reset Rate Limit]      │
└──────────────────────────────────────────────────────────────────┘
```

---

## 🗂️ COMPONENT ARCHITECTURE

### Reusable Components to Create

#### 1. Status Badge Component
**File:** `frontend/src/components/common/StatusBadge.js`

```javascript
<StatusBadge 
  status="active"    // active, locked, suspended, inactive
  size="sm"          // sm, md, lg
  showIcon={true}
/>
```

#### 2. Verification Icon Component
**File:** `frontend/src/components/common/VerificationIcon.js`

```javascript
<VerificationIcon 
  type="email"       // email, phone
  verified={true}
  size="md"
/>
```

#### 3. Action Dropdown Menu
**File:** `frontend/src/components/common/ActionDropdown.js`

```javascript
<ActionDropdown 
  items={[
    { label: 'View', icon: '👁️', onClick: handleView },
    { label: 'Edit', icon: '✏️', onClick: handleEdit },
    { label: 'Delete', icon: '🗑️', onClick: handleDelete, danger: true }
  ]}
/>
```

#### 4. Confirmation Modal
**File:** `frontend/src/components/common/ConfirmModal.js`

```javascript
<ConfirmModal
  isOpen={true}
  title="Lock Account"
  message="Are you sure you want to lock this account?"
  type="warning"     // info, warning, danger, success
  confirmText="Lock Account"
  onConfirm={handleConfirm}
  onCancel={handleCancel}
/>
```

#### 5. Timeline Component
**File:** `frontend/src/components/common/Timeline.js`

```javascript
<Timeline
  events={[
    {
      timestamp: '2026-01-24T10:30:00Z',
      title: 'Account Activated',
      description: 'By: admin@system.com',
      type: 'success'
    }
  ]}
/>
```

---

## 🎨 STYLING & THEMING

### Color Palette for Status
```css
/* Status Colors */
.status-active { background: #10b981; }      /* Green */
.status-locked { background: #ef4444; }      /* Red */
.status-suspended { background: #f59e0b; }   /* Yellow */
.status-inactive { background: #6b7280; }    /* Gray */

/* Verification */
.verified { color: #10b981; }
.not-verified { color: #f59e0b; }

/* Social Providers */
.google { background: #4285f4; }
.facebook { background: #1877f2; }
.github { background: #333333; }
```

---

## 📱 RESPONSIVE DESIGN

### Mobile Breakpoints
- **Desktop:** 1024px+ (Full features)
- **Tablet:** 768px-1023px (Compact layout)
- **Mobile:** <768px (Stack vertically, simplified actions)

### Mobile Considerations
- Replace dropdown menus with bottom sheets
- Stack cards vertically
- Use swipe actions for quick operations
- Simplify forms to single column
- Use full-width buttons

---

## 🔐 PERMISSIONS & RBAC

### Route Guards
```javascript
// Admin-only routes
/admin/users             → permission:users:read
/admin/users/:id         → permission:users:read
/admin/otp-monitoring    → permission:otp:read

// Actions requiring permissions
Lock Account             → permission:users:lock
Suspend Account          → permission:users:suspend
View OTP History         → permission:otp:read
Manage Blacklist         → permission:otp:manage
```

### Permission Checks in UI
```javascript
{hasPermission('users:lock') && (
  <Button onClick={handleLock}>Lock Account</Button>
)}
```

---

## 🧪 TESTING CHECKLIST

### Unit Tests
- [ ] Service layer methods
- [ ] Component rendering
- [ ] Form validation
- [ ] Error handling

### Integration Tests
- [ ] API calls with mock responses
- [ ] Authentication flow
- [ ] OAuth callbacks
- [ ] Form submissions

### E2E Tests
- [ ] Complete verification flow
- [ ] Account status changes
- [ ] Social login connection
- [ ] OTP blacklist management

---

## 📦 DELIVERABLES SUMMARY

### New Files to Create (30+ files)

#### Services (4 files)
- `frontend/src/services/verificationService.js`
- `frontend/src/services/accountStatusService.js`
- `frontend/src/services/socialAuthService.js`
- `frontend/src/services/otpAdminService.js`

#### Pages (8 files)
- `frontend/src/pages/VerifyEmail.js`
- `frontend/src/pages/OAuthCallback.js`
- `frontend/src/pages/settings/DeactivateAccount.js`
- `frontend/src/pages/settings/SocialConnections.js`
- `frontend/src/pages/admin/UserDetailPage.js`
- `frontend/src/pages/admin/OTPDashboard.js`
- `frontend/src/pages/admin/OTPBlacklist.js`

#### Components (10+ files)
- `frontend/src/components/PhoneVerification.js`
- `frontend/src/components/VerificationStatus.js`
- `frontend/src/components/OTPStatusChecker.js`
- `frontend/src/components/common/StatusBadge.js`
- `frontend/src/components/common/VerificationIcon.js`
- `frontend/src/components/common/ActionDropdown.js`
- `frontend/src/components/common/ConfirmModal.js`
- `frontend/src/components/common/Timeline.js`
- `frontend/src/components/modals/SuspendAccountModal.js`
- `frontend/src/components/modals/LockAccountModal.js`

#### Files to Update (5 files)
- `frontend/src/App.js` - Add new routes
- `frontend/src/pages/Login.js` - Add social login
- `frontend/src/pages/Register.js` - Add social registration
- `frontend/src/pages/admin/UserManagementPage.js` - Enhanced features
- `frontend/src/layouts/DashboardLayout.js` - Add admin menu items

---

## ⏱️ ESTIMATED TIMELINE

| Phase | Features | Estimated Time |
|-------|----------|----------------|
| Phase 1 | User Verification & Account Management | 1-2 weeks |
| Phase 2 | Social Authentication | 1 week |
| Phase 3 | OTP Administration | 3-5 days |
| **Total** | **All Non-Integrated APIs** | **3-4 weeks** |

---

## 🚀 IMPLEMENTATION PRIORITIES

### Week 1-2: Core Security Features
1. Email verification flow
2. Phone verification component
3. Verification status widget
4. Account status management (admin)
5. User detail page enhancements

### Week 3: Social Auth
1. Social login buttons
2. OAuth callback handler
3. Social connections settings
4. Provider management

### Week 4: Admin Monitoring
1. OTP dashboard
2. Blacklist management
3. Status checker
4. Cleanup tools

---

## ✅ SUCCESS METRICS

- [ ] All 23 APIs integrated
- [ ] Zero TypeScript/ESLint errors
- [ ] All components responsive
- [ ] Full test coverage (>80%)
- [ ] Documentation complete
- [ ] Figma designs approved (if applicable)
- [ ] Security audit passed
- [ ] Performance benchmarks met

---

**Document Version:** 1.0  
**Last Updated:** January 24, 2026  
**Author:** GitHub Copilot  
**Status:** Ready for Implementation
