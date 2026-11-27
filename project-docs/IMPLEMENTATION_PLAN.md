# Implementation Plan: Expense Management System API

## Overview

This plan converts the technical audit findings into actionable implementation tasks organized by phase, priority, and dependencies.

---

## Phase 1: Production Hardening (Weeks 1-4)

This phase focuses on making the application production-ready by fixing critical issues and implementing essential security features.

---

## Task P1-001: Implement Global Exception Handler

**ID**: P1-001  
**Priority**: CRITICAL  
**Complexity**: Medium  
**Estimated Time**: 4 hours  
**Dependencies**: None  

**Description**:  
Extend the application's exception handler to return consistent JSON responses for all errors, including unhandled exceptions, validation errors, and HTTP errors.

**Files Affected**:
- `app/Exceptions/Handler.php`
- `app/Http/Middleware/HandleJsonExceptions.php` (new)

**Expected Outcome**:
- All exceptions return JSON in the format: `{"status": "error", "message": "...", "errors": {...}}`
- 404 responses return JSON
- 500 errors return JSON without exposing stack traces in production
- 422 validation errors return JSON with field details

**Acceptance Criteria**:
- [ ] Unhandled exception returns 500 JSON response
- [ ] 404 endpoints return 404 JSON response
- [ ] Validation errors return 422 JSON response
- [ ] Stack traces hidden in production mode
- [ ] All tests still pass

---

## Task P1-002: Create API Documentation (OpenAPI/Swagger)

**ID**: P1-002  
**Priority**: CRITICAL  
**Complexity**: Large  
**Estimated Time**: 12 hours  
**Dependencies**: P1-001  

**Description**:  
Generate and maintain comprehensive API documentation in OpenAPI 3.0 format, including all endpoints, parameters, responses, error codes, and examples.

**Files Affected**:
- `docs/openapi.yaml` (new)
- `docs/endpoints.md` (new)
- Controllers (add PHPDoc comments)

**Expected Outcome**:
- Swagger UI accessible at `/api/docs`
- All endpoints documented with request/response examples
- Error responses documented
- Authentication requirements documented
- Pagination behavior documented

**Acceptance Criteria**:
- [ ] OpenAPI spec is valid and passes schema validation
- [ ] All endpoints documented with examples
- [ ] Response schemas include all fields
- [ ] Error responses documented (401, 403, 404, 422, 500)
- [ ] Can be imported into Postman/Insomnia
- [ ] All tests still pass

---

## Task P1-003: Remove Circular Resource Relationships

**ID**: P1-003  
**Priority**: CRITICAL  
**Complexity**: Medium  
**Estimated Time**: 6 hours  
**Dependencies**: P1-001  

**Description**:  
Refactor resource classes to prevent infinite nesting of related resources. Implement conditional loading based on query parameters.

**Files Affected**:
- `app/Http/Resources/UserResource.php`
- `app/Http/Resources/ExpenseResource.php`
- `app/Http/Resources/CompanyResource.php`
- Controllers (add `?include=` parameter support)

**Expected Outcome**:
- Resources don't include nested resources by default
- API consumers can optionally include related data via `?include=user,company`
- Prevents accidental infinite nesting
- Smaller response payloads by default
- Better performance

**Acceptance Criteria**:
- [ ] `GET /api/expenses` returns minimal data (no user object by default)
- [ ] `GET /api/expenses?include=user` returns expense with user data
- [ ] `GET /api/expenses?include=user,company` returns both
- [ ] Invalid include parameters are rejected (400 error)
- [ ] All existing tests updated and passing
- [ ] Response size reduced by average 60%

---

## Task P1-004: Implement Rate Limiting on Authentication Endpoints

**ID**: P1-004  
**Priority**: CRITICAL  
**Complexity**: Small  
**Estimated Time**: 2 hours  
**Dependencies**: None  

**Description**:  
Add rate limiting middleware to prevent brute force attacks on login and registration endpoints.

**Files Affected**:
- `routes/api.php`
- `app/Http/Middleware/ThrottleRequests.php` (configure)

**Expected Outcome**:
- `/api/login` limited to 5 attempts per minute per IP
- `/api/register` limited to 3 attempts per hour per IP
- 429 Too Many Requests response when exceeded
- Clear retry-after header

**Acceptance Criteria**:
- [ ] 5 login attempts per minute allowed
- [ ] 6th attempt returns 429 response
- [ ] Rate limit resets after minute expires
- [ ] Registration limited to 3 per hour
- [ ] Rate limits bypass for authenticated users (optional)
- [ ] All tests still pass

---

## Task P1-005: Implement Email Verification

**ID**: P1-005  
**Priority**: HIGH  
**Complexity**: Medium  
**Estimated Time**: 6 hours  
**Dependencies**: P1-001  

**Description**:  
Add email verification workflow to registration. Users must verify email before accessing most endpoints.

**Files Affected**:
- `database/migrations/*_add_email_verified_at_to_users_table.php` (new)
- `app/Models/User.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Mail/VerifyEmailMail.php` (new)
- `routes/api.php` (new endpoints)

**Expected Outcome**:
- `/api/register` sends verification email
- `/api/verify-email` accepts verification token
- `/api/resend-verification-email` resends token
- Unverified users cannot access protected endpoints
- Tokens expire after 24 hours

**Acceptance Criteria**:
- [ ] Verification email sent on registration
- [ ] Token in email is valid and unique
- [ ] `/api/verify-email` marks email as verified
- [ ] Unverified users get 403 Forbidden on protected endpoints
- [ ] Token expires after 24 hours
- [ ] Invalid/expired token returns 400 Bad Request
- [ ] New tests verify workflow
- [ ] All existing tests still pass

---

## Task P1-006: Implement Password Reset Functionality

**ID**: P1-006  
**Priority**: HIGH  
**Complexity**: Medium  
**Estimated Time**: 6 hours  
**Dependencies**: P1-001  

**Description**:  
Add password reset workflow. Users can reset forgotten passwords via email link.

**Files Affected**:
- `database/migrations/*_create_password_reset_tokens_table.php` (modify/update)
- `app/Http/Controllers/Api/AuthController.php`
- `app/Mail/ResetPasswordMail.php` (new)
- `routes/api.php` (new endpoints)

**Expected Outcome**:
- `/api/forgot-password` sends reset email
- Email contains reset link/token
- `/api/reset-password` accepts token and new password
- Token expires after 1 hour
- Reset invalidates all existing tokens

**Acceptance Criteria**:
- [ ] Forgot password endpoint accepts email
- [ ] Reset email sent if user exists
- [ ] Token is valid and unique
- [ ] `/api/reset-password` resets password
- [ ] Old tokens invalidated after reset
- [ ] Token expires after 1 hour
- [ ] Invalid/expired token returns 400 Bad Request
- [ ] New tests verify workflow
- [ ] All existing tests still pass

---

## Task P1-007: Implement Soft Deletes

**ID**: P1-007  
**Priority**: HIGH  
**Complexity**: Medium  
**Estimated Time**: 8 hours  
**Dependencies**: None  

**Description**:  
Add soft delete capability to User, Expense, and Company models. Enable data recovery and compliance with GDPR retention requirements.

**Files Affected**:
- `database/migrations/*_add_deleted_at_to_users_table.php` (new)
- `database/migrations/*_add_deleted_at_to_expenses_table.php` (new)
- `database/migrations/*_add_deleted_at_to_companies_table.php` (new)
- `app/Models/User.php`
- `app/Models/Expense.php`
- `app/Models/Company.php`
- Controllers (new restore endpoints)

**Expected Outcome**:
- Deleted records remain in database with `deleted_at` timestamp
- Queries exclude soft-deleted records by default
- Admins can restore deleted records
- Audit logs reference records correctly
- Cascade deletes updated for soft deletes

**Acceptance Criteria**:
- [ ] `SoftDeletes` trait added to all models
- [ ] `deleted_at` migrations created
- [ ] Queries exclude soft-deleted records by default
- [ ] `DELETE /api/expenses/{id}` soft-deletes record
- [ ] `POST /api/expenses/{id}/restore` restores record
- [ ] Only admins can restore records
- [ ] Audit logs reflect soft deletes
- [ ] All tests updated and passing

---

## Task P1-008: Create Form Request Classes & Custom Validation Messages

**ID**: P1-008  
**Priority**: HIGH  
**Complexity**: Small  
**Estimated Time**: 4 hours  
**Dependencies**: P1-001  

**Description**:  
Create Form Request classes for all endpoints to centralize validation rules and add user-friendly error messages.

**Files Affected**:
- `app/Http/Requests/RegisterRequest.php` (new)
- `app/Http/Requests/LoginRequest.php` (new)
- `app/Http/Requests/StoreExpenseRequest.php` (new)
- `app/Http/Requests/UpdateExpenseRequest.php` (new)
- `app/Http/Requests/StoreUserRequest.php` (new)
- `app/Http/Requests/UpdateUserRequest.php` (new)
- Controllers (use new FormRequests)

**Expected Outcome**:
- Validation centralized in FormRequest classes
- Error messages are user-friendly and localized
- Consistent validation across endpoints
- Easy to modify validation rules
- No inline validation in controllers

**Acceptance Criteria**:
- [ ] All endpoints use FormRequest classes
- [ ] Error messages are clear and user-friendly
- [ ] Messages mention field names and rules
- [ ] All tests still pass
- [ ] Validation rules consistent with original behavior

---

## Task P1-009: Implement Request Logging Middleware

**ID**: P1-009  
**Priority**: HIGH  
**Complexity**: Small  
**Estimated Time**: 4 hours  
**Dependencies**: None  

**Description**:  
Create middleware to log all API requests including method, path, status, duration, user ID, and IP address.

**Files Affected**:
- `app/Http/Middleware/LogApiRequests.php` (new)
- `app/Http/Kernel.php` (register middleware)
- Configuration for log file location
- Database table for persistent logging (optional)

**Expected Outcome**:
- All API requests logged to file
- Includes method, path, status, duration, user, IP
- Useful for debugging and security audit
- Performance impact minimal
- Can query request history

**Acceptance Criteria**:
- [ ] All requests logged to file or database
- [ ] Log includes timestamp, method, path, status, duration, user_id, ip
- [ ] Sensitive data (passwords, tokens) not logged
- [ ] Log rotation implemented
- [ ] Query performance not degraded
- [ ] All tests still pass

---

## Task P1-010: Add User Profile Management Endpoints

**ID**: P1-010  
**Priority**: HIGH  
**Complexity**: Small  
**Estimated Time**: 4 hours  
**Dependencies**: P1-008  

**Description**:  
Add endpoints for users to manage their own profile (name, email, password).

**Files Affected**:
- `app/Http/Controllers/Api/ProfileController.php` (new)
- `app/Http/Requests/UpdateProfileRequest.php` (new)
- `app/Http/Requests/ChangePasswordRequest.php` (new)
- `routes/api.php`

**Expected Outcome**:
- `PUT /api/profile` updates name and email
- `POST /api/change-password` changes password
- Email change requires verification
- Changes logged to audit logs
- Users can only update their own profile

**Acceptance Criteria**:
- [ ] `PUT /api/profile` updates name and email
- [ ] `POST /api/change-password` changes password
- [ ] Email verification required for email change
- [ ] Old password verified before password change
- [ ] Cannot change another user's profile
- [ ] Changes audit-logged
- [ ] All tests passing

---

## Phase 2: Refactoring & Maintenance (Weeks 5-6)

---

## Task P2-001: Refactor Cache Management (Service Layer)

**ID**: P2-001  
**Priority**: MEDIUM  
**Complexity**: Medium  
**Estimated Time**: 6 hours  
**Dependencies**: None  

**Description**:  
Extract cache logic from controllers into a service layer using Laravel cache tags for safer invalidation.

**Files Affected**:
- `app/Services/CacheService.php` (new)
- `app/Http/Controllers/Api/ExpenseController.php`
- `app/Http/Controllers/Api/UserController.php`
- `app/Models/Expense.php`
- `app/Models/User.php`

**Expected Outcome**:
- Cache logic centralized in service
- Uses cache tags instead of hardcoded keys
- Easy to modify caching strategy
- Controllers focus on HTTP logic only
- Testable cache behavior in isolation

**Acceptance Criteria**:
- [ ] `CacheService` handles all cache operations
- [ ] Controllers use `CacheService` for caching
- [ ] Cache tags used instead of manual keys
- [ ] Models use `CacheService` for invalidation
- [ ] All tests still pass
- [ ] Cache behavior unchanged from user perspective

---

## Task P2-002: Improve Pagination Validation

**ID**: P2-002  
**Priority**: MEDIUM  
**Complexity**: Small  
**Estimated Time**: 2 hours  
**Dependencies**: P1-008  

**Description**:  
Add validation for pagination parameters to prevent invalid input.

**Files Affected**:
- `app/Http/Requests/ExpenseListRequest.php` (new)
- `app/Http/Requests/UserListRequest.php` (new)
- Controllers

**Expected Outcome**:
- Page parameter validated (numeric, >= 1)
- Per-page parameter validated (numeric, 1-100)
- Invalid values return 400 Bad Request

**Acceptance Criteria**:
- [ ] Page parameter must be numeric and >= 1
- [ ] Invalid page returns 400 Bad Request
- [ ] Per-page parameter must be numeric and 1-100
- [ ] All tests still pass

---

## Task P2-003: Add Category Validation/Management

**ID**: P2-003  
**Priority**: MEDIUM  
**Complexity**: Medium  
**Estimated Time**: 4 hours  
**Dependencies**: None  

**Description**:  
Define and validate expense categories against a predefined list.

**Files Affected**:
- `database/migrations/*_create_expense_categories_table.php` (new, optional)
- `app/Models/ExpenseCategory.php` (new, optional)
- `app/Http/Requests/StoreExpenseRequest.php`
- Seed default categories

**Expected Outcome**:
- Predefined category list (Travel, Meals, Office, etc.)
- Category validation on expense creation
- Invalid categories return 400 Bad Request
- Admins can manage categories per company (optional)

**Acceptance Criteria**:
- [ ] Default categories defined and seeded
- [ ] Expense category validated against list
- [ ] Invalid category returns 400 Bad Request
- [ ] All tests updated and passing

---

## Phase 3: Advanced Features (Weeks 7-10)

---

## Task P3-001: Add Expense Attachments

**ID**: P3-001  
**Priority**: LOW  
**Complexity**: Large  
**Estimated Time**: 12 hours  
**Dependencies**: P1-001  

**Description**:  
Enable users to attach receipt images and documents to expenses.

**Files Affected**:
- `database/migrations/*_create_expense_attachments_table.php` (new)
- `app/Models/ExpenseAttachment.php` (new)
- `app/Http/Controllers/Api/ExpenseAttachmentController.php` (new)
- `routes/api.php`
- Storage configuration

**Expected Outcome**:
- `/api/expenses/{id}/attachments` list attachments
- `/api/expenses/{id}/attachments` POST to add attachment
- `/api/expenses/{id}/attachments/{attachmentId}` DELETE to remove
- File size limits enforced
- Only image/PDF files accepted
- Files stored securely

**Acceptance Criteria**:
- [ ] Attachment model created
- [ ] Upload endpoint working
- [ ] File validation (type, size)
- [ ] Delete endpoint removes files
- [ ] Only expense owner/admin can delete
- [ ] All tests passing

---

## Task P3-002: Implement CSV/PDF Export

**ID**: P3-002  
**Priority**: LOW  
**Complexity**: Medium  
**Estimated Time**: 8 hours  
**Dependencies**: P1-001  

**Description**:  
Add endpoints to export expenses and reports in CSV and PDF formats.

**Files Affected**:
- `app/Http/Controllers/Api/ExportController.php` (new)
- `app/Exports/ExpensesExport.php` (new)
- `routes/api.php`

**Expected Outcome**:
- `GET /api/expenses/export?format=csv` exports to CSV
- `GET /api/expenses/export?format=pdf` exports to PDF
- Exports include all visible fields
- Exports respect user permissions
- Large exports queued as background job

**Acceptance Criteria**:
- [ ] CSV export working and downloadable
- [ ] PDF export working and downloadable
- [ ] Only authenticated users can export
- [ ] Export respects company isolation
- [ ] All tests passing

---

## Task P3-003: Implement Multi-Currency Support

**ID**: P3-003  
**Priority**: LOW  
**Complexity**: Medium  
**Estimated Time**: 8 hours  
**Dependencies**: None  

**Description**:  
Add support for tracking expenses in multiple currencies.

**Files Affected**:
- `database/migrations/*_add_currency_to_expenses_table.php` (new)
- `database/migrations/*_add_default_currency_to_companies_table.php` (new)
- `app/Models/Expense.php`
- `app/Models/Company.php`
- `app/Http/Resources/ExpenseResource.php`

**Expected Outcome**:
- Expenses can be recorded in any currency
- Company has default currency
- Exchange rates fetched for reporting
- Totals calculated correctly
- API returns currency with amount

**Acceptance Criteria**:
- [ ] Currency field added to expenses
- [ ] Default currency set per company
- [ ] API accepts and returns currency
- [ ] Reports calculate totals correctly
- [ ] All tests passing

---

## Task P3-004: Implement Expense Approval Workflow

**ID**: P3-004  
**Priority**: LOW  
**Complexity**: Large  
**Estimated Time**: 16 hours  
**Dependencies**: P1-001  

**Description**:  
Add approval workflow for expenses with status tracking and notifications.

**Files Affected**:
- `database/migrations/*_add_status_to_expenses_table.php` (new)
- `app/Models/Expense.php`
- `app/Models/ExpenseApproval.php` (new)
- Controllers
- Notifications (email/in-app)

**Expected Outcome**:
- Expenses have status: pending, approved, rejected, reimbursed
- Multi-level approval chain
- Managers approve; admins review
- Comments on approval/rejection
- Notifications sent on status change
- Resubmit rejected expenses

**Acceptance Criteria**:
- [ ] Status field added
- [ ] Approval endpoints working
- [ ] Only approvers can approve
- [ ] Comments logged
- [ ] Notifications sent
- [ ] Resubmit working
- [ ] All tests passing

---

## Task P3-005: Create Admin Dashboard API Endpoints

**ID**: P3-005  
**Priority**: MEDIUM  
**Complexity**: Medium  
**Estimated Time**: 8 hours  
**Dependencies**: None  

**Description**:  
Add API endpoints for dashboard data (usage statistics, spending trends, audit trails).

**Files Affected**:
- `app/Http/Controllers/Api/DashboardController.php` (new)
- `routes/api.php`

**Expected Outcome**:
- `/api/dashboard/stats` returns: expense count, total spent, average, user count
- `/api/dashboard/spending-by-category` returns breakdown
- `/api/dashboard/top-expenses` returns largest expenses
- `/api/dashboard/trends` returns spending over time
- Respect date range parameters

**Acceptance Criteria**:
- [ ] All endpoints returning correct data
- [ ] Only admins can access
- [ ] Date range filtering working
- [ ] All tests passing

---

## Summary

**Total Tasks**: 19  
**Estimated Total Time**: ~110 hours (3 person-weeks)  
**Critical Path**:
1. P1-001 → P1-002 (Exception handler → Docs)
2. P1-003 (Resource relationships)
3. P1-004 (Rate limiting)
4. P1-005, P1-006 (Auth flows)
5. P1-007 (Soft deletes)
6. P1-008 → P1-010 (Validation & profiles)
7. P1-009 (Logging)

All Phase 1 tasks are prerequisites for production deployment. Phase 2 & 3 tasks enhance the product but are not blockers.
