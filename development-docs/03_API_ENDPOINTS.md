# HanapBuhay — API Endpoints Reference
**For: Laravel Developer (PM)**

---

> This document is the CONTRACT between the
> backend (Laravel) and the frontends
> (Flutter + React).
>
> Share this with your Flutter Dev and Web Dev
> so they can build their UIs against these
> exact request/response formats.
>
> Base URL (local): http://hanapbuhay-api.test/api
> Base URL (emulator): http://10.0.2.2/hanapbuhay-api/public/api

---

## Authentication Header

All protected endpoints require:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## Standard Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

---

## Route Groups Overview

```
Public (no auth required):
  POST /api/auth/register
  POST /api/auth/login
  POST /api/auth/google
  POST /api/auth/email/verify
  POST /api/auth/email/resend-otp
  POST /api/auth/password/forgot
  POST /api/auth/password/verify-otp
  POST /api/auth/password/reset
  GET  /api/barangays
  GET  /api/service-categories
  GET  /api/ping

Protected (requires auth token):
  All other endpoints

Admin Only (requires auth + admin role):
  All /api/admin/* endpoints
```

---

# ═══════════════════════════
# SECTION A — Public Routes
# ═══════════════════════════

---

## A1. Ping / Health Check

```
GET /api/ping

Response 200:
{
    "success": true,
    "message": "HanapBuhay API is running",
    "data": {
        "version": "1.0.0",
        "scope": "Trinidad, Bohol"
    }
}
```

---

## A2. Get All Barangays

```
GET /api/barangays

Response 200:
{
    "success": true,
    "message": "Barangays retrieved",
    "data": [
        {
            "id": 1,
            "name": "Alapuyan",
            "latitude": 9.9612,
            "longitude": 124.3701
        },
        ...
    ]
}
```

---

## A3. Get Service Categories

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
        ...
    ]
}
```

---

# ═══════════════════════════
# SECTION B — Authentication
# ═══════════════════════════

---

## B1. Register (Manual)

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
- name: required, string, max:255
- email: required, email, unique:users
- password: required, min:8, confirmed
- mobile_number: nullable, string
- role: required, in:client,worker
- barangay_id: required, exists:barangays,id

Success Response 201:
{
    "success": true,
    "message": "Registration successful. Please verify your email.",
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
- Creates user record
- If role = 'worker': creates worker_profile record
- Sends OTP email via Brevo
- OTP valid for 10 minutes
```

---

## B2. Login (Manual)

```
POST /api/auth/login

Request Body:
{
    "email": "juan@email.com",
    "password": "password123"
}

Success Response 200:
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "1|abc123xyz...",
        "user": {
            "id": 1,
            "name": "Juan dela Cruz",
            "email": "juan@email.com",
            "role": "client",
            "profile_photo_url": null,
            "barangay": {
                "id": 9,
                "name": "Calanggaman",
                "latitude": 9.9523,
                "longitude": 124.3701
            },
            "email_verified_at": "2026-01-01T00:00:00Z",
            "is_google_account": false
        }
    }
}

Error Response 401:
{
    "success": false,
    "message": "Invalid email or password"
}

Error Response 403 (unverified):
{
    "success": false,
    "message": "Please verify your email first"
}
```

---

## B3. Google Sign-In

```
POST /api/auth/google

Request Body:
{
    "google_token": "google_id_token_from_flutter",
    "role": "client"
}
Note: role only required for NEW users.
      Existing users: role is ignored.

Flow:
1. Flutter gets ID token from google_sign_in package
2. Sends token to this endpoint
3. Laravel verifies token with Google
4. Creates or finds user account
5. Returns Sanctum token

Success Response 200 (existing user):
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "2|xyz789...",
        "is_new_user": false,
        "user": {
            "id": 2,
            "name": "Maria Santos",
            "email": "maria@gmail.com",
            "role": "worker",
            "profile_photo_url": "https://...",
            "barangay": { ... },
            "is_google_account": true
        }
    }
}

Success Response 201 (new user):
{
    "success": true,
    "message": "Account created successfully",
    "data": {
        "token": "3|abc456...",
        "is_new_user": true,
        "user": {
            "id": 3,
            "name": "Pedro Reyes",
            "email": "pedro@gmail.com",
            "role": null,
            "barangay": null,
            "is_google_account": true
        }
    }
}

Note: If is_new_user = true, Flutter
navigates to CompleteProfileScreen
where user selects role and barangay.
```

---

## B4. Complete Google Profile

```
POST /api/auth/google/complete-profile
(Protected — requires token from B3)

Request Body (multipart/form-data):
{
    "name": "Pedro Reyes",
    "mobile_number": "09187654321",
    "role": "client",
    "barangay_id": 27,
    "profile_photo": [file] (optional)
}

Success Response 200:
{
    "success": true,
    "message": "Profile completed",
    "data": {
        "user": { ...complete user object... }
    }
}
```

---

## B5. Email OTP Verification

```
POST /api/auth/email/verify

Request Body:
{
    "email": "juan@email.com",
    "code": "482931"
}

Success Response 200:
{
    "success": true,
    "message": "Email verified successfully",
    "data": {
        "token": "4|xyz123...",
        "user": { ...complete user object... }
    }
}

Note: Returns token on successful verification
so user is immediately logged in.

Error Response 422:
{
    "success": false,
    "message": "Invalid or expired verification code"
}
```

---

## B6. Resend Email OTP

```
POST /api/auth/email/resend-otp

Request Body:
{
    "email": "juan@email.com"
}

Success Response 200:
{
    "success": true,
    "message": "Verification code resent to your email"
}

Rate Limited: max 3 requests per 10 minutes
```

---

## B7. Forgot Password — Send OTP

```
POST /api/auth/password/forgot

Request Body:
{
    "email": "juan@email.com"
}

Success Response 200:
{
    "success": true,
    "message": "Password reset code sent to your email"
}

Note: Always returns 200 even if email
doesn't exist (security — don't reveal
if email is registered or not)
```

---

## B8. Forgot Password — Verify OTP

```
POST /api/auth/password/verify-otp

Request Body:
{
    "email": "juan@email.com",
    "code": "739201"
}

Success Response 200:
{
    "success": true,
    "message": "Code verified. You can now reset your password.",
    "data": {
        "reset_token": "temp_reset_token_xyz"
    }
}

Note: reset_token is a short-lived token
used in the next step to authorize
the password reset.
```

---

## B9. Forgot Password — Reset

```
POST /api/auth/password/reset

Request Body:
{
    "email": "juan@email.com",
    "reset_token": "temp_reset_token_xyz",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}

Success Response 200:
{
    "success": true,
    "message": "Password reset successfully. Please log in."
}
```

---

## B10. Logout

```
POST /api/auth/logout
(Protected)

Success Response 200:
{
    "success": true,
    "message": "Logged out successfully"
}

Side Effect: Deletes current Sanctum token
```

---

# ═══════════════════════════════
# SECTION C — User Profile
# ═══════════════════════════════

---

## C1. Get Current User

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
        "profile_photo_url": "http://hanapbuhay-api.test/storage/photos/1.jpg",
        "barangay": {
            "id": 9,
            "name": "Calanggaman",
            "latitude": 9.9523,
            "longitude": 124.3701
        },
        "email_verified_at": "2026-01-01T00:00:00Z",
        "is_google_account": false,
        "worker_profile": null
        // or worker profile object if role=worker
    }
}
```

---

## C2. Update Profile

```
POST /api/user/profile
(Protected, multipart/form-data)

Request Body:
{
    "name": "Juan C. dela Cruz",
    "mobile_number": "09987654321",
    "barangay_id": 27,
    "profile_photo": [file] (optional)
}

Response 200:
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "user": { ...updated user object... }
    }
}
```

---

## C3. Change Password

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
    "message": "Password changed successfully"
}

Error 422 (wrong current password):
{
    "success": false,
    "message": "Current password is incorrect"
}
```

---

## C4. Get Login Activity

```
GET /api/user/login-activity
(Protected)

Response 200:
{
    "success": true,
    "data": [
        {
            "id": 1,
            "device": "Android",
            "ip_address": "192.168.1.5",
            "last_used_at": "2026-08-25T10:30:00Z",
            "is_current": true
        },
        ...
    ]
}
```

---

## C5. Revoke Session (Logout Other Device)

```
DELETE /api/user/sessions/{tokenId}
(Protected)

Response 200:
{
    "success": true,
    "message": "Session revoked"
}
```

---

# ═══════════════════════════════
# SECTION D — Worker Features
# ═══════════════════════════════

---

## D1. Search / Browse Workers

```
GET /api/workers
(Protected)

Query Parameters:
- barangay_id (optional) — filter by barangay
- category_id (optional) — filter by service category
- min_rating (optional) — minimum rating (1-5)
- verified_only (optional) — boolean
- search (optional) — search by name

Example:
GET /api/workers?barangay_id=9&category_id=1&verified_only=true

Response 200:
{
    "success": true,
    "data": {
        "workers": [
            {
                "worker_profile_id": 1,
                "user_id": 5,
                "name": "Pedro Alonzo",
                "profile_photo_url": "...",
                "barangay": "Calanggaman",
                "barangay_id": 9,
                "distance_km": 0.0,
                "distance_label": "~0.0 km",
                "categories": ["Electrical Works", "Plumbing"],
                "average_rating": 4.8,
                "total_reviews": 23,
                "completed_jobs": 45,
                "trust_tier": "trusted",
                "verification_status": "approved",
                "availability_status": "available"
            },
            ...
        ],
        "total": 12
    }
}

Note: distance_km computed via Haversine using
client's barangay coords vs worker's barangay coords
```

---

## D2. Get Worker Profile (Public)

```
GET /api/workers/{workerProfileId}
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "worker_profile_id": 1,
        "user_id": 5,
        "name": "Pedro Alonzo",
        "profile_photo_url": "...",
        "bio": "Licensed electrician with 5 years experience.",
        "barangay": "Calanggaman",
        "distance_km": 2.3,
        "distance_label": "~2.3 km",
        "categories": [
            {"id": 1, "name": "Electrical Works"},
            {"id": 2, "name": "Plumbing"}
        ],
        "portfolio_photos": [
            "http://.../storage/portfolio/1.jpg",
            "http://.../storage/portfolio/2.jpg"
        ],
        "average_rating": 4.8,
        "total_reviews": 23,
        "completed_jobs": 45,
        "trust_tier": "trusted",
        "verification_status": "approved",
        "availability_status": "available",
        "reviews": [
            {
                "rated_by_name": "Ana Cruz",
                "score": 5,
                "comment": "Very professional!",
                "created_at": "2026-08-01T00:00:00Z"
            },
            ...
        ]
    }
}
```

---

## D3. Submit Verification Documents

```
POST /api/worker/verification/submit
(Protected, Worker only, multipart/form-data)

Request Body:
{
    "government_id": [file],
    "barangay_certificate": [file],
    "selfie_with_id": [file],
    "skill_certificate": [file] (optional)
}

Response 201:
{
    "success": true,
    "message": "Documents submitted for review",
    "data": {
        "verification_status": "pending"
    }
}

Error 422 (already pending or approved):
{
    "success": false,
    "message": "You already have a pending or approved verification"
}
```

---

## D4. Get Worker's Own Verification Status

```
GET /api/worker/verification/status
(Protected, Worker only)

Response 200:
{
    "success": true,
    "data": {
        "verification_status": "rejected",
        "trust_tier": null,
        "remarks": "Barangay certificate image is unclear. Please resubmit.",
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

## D5. Update Worker Profile

```
POST /api/worker/profile
(Protected, Worker only, multipart/form-data)

Request Body:
{
    "bio": "Experienced plumber...",
    "availability_status": "available",
    "category_ids": [1, 2, 5],
    "portfolio_photos": [file1, file2]
    // (new photos to add)
}

Response 200:
{
    "success": true,
    "message": "Profile updated",
    "data": {
        "worker_profile": { ... }
    }
}
```

---

## D6. Delete Portfolio Photo

```
DELETE /api/worker/portfolio/{photoId}
(Protected, Worker only)

Response 200:
{
    "success": true,
    "message": "Photo removed"
}
```

---

# ═══════════════════════════════
# SECTION E — Bookings
# ═══════════════════════════════

---

## E1. Create Booking Request (Client)

```
POST /api/bookings
(Protected, Client only)

Request Body:
{
    "worker_profile_id": 1,
    "service_category_id": 1,
    "scheduled_at": "2026-09-15T09:00:00",
    "notes": "Leaking pipe under kitchen sink"
}

Response 201:
{
    "success": true,
    "message": "Booking request sent",
    "data": {
        "booking": {
            "id": 1,
            "booking_code": "HB-2026-00001",
            "status": "pending",
            "worker": {
                "name": "Pedro Alonzo",
                "profile_photo_url": "..."
            },
            "service_category": "Plumbing",
            "scheduled_at": "2026-09-15T09:00:00Z",
            "notes": "Leaking pipe under kitchen sink"
        }
    }
}
```

---

## E2. Get My Bookings

```
GET /api/bookings
(Protected)

Query Parameters:
- status (optional): pending, accepted, active,
  completed, cancelled
- role (optional): as_client, as_worker
  (defaults to current user's role)

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
                "service_category": "Plumbing",
                "scheduled_at": "2026-09-15T09:00:00Z",
                "is_client_tracking": false,
                "is_worker_tracking": false
            },
            ...
        ]
    }
}
```

---

## E3. Get Booking Detail

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
            "name": "Juan dela Cruz",
            "profile_photo_url": "...",
            "barangay": {
                "id": 9,
                "name": "Calanggaman",
                "latitude": 9.9523,
                "longitude": 124.3701
            }
        },
        "worker": {
            "id": 5,
            "name": "Pedro Alonzo",
            "profile_photo_url": "...",
            "barangay": {
                "id": 27,
                "name": "Poblacion",
                "latitude": 9.9545,
                "longitude": 124.3656
            }
        },
        "service_category": "Plumbing",
        "scheduled_at": "2026-09-15T09:00:00Z",
        "notes": "Leaking pipe",
        "is_client_tracking": false,
        "is_worker_tracking": true,
        "worker_current_location": {
            "latitude": 9.9530,
            "longitude": 124.3660,
            "recorded_at": "2026-09-15T08:45:00Z"
        },
        "client_current_location": null,
        "distance_km": 0.3,
        "started_at": null,
        "completed_at": null
    }
}
```

---

## E4. Accept / Decline Booking (Worker)

```
POST /api/bookings/{bookingId}/respond
(Protected, Worker only)

Request Body:
{
    "action": "accept"
    // or "decline"
}

Response 200:
{
    "success": true,
    "message": "Booking accepted",
    "data": {
        "booking": { ...updated booking... }
    }
}

Side Effects:
- Sends push notification to client
- If accepted: sends notification
  "Worker accepted your booking"
- If declined: sends notification
  "Worker declined your booking request"
```

---

## E5. Cancel Booking

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
    "message": "Booking cancelled",
    "data": {
        "booking": { ...updated booking... }
    }
}
```

---

## E6. Start Tracking (I'm On My Way)

```
POST /api/bookings/{bookingId}/tracking/start
(Protected, Client or Worker)

No request body needed.
Role determined from auth token.

Response 200:
{
    "success": true,
    "message": "Location sharing started",
    "data": {
        "tracking_role": "worker",
        "is_worker_tracking": true,
        "is_client_tracking": false
    }
}

Side Effects:
- Sets is_X_tracking = true in bookings table
- Sends push notification to other party:
  "Worker is on the way!" or
  "Client is heading to your location"
```

---

## E7. Update Live Location (WebSocket)

```
This is a WebSocket event, NOT a REST endpoint.
Flutter broadcasts location via Laravel Echo.

Channel: private-booking.{bookingId}
Event:   LocationUpdated

Payload sent by Flutter:
{
    "latitude": 9.9530,
    "longitude": 124.3660,
    "role": "worker",
    "accuracy": 5.2
}

Laravel re-broadcasts to the other party.
Other party's Flutter map updates in real-time.

Also: POST /api/bookings/{bookingId}/tracking/location
(REST fallback if WebSocket unavailable)

Request Body:
{
    "latitude": 9.9530,
    "longitude": 124.3660,
    "accuracy": 5.2
}

Response 200:
{
    "success": true,
    "message": "Location updated"
}
```

---

## E8. Stop Tracking (I've Arrived)

```
POST /api/bookings/{bookingId}/tracking/stop
(Protected, Client or Worker)

No request body needed.
Role determined from auth token.

Response 200:
{
    "success": true,
    "message": "Location sharing stopped",
    "data": {
        "is_worker_tracking": false,
        "is_client_tracking": false
    }
}

Side Effects:
- Sets is_X_tracking = false
- Sends push notification to other party:
  "Worker has arrived!" or
  "Client has arrived!"
```

---

## E9. Mark Job as Started / Completed

```
POST /api/bookings/{bookingId}/status
(Protected)

Request Body:
{
    "status": "active"
    // or "completed"
}

Response 200:
{
    "success": true,
    "message": "Booking status updated",
    "data": {
        "booking": { ...updated booking... }
    }
}

Note: Both client and worker must confirm
completion (or just worker, depending on
your final decision on who marks complete)
```

---

# ═══════════════════════════════
# SECTION F — Ratings & Reports
# ═══════════════════════════════

---

## F1. Submit Rating & Review

```
POST /api/ratings
(Protected)

Request Body:
{
    "booking_id": 1,
    "score": 5,
    "comment": "Very professional and on time!"
}

Response 201:
{
    "success": true,
    "message": "Review submitted",
    "data": {
        "rating": {
            "id": 1,
            "score": 5,
            "comment": "Very professional and on time!"
        }
    }
}

Side Effects:
- Updates worker's average_rating in worker_profiles
- Sends notification to rated user
```

---

## F2. File a Report

```
POST /api/reports
(Protected, multipart/form-data)

Request Body:
{
    "booking_id": 1,
    "reported_user_id": 5,
    "reason": "no_show",
    "description": "Worker did not show up.",
    "evidence_photos": [file1, file2] (optional)
}

Response 201:
{
    "success": true,
    "message": "Report submitted",
    "data": {
        "report_id": 1,
        "status": "under_review"
    }
}
```

---

## F3. Get My Reports

```
GET /api/reports
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": 1,
                "booking_code": "HB-2026-00001",
                "reason": "no_show",
                "status": "resolved",
                "admin_remarks": "Warning issued to worker.",
                "created_at": "2026-09-01T00:00:00Z"
            }
        ]
    }
}
```

---

# ═════════════════════════════════
# SECTION G — Messaging
# ═════════════════════════════════

---

## G1. Get Chat Inbox

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
                "booking_code": "HB-2026-00001",
                "other_party": {
                    "name": "Pedro Alonzo",
                    "profile_photo_url": "..."
                },
                "last_message": "Okay, see you then!",
                "last_message_at": "2026-09-14T20:00:00Z",
                "unread_count": 2
            }
        ]
    }
}
```

---

## G2. Get Messages for a Booking

```
GET /api/messages/{bookingId}
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "messages": [
            {
                "id": 1,
                "sender_id": 1,
                "content": "Hi, I need help with my pipe.",
                "attachment_url": null,
                "is_read": true,
                "created_at": "2026-09-13T10:00:00Z"
            }
        ]
    }
}

Side Effect: Marks all unread messages as read
```

---

## G3. Send Message

```
POST /api/messages/{bookingId}
(Protected, multipart/form-data)

Request Body:
{
    "content": "Okay, I'll be there at 9am.",
    "attachment": [file] (optional)
}

Response 201:
{
    "success": true,
    "data": {
        "message": {
            "id": 2,
            "sender_id": 5,
            "content": "Okay, I'll be there at 9am.",
            "created_at": "2026-09-14T20:00:00Z"
        }
    }
}

Side Effect: Sends push notification to receiver
```

---

# ═════════════════════════════════
# SECTION H — Notifications
# ═════════════════════════════════

---

## H1. Get Notifications

```
GET /api/notifications
(Protected)

Response 200:
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": 1,
                "title": "Booking Accepted",
                "body": "Pedro Alonzo accepted your booking.",
                "type": "booking_accepted",
                "data": {"booking_id": 1},
                "is_read": false,
                "created_at": "2026-09-14T10:00:00Z"
            }
        ],
        "unread_count": 3
    }
}
```

---

## H2. Mark Notification as Read

```
POST /api/notifications/{id}/read
(Protected)

Response 200:
{
    "success": true,
    "message": "Notification marked as read"
}
```

---

## H3. Mark All Notifications as Read

```
POST /api/notifications/read-all
(Protected)

Response 200:
{
    "success": true,
    "message": "All notifications marked as read"
}
```

---

## H4. Update FCM Token

```
POST /api/user/fcm-token
(Protected)

Request Body:
{
    "fcm_token": "device_fcm_token_string"
}

Response 200:
{
    "success": true,
    "message": "FCM token updated"
}

Note: Call this every time app starts
and when Firebase issues a new token.
```

---

# ═════════════════════════════════
# SECTION I — Admin Routes
# ═════════════════════════════════

All admin routes require:
- Valid auth token
- User role = 'admin'
Middleware: auth:sanctum + AdminOnly

---

## I1. Admin Dashboard Stats

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
        "recent_activity": [...]
    }
}
```

---

## I2. Get Pending Verifications

```
GET /api/admin/verifications/pending
(Admin only)

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
                    "email": "liza@email.com",
                    "barangay": "Poblacion"
                },
                "submitted_at": "2026-09-14T08:00:00Z",
                "documents": [
                    {
                        "id": 1,
                        "type": "government_id",
                        "file_url": "http://.../storage/docs/1.jpg",
                        "status": "pending"
                    },
                    ...
                ]
            }
        ]
    }
}
```

---

## I3. Approve / Reject Verification

```
POST /api/admin/verifications/{workerProfileId}/review
(Admin only)

Request Body:
{
    "action": "approve",
    "remarks": "Documents verified successfully."
    // remarks required if action = "reject"
}

Response 200:
{
    "success": true,
    "message": "Worker verification approved",
    "data": {
        "verification_status": "approved",
        "trust_tier": "verified"
    }
}

Side Effects:
- Updates worker_profile verification_status
- If approved: sets trust_tier = 'verified'
- Sends push notification to worker
- Logs action in admin_audit_logs
```

---

## I4. Request Document Resubmission

```
POST /api/admin/verifications/{workerProfileId}/resubmit
(Admin only)

Request Body:
{
    "remarks": "Barangay certificate is unclear. Please resubmit."
}

Response 200:
{
    "success": true,
    "message": "Resubmission requested"
}
```

---

## I5. Update Worker Trust Tier

```
POST /api/admin/workers/{workerProfileId}/trust-tier
(Admin only)

Request Body:
{
    "trust_tier": "flagged",
    "remarks": "Multiple complaints received."
}

Response 200:
{
    "success": true,
    "message": "Trust tier updated to flagged"
}
```

---

## I6. Get All Users

```
GET /api/admin/users
(Admin only)

Query Parameters:
- role (optional): client, worker, admin
- status (optional): active, suspended
- search (optional): name or email
- page (optional): pagination

Response 200:
{
    "success": true,
    "data": {
        "users": [...],
        "total": 284,
        "per_page": 20,
        "current_page": 1
    }
}
```

---

## I7. Suspend / Reactivate User

```
POST /api/admin/users/{userId}/toggle-status
(Admin only)

Request Body:
{
    "action": "suspend",
    "reason": "Repeated policy violations"
}

Response 200:
{
    "success": true,
    "message": "User account suspended"
}
```

---

## I8. Get All Bookings (Admin)

```
GET /api/admin/bookings
(Admin only)

Query Parameters:
- status (optional)
- date_from (optional)
- date_to (optional)

Response 200:
{
    "success": true,
    "data": {
        "bookings": [...],
        "total": 156
    }
}
```

---

## I9. Force Cancel Booking

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
    "message": "Booking force cancelled"
}
```

---

## I10. Get All Reports / Disputes

```
GET /api/admin/reports
(Admin only)

Query Parameters:
- status (optional): under_review, resolved, dismissed

Response 200:
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": 1,
                "booking_code": "HB-2026-00001",
                "reported_by": "Ana Cruz",
                "reported_user": "Pedro Alonzo",
                "reason": "no_show",
                "status": "under_review",
                "evidence_urls": [...]
            }
        ]
    }
}
```

---

## I11. Resolve Report

```
POST /api/admin/reports/{reportId}/resolve
(Admin only)

Request Body:
{
    "resolution_action": "warning_issued",
    "admin_remarks": "First offense. Warning issued to worker."
}

Response 200:
{
    "success": true,
    "message": "Report resolved"
}
```

---

## I12. Get Audit Logs

```
GET /api/admin/audit-logs
(Admin only)

Response 200:
{
    "success": true,
    "data": {
        "logs": [
            {
                "id": 1,
                "admin_name": "Admin User",
                "action": "approved_worker_verification",
                "target_type": "WorkerProfile",
                "target_id": 5,
                "details": {"worker_name": "Liza Dimaano"},
                "created_at": "2026-09-14T10:00:00Z"
            }
        ]
    }
}
```

---

## I13. Platform Settings

```
GET /api/admin/settings
POST /api/admin/settings
(Admin only)

GET Response 200:
{
    "success": true,
    "data": {
        "service_categories": [...],
        "report_reasons": [...],
        "announcement": "Platform maintenance on Sept 30."
    }
}

POST Request Body:
{
    "action": "add_category",
    "name": "Tailoring"
}
```
