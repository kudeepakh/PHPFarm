# 📋 **PHPFrarm Framework - Complete API Reference**

> **Enterprise API Catalog with End-to-End Operations**  
> Generated: January 26, 2026  
> Framework: PHPFrarm v1.0 Enterprise

---

## 🎯 **Overview**

This document provides a comprehensive list of all available APIs in the PHPFrarm framework, including complete end-to-end operation flows, request/response examples, authentication requirements, and error handling.

**Framework Features:**
- ✅ Automatic CSRF protection  
- ✅ Request timeout enforcement
- ✅ Error sanitization 
- ✅ Idempotency support
- ✅ Correlation ID tracing
- ✅ Soft delete operations
- ✅ Optimistic locking

---

## 🏗️ **Framework Architecture & Data Flow**

### **Complete Request Processing Pipeline**

```
[Client Request] 
    ↓
[Nginx/Apache] → [Route Discovery] 
    ↓
[Middleware Stack] → [CORS] → [Rate Limiting] → [Timeout] → [Error Sanitization] → [Auth] → [CSRF] → [Idempotency] → [Input Validation]
    ↓
[Controller] → [Service Layer] → [DAO Layer] → [Database]
    ↓
[Response Envelope] → [Correlation ID Injection] → [Error Handling] → [JSON Response]
    ↓
[Client Response]
```

### **Layer Architecture**

#### **1. Controller Layer** (`/app/Controllers/`)
- **Responsibility**: HTTP request handling, route binding, response formatting
- **Files**: `AuthController.php`, `UserController.php`, `RoleController.php`
- **Functions**: 
  - Request validation and sanitization
  - Service layer delegation
  - Response envelope creation
  - Error handling and HTTP status mapping

#### **2. Service Layer** (`/app/Services/`)
- **Responsibility**: Business logic, transaction management, validation
- **Files**: `AuthService.php`, `UserService.php`, `RoleService.php`
- **Functions**:
  - Complex business rule enforcement
  - Multi-DAO operations and transactions
  - Data transformation and validation
  - External service integration

#### **3. DAO Layer** (`/app/DAO/`)
- **Responsibility**: Database operations, stored procedure calls
- **Files**: `UserDAO.php`, `AuthDAO.php`, `RoleDAO.php`
- **Functions**:
  - Stored procedure execution only
  - Connection management
  - Result set mapping
  - Database error handling

#### **4. DTO Layer** (`/app/DTO/`)
- **Responsibility**: Data transfer objects, request/response mapping
- **Files**: `UserDTO.php`, `AuthDTO.php`, `CreateUserRequest.php`
- **Functions**:
  - Data structure definition
  - Validation rules
  - Serialization/deserialization
  - Type safety enforcement

#### **5. Model Layer** (`/app/Models/`)
- **Responsibility**: Domain entities, business rules
- **Files**: `User.php`, `Role.php`, `Permission.php`
- **Functions**:
  - Entity state management
  - Business rule validation
  - Domain event generation
  - Relationship definitions

---

## 📊 **Database Operation Flows**

### **MySQL Stored Procedure Architecture**

🚫 **STRICT RULE**: No direct SQL queries from PHP code - only stored procedure calls

#### **Database Structure**
```
/database/mysql/
├── tables/
│   ├── users.sql
│   ├── roles.sql
│   ├── permissions.sql
│   ├── user_sessions.sql
│   ├── operation_logs.sql
│   └── idempotent_requests.sql
├── stored_procedures/
│   ├── user/
│   │   ├── sp_create_user.sql
│   │   ├── sp_get_user_by_id.sql
│   │   ├── sp_update_user_with_locking.sql
│   │   └── sp_soft_delete_user.sql
│   ├── auth/
│   │   ├── sp_register_user_with_phone.sql
│   │   ├── sp_verify_phone_registration.sql
│   │   ├── sp_initiate_phone_login.sql
│   │   └── sp_login_with_phone_otp.sql
│   └── system/
│       ├── sp_log_operation.sql
│       └── sp_check_idempotency.sql
└── migrations/
    ├── v1.0.0_initial_schema.sql
    └── v1.1.0_add_operation_logs.sql
```

### **Complete Database Operation Example: User Registration**

#### **1. Controller → Service → DAO Flow**

**AuthController.php**
```php
#[Route('POST', '/api/v1/auth/register')]
public function register(Request $request): JsonResponse 
{
    // 1. Extract and validate request data
    $dto = CreateUserRequest::fromRequest($request);
    
    // 2. Delegate to service layer
    $result = $this->authService->registerUser($dto);
    
    // 3. Return formatted response
    return $this->successResponse($result, 'User registered successfully', 201);
}
```

**AuthService.php**
```php
public function registerUser(CreateUserRequest $dto): UserDTO 
{
    // 1. Business logic validation
    $this->validateRegistrationRules($dto);
    
    // 2. Hash password
    $hashedPassword = password_hash($dto->password, PASSWORD_ARGON2ID);
    
    // 3. Generate UUID
    $userId = $this->generateUUID();
    
    // 4. Call DAO for database operation
    $user = $this->userDAO->createUser(
        $userId,
        $dto->email,
        $hashedPassword,
        $dto->firstName,
        $dto->lastName,
        $this->getCorrelationId()
    );
    
    // 5. Log operation
    $this->logOperation('user_registered', $userId);
    
    return UserDTO::fromModel($user);
}
```

**UserDAO.php**
```php
public function createUser(
    string $userId,
    string $email,
    string $hashedPassword,
    string $firstName,
    string $lastName,
    string $correlationId
): User {
    // 1. Prepare stored procedure call
    $stmt = $this->connection->prepare(
        'CALL sp_create_user(?, ?, ?, ?, ?, ?, @result_code, @result_message)'
    );
    
    // 2. Bind parameters
    $stmt->bind_param(
        'ssssss',
        $userId,
        $email,
        $hashedPassword,
        $firstName,
        $lastName,
        $correlationId
    );
    
    // 3. Execute stored procedure
    $stmt->execute();
    
    // 4. Get result codes
    $result = $this->connection->query(
        'SELECT @result_code as code, @result_message as message'
    )->fetch_assoc();
    
    // 5. Handle database response
    if ($result['code'] !== '200') {
        throw new DatabaseException($result['message']);
    }
    
    // 6. Return User model
    return $this->getUserById($userId);
}
```

#### **2. Stored Procedure Example: `sp_create_user`**

```sql
DELIMITER //

CREATE PROCEDURE sp_create_user(
    IN p_user_id VARCHAR(36),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_correlation_id VARCHAR(36),
    OUT p_result_code VARCHAR(10),
    OUT p_result_message VARCHAR(255)
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;
    DECLARE v_error_msg VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
        SET p_result_code = '500';
        SET p_result_message = CONCAT('Database error: ', v_error_msg);
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- 1. Check if email already exists
    SELECT COUNT(*) INTO v_existing_count
    FROM users
    WHERE email = p_email AND deleted_at IS NULL;
    
    IF v_existing_count > 0 THEN
        SET p_result_code = '409';
        SET p_result_message = 'Email already registered';
        ROLLBACK;
    ELSE
        -- 2. Insert new user
        INSERT INTO users (
            user_id,
            email,
            password_hash,
            first_name,
            last_name,
            status,
            email_verified,
            created_at,
            updated_at,
            version
        ) VALUES (
            p_user_id,
            p_email,
            p_password_hash,
            p_first_name,
            p_last_name,
            'active',
            FALSE,
            NOW(),
            NOW(),
            1
        );
        
        -- 3. Log the operation
        CALL sp_log_operation(
            UUID(),
            'user_created',
            p_user_id,
            CONCAT('User registered: ', p_email),
            p_correlation_id
        );
        
        SET p_result_code = '201';
        SET p_result_message = 'User created successfully';
        COMMIT;
    END IF;
END//

DELIMITER ;
```

#### **3. Optimistic Locking Flow Example**

**Update User with Version Control**

```sql
DELIMITER //

CREATE PROCEDURE sp_update_user_with_locking(
    IN p_user_id VARCHAR(36),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_expected_version INT,
    IN p_correlation_id VARCHAR(36),
    OUT p_result_code VARCHAR(10),
    OUT p_result_message VARCHAR(255),
    OUT p_new_version INT
)
BEGIN
    DECLARE v_current_version INT DEFAULT 0;
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_result_code = '500';
        SET p_result_message = 'Database error occurred';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- 1. Get current version
    SELECT version INTO v_current_version
    FROM users
    WHERE user_id = p_user_id AND deleted_at IS NULL
    FOR UPDATE;
    
    -- 2. Check version conflict
    IF v_current_version != p_expected_version THEN
        SET p_result_code = '409';
        SET p_result_message = CONCAT(
            'Version conflict: expected ', p_expected_version,
            ', current ', v_current_version
        );
        SET p_new_version = v_current_version;
        ROLLBACK;
    ELSE
        -- 3. Update with version increment
        UPDATE users SET
            first_name = p_first_name,
            last_name = p_last_name,
            phone = p_phone,
            updated_at = NOW(),
            version = version + 1
        WHERE user_id = p_user_id AND version = p_expected_version;
        
        GET DIAGNOSTICS v_affected_rows = ROW_COUNT;
        
        IF v_affected_rows = 0 THEN
            SET p_result_code = '409';
            SET p_result_message = 'Concurrent modification detected';
            ROLLBACK;
        ELSE
            SET p_new_version = p_expected_version + 1;
            
            -- 4. Log the update operation
            CALL sp_log_operation(
                UUID(),
                'user_updated',
                p_user_id,
                CONCAT('User updated from version ', p_expected_version, ' to ', p_new_version),
                p_correlation_id
            );
            
            SET p_result_code = '200';
            SET p_result_message = 'User updated successfully';
            COMMIT;
        END IF;
    END IF;
END//

DELIMITER ;
```

### **MongoDB Logging Architecture**

#### **Collections Structure**
```javascript
// operation_logs collection
{
  "_id": ObjectId("...")
  "operation_id": "op_123456789",
  "correlation_id": "req_123456",
  "transaction_id": "txn_789012",
  "operation_type": "user_created",
  "entity_id": "550e8400-e29b-41d4-a716-446655440000",
  "entity_type": "user",
  "description": "User registered: user@example.com",
  "metadata": {
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "endpoint": "/api/v1/auth/register",
    "method": "POST"
  },
  "created_at": ISODate("2026-01-26T10:00:00Z")
}

// request_logs collection
{
  "_id": ObjectId("..."),
  "correlation_id": "req_123456",
  "transaction_id": "txn_789012",
  "request_id": "request_456789",
  "method": "POST",
  "endpoint": "/api/v1/auth/register",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_size": 245,
  "response_size": 512,
  "status_code": 201,
  "response_time": 156,
  "timestamp": ISODate("2026-01-26T10:00:00Z")
}
```

---

## 🔐 **Authentication APIs**

### 1. User Registration (Email/Password)

**Endpoint:** `POST /api/v1/auth/register`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "email": "user@example.com",
    "password": "SecurePassword123!",
    "first_name": "John",
    "last_name": "Doe"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "auth.register.success",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "email": "user@example.com"
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

**End-to-End Flow:**
1. **Client Request**: POST data to `/api/v1/auth/register`
2. **Nginx/Route**: Request routed to `AuthController::register()`
3. **Middleware Stack**: CORS → Rate Limit → Timeout → Error Sanitization → JSON Parser → CSRF → Input Validation
4. **Controller Layer**: `AuthController::register()`
   - Creates `CreateUserRequest` DTO from request
   - Validates request structure and data types
5. **Service Layer**: `AuthService::registerUser()`
   - Business logic validation (email format, password strength)
   - Password hashing using `PASSWORD_ARGON2ID`
   - UUID generation for user ID
6. **DAO Layer**: `UserDAO::createUser()`
   - Prepares stored procedure call `sp_create_user`
   - Binds parameters (user_id, email, password_hash, names, correlation_id)
   - Executes procedure with error handling
7. **Database Layer**: `sp_create_user` stored procedure
   - Checks email uniqueness
   - Inserts user record with status 'active'
   - Logs operation via `sp_log_operation`
   - Returns success/failure codes
8. **MongoDB Logging**: Operation logged to `operation_logs` collection
9. **Response Assembly**: Success response with correlation ID
10. **Client Response**: JSON with user details and HTTP 201

**Database Operations:**
```sql
-- Primary table insert
INSERT INTO users (user_id, email, password_hash, first_name, last_name, status, version)
-- Operation logging
INSERT INTO operation_logs (operation_id, operation_type, entity_id, correlation_id)
-- MongoDB audit trail
db.operation_logs.insertOne({correlation_id, operation_type: 'user_created'})
```

---

### 2. User Login (Email/Password)

**Endpoint:** `POST /api/v1/auth/login`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "identifier": "user@example.com",
    "password": "SecurePassword123!"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "auth.login.success",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 3600,
        "user": {
            "user_id": "550e8400-e29b-41d4-a716-446655440000",
            "email": "user@example.com",
            "first_name": "John",
            "last_name": "Doe"
        }
    }
}
```

**End-to-End Flow:**
1. Client provides credentials
2. User retrieved via `sp_get_user_by_email`
3. Password verified using `password_verify()`
4. JWT tokens generated (access + refresh)
5. User session created via `sp_create_user_session`
6. Tokens returned with expiration info

---

### 3. Phone Registration Initiation

**Endpoint:** `POST /api/v1/auth/register/phone`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "phone": "+1234567890",
    "first_name": "John",
    "last_name": "Doe",
    "password": "SecurePassword123!"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Registration initiated, OTP sent to phone number",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "otp_id": "otp_123456",
        "expires_at": "2026-01-26T10:10:00Z",
        "next_step": "verify_phone_registration",
        "dev_otp": "123456"
    }
}
```

**End-to-End Flow:**
1. Client provides phone and user details
2. Phone number validated (format, uniqueness)
3. User created with `pending_phone_verification` status
4. OTP generated and stored via `sp_register_user_with_phone`
5. SMS sent (mocked in development)
6. OTP details returned for verification step

---

### 4. Phone Registration Verification

**Endpoint:** `POST /api/v1/auth/register/phone/verify`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "phone": "+1234567890",
    "otp": "123456"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Registration completed successfully",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "status": "phone_verified",
        "next_step": "login"
    }
}
```

**End-to-End Flow:**
1. Client provides phone and OTP
2. OTP verified via `sp_verify_phone_registration`
3. User status updated to `active`
4. Phone marked as verified
5. Registration completion confirmed

---

### 5. Phone Login Initiation

**Endpoint:** `POST /api/v1/auth/login/phone`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "phone": "+1234567890"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login OTP sent to phone number",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "otp_id": "otp_789012",
        "expires_at": "2026-01-26T10:10:00Z",
        "next_step": "verify_phone_login",
        "dev_otp": "654321"
    }
}
```

**End-to-End Flow:**
1. Client provides phone number
2. User existence and status verified
3. Login OTP generated via `sp_initiate_phone_login`
4. Previous login OTPs invalidated
5. SMS sent with new OTP

---

### 6. Phone Login Verification

**Endpoint:** `POST /api/v1/auth/login/phone/verify`  
**Authentication:** None (Public)  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "phone": "+1234567890",
    "otp": "654321"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 3600,
        "user": {
            "user_id": "550e8400-e29b-41d4-a716-446655440000",
            "phone": "+1234567890",
            "first_name": "John",
            "last_name": "Doe",
            "status": "active"
        }
    }
}
```

**End-to-End Flow:**
1. **Client Request**: POST with phone and OTP to `/api/v1/auth/login/phone/verify`
2. **Middleware Processing**: CORS → Rate Limit → JSON Parser → Input Validation
3. **Controller**: `PhoneLoginController::verifyPhoneLogin()`
   - Creates `VerifyPhoneLoginRequest` DTO
   - Validates phone format and OTP format
4. **Service Layer**: `AuthService::verifyPhoneLogin()`
   - OTP expiration check
   - Rate limiting validation
5. **DAO Operations**: Multiple stored procedure calls
   - `sp_login_with_phone_otp()` - validates OTP and retrieves user
   - `sp_create_user_session()` - creates JWT session record
   - `sp_update_last_login()` - updates user login timestamp
6. **Database Transactions**:
   ```sql
   -- OTP validation and user retrieval
   CALL sp_login_with_phone_otp('+1234567890', '654321', 'req_123456')
   -- Session creation
   CALL sp_create_user_session('user_id', 'session_token', 'access_token')
   -- Login timestamp update
   UPDATE users SET last_login_at = NOW() WHERE user_id = ?
   ```
7. **JWT Token Generation**:
   - Access token (1 hour expiry)
   - Refresh token (30 days expiry)
   - Token claims: user_id, email, phone, roles, permissions
8. **MongoDB Logging**:
   ```javascript
   db.operation_logs.insertOne({
     correlation_id: 'req_123456',
     operation_type: 'phone_login_success',
     entity_id: 'user_id',
     metadata: { phone: '+1234567890', login_method: 'phone_otp' }
   })
   ```
9. **Response Assembly**: JWT tokens + user profile + correlation IDs
10. **Client Response**: Complete authentication payload

---

### 7. Token Refresh

**Endpoint:** `POST /api/v1/auth/refresh`  
**Authentication:** Refresh Token Required  
**Middlewares:** `['cors', 'rateLimit', 'jsonParser']`

**Request:**
```json
{
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "expires_in": 3600,
        "token_type": "Bearer"
    }
}
```

---

### 8. Logout

**Endpoint:** `POST /api/v1/auth/logout`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'rateLimit']`

**Request Headers:**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully",
    "data": {
        "message": "Session terminated"
    }
}
```

---

## 👤 **User Management APIs**

### 9. Get User Profile

**Endpoint:** `GET /api/v1/users/profile`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth']`

**Request Headers:**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Response (200):**
```json
{
    "success": true,
    "message": "Profile retrieved successfully",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "email": "user@example.com",
        "phone": "+1234567890",
        "first_name": "John",
        "last_name": "Doe",
        "status": "active",
        "email_verified": true,
        "phone_verified": true,
        "created_at": "2026-01-25T10:00:00Z",
        "version": 1
    }
}
```

---

### 10. Update User Profile (with Optimistic Locking)

**Endpoint:** `PUT /api/v1/users/profile`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'jsonParser']`

**Request:**
```json
{
    "first_name": "Jane",
    "last_name": "Smith",
    "phone": "+1987654321",
    "version": 1
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "first_name": "Jane",
        "last_name": "Smith",
        "phone": "+1987654321",
        "previous_version": 1,
        "new_version": 2,
        "updated_at": "2026-01-26T10:05:00Z"
    }
}
```

**Version Conflict (409):**
```json
{
    "success": false,
    "error": "CONFLICT",
    "message": "Record has been modified by another process. Please refresh and try again.",
    "data": {
        "expected_version": 1,
        "current_version": 3,
        "conflict_type": "version_mismatch"
    }
}
```

**End-to-End Flow:**
1. **Client Request**: PUT with user data + version to `/api/v1/users/profile`
2. **Authentication**: JWT token validation and user extraction
3. **Middleware Stack**: CORS → Auth → JSON Parser → Input Validation → CSRF
4. **Controller**: `UserController::updateProfile()`
   - Creates `UpdateUserRequest` DTO with version
   - Validates request payload structure
5. **Service Layer**: `UserService::updateProfile()`
   - Business rules validation (phone format, name length)
   - Authorization check (user can only update own profile)
6. **DAO Layer**: `UserDAO::updateUserWithLocking()`
   - Calls `sp_update_user_with_locking` stored procedure
   - Handles version conflict responses
7. **Database Optimistic Locking Flow**:
   ```sql
   START TRANSACTION;
   
   -- 1. Lock and get current version
   SELECT version FROM users WHERE user_id = ? FOR UPDATE;
   
   -- 2. Version comparison
   IF current_version != expected_version THEN
       ROLLBACK; -- Return 409 Conflict
   ELSE
       -- 3. Update with version increment
       UPDATE users SET 
           first_name = ?, 
           last_name = ?, 
           phone = ?,
           updated_at = NOW(),
           version = version + 1
       WHERE user_id = ? AND version = expected_version;
       
       -- 4. Log operation
       INSERT INTO operation_logs (...)
       
       COMMIT;
   END IF;
   ```
8. **Concurrency Handling**:
   - **Success Case**: Version incremented (1 → 2), changes saved
   - **Conflict Case**: 409 response with current version info
9. **MongoDB Audit Trail**:
   ```javascript
   db.operation_logs.insertOne({
     operation_type: 'user_profile_updated',
     entity_id: 'user_id',
     changes: { first_name: 'old → new', version: '1 → 2' },
     correlation_id: 'req_123456'
   })
   ```
10. **Response**: Updated user data with new version or conflict details

---

### 11. Email Verification

**Endpoint:** `POST /api/v1/users/verify-email`  
**Authentication:** None (Token-based)  
**Middlewares:** `['cors', 'jsonParser']`

**Request:**
```json
{
    "token": "email_verify_token_123456"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Email verified successfully",
    "data": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "email": "user@example.com",
        "verified_at": "2026-01-26T10:00:00Z"
    }
}
```

---

## 🛡️ **Role & Permission Management APIs**

### 12. Get All Roles

**Endpoint:** `GET /api/v1/roles`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:roles:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Roles retrieved successfully",
    "data": {
        "roles": [
            {
                "role_id": "role_123456",
                "name": "admin",
                "description": "System Administrator",
                "priority": 100,
                "is_system_role": true,
                "version": 1,
                "created_at": "2026-01-25T10:00:00Z"
            },
            {
                "role_id": "role_789012",
                "name": "user",
                "description": "Regular User",
                "priority": 10,
                "is_system_role": true,
                "version": 1,
                "created_at": "2026-01-25T10:00:00Z"
            }
        ],
        "total_count": 2
    }
}
```

---

### 13. Update Role (with Optimistic Locking)

**Endpoint:** `PUT /api/v1/roles/{role_id}`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:roles:update', 'jsonParser']`

**Request:**
```json
{
    "description": "Updated Administrator Role",
    "priority": 110,
    "version": 1
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Role updated successfully",
    "data": {
        "role_id": "role_123456",
        "name": "admin",
        "description": "Updated Administrator Role",
        "priority": 110,
        "previous_version": 1,
        "new_version": 2,
        "updated_at": "2026-01-26T10:05:00Z"
    }
}
```

---

### 14. Soft Delete Role

**Endpoint:** `DELETE /api/v1/roles/{role_id}`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:roles:delete']`

**Response (200):**
```json
{
    "success": true,
    "message": "Role deleted successfully",
    "data": {
        "role_id": "role_123456",
        "deleted_at": "2026-01-26T10:10:00Z",
        "affected_rows": 1
    }
}
```

---

## 🏥 **System Health APIs**

### 15. Liveness Probe

**Endpoint:** `GET /health`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response (200):**
```json
{
    "success": true,
    "message": "health.ok",
    "data": {
        "status": "ok",
        "service": "PHPFrarm API",
        "timestamp": "2026-01-26T10:00:00Z"
    }
}
```

---

### 16. Readiness Probe

**Endpoint:** `GET /health/ready`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response (200):**
```json
{
    "success": true,
    "message": "All systems ready",
    "data": {
        "status": "ready",
        "checks": {
            "mysql": {
                "status": "ok",
                "response_time": 2.5
            },
            "mongodb": {
                "status": "ok",
                "response_time": 1.8
            },
            "redis": {
                "status": "ok",
                "response_time": 0.5
            }
        },
        "timestamp": "2026-01-26T10:00:00Z"
    }
}
```

---

### 17. Detailed Health Check

**Endpoint:** `GET /health/detailed`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:health']`

**Response (200):**
```json
{
    "success": true,
    "message": "Detailed health check completed",
    "data": {
        "status": "healthy",
        "uptime": "2 days, 14 hours, 32 minutes",
        "memory_usage": {
            "current": "256MB",
            "peak": "312MB",
            "limit": "512MB"
        },
        "database": {
            "mysql": {
                "status": "connected",
                "version": "8.0.35",
                "connections": 12,
                "slow_queries": 0
            },
            "mongodb": {
                "status": "connected",
                "version": "7.0.5",
                "collections": 8,
                "indexes": 24
            }
        },
        "cache": {
            "redis": {
                "status": "connected",
                "version": "7.2.3",
                "memory_used": "45MB",
                "keys": 1250
            }
        }
    }
}
```

---

## 📂 **Storage APIs**

### 18. Upload File

**Endpoint:** `POST /api/v1/storage/upload`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:files:upload']`

**Request (Multipart):**
```
Content-Type: multipart/form-data
file: [binary data]
path: /documents/contracts/
metadata: {"description": "Contract document", "category": "legal"}
```

**Response (201):**
```json
{
    "success": true,
    "message": "File uploaded successfully",
    "data": {
        "file_id": "file_123456789",
        "filename": "contract.pdf",
        "path": "/documents/contracts/contract.pdf",
        "size": 2048576,
        "mime_type": "application/pdf",
        "checksum": "sha256:abcd1234...",
        "uploaded_at": "2026-01-26T10:15:00Z",
        "expires_at": null
    }
}
```

---

## �️ **Cache Management APIs**

### 21. Get Cache Statistics

**Endpoint:** `GET /api/v1/system/cache/statistics`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:cache:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Cache statistics retrieved successfully",
    "data": {
        "statistics": {
            "hits": 15420,
            "misses": 3210,
            "hit_ratio": 0.827,
            "memory_usage": "128MB",
            "total_keys": 1847,
            "evictions": 45
        },
        "enabled": true,
        "query_cache_enabled": true
    }
}
```

---

### 22. Clear All Cache

**Endpoint:** `POST /api/v1/system/cache/clear`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:cache:manage']`

**Response (200):**
```json
{
    "success": true,
    "message": "All cache cleared successfully",
    "data": {
        "cleared": true,
        "keys_cleared": 1847,
        "memory_freed": "128MB"
    }
}
```

---

### 23. Clear Cache by Tags

**Endpoint:** `POST /api/v1/system/cache/clear-tags`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:cache:manage']`

**Request:**
```json
{
    "tags": ["users", "roles", "permissions"]
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Cache cleared by tags",
    "data": {
        "tags": ["users", "roles", "permissions"],
        "keys_cleared": 342
    }
}
```

---

### 24. Clear Cache by Pattern

**Endpoint:** `POST /api/v1/system/cache/clear-pattern`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:cache:manage']`

**Request:**
```json
{
    "pattern": "user:*"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Cache cleared by pattern",
    "data": {
        "pattern": "user:*",
        "keys_cleared": 156
    }
}
```

---

### 25. Delete Specific Cache Key

**Endpoint:** `DELETE /api/v1/system/cache/keys/{key}`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:cache:manage']`

**Response (200):**
```json
{
    "success": true,
    "message": "Cache key deleted",
    "data": {
        "key": "user:123456",
        "deleted": true
    }
}
```

---

## 🛡️ **Security Management APIs**

### 26. Security Overview Dashboard

**Endpoint:** `GET /api/v1/system/security/overview`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:security:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Security overview retrieved",
    "data": {
        "threat_level": "low",
        "active_threats": 2,
        "blocked_ips": 45,
        "bot_detection": {
            "enabled": true,
            "blocked_today": 12,
            "false_positives": 1
        },
        "waf_status": {
            "enabled": true,
            "rules_active": 127,
            "blocked_requests": 89
        },
        "anomaly_detection": {
            "enabled": true,
            "anomalies_detected": 3,
            "auto_mitigation": true
        }
    }
}
```

---

### 27. IP Reputation Management

**Endpoint:** `POST /api/v1/system/security/ip/blacklist`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:security:manage']`

**Request:**
```json
{
    "ip_address": "192.168.1.100",
    "reason": "Multiple failed login attempts",
    "duration": 3600
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "IP address blacklisted",
    "data": {
        "ip_address": "192.168.1.100",
        "blacklisted_at": "2026-01-26T10:00:00Z",
        "expires_at": "2026-01-26T11:00:00Z",
        "reason": "Multiple failed login attempts"
    }
}
```

---

### 28. Security Event Logs

**Endpoint:** `GET /api/v1/system/security/events`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:security:read']`

**Query Parameters:**
- `limit`: Number of events (default: 50, max: 500)
- `severity`: Event severity (low, medium, high, critical)
- `type`: Event type (bot_detection, ip_blocked, waf_triggered, etc.)

**Response (200):**
```json
{
    "success": true,
    "message": "Security events retrieved",
    "data": {
        "events": [
            {
                "event_id": "evt_123456",
                "type": "bot_detection",
                "severity": "medium",
                "ip_address": "203.0.113.42",
                "description": "Suspicious bot behavior detected",
                "action_taken": "request_blocked",
                "timestamp": "2026-01-26T09:45:00Z"
            }
        ],
        "total_count": 1,
        "has_more": false
    }
}
```

---

## 🚦 **Traffic Management APIs**

### 29. Rate Limit Statistics

**Endpoint:** `GET /api/v1/system/traffic/rate-limit/stats`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:traffic:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Rate limit statistics retrieved",
    "data": {
        "global_stats": {
            "total_requests": 125430,
            "blocked_requests": 1247,
            "block_rate": 0.0099
        },
        "by_endpoint": {
            "/api/v1/auth/login": {
                "requests": 15420,
                "blocked": 234,
                "limit": 10,
                "window": 60
            }
        },
        "top_blocked_ips": [
            {
                "ip": "203.0.113.42",
                "blocked_count": 45,
                "last_blocked": "2026-01-26T09:30:00Z"
            }
        ]
    }
}
```

---

### 30. Client Quota Status

**Endpoint:** `GET /api/v1/system/traffic/quota/{clientId}`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:traffic:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Client quota status retrieved",
    "data": {
        "client_id": "client_123456",
        "quota": {
            "daily_limit": 10000,
            "daily_used": 3245,
            "daily_remaining": 6755,
            "monthly_limit": 300000,
            "monthly_used": 45230,
            "monthly_remaining": 254770
        },
        "status": "active",
        "reset_times": {
            "daily_reset": "2026-01-27T00:00:00Z",
            "monthly_reset": "2026-02-01T00:00:00Z"
        }
    }
}
```

---

### 31. Reset Rate Limits

**Endpoint:** `POST /api/v1/system/traffic/rate-limit/reset`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:traffic:manage']`

**Request:**
```json
{
    "target_type": "ip",
    "target_value": "192.168.1.100",
    "reason": "False positive - legitimate traffic"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Rate limit reset successfully",
    "data": {
        "target_type": "ip",
        "target_value": "192.168.1.100",
        "reset_at": "2026-01-26T10:05:00Z",
        "reason": "False positive - legitimate traffic"
    }
}
```

---

## ⚡ **System Resilience APIs**

### 32. Retry Statistics

**Endpoint:** `GET /api/v1/system/resilience/retry/stats`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Retry statistics retrieved",
    "data": {
        "stats": {
            "database_operations": {
                "total_operations": 125430,
                "total_retries": 342,
                "total_successes": 125088,
                "total_failures": 0,
                "retry_rate": 0.0027
            },
            "external_api_calls": {
                "total_operations": 5420,
                "total_retries": 123,
                "total_successes": 5397,
                "total_failures": 0,
                "retry_rate": 0.0227
            }
        },
        "total_operations": 130850,
        "total_retries": 465,
        "total_successes": 130485,
        "total_failures": 0
    }
}
```

---

### 33. Circuit Breaker Status

**Endpoint:** `GET /api/v1/system/resilience/circuit-breaker/status`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:read']`

**Query Parameters:**
- `service`: Service name (optional)

**Response (200):**
```json
{
    "success": true,
    "message": "Circuit breaker status retrieved",
    "data": {
        "services": {
            "payment_gateway": {
                "state": "closed",
                "failure_count": 2,
                "failure_threshold": 5,
                "last_failure": "2026-01-26T09:30:00Z",
                "next_retry": null
            },
            "email_service": {
                "state": "half_open",
                "failure_count": 5,
                "failure_threshold": 5,
                "last_failure": "2026-01-26T09:45:00Z",
                "next_retry": "2026-01-26T10:15:00Z"
            }
        }
    }
}
```

---

### 34. Reset Circuit Breaker

**Endpoint:** `POST /api/v1/system/resilience/circuit-breaker/reset`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:manage']`

**Request:**
```json
{
    "service": "email_service",
    "reason": "Service restored - manual reset"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Circuit breaker reset successfully",
    "data": {
        "service": "email_service",
        "previous_state": "open",
        "new_state": "closed",
        "reset_at": "2026-01-26T10:10:00Z",
        "reason": "Service restored - manual reset"
    }
}
```

---

## 🔒 **Optimistic Locking Management APIs**

### 35. Locking Statistics

**Endpoint:** `GET /api/v1/system/locking/statistics`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Locking statistics retrieved",
    "data": {
        "total_operations": 45230,
        "total_conflicts": 123,
        "conflict_rate": 0.0027,
        "entities": {
            "users": {
                "operations": 25430,
                "conflicts": 67,
                "conflict_rate": 0.0026
            },
            "roles": {
                "operations": 2340,
                "conflicts": 12,
                "conflict_rate": 0.0051
            },
            "permissions": {
                "operations": 1560,
                "conflicts": 8,
                "conflict_rate": 0.0051
            }
        }
    }
}
```

---

### 36. Top Conflicting Entities

**Endpoint:** `GET /api/v1/system/locking/conflicts/top`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:read']`

**Response (200):**
```json
{
    "success": true,
    "message": "Top conflicts retrieved",
    "data": {
        "top_conflicts": [
            {
                "entity_type": "users",
                "entity_id": "user_123456",
                "conflicts": 15,
                "last_conflict": "2026-01-26T09:45:00Z"
            },
            {
                "entity_type": "roles",
                "entity_id": "role_admin",
                "conflicts": 8,
                "last_conflict": "2026-01-26T09:30:00Z"
            }
        ],
        "total_entities": 25
    }
}
```

---

## 📚 **Documentation APIs**

### 37. Swagger UI Interface

**Endpoint:** `GET /docs`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response:** HTML page with Swagger UI interface for interactive API exploration

---

### 38. OpenAPI Specification

**Endpoint:** `GET /docs/openapi.json`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response (200):**
```json
{
    "openapi": "3.0.3",
    "info": {
        "title": "PHPFrarm Enterprise API",
        "version": "1.0.0",
        "description": "Complete API specification for PHPFrarm framework"
    },
    "servers": [
        {
            "url": "http://localhost:8787/api/v1",
            "description": "Development server"
        }
    ],
    "paths": {
        "/auth/register": {
            "post": {
                "summary": "User registration",
                "requestBody": {
                    "content": {
                        "application/json": {
                            "schema": {
                                "type": "object",
                                "properties": {
                                    "email": {"type": "string", "format": "email"},
                                    "password": {"type": "string", "minLength": 8}
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
```

---

### 39. Error Catalog

**Endpoint:** `GET /docs/errors`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response:** Markdown document with all error codes and descriptions

---

### 40. Postman Collection

**Endpoint:** `GET /docs/postman`  
**Authentication:** None (Public)  
**Middlewares:** `['cors']`

**Response (200):**
```json
{
    "info": {
        "name": "PHPFrarm API Collection",
        "description": "Complete API collection for PHPFrarm framework",
        "version": "1.0.0"
    },
    "item": [
        {
            "name": "Authentication",
            "item": [
                {
                    "name": "User Registration",
                    "request": {
                        "method": "POST",
                        "url": "{{baseUrl}}/auth/register",
                        "body": {
                            "mode": "raw",
                            "raw": "{\n  \"email\": \"user@example.com\",\n  \"password\": \"SecurePassword123!\"\n}"
                        }
                    }
                }
            ]
        }
    ]
}
```

---

## �📊 **System Administration APIs**

### 19. Legacy Cache Clear (Deprecated - Use Cache Management APIs)

**Endpoint:** `DELETE /api/v1/system/cache`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:cache:clear']`

**Note:** This endpoint is deprecated. Use `/api/v1/system/cache/clear` instead.

**Response (200):**
```json
{
    "success": true,
    "message": "Cache cleared successfully",
    "data": {
        "cleared_keys": 1247,
        "memory_freed": "128MB",
        "operation_time": "0.245s"
    }
}
```

---

### 20. Security Scan

**Endpoint:** `POST /api/v1/system/security/scan`  
**Authentication:** Bearer Token Required  
**Middlewares:** `['cors', 'auth', 'permission:system:security:scan']`

**Response (200):**
```json
{
    "success": true,
    "message": "Security scan completed",
    "data": {
        "scan_id": "scan_987654321",
        "status": "completed",
        "threats_found": 0,
        "warnings": 2,
        "checks_performed": {
            "sql_injection": "passed",
            "xss_protection": "passed",
            "csrf_protection": "passed",
            "rate_limiting": "passed",
            "authentication": "passed",
            "authorization": "warning"
        },
        "recommendations": [
            "Consider implementing additional permission granularity for admin roles"
        ]
    }
}
```

---

## 🚨 **Error Response Format**

All APIs follow consistent error response format:

**Validation Error (400):**
```json
{
    "success": false,
    "error": "VALIDATION_FAILED",
    "message": "Request validation failed",
    "data": {
        "errors": {
            "email": ["Email format is invalid"],
            "password": ["Password must be at least 8 characters"]
        }
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

**Authentication Error (401):**
```json
{
    "success": false,
    "error": "UNAUTHORIZED",
    "message": "Authentication required",
    "data": {
        "required_auth": "Bearer token"
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

**Permission Error (403):**
```json
{
    "success": false,
    "error": "FORBIDDEN",
    "message": "Insufficient permissions",
    "data": {
        "required_permission": "users:create",
        "user_permissions": ["users:read"]
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

**Version Conflict (409):**
```json
{
    "success": false,
    "error": "CONFLICT",
    "message": "Resource has been modified by another process",
    "data": {
        "expected_version": 1,
        "current_version": 3,
        "conflict_type": "version_mismatch"
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

**Rate Limit Error (429):**
```json
{
    "success": false,
    "error": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests",
    "data": {
        "limit": 100,
        "window": "1 hour",
        "retry_after": 3600
    },
    "correlation_id": "req_123456",
    "timestamp": "2026-01-26T10:00:00Z"
}
```

---

## ⚙️ **Middleware Processing Pipeline**

### **Complete Middleware Stack Processing**

Every API request goes through this exact sequence:

#### **1. CORS Middleware** (`CorsMiddleware.php`)
```php
// Handles preflight OPTIONS requests
// Sets headers: Access-Control-Allow-Origin, Access-Control-Allow-Methods
// Validates origin against whitelist
if ($request->getMethod() === 'OPTIONS') {
    return $this->handlePreflightRequest($request);
}
```

#### **2. Rate Limiting Middleware** (`RateLimitMiddleware.php`)
```php
// Redis-based rate limiting per IP/user
$key = 'rate_limit:' . $clientIp . ':' . $endpoint;
$current = $redis->incr($key);
if ($current > $limit) {
    throw new TooManyRequestsException('Rate limit exceeded');
}
```

#### **3. Timeout Middleware** (`TimeoutMiddleware.php`)
```php
// Sets maximum execution time per request
set_time_limit($this->maxExecutionTime);
register_shutdown_function(function() {
    if (connection_status() === CONNECTION_TIMEOUT) {
        $this->logTimeout();
    }
});
```

#### **4. Error Sanitization Middleware** (`ErrorSanitizationMiddleware.php`)
```php
// Wraps request processing with error handling
try {
    $response = $next($request);
} catch (Throwable $e) {
    // Log full error details
    $this->logger->error($e);
    // Return sanitized error to client
    return $this->sanitizeError($e);
}
```

#### **5. Authentication Middleware** (`AuthMiddleware.php`)
```php
// JWT token validation and user context setup
$token = $this->extractBearerToken($request);
$payload = JWT::decode($token, $this->jwtKey, ['HS256']);
$user = $this->userService->getById($payload->user_id);
$request->setAttribute('authenticated_user', $user);
```

#### **6. CSRF Middleware** (`CsrfMiddleware.php`)
```php
// Validates CSRF token for state-changing requests
if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    $csrfToken = $request->getHeader('X-CSRF-Token');
    if (!$this->csrfTokenManager->isValid($csrfToken)) {
        throw new CsrfTokenException('Invalid CSRF token');
    }
}
```

#### **7. Idempotency Middleware** (`IdempotencyMiddleware.php`)
```php
// Handles duplicate request prevention
$idempotencyKey = $request->getHeader('Idempotency-Key');
if ($idempotencyKey) {
    $cached = $this->getCachedResponse($idempotencyKey);
    if ($cached) {
        return $cached; // Return previous response
    }
    // Store response after processing
    $this->storeResponse($idempotencyKey, $response);
}
```

#### **8. Input Validation Middleware** (`InputValidationMiddleware.php`)
```php
// Validates request payload against schema
$validator = $this->getValidatorForRoute($request->getUri()->getPath());
$errors = $validator->validate($request->getParsedBody());
if (!empty($errors)) {
    throw new ValidationException('Invalid input data', $errors);
}
```

### **Middleware Configuration Example**

```php
// Route with middleware stack
#[Route('POST', '/api/v1/auth/register', [
    'cors',           // Always first
    'rateLimit',      // Protect against abuse
    'timeout',        // Prevent hanging requests
    'errorSanitization', // Wrap error handling
    'jsonParser',     // Parse JSON body
    'csrf',          // CSRF protection
    'inputValidation', // Validate request data
])]
public function register(Request $request): JsonResponse
{
    // Controller logic here - all validation already done
}
```

---

## 🛡️ **Security Features**

### Automatic Protections

All APIs include these security features by default:

1. **CSRF Protection**: Automatic token validation for state-changing methods
2. **Rate Limiting**: Request throttling per IP/user  
3. **Request Timeouts**: Configurable timeouts to prevent resource exhaustion
4. **Error Sanitization**: Internal errors never exposed to clients
5. **Correlation ID Tracking**: Full request traceability
6. **Input Validation**: Comprehensive payload validation
7. **SQL Injection Prevention**: Only stored procedures allowed
8. **XSS Protection**: Input sanitization and output encoding

### Headers

**Standard Request Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-Correlation-Id: req_123456 (auto-generated if not provided)
X-CSRF-Token: csrf_token_789012 (required for state-changing requests)
Idempotency-Key: idem_key_456789 (optional, for idempotent operations)
```

**Standard Response Headers:**
```
X-Correlation-Id: req_123456
X-Transaction-Id: txn_789012
X-Request-Id: request_456789
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
```

---

## 📈 **Performance Features**

1. **Idempotency Support**: Use `Idempotency-Key` header to prevent duplicate operations
2. **Optimistic Locking**: Version-based concurrency control  
3. **Soft Delete**: Logical deletion with restore capability
4. **Database Optimization**: All operations via stored procedures
5. **Response Caching**: Intelligent caching for read operations
6. **Connection Pooling**: Efficient database connection management

---

## 🔧 **Development & Testing**

### Development Environment Features:

1. **Debug Information**: Additional debug data in development mode
2. **Test OTP Codes**: OTP codes included in development responses
3. **Detailed Error Messages**: More verbose error information
4. **Request Logging**: Comprehensive request/response logging

### Testing Support:

1. **Contract Testing**: OpenAPI schema validation
2. **Load Testing**: Performance benchmarking endpoints
3. **Security Testing**: Automated security scan endpoints
4. **Health Monitoring**: Comprehensive health check endpoints

---

**Generated by PHPFrarm Framework**  
**Version:** 1.0 Enterprise  
**Documentation Updated:** January 26, 2026
