# HanapBuhay — API Endpoints Reference
**Document Type:** Complete API Contract
**Base URL (local):** http://127.0.0.1:8000/api
**Base URL (emulator):** http://10.0.2.2:8000/api
**Framework:** Laravel 13 + Sanctum
**Last Updated:** September 2026

---

## How to Use This Document

This is the contract between the Laravel backend and both frontends (Flutter mobile + React web). Every endpoint is listed with exact request body, response format, and status codes.

Share this with:
- Flutter App Developer (mobile API calls)
- React Web Developer (admin panel API calls)
- QA Tester (Postman test reference)

---

## Authentication Header

All protected endpoints require:
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
Accept: application/json
```

Public endpoints (no header needed):
```
GET  /api/ping
GET  /api/barangays
GET  /api/service-categories
POST /api/auth/register
POST /api/auth/login
POST /api/auth/google
POST /api/auth/email/verify
POST /api/auth/email/resend-otp
POST /api/auth/password/forgot
POST /api/auth/password/verify-otp
POST /api/auth/password/reset
```

---

## Standard Response Format

### Success
```json
{
    "success": true,
    "message": "Human readable success message",
    "data": { }
}
```

### Error
```json
{
    "success": false,
    "message": "Human readable error message",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

### HTTP Status Codes Used
```
200 → OK (success, data returned)
201 → Created (new resource created)
401 → Unauthorized (invalid/missing token)
403 → Forbidden (valid token, wrong role/permission)
404 → Not Found
422 → Unprocessable Entity (validation failed)
500 → Internal Server Error
```

---

## SECTION A: PUBLIC ROUTES

---

### A1. Health Check
```
GET /api/ping

Response 200:
{
    "success": true,
    "message": "HanapBuhay API is running",
    "data": {
        "version": "1.0.0",
        "scope": "Trinidad, Bohol",
        "environment": "local"
    }
}
```

---

### A2. Get All Barangays
```
GET /api/barangays

Response 200:
{
    "success": true,
    "message": "Barangays retrieved",
    "data": [
        {
            "id": 1,
            "name": "Alicia",
            "latitude": 9.9612000,
            "longitude": 124.3701000
        },
        {
            "id": 17,
            "name": "Poblacion",
            "latitude": 9.9545000,
            "longitude": 124.3656000
        }
        // ... all 20 Trinidad barangays
    ]
}
```

---

### A3. Get Service Categories
```
GET /api/service-categories

Response 200:
{
    "success": true,
    "message": "Categories retrieved",
    "data": [
        {
            "id": 1,
            "name": "Electrical Works",
            "icon": "electrical"
        },
        {
            "id": 6,
            "name": "Barbering & Hairstyling",
            "icon": "barber"
        }
        // ... all active categories
    ]
}
```

---

## SECTION B: AUTHENTICATION

---

### B1. Register (Manual)
```
POST /api/auth/register

Request Body:
{
    "name": "Juan dela Cruz",
    "email": "juan@email.com",
    "password": "password123",
    "password_confirmation": "password123",
    "mobile_number": "09123456789",
    "role": "client",
    "barangay_id": 9
}

Validation Rules:
  name:                  required, string, max:255
  email:                 required, email,
                         unique:users
  password:              required, min:8, confirmed
  mobile_number:         required, string, max:20
  role:                  required, in:client,worker
  barangay_id:           required,
                         exists:barangays,id

Response 201:
{
    "success": true,
    "message": "Registration successful.
                Please verify your email.",
    "data": {
        "user": {
            "id": 1,
            "name": "Juan dela Cruz",
            "email": "juan@email.com",
            "role": "client",
            "barangay": {
                "id": 9,
                "name": "Calanggaman"
            },
            "email_verified_at": null,
            "is_google_account": false
        }
    }
}

Side Effects:
  Creates user record
  If role = worker: creates worker_profile
  Generates 6-digit OTP
  Sends OTP to user's email via Laravel Mail
  OTP expires in 10 minutes

Response 422 (validation failed):
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "email": ["This email is already
                   registered."]
    }
}
```

---

### B2. Email OTP Verification
```
POST /api/auth/email/verify

Request Body:
{
    "email": "juan@email.com",
    "code": "482931"
}

Response 200:
{
    "success": true,
    "message": "Email verified successfully.",
    "data": {
        "token": "1|abc123xyz...",
        "user": {
            "id": 1,
            "name": "Juan dela Cruz",
            "email": "juan@email.com",
            "role": "client",
            "barangay": {
                "id": 9,
                "name": "Calanggaman",
                "latitude": 9.9523000,
                "longitude": 124.3701000
            },
            "profile_photo_url": null,
            "email_verified_at":
                "2026-08-26T00:00:00.000000Z",
            "is_google_account": false,
            "worker_profile": null
        }
    }
}

Response 422 (invalid/expired code):
{
    "success": false,
    "message": "Invalid or expired
                verification code."
}
```

---

### B3. Resend Email OTP
```
POST /api/auth/email/resend-otp

Request Body:
{
    "email": "juan@email.com"
}

Response 200:
{
    "success": true,
    "message": "Verification code resent
                to your email."
}

Rate limited: max 3 requests per 10 minutes
per email address
```

---

### B4. Login (Manual)
```
POST /api/auth/login

Request Body:
{
    "email": "juan@email.com",
    "password": "password123"
}

Response 200:
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "token": "2|xyz789abc...",
        "user": {
            "id": 1,
            "name": "Juan dela Cruz",
            "email": "juan@email.com",
            "role": "client",
            "profile_photo_url": null,
            "barangay": {
                "id": 9,
                "name": "Calanggaman",
                "latitude": 9.9523000,
                "longitude": 124.3701000
            },
            "email_verified_at":
                "2026-08-26T00:00:00.000000Z",
            "is_google_account": false,
            "worker_profile": null
            // or worker_profile object
            // if role = worker
        }
    }
}

Response 401 (wrong credentials):
{
    "success": false,
    "message": "Invalid email or password."
}

Response 403 (email not verified):
{
    "success": false,
    "message": "Please verify your email
                before logging in."
}

Response 403 (account suspended):
{
    "success": false,
    "message": "Your account has been
                suspended. Please contact
                support."
}
```

---

### B5. Google Sign-In
```
POST /api/auth/google

Request Body:
{
    "google_token": "google_id_token_from_flutter",
    "role": "client"
}
Note: role required only for NEW users.
      For existing users, role is ignored.

Flow:
  1. Flutter gets ID token from
     google_sign_in package
  2. Sends token to this endpoint
  3. Laravel verifies with Google
  4. Creates or finds user account
  5. Returns Sanctum token

Response 200 (existing user):
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "token": "3|abc456...",
        "is_new_user": false,
        "user": {
            "id": 2,
            "name": "Maria Santos",
            "email": "maria@gmail.com",
            "role": "worker",
            "profile_photo_url": "https://...",
            "barangay": { ... },
            "is_google_account": true,
            "worker_profile": { ... }
        }
    }
}

Response 201 (new user):
{
    "success": true,
    "message": "Account created. Please
                complete your profile.",
    "data": {
        "token": "4|xyz123...",
        "is_new_user": true,
        "user": {
            "id": 3,
            "name": "Pedro Reyes",
            "email": "pedro@gmail.com",
            "role": null,
            "barangay": null,
            "is_google_account": true,
            "profile_photo_url":
                "https://google-photo-url"
        }
    }
}

Note: If is_new_user = true, Flutter navigates
to Role Selection → Complete Profile Screen.
The token is already valid — use it for the
complete-profile request.
```

---

### B6. Complete Google Profile
```
POST /api/auth/google/complete-profile
(Protected)

Request Body (multipart/form-data):
  name:           string, required
  mobile_number:  string, required
  role:           string, required,
                  in:client,worker
  barangay_id:    integer, required
  profile_photo:  file, optional
                  (jpeg/png, max 2MB)

Response 200:
{
    "success": true,
    "message": "Profile completed.",
    "data": {
        "user": {
            "id": 3,
            "name": "Pedro Reyes",
            "email": "pedro@gmail.com",
            "role": "client",
            "profile_photo_url": "...",
            "barangay": {
                "id": 17,
                "name": "Poblacion"
            },
            "is_google_account": true,
            "worker_profile": null
        }
    }
}
```

---

### B7. Forgot Password — Send OTP
```
POST /api/auth/password/forgot

Request Body:
{
    "email": "juan@email.com"
}

Response 200:
{
    "success": true,
    "message": "Password reset code sent
                to your email."
}

Note: Always returns 200 even if email
doesn't exist — security best practice
(don't reveal which emails are registered)
```

---

### B8. Forgot Password — Verify OTP
```
POST /api/auth/password/verify-otp

Request Body:
{
    "email": "juan@email.com",
    "code": "739201"
}

Response 200:
{
    "success": true,
    "message": "Code verified.",
    "data": {
        "reset_token": "temp_token_xyz"
    }
}

Note: reset_token is a short-lived temporary
token (stored in otp_codes or cache).
Used in next step to authorize password reset.
Expires in 15 minutes.
```

---

### B9. Forgot Password — Reset
```
POST /api/auth/password/reset

Request Body:
{
    "email": "juan@email.com",
    "reset_token": "temp_token_xyz",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}

Response 200:
{
    "success": true,
    "message": "Password reset successfully.
                Please log in."
}
```

---

### B10. Logout
```
POST /api/auth/logout
(Protected)

No request body needed.

Response 200:
{
    "success": true,
    "message": "Logged out successfully."
}

Side Effect: Deletes current Sanctum token only.
Other sessions remain active.
```

---

## SECTION C: USER PROFILE

---

### C1. Get Authenticated User
```
GET /api/user
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Juan dela Cruz",
        "email": "juan@email.com",
        "mobile_number": "09123456789",
        "role": "client",
        "profile_photo_url":
            "http://127.0.0.1:8000/storage/
             photos/1.jpg",
        "barangay": {
            "id": 9,
            "name": "Calanggaman",
            "latitude": 9.9523000,
            "longitude": 124.3701000
        },
        "email_verified_at":
            "2026-08-26T00:00:00.000000Z",
        "is_google_account": false,
        "is_active": true,
        "worker_profile": null
        // or full worker_profile object
        // for worker accounts
    }
}

Worker profile object (when role = worker):
"worker_profile": {
    "id": 1,
    "bio": "Experienced electrician...",
    "verification_status": "approved",
    "trust_tier": "verified",
    "availability_status": "available",
    "average_rating": 4.80,
    "total_reviews": 23,
    "completed_jobs": 45
}
```

---

### C2. Update Profile
```
POST /api/user/profile
(Protected, multipart/form-data)

Request Body:
  name:           string, optional
  mobile_number:  string, optional
  barangay_id:    integer, optional,
                  exists:barangays,id
  profile_photo:  file, optional
                  (jpeg/png, max 2MB)

Response 200:
{
    "success": true,
    "message": "Profile updated successfully.",
    "data": {
        "user": { ...updated user object... }
    }
}
```

---

### C3. Change Password
```
POST /api/user/password
(Protected)

Request Body:
{
    "current_password": "oldpassword",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}

Response 200:
{
    "success": true,
    "message": "Password changed successfully."
}

Response 422 (wrong current password):
{
    "success": false,
    "message": "Current password is incorrect."
}

Note: For Google accounts with no password set,
omit current_password field.
Endpoint handles both cases.
```

---

### C4. Get Login Activity
```
GET /api/user/sessions
(Protected)

Response 200:
{
    "success": true,
    "data": [
        {
            "id": 5,
            "device": "Android",
            "ip_address": "192.168.1.5",
            "last_used_at":
                "2026-08-26T10:30:00.000000Z",
            "is_current": true
        },
        {
            "id": 3,
            "device": "Chrome on Windows",
            "ip_address": "192.168.1.8",
            "last_used_at":
                "2026-08-25T08:00:00.000000Z",
            "is_current": false
        }
    ]
}
```

---

### C5. Revoke Session
```
DELETE /api/user/sessions/{tokenId}
(Protected)

Response 200:
{
    "success": true,
    "message": "Session revoked."
}

Response 403 (trying to revoke own session):
{
    "success": false,
    "message": "Use the logout endpoint to
                end your current session."
}
```

---

### C6. Update FCM Token
```
POST /api/user/fcm-token
(Protected)

Request Body:
{
    "fcm_token": "device_fcm_token_string",
    "device_type": "android"
}

Response 200:
{
    "success": true,
    "message": "FCM token updated."
}

Note: Call this every time the app starts
and whenever Firebase issues a new token.
```

---

## SECTION D: WORKER FEATURES

---

### D1. Get Worker Feed (Client Home)
```
GET /api/feed
(Protected, Client only)

Query Parameters:
  category_id     integer, optional
  barangay_id     integer, optional
  rate_type       string, optional
                  (hourly/daily/weekly/
                  monthly/per_session/
                  per_project)
  verification    string, optional
                  (all/verified/unverified)
                  default: all
  availability    string, optional
                  (all/available)
                  default: all
  page            integer, default: 1

Example:
GET /api/feed?category_id=1
    &verification=verified
    &page=1

Response 200:
{
    "success": true,
    "data": {
        "posts": [
            {
                "job_post_id": 5,
                "worker_profile_id": 3,
                "worker": {
                    "user_id": 8,
                    "name": "Pedro Alonzo",
                    "profile_photo_url": "...",
                    "barangay": "Poblacion",
                    "barangay_id": 17,
                    "distance_km": 1.2,
                    "distance_label": "~1.2 km",
                    "average_rating": 4.80,
                    "total_reviews": 23,
                    "trust_tier": "verified",
                    "verification_status": "approved"
                },
                "category": {
                    "id": 1,
                    "name": "Electrical Works",
                    "icon": "electrical"
                },
                "title": "Expert Electrical
                          Installation & Repair",
                "description": "Licensed
                    electrician with 5 years...",
                "rate_amount": 300.00,
                "rate_type": "daily",
                "rate_display": "₱300.00/day",
                "is_available": true,
                "images": [
                    {
                        "id": 21,
                        "thumbnail_url": "...",
                        "image_url": "...",
                        "display_order": 0
                    }
                ],
                "posted_at": "2026-08-20T..."
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 42,
            "last_page": 3
        }
    }
}

Sort order:
  1. Distance (nearest first)
     computed via Haversine formula
     using client's barangay coords
     vs worker's barangay coords
  2. Trust tier
     (trusted > verified > unverified)
  3. Average rating (highest first)
     within same tier

Distance computation:
  Uses client's barangay center coords
  from their registered barangay
  vs worker's barangay center coords
  No real-time GPS used here
```

---

### D2. Browse Workers by Category
```
GET /api/categories/{categoryId}/workers
(Protected, Client only)

Query Parameters:
  barangay_id      integer, optional
  verified_only    boolean, optional
  page             integer, default: 1

Response 200:
{
    "success": true,
    "data": {
        "category": {
            "id": 1,
            "name": "Electrical Works"
        },
        "workers": [
            {
                "worker_profile_id": 3,
                "user_id": 8,
                "name": "Pedro Alonzo",
                "profile_photo_url": "...",
                "barangay": "Poblacion",
                "distance_km": 1.2,
                "distance_label": "~1.2 km",
                "average_rating": 4.80,
                "total_reviews": 23,
                "trust_tier": "verified",
                "verification_status": "approved",
                "availability_status": "available",
                "job_post": {
                    "id": 5,
                    "rate_amount": 300.00,
                    "rate_type": "daily",
                    "rate_display": "₱300.00/day"
                }
            }
        ],
        "pagination": { ... }
    }
}
```

---

### D3. Get Worker Profile (Public)
```
GET /api/workers/{workerProfileId}
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "worker_profile_id": 3,
        "user_id": 8,
        "name": "Pedro Alonzo",
        "profile_photo_url": "...",
        "bio": "Licensed electrician with
                5 years experience...",
        "barangay": {
            "id": 17,
            "name": "Poblacion",
            "latitude": 9.9545000,
            "longitude": 124.3656000
        },
        "distance_km": 1.2,
        "distance_label": "~1.2 km",
        "trust_tier": "verified",
        "verification_status": "approved",
        "availability_status": "available",
        "average_rating": 4.80,
        "total_reviews": 23,
        "completed_jobs": 45,
        "job_posts": [
            {
                "id": 5,
                "category": {
                    "id": 1,
                    "name": "Electrical Works"
                },
                "title": "Expert Electrical
                          Installation & Repair",
                "description": "...",
                "rate_amount": 300.00,
                "rate_type": "daily",
                "rate_display": "₱300.00/day",
                "is_available": true
            }
        ],
        "reviews": [
            {
                "rated_by_name": "Ana Cruz",
                "score": 5,
                "comment": "Very professional!",
                "created_at": "2026-08-01T..."
            }
        ]
    }
}
```

---

### D4. Submit Verification Documents
```
POST /api/worker/verification/submit
(Protected, Worker only,
multipart/form-data)

Request Body:
  government_id:         file, required
                         (jpeg/png, max 5MB)
  barangay_certificate:  file, required
                         (jpeg/png, max 5MB)
  selfie_with_id:        file, required
                         (jpeg/png, max 5MB)
  skill_certificate:     file, optional
                         (jpeg/png, max 5MB)

Response 201:
{
    "success": true,
    "message": "Documents submitted for
                review. This usually takes
                1–3 business days.",
    "data": {
        "verification_status": "pending"
    }
}

Response 422 (already pending/approved):
{
    "success": false,
    "message": "You already have a pending
                or approved verification."
}
```

---

### D5. Get Verification Status
```
GET /api/worker/verification/status
(Protected, Worker only)

Response 200:
{
    "success": true,
    "data": {
        "verification_status": "rejected",
        "trust_tier": null,
        "verification_remarks": "Barangay
            certificate image is unclear.
            Please resubmit.",
        "documents": [
            {
                "type": "government_id",
                "status": "approved"
            },
            {
                "type": "barangay_certificate",
                "status": "rejected"
            },
            {
                "type": "selfie_with_id",
                "status": "approved"
            }
        ]
    }
}
```

---

### D6. Update Worker Profile
```
POST /api/worker/profile
(Protected, Worker only,
multipart/form-data)

Request Body:
  bio:              string, optional,
                    max:500
  availability_status: string, optional,
                    in:available,busy,offline
  category_ids:     array of integers,
                    optional
  portfolio_photos: file[], optional
                    (multiple files)
                    (jpeg/png, max 2MB each)

Response 200:
{
    "success": true,
    "message": "Profile updated.",
    "data": {
        "worker_profile": {
            "bio": "...",
            "availability_status": "available",
            "categories": [...]
        }
    }
}
```

---

## SECTION E: JOB POSTS

---

### E0. Get Job Post Detail
```
GET /api/posts/{postId}
(Protected, Client only)

Response 200:
{
    "success": true,
    "data": {
        "job_post": {
            "id": 5,
            "worker_profile_id": 3,
            "service_category_id": 1,
            "worker": { ... },
            "category": { ... },
            "title": "Expert Aircon Cleaning & Repair",
            "description": "Complete service description...",
            "rate_amount": 300.00,
            "rate_type": "per_session",
            "rate_display": "From ₱300.00/session",
            "is_available": true,
            "is_active": true,
            "images": [
                {
                    "id": 21,
                    "image_url": "...",
                    "thumbnail_url": "...",
                    "display_order": 0
                }
            ]
        }
    }
}
```

Images are returned ordered by `display_order`. An inactive or deleted post
returns 404 for the client detail endpoint.

### E1. Create Job Post
```
POST /api/worker/posts
(Protected, Worker only)

Request Body:
{
    "service_category_id": 1,
    "title": "Expert Aircon Cleaning & Repair",
    "description": "Professional aircon
        cleaning with over 3 years
        experience...",
    "rate_amount": 300.00,
    "rate_type": "per_session",
    "is_available": true
}

Validation:
  service_category_id: required,
                        exists:service_categories
  title:               required, string, max:100
  description:         required, string,
                        max:500
  rate_amount:         required, numeric, min:0
  rate_type:           required, in:hourly,daily,
                        weekly,monthly,
                        per_session,per_project
  is_available:        boolean

Business Rule:
  If worker already has a post in this category,
  the old post is soft-deleted and replaced.

Response 201:
{
    "success": true,
    "message": "Job post created.",
    "data": {
        "job_post": {
            "id": 5,
            "service_category_id": 1,
            "category_name": "Aircon Repair
                              & Cleaning",
            "title": "Expert Aircon Cleaning
                       & Repair",
            "description": "...",
            "rate_amount": 300.00,
            "rate_type": "per_session",
            "rate_display":
                "From ₱300.00/session",
            "is_available": true,
            "is_active": true,
            "created_at": "2026-08-26T..."
        }
    }
}
```

---

### E1A. Upload Post Images
```
POST /api/worker/posts/{postId}/images
(Protected, Worker owner only)
Content-Type: multipart/form-data

Fields:
  images[]: one or more JPEG, PNG, or WebP files

Rules:
  Maximum 10 total images per post
  Maximum 10 MB per uploaded file before compression
  Server generates optimized image and thumbnail files
  New images are appended after existing images

Response 201:
{
    "success": true,
    "data": {
        "images": [
            {
                "id": 21,
                "image_url": "...",
                "thumbnail_url": "...",
                "display_order": 0
            }
        ]
    }
}
```

### E1B. Delete Post Image
```
DELETE /api/worker/posts/{postId}/images/{imageId}
(Protected, Worker owner only)
```

Deletes the image and its stored files. The remaining images are renumbered
from zero in their existing order.

### E1C. Reorder Post Images
```
PUT /api/worker/posts/{postId}/images/order
(Protected, Worker owner only)

Request Body:
{
    "image_ids": [23, 21, 22]
}
```

The request must include every image belonging to the post exactly once.
The array position becomes `display_order`; the first image becomes the feed
preview.

---

### E2. Get Worker's Own Posts
```
GET /api/worker/posts
(Protected, Worker only)

Query Parameters:
  include_inactive: boolean, default: false

Response 200:
{
    "success": true,
    "data": {
        "posts": [
            {
                "id": 5,
                "category": {
                    "id": 7,
                    "name": "Aircon Repair
                              & Cleaning"
                },
                "title": "Expert Aircon
                           Cleaning & Repair",
                "rate_amount": 300.00,
                "rate_type": "per_session",
                "rate_display":
                    "From ₱300.00/session",
                "is_available": true,
                "is_active": true,
                "created_at": "2026-08-26T..."
            }
        ]
    }
}
```

---

### E3. Update Job Post
```
PUT /api/worker/posts/{postId}
(Protected, Worker only)

Request Body (same as create, all optional):
{
    "title": "Updated title",
    "description": "Updated description...",
    "rate_amount": 350.00,
    "rate_type": "per_session",
    "is_available": false
}

Response 200:
{
    "success": true,
    "message": "Job post updated.",
    "data": {
        "job_post": { ...updated post... }
    }
}
```

---

### E4. Deactivate Job Post
```
DELETE /api/worker/posts/{postId}
(Protected, Worker only)

Response 200:
{
    "success": true,
    "message": "Job post deactivated.
                It is no longer visible
                to clients."
}

Note: Soft delete only.
Post remains in database.
Worker can reactivate via PUT endpoint
with is_active: true.
```

---

## SECTION F: BOOKINGS

---

### F1. Create Booking Request
```
POST /api/bookings
(Protected, Client only)

Request Body:
{
    "worker_profile_id": 3,
    "service_category_id": 1,
    "job_post_id": 5,
    "scheduled_at": "2026-09-15T09:00:00",
    "notes": "2 aircon units, 1HP each"
}

Validation:
  worker_profile_id:    required
  service_category_id:  required
  job_post_id:          optional (nullable)
  scheduled_at:         required, date,
                        after:now
  notes:                optional, max:300

Response 201:
{
    "success": true,
    "message": "Booking request sent.",
    "data": {
        "booking": {
            "id": 1,
            "booking_code": "HB-2026-00001",
            "status": "pending",
            "worker": {
                "name": "Pedro Alonzo",
                "profile_photo_url": "...",
                "verification_status": "approved"
            },
            "service_category": "Aircon Repair
                                  & Cleaning",
            "job_post": {
                "title": "Expert Aircon
                           Cleaning & Repair",
                "rate_display":
                    "From ₱300.00/session"
            },
            "scheduled_at":
                "2026-09-15T09:00:00.000000Z",
            "notes": "2 aircon units, 1HP each"
        }
    }
}

Side Effects:
  Creates booking with status: pending
  Sends push notification to worker:
  "New booking request from [client name]"
```

---

### F2. Get My Bookings
```
GET /api/bookings
(Protected)

Query Parameters:
  status:   string, optional
            (pending/accepted/active/
             completed/cancelled/declined)
  page:     integer, default: 1

Response 200:
{
    "success": true,
    "data": {
        "bookings": [
            {
                "id": 1,
                "booking_code": "HB-2026-00001",
                "status": "accepted",
                "other_party": {
                    "name": "Pedro Alonzo",
                    "profile_photo_url": "...",
                    "role": "worker"
                },
                "service_category":
                    "Aircon Repair & Cleaning",
                "job_post_title": "Expert Aircon
                    Cleaning & Repair",
                "scheduled_at":
                    "2026-09-15T09:00:00.000000Z",
                "is_client_tracking": false,
                "is_worker_tracking": false,
                "created_at": "2026-08-26T..."
            }
        ],
        "pagination": { ... }
    }
}

Note: "other_party" is the worker if the
authenticated user is the client, and
vice versa.
```

---

### F3. Get Booking Detail
```
GET /api/bookings/{bookingId}
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "id": 1,
        "booking_code": "HB-2026-00001",
        "status": "accepted",
        "client": {
            "id": 1,
            "name": "Ana Cruz",
            "profile_photo_url": "...",
            "barangay": {
                "id": 9,
                "name": "Calanggaman",
                "latitude": 9.9523000,
                "longitude": 124.3701000
            }
        },
        "worker": {
            "id": 8,
            "name": "Pedro Alonzo",
            "profile_photo_url": "...",
            "verification_status": "approved",
            "trust_tier": "verified",
            "barangay": {
                "id": 17,
                "name": "Poblacion",
                "latitude": 9.9545000,
                "longitude": 124.3656000
            }
        },
        "service_category": {
            "id": 7,
            "name": "Aircon Repair & Cleaning"
        },
        "job_post": {
            "id": 5,
            "title": "Expert Aircon Cleaning
                       & Repair",
            "rate_display":
                "From ₱300.00/session"
        },
        "scheduled_at":
            "2026-09-15T09:00:00.000000Z",
        "notes": "2 aircon units, 1HP each",
        "is_client_tracking": false,
        "is_worker_tracking": true,
        "worker_current_location": {
            "latitude": 9.9530000,
            "longitude": 124.3660000,
            "recorded_at":
                "2026-09-15T08:45:00.000000Z"
        },
        "client_current_location": null,
        "distance_km": 0.3,
        "started_at": null,
        "completed_at": null,
        "created_at": "2026-08-26T..."
    }
}
```

---

### F4. Respond to Booking (Worker)
```
POST /api/bookings/{bookingId}/respond
(Protected, Worker only)

Request Body:
{
    "action": "accept"
}
OR:
{
    "action": "decline",
    "reason": "Schedule conflict"
}

Validation:
  action: required, in:accept,decline
  reason: required if action = decline

Response 200:
{
    "success": true,
    "message": "Booking accepted.",
    "data": {
        "booking": {
            "id": 1,
            "status": "accepted"
        }
    }
}

Side Effects (accept):
  Push notification to client:
  "Pedro Alonzo accepted your booking!"

Side Effects (decline):
  Push notification to client:
  "Pedro Alonzo declined your booking request."
```

---

### F5. Cancel Booking
```
POST /api/bookings/{bookingId}/cancel
(Protected, Client or Worker)

Request Body:
{
    "reason": "Schedule conflict"
}

Response 200:
{
    "success": true,
    "message": "Booking cancelled.",
    "data": {
        "booking": {
            "id": 1,
            "status": "cancelled",
            "cancelled_by": "client"
        }
    }
}
```

---

### F6. Update Booking Status
```
POST /api/bookings/{bookingId}/status
(Protected)

Request Body:
{
    "status": "active"
}
OR:
{
    "status": "completed"
}

Validation:
  status: required, in:active,completed

Rules:
  active: only worker can set
          (means job has started)
  completed: client confirms completion
             (worker marks completed first,
              or both confirm)

Response 200:
{
    "success": true,
    "message": "Booking marked as active.",
    "data": {
        "booking": {
            "id": 1,
            "status": "active",
            "started_at":
                "2026-09-15T09:05:00.000000Z"
        }
    }
}
```

---

## SECTION G: LIVE TRACKING

---

### G1. Start Tracking
```
POST /api/bookings/{bookingId}/tracking/start
(Protected, Client or Worker)

No request body needed.
Role determined from auth token.

Response 200:
{
    "success": true,
    "message": "Location sharing started.",
    "data": {
        "tracking_role": "worker",
        "is_worker_tracking": true,
        "is_client_tracking": false
    }
}

Side Effects:
  Sets is_{role}_tracking = true
  Push notification to other party:
  Worker started: "Worker is on the way!"
  Client started: "Client is heading to
                   your location!"
```

---

### G2. Update Live Location (REST Fallback)
```
POST /api/bookings/{bookingId}/tracking/location
(Protected, Client or Worker)

Request Body:
{
    "latitude": 9.9530000,
    "longitude": 124.3660000,
    "accuracy": 5.2
}

Response 200:
{
    "success": true,
    "message": "Location updated."
}

Note: Primary method is WebSocket broadcast.
This REST endpoint is a fallback only.

WebSocket channel:
  private-booking.{bookingId}
  Event: LocationUpdated
  Payload: {
    "role": "worker",
    "latitude": 9.9530000,
    "longitude": 124.3660000,
    "accuracy": 5.2
  }
```

---

### G3. Stop Tracking
```
POST /api/bookings/{bookingId}/tracking/stop
(Protected, Client or Worker)

No request body needed.
Role determined from auth token.

Response 200:
{
    "success": true,
    "message": "Location sharing stopped.",
    "data": {
        "is_worker_tracking": false,
        "is_client_tracking": false
    }
}

Side Effects:
  Sets is_{role}_tracking = false
  Push notification to other party:
  Worker stopped: "Worker has arrived!"
  Client stopped: "Client has arrived!"
```

---

### G4. Get Current Tracking Status
```
GET /api/bookings/{bookingId}/tracking
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "is_client_tracking": false,
        "is_worker_tracking": true,
        "client_current_location": null,
        "worker_current_location": {
            "latitude": 9.9530000,
            "longitude": 124.3660000,
            "accuracy": 5.2,
            "recorded_at":
                "2026-09-15T08:45:00.000000Z"
        },
        "client_barangay": {
            "name": "Calanggaman",
            "latitude": 9.9523000,
            "longitude": 124.3701000
        },
        "worker_barangay": {
            "name": "Poblacion",
            "latitude": 9.9545000,
            "longitude": 124.3656000
        }
    }
}
```

---

## SECTION H: RATINGS & REPORTS

---

### H1. Submit Rating
```
POST /api/ratings
(Protected)

Request Body:
{
    "booking_id": 1,
    "score": 5,
    "comment": "Very professional and
                on time!"
}

Validation:
  booking_id: required, exists:bookings
  score:      required, integer, between:1,5
  comment:    optional, max:300

Business Rule:
  Booking must be completed status.
  Authenticated user must be participant
  (client or worker) in the booking.
  One review per person per booking.

Response 201:
{
    "success": true,
    "message": "Review submitted.",
    "data": {
        "rating": {
            "id": 1,
            "score": 5,
            "comment": "Very professional
                        and on time!"
        }
    }
}

Side Effects:
  If rated_user is a worker:
    Recalculates worker's average_rating
    Updates total_reviews count
  Push notification to rated user:
  "You received a new review!"
```

---

### H2. File a Report
```
POST /api/reports
(Protected, multipart/form-data)

Request Body:
  booking_id:       integer, optional
  reported_user_id: integer, required
  reason:           string, required,
                    in:no_show,
                    unsatisfactory_work,
                    misconduct,
                    non_payment,
                    unsafe_environment,
                    abusive_behavior,
                    false_information,
                    other
  description:      string, required,
                    min:20, max:1000
  evidence_photos:  file[], optional
                    max 5 photos
                    jpeg/png, max 2MB each

Response 201:
{
    "success": true,
    "message": "Report submitted. Our admin
                team will review it within
                1–3 business days.",
    "data": {
        "report_id": 1,
        "status": "under_review"
    }
}
```

---

### H3. Get My Reports
```
GET /api/reports
(Protected)

Query Parameters:
  status: string, optional
          (under_review/resolved/dismissed)

Response 200:
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": 1,
                "booking_code":
                    "HB-2026-00001",
                "reported_user_name":
                    "Pedro Alonzo",
                "reason": "no_show",
                "description": "Worker did
                    not show up...",
                "status": "resolved",
                "admin_remarks": "Warning
                    issued to worker.",
                "resolution_action":
                    "warning_issued",
                "created_at": "2026-09-01T...",
                "resolved_at": "2026-09-02T..."
            }
        ]
    }
}
```

---

## SECTION I: MESSAGING

---

### I1. Get Chat Inbox
```
GET /api/messages
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "conversations": [
            {
                "booking_id": 1,
                "booking_code":
                    "HB-2026-00001",
                "booking_status": "accepted",
                "other_party": {
                    "id": 8,
                    "name": "Pedro Alonzo",
                    "profile_photo_url": "..."
                },
                "last_message": "Okay, see
                    you then!",
                "last_message_at":
                    "2026-09-14T20:00:00Z",
                "unread_count": 2
            }
        ]
    }
}
```

---

### I2. Get Messages for Booking
```
GET /api/messages/{bookingId}
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "booking": {
            "id": 1,
            "booking_code": "HB-2026-00001",
            "status": "accepted"
        },
        "messages": [
            {
                "id": 1,
                "sender_id": 1,
                "sender_name": "Ana Cruz",
                "content": "Hi, I need help
                    with my aircon.",
                "attachment_url": null,
                "is_read": true,
                "created_at":
                    "2026-09-13T10:00:00Z"
            }
        ]
    }
}

Side Effect:
  Marks all unread messages from other
  party as read (is_read = true,
  read_at = now())
```

---

### I3. Send Message
```
POST /api/messages/{bookingId}
(Protected, multipart/form-data)

Request Body:
  content:    string, required if no
              attachment, max:1000
  attachment: file, optional
              (jpeg/png, max 5MB)

Response 201:
{
    "success": true,
    "data": {
        "message": {
            "id": 2,
            "sender_id": 1,
            "content": "Okay, I'll be
                        there at 9am.",
            "attachment_url": null,
            "is_read": false,
            "created_at":
                "2026-09-14T20:00:00Z"
        }
    }
}

Side Effects:
  WebSocket broadcast to receiver:
  Channel: private-booking.{bookingId}
  Event: NewMessage
  Push notification to receiver:
  "New message from [sender name]"
```

---

## SECTION J: NOTIFICATIONS

---

### J1. Get Notifications
```
GET /api/notifications
(Protected)

Query Parameters:
  page: integer, default: 1

Response 200:
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": 1,
                "title": "Booking Accepted",
                "body": "Pedro Alonzo accepted
                         your booking.",
                "type": "booking_accepted",
                "data": {
                    "booking_id": 1,
                    "booking_code":
                        "HB-2026-00001"
                },
                "is_read": false,
                "created_at":
                    "2026-09-14T10:00:00Z"
            }
        ],
        "unread_count": 3,
        "pagination": { ... }
    }
}
```

---

### J2. Mark Notification as Read
```
POST /api/notifications/{id}/read
(Protected)

Response 200:
{
    "success": true,
    "message": "Notification marked as read."
}
```

---

### J3. Mark All Notifications as Read
```
POST /api/notifications/read-all
(Protected)

Response 200:
{
    "success": true,
    "message": "All notifications marked
                as read."
}
```

---

## SECTION K: ADMIN ROUTES

All admin routes require:
- Valid Sanctum token
- User role = 'admin'
- Middleware: auth:sanctum + AdminOnly

---

### K1. Admin Dashboard Stats
```
GET /api/admin/dashboard
(Admin only)

Response 200:
{
    "success": true,
    "data": {
        "total_users": 284,
        "total_clients": 180,
        "total_workers": 104,
        "pending_verifications": 12,
        "active_bookings": 8,
        "open_disputes": 3,
        "completed_bookings_today": 15,
        "total_active_job_posts": 67,
        "recent_activity": [
            {
                "type": "verification_submitted",
                "description": "Liza Dimaano
                    submitted verification
                    documents",
                "created_at": "2026-08-26T..."
            }
        ]
    }
}
```

---

### K2. Get Pending Verifications
```
GET /api/admin/verifications/pending
(Admin only)

Query Parameters:
  search: string, optional
  page:   integer, default: 1

Response 200:
{
    "success": true,
    "data": {
        "verifications": [
            {
                "worker_profile_id": 5,
                "user": {
                    "id": 10,
                    "name": "Liza Dimaano",
                    "email":
                        "liza@email.com",
                    "mobile_number":
                        "09987654321",
                    "barangay": "Poblacion"
                },
                "submitted_at":
                    "2026-08-26T08:00:00Z",
                "time_elapsed": "2 hours ago",
                "documents": [
                    {
                        "id": 1,
                        "type": "government_id",
                        "file_url": "http://...
                            storage/
                            verifications/
                            10/gov_id.jpg",
                        "status": "pending"
                    },
                    {
                        "id": 2,
                        "type":
                            "barangay_certificate",
                        "file_url": "...",
                        "status": "pending"
                    },
                    {
                        "id": 3,
                        "type": "selfie_with_id",
                        "file_url": "...",
                        "status": "pending"
                    }
                ]
            }
        ],
        "pagination": { ... }
    }
}
```

---

### K3. Review Verification
```
POST /api/admin/verifications/
     {workerProfileId}/review
(Admin only)

Request Body:
{
    "action": "approve",
    "remarks": "Documents verified
                successfully."
}
OR:
{
    "action": "reject",
    "remarks": "Barangay certificate
                image is unclear. Please
                resubmit a clearer photo."
}
OR:
{
    "action": "request_resubmission",
    "remarks": "Selfie with ID is missing.
                Please retake."
}

Validation:
  action:  required,
           in:approve,reject,
           request_resubmission
  remarks: required

Response 200:
{
    "success": true,
    "message": "Worker verification approved.",
    "data": {
        "verification_status": "approved",
        "trust_tier": "verified"
    }
}

Side Effects:
  Updates worker_profile verification fields
  If approved: trust_tier = 'verified'
  Push notification sent to worker
  Logged in admin_audit_logs
```

---

### K4. Update Worker Trust Tier
```
POST /api/admin/workers/
     {workerProfileId}/trust-tier
(Admin only)

Request Body:
{
    "trust_tier": "flagged",
    "remarks": "Multiple complaints received."
}

Validation:
  trust_tier: required,
              in:verified,trusted,
              flagged,revoked
  remarks:    required

Response 200:
{
    "success": true,
    "message": "Trust tier updated to flagged."
}

Side Effects:
  Updates trust_tier in worker_profiles
  If revoked: job posts hidden from feed,
              user cannot log in
  Push notification to worker
  Logged in admin_audit_logs
```

---

### K5. Get All Users (Admin)
```
GET /api/admin/users
(Admin only)

Query Parameters:
  role:     string (client/worker/admin)
  status:   string (active/suspended)
  barangay: integer (barangay_id)
  search:   string (name or email)
  page:     integer, default: 1

Response 200:
{
    "success": true,
    "data": {
        "users": [
            {
                "id": 1,
                "name": "Juan dela Cruz",
                "email": "juan@email.com",
                "role": "client",
                "barangay": "Calanggaman",
                "is_active": true,
                "email_verified_at": "...",
                "created_at": "...",
                "verification_status": null
                // null for clients
                // "approved" etc for workers
            }
        ],
        "pagination": { ... }
    }
}
```

---

### K6. Get User Detail (Admin)
```
GET /api/admin/users/{userId}
(Admin only)

Response 200:
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Juan dela Cruz",
        "email": "juan@email.com",
        "mobile_number": "09123456789",
        "role": "client",
        "barangay": "Calanggaman",
        "is_active": true,
        "created_at": "...",
        "last_login_at": "...",
        "total_bookings": 12,
        "completed_bookings": 10,
        "reports_filed": 1,
        "reports_received": 0,
        "worker_profile": null
        // full worker_profile if worker
    }
}
```

---

### K7. Toggle User Status
```
POST /api/admin/users/{userId}/toggle-status
(Admin only)

Request Body:
{
    "action": "suspend",
    "reason": "Repeated policy violations"
}
OR:
{
    "action": "reactivate"
}

Response 200:
{
    "success": true,
    "message": "User account suspended."
}

Side Effects:
  Updates is_active on users table
  If suspended: user cannot log in
  Logged in admin_audit_logs
```

---

### K8. Get All Job Posts (Admin)
```
GET /api/admin/posts
(Admin only)

Query Parameters:
  category_id:     integer, optional
  barangay_id:     integer, optional
  status:          string (active/inactive)
  verification:    string (verified/unverified)
  search:          string
  page:            integer

Response 200:
{
    "success": true,
    "data": {
        "posts": [ ... ],
        "pagination": { ... }
    }
}
```

---

### K9. Deactivate Job Post (Admin)
```
DELETE /api/admin/posts/{postId}
(Admin only)

Request Body:
{
    "reason": "Violates platform policies"
}

Response 200:
{
    "success": true,
    "message": "Job post removed."
}

Side Effects:
  Soft deletes the post
  Worker notified
  Logged in admin_audit_logs
```

---

### K10. Get All Bookings (Admin)
```
GET /api/admin/bookings
(Admin only)

Query Parameters:
  status:      string, optional
  category_id: integer, optional
  date_from:   date, optional
  date_to:     date, optional
  search:      string
               (booking code, client,
               or worker name)
  page:        integer

Response 200:
{
    "success": true,
    "data": {
        "bookings": [
            {
                "id": 1,
                "booking_code":
                    "HB-2026-00001",
                "client_name": "Ana Cruz",
                "worker_name": "Pedro Alonzo",
                "category":
                    "Aircon Repair & Cleaning",
                "scheduled_at": "...",
                "status": "completed",
                "created_at": "..."
            }
        ],
        "pagination": { ... }
    }
}
```

---

### K11. Force Cancel Booking (Admin)
```
POST /api/admin/bookings/{bookingId}/cancel
(Admin only)

Request Body:
{
    "reason": "Fraudulent booking detected"
}

Response 200:
{
    "success": true,
    "message": "Booking force cancelled."
}

Side Effects:
  Sets status = cancelled
  Sets cancelled_by = 'admin'
  Sets force_cancelled_by = admin user ID
  Both parties notified
  Logged in admin_audit_logs
```

---

### K12. Get All Reports (Admin)
```
GET /api/admin/reports
(Admin only)

Query Parameters:
  status: string (under_review/resolved/
          dismissed)
  reason: string, optional
  page:   integer

Response 200:
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": 1,
                "booking_code":
                    "HB-2026-00001",
                "filed_by": {
                    "name": "Ana Cruz",
                    "role": "client"
                },
                "reported_user": {
                    "name": "Pedro Alonzo",
                    "role": "worker",
                    "trust_tier": "verified"
                },
                "reason": "no_show",
                "status": "under_review",
                "evidence_urls": [
                    "http://.../storage/
                     reports/1/photo1.jpg"
                ],
                "created_at": "..."
            }
        ],
        "pagination": { ... }
    }
}
```

---

### K13. Resolve Report (Admin)
```
POST /api/admin/reports/{reportId}/resolve
(Admin only)

Request Body:
{
    "resolution_action": "warning_issued",
    "admin_remarks": "First offense. Warning
        issued to worker. Repeated violations
        will result in account suspension."
}

Validation:
  resolution_action: required,
    in:warning_issued,
    account_suspended,
    verification_revoked,
    no_action
  admin_remarks: required

Response 200:
{
    "success": true,
    "message": "Report resolved."
}

Side Effects:
  Updates report status to 'resolved'
  Applies resolution_action:
    account_suspended → is_active = false
    verification_revoked → trust_tier = 'revoked'
  Both parties notified of resolution
  Logged in admin_audit_logs
```

---

### K14. Get All Ratings (Admin)
```
GET /api/admin/ratings
(Admin only)

Query Parameters:
  score:     integer (1-5), optional
  direction: string (client_to_worker/
             worker_to_client), optional
  search:    string
  page:      integer

Response 200:
{
    "success": true,
    "data": {
        "ratings": [
            {
                "id": 1,
                "rated_by_name": "Ana Cruz",
                "rated_user_name":
                    "Pedro Alonzo",
                "booking_code":
                    "HB-2026-00001",
                "score": 5,
                "comment": "Very professional!",
                "created_at": "..."
            }
        ]
    }
}
```

---

### K15. Remove Rating (Admin)
```
DELETE /api/admin/ratings/{ratingId}
(Admin only)

Request Body:
{
    "reason": "Review violates platform
               content policy"
}

Response 200:
{
    "success": true,
    "message": "Review removed."
}

Side Effects:
  Deletes rating record
  Recalculates worker's average_rating
  Logged in admin_audit_logs
```

---

### K16. Get Audit Logs (Admin)
```
GET /api/admin/audit-logs
(Admin only)

Query Parameters:
  admin_id:    integer, optional
  action:      string, optional
  target_type: string, optional
  date_from:   date, optional
  date_to:     date, optional
  page:        integer

Response 200:
{
    "success": true,
    "data": {
        "logs": [
            {
                "id": 1,
                "admin": {
                    "id": 5,
                    "name": "Admin User"
                },
                "action":
                    "approved_worker_verification",
                "target_type": "WorkerProfile",
                "target_id": 3,
                "details": {
                    "worker_name":
                        "Liza Dimaano"
                },
                "ip_address": "192.168.1.5",
                "created_at": "..."
            }
        ],
        "pagination": { ... }
    }
}
```

---

### K17. Platform Settings (Admin)
```
GET /api/admin/settings
(Admin only)

Response 200:
{
    "success": true,
    "data": {
        "service_categories": [ ... ],
        "report_reasons": [ ... ],
        "notification_templates": [ ... ],
        "active_announcement": {
            "title": "Platform maintenance
                       on Sept 30.",
            "body": "...",
            "expires_at": "2026-09-30"
        }
    }
}

POST /api/admin/settings
(Admin only)

Request Body:
{
    "action": "add_category",
    "name": "Tailoring",
    "icon": "tailoring"
}
OR:
{
    "action": "post_announcement",
    "title": "System Maintenance",
    "body": "HanapBuhay will be down
              for maintenance on Sept 30,
              2026 from 12AM-3AM.",
    "expires_at": "2026-09-30"
}

Response 200:
{
    "success": true,
    "message": "Setting updated."
}
```

---

## WebSocket Events Reference

```
All WebSocket channels are private and
require authentication via Sanctum.

Channel: private-booking.{bookingId}

Events:
  LocationUpdated
  Payload: {
    role: "worker" | "client",
    latitude: float,
    longitude: float,
    accuracy: float
  }

  NewMessage
  Payload: {
    id: integer,
    sender_id: integer,
    sender_name: string,
    content: string,
    attachment_url: string | null,
    created_at: string
  }

  BookingStatusUpdated
  Payload: {
    booking_id: integer,
    status: string,
    updated_at: string
  }

  TrackingStarted
  Payload: {
    role: "worker" | "client"
  }

  TrackingStopped
  Payload: {
    role: "worker" | "client"
  }
```

---

## Postman Collection Notes

```
Create a Postman environment with:
  base_url: http://127.0.0.1:8000/api
  token: (empty initially)

After login or verify-otp,
copy the token value and set:
  token: 1|abc123xyz...

All protected requests use:
  Authorization: Bearer {{token}}

Test order for Section B:
  1. POST /auth/register (role: client)
  2. Check Laravel log for OTP code:
     Get-Content storage\logs\laravel.log -Tail 30
  3. POST /auth/email/verify (with code)
  4. Copy token from response
  5. GET /user (verify token works)
  6. POST /auth/logout
  7. Try GET /user (should return 401)
```