# Expense Management System API Documentation

**Version**: 1.0.0  
**Last Updated**: 2026-11-27  
**Base URL**: `http://localhost:8000/api` (Development) | `https://api.expensemanagement.com/api` (Production)

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Rate Limiting](#rate-limiting)
4. [Error Handling](#error-handling)
5. [Include Parameter](#include-parameter)
6. [Pagination](#pagination)
7. [API Endpoints](#api-endpoints)
8. [Examples](#examples)
9. [Response Schemas](#response-schemas)

---

## Overview

The Expense Management System API is a RESTful API for managing company expenses with the following features:

- **Multi-tenant architecture**: Each company has isolated data
- **Role-based access control**: Admin and Employee roles with different permissions
- **Expense tracking**: Create, read, update, and delete expenses
- **User management**: Create and manage company users
- **Audit logging**: Track all changes to expenses and users
- **Rate limiting**: Protect endpoints from abuse
- **Token-based authentication**: Using Laravel Sanctum

### Key Features

✅ Company registration and user onboarding  
✅ Multi-user expense management  
✅ Role-based permissions (Admin vs Employee)  
✅ Automatic audit trail for compliance  
✅ Search and filtering capabilities  
✅ Paginated responses for scalability  
✅ Optional relationship loading with `?include` parameter  

---

## Authentication

The API uses **Laravel Sanctum** for token-based authentication.

### Getting an API Token

#### 1. Register a new company and admin user:

```bash
POST /register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "company_name": "ACME Corporation",
  "company_email": "contact@acme.com"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Admin user for the company registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "Admin",
      "company_id": 1,
      "created_at": "2026-11-27T12:34:56.000000Z"
    },
    "token": "1|abcdefghijklmnopqrstuvwxyz"
  }
}
```

#### 2. Login with existing credentials:

```bash
POST /login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "1|abcdefghijklmnopqrstuvwxyz",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "Admin",
      "company_id": 1,
      "created_at": "2026-11-27T12:34:56.000000Z"
    }
  }
}
```

### Using the API Token

Include the token in the `Authorization` header for all authenticated requests:

```bash
Authorization: Bearer {token}
```

**Example:**
```bash
curl -X GET http://localhost:8000/api/expenses \
  -H "Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz" \
  -H "Content-Type: application/json"
```

---

## Rate Limiting

The API implements rate limiting to prevent abuse:

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /register` | 3 requests | 1 minute |
| `POST /login` | 5 requests | 1 minute |
| All other endpoints | No limit | - |

**Rate Limit Response:**

When rate limited, the API returns `HTTP 429 Too Many Requests`:

```json
{
  "status": "error",
  "message": "Too many requests"
}
```

**Headers:**

The following headers indicate rate limit status:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in current window
- `X-RateLimit-Reset`: Unix timestamp when limit resets

---

## Error Handling

The API returns structured error responses with appropriate HTTP status codes.

### Error Response Format

```json
{
  "status": "error",
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Validation error message"],
    "another_field": ["Another error"]
  }
}
```

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Successful GET, PUT request |
| 201 | Created | Successful POST request |
| 400 | Bad Request | Invalid JSON or malformed request |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Insufficient permissions for resource |
| 404 | Not Found | Resource does not exist |
| 422 | Unprocessable Entity | Validation error |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

### Common Error Scenarios

**Missing Authentication Token:**
```json
{
  "status": "error",
  "message": "Unauthenticated"
}
```
HTTP 401

**Invalid Credentials:**
```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```
HTTP 401

**Validation Error:**
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```
HTTP 422

**Permission Denied:**
```json
{
  "status": "error",
  "message": "Forbidden"
}
```
HTTP 403

---

## Include Parameter

The API supports optional relationship loading using the `?include` query parameter. This allows you to control which relationships are included in responses.

### Benefits

- **Smaller payloads**: Only get the data you need
- **Faster responses**: Avoid unnecessary nested data
- **Circular reference prevention**: No infinite recursion
- **API consumer control**: Each client chooses what to load

### Available Includes

**For Expenses:**
- `user` - Include the user who created the expense
- `company` - Include the company the expense belongs to

**For Users:**
- `company` - Include the company the user belongs to
- `expenses` - Include all expenses created by the user

**For Companies:**
- `users` - Include all users in the company
- `expenses` - Include all expenses for the company

### Usage Examples

**Single include:**
```bash
GET /api/expenses?include=user
```

**Multiple includes (comma-separated):**
```bash
GET /api/expenses?include=user,company
```

**Without includes (default - smallest payload):**
```bash
GET /api/expenses
```

### Response Comparison

**Without include:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Office Supplies",
      "amount": 150.50,
      "category": "Supplies",
      "created_at": "2026-11-27T12:34:56.000000Z"
    }
  ]
}
```

**With `?include=user`:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Office Supplies",
      "amount": 150.50,
      "category": "Supplies",
      "created_at": "2026-11-27T12:34:56.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "Admin",
        "company_id": 1,
        "created_at": "2026-11-27T12:34:56.000000Z"
      }
    }
  ]
}
```

**With `?include=user,company`:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Office Supplies",
      "amount": 150.50,
      "category": "Supplies",
      "created_at": "2026-11-27T12:34:56.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "Admin",
        "company_id": 1,
        "created_at": "2026-11-27T12:34:56.000000Z"
      },
      "company": {
        "id": 1,
        "name": "ACME Corporation",
        "email": "contact@acme.com"
      }
    }
  ]
}
```

---

## Pagination

List endpoints return paginated results with 10 items per page by default.

### Pagination Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 10) - Not customizable currently |

### Pagination Response Format

```json
{
  "data": [ /* array of resources */ ],
  "links": {
    "first": "http://localhost:8000/api/expenses?page=1",
    "last": "http://localhost:8000/api/expenses?page=5",
    "prev": "http://localhost:8000/api/expenses?page=1",
    "next": "http://localhost:8000/api/expenses?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 11,
    "last_page": 5,
    "per_page": 10,
    "to": 20,
    "total": 50
  }
}
```

### Pagination Examples

**Get first page:**
```bash
GET /api/expenses
```

**Get specific page:**
```bash
GET /api/expenses?page=3
```

**Get page with includes:**
```bash
GET /api/expenses?page=2&include=user,company
```

**Get page with search:**
```bash
GET /api/expenses?page=1&search=Office
```

---

## API Endpoints

### Authentication Endpoints

#### POST /register

Create a new company and register the first admin user.

**Rate Limit:** 3 requests per minute

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "company_name": "ACME Corporation",
  "company_email": "contact@acme.com"
}
```

**Validation Rules:**
- `name`: Required, string, 2-255 characters
- `email`: Required, email, unique across system
- `password`: Required, minimum 8 characters, must be confirmed
- `company_name`: Required, string, 2-255 characters
- `company_email`: Required, email, unique across system

**Response:** 201 Created
```json
{
  "status": "success",
  "message": "Admin user for the company registered successfully",
  "data": {
    "user": { /* UserResource */ },
    "token": "1|abcdefghijklmnopqrstuvwxyz"
  }
}
```

---

#### POST /login

Authenticate a user and get an API token.

**Rate Limit:** 5 requests per minute

**Request:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

**Validation Rules:**
- `email`: Required, email format
- `password`: Required, minimum 8 characters

**Response:** 200 OK
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "1|abcdefghijklmnopqrstuvwxyz",
    "user": { /* UserResource */ }
  }
}
```

---

### Expenses Endpoints

#### GET /expenses

List all expenses for the authenticated user's company.

**Authentication:** Required  
**Permissions:** Any authenticated user

**Query Parameters:**
- `page` (integer): Page number, default 1
- `search` (string): Search by title or category
- `include` (string): Include relations (user, company)

**Example Requests:**
```bash
# Get first page of expenses
GET /api/expenses

# Search for specific expenses
GET /api/expenses?search=Office

# Get page 2 with user details
GET /api/expenses?page=2&include=user

# Get expenses with all relationships
GET /api/expenses?include=user,company
```

**Response:** 200 OK
```json
{
  "data": [
    {
      "id": 1,
      "title": "Office Supplies",
      "amount": 150.50,
      "category": "Supplies",
      "created_at": "2026-11-27T12:34:56.000000Z"
    }
  ],
  "links": { /* pagination */ },
  "meta": { /* pagination metadata */ }
}
```

---

#### POST /expenses

Create a new expense.

**Authentication:** Required  
**Permissions:** Any authenticated user

**Request:**
```json
{
  "title": "Office Supplies",
  "amount": 150.50,
  "category": "Supplies"
}
```

**Validation Rules:**
- `title`: Required, string, 3-255 characters
- `amount`: Required, number, minimum 0.01
- `category`: Required, string, 2-50 characters

**Response:** 201 Created
```json
{
  "status": "success",
  "message": "Expense created successfully",
  "data": { /* ExpenseResource */ }
}
```

---

#### PUT /expenses/{id}

Update an existing expense.

**Authentication:** Required  
**Permissions:** Expense owner or company admin

**URL Parameters:**
- `id`: Expense ID

**Request:**
```json
{
  "title": "Updated Office Supplies",
  "amount": 175.75,
  "category": "Office"
}
```

**Validation Rules:**
- All fields optional, same validation as POST

**Response:** 200 OK
```json
{
  "status": "success",
  "message": "Expense updated successfully",
  "data": { /* ExpenseResource */ }
}
```

**Error Responses:**
- 403 Forbidden: User does not own expense and is not admin
- 404 Not Found: Expense does not exist

---

#### DELETE /expenses/{id}

Delete an expense.

**Authentication:** Required  
**Permissions:** Company admin only

**URL Parameters:**
- `id`: Expense ID

**Response:** 200 OK
```json
{
  "status": "success",
  "message": "Expense deleted successfully"
}
```

**Error Responses:**
- 403 Forbidden: User is not admin
- 404 Not Found: Expense does not exist

---

### Users Endpoints

#### GET /users

List all users in the authenticated user's company.

**Authentication:** Required  
**Permissions:** Company admin only

**Query Parameters:**
- `page` (integer): Page number, default 1
- `include` (string): Include relations (company, expenses)

**Example Requests:**
```bash
# Get first page of users
GET /api/users

# Get users with their company info
GET /api/users?include=company

# Get users with their expenses
GET /api/users?page=2&include=expenses
```

**Response:** 200 OK
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "Admin",
      "company_id": 1,
      "created_at": "2026-11-27T12:34:56.000000Z"
    }
  ],
  "links": { /* pagination */ },
  "meta": { /* pagination metadata */ }
}
```

---

#### POST /users

Create a new user in the company.

**Authentication:** Required  
**Permissions:** Company admin only

**Request:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "role": "Employee"
}
```

**Validation Rules:**
- `name`: Required, string, 2-255 characters
- `email`: Required, email, unique within company
- `password`: Required, minimum 8 characters, must be confirmed
- `role`: Required, enum: "Admin", "Employee"

**Response:** 201 Created
```json
{
  "status": "success",
  "message": "User created successfully",
  "data": { /* UserResource */ }
}
```

---

#### PUT /users/{id}

Update a user's role.

**Authentication:** Required  
**Permissions:** The user themselves or company admin

**URL Parameters:**
- `id`: User ID

**Request:**
```json
{
  "role": "Admin"
}
```

**Validation Rules:**
- `role`: Required, enum: "Admin", "Employee"

**Response:** 200 OK
```json
{
  "status": "success",
  "message": "User role updated successfully",
  "data": { /* UserResource */ }
}
```

**Error Responses:**
- 403 Forbidden: User does not have permission
- 404 Not Found: User does not exist

---

### Audit Logs Endpoint

#### GET /logs

List audit logs for the company.

**Authentication:** Required  
**Permissions:** Company admin only

**Query Parameters:**
- `page` (integer): Page number, default 1

**Example Requests:**
```bash
# Get first page of logs
GET /api/logs

# Get page 3 of logs
GET /api/logs?page=3
```

**Response:** 200 OK
```json
{
  "data": [
    {
      "id": 1,
      "action": "updated",
      "model": "Expense",
      "model_id": 5,
      "user_id": 1,
      "company_id": 1,
      "changes": {
        "amount": {
          "old": 150.00,
          "new": 175.75
        }
      },
      "created_at": "2026-11-27T12:34:56.000000Z"
    }
  ],
  "links": { /* pagination */ },
  "meta": { /* pagination metadata */ }
}
```

---

## Examples

### Complete Workflow

This example shows a complete workflow from registration to expense management.

#### Step 1: Register Company and Admin User

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Admin",
    "email": "admin@acme.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!",
    "company_name": "ACME Corporation",
    "company_email": "contact@acme.com"
  }'
```

**Save the token from response:**
```
Token: 1|abcdefghijklmnopqrstuvwxyz
```

#### Step 2: Create a New Employee User

```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz" \
  -d '{
    "name": "Jane Employee",
    "email": "employee@acme.com",
    "password": "AnotherSecure123!",
    "password_confirmation": "AnotherSecure123!",
    "role": "Employee"
  }'
```

#### Step 3: Create an Expense (as Employee)

First, login as the employee:
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "employee@acme.com",
    "password": "AnotherSecure123!"
  }'
```

**Save the employee token:**
```
Employee Token: 2|xyz123...
```

Create an expense:
```bash
curl -X POST http://localhost:8000/api/expenses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 2|xyz123..." \
  -d '{
    "title": "Client Lunch Meeting",
    "amount": 85.50,
    "category": "Meals"
  }'
```

#### Step 4: View Expenses (as Admin)

```bash
curl -X GET "http://localhost:8000/api/expenses?include=user,company" \
  -H "Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz"
```

#### Step 5: Admin Reviews Audit Logs

```bash
curl -X GET http://localhost:8000/api/logs \
  -H "Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz"
```

---

## Response Schemas

### UserResource

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "role": "Admin",
  "company_id": 1,
  "created_at": "2026-11-27T12:34:56.000000Z",
  "company": { /* Only when ?include=company */ },
  "expenses": [ /* Only when ?include=expenses */ ]
}
```

### CompanyResource

```json
{
  "id": 1,
  "name": "ACME Corporation",
  "email": "contact@acme.com",
  "users": [ /* Only when ?include=users */ ],
  "expenses": [ /* Only when ?include=expenses */ ]
}
```

### ExpenseResource

```json
{
  "id": 1,
  "title": "Office Supplies",
  "amount": 150.50,
  "category": "Supplies",
  "created_at": "2026-11-27T12:34:56.000000Z",
  "user": { /* Only when ?include=user */ },
  "company": { /* Only when ?include=company */ }
}
```

### AuditLog

```json
{
  "id": 1,
  "action": "created",
  "model": "Expense",
  "model_id": 5,
  "user_id": 1,
  "company_id": 1,
  "changes": null,
  "created_at": "2026-11-27T12:34:56.000000Z"
}
```

---

## Best Practices

### Authentication
- ✅ Always store tokens securely (not in localStorage if possible)
- ✅ Include token in Authorization header for all authenticated requests
- ✅ Regenerate tokens periodically
- ❌ Do not share tokens across users

### Performance
- ✅ Use `?include` parameter to only fetch needed relationships
- ✅ Use pagination to avoid large data transfers
- ✅ Cache responses when appropriate
- ❌ Do not make unnecessary API calls
- ❌ Do not fetch all pages sequentially

### Error Handling
- ✅ Check HTTP status codes
- ✅ Parse error messages from response body
- ✅ Handle validation errors (422) with field-specific messages
- ✅ Retry on 429 (rate limit) with exponential backoff
- ❌ Do not assume 200 response means success

### Security
- ✅ Use HTTPS in production
- ✅ Validate input before sending
- ✅ Never expose tokens in URLs or logs
- ✅ Use strong passwords (8+ characters)
- ❌ Do not hardcode credentials

---

## Support

For API issues or questions:
- 📧 Email: api-support@expensemanagement.com
- 📖 Documentation: https://docs.expensemanagement.com
- 🐛 Issues: https://github.com/expensemanagement/api/issues

---

**Last Updated**: 2026-11-27  
**API Version**: 1.0.0
