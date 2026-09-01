# HanapBuhay — Web Functionalities

**Document Type:** Admin Web Panel Feature Reference
**Platform:** React.js (web browser)
**Audience:** Web Developer, Project Manager, QA Tester
**Last Updated:** August 2026

---

## Overview

The HanapBuhay Admin Web Panel is a React.js application consumed by system administrators only. It connects to the same Laravel 13 backend API as the mobile app. Admins access this via browser — there is no mobile version of the admin panel.

The web panel does NOT handle:
- Client-facing features (those are mobile only)
- Worker-facing features (those are mobile only)
- Payment processing (out of scope entirely)

---

## Admin Roles

There is ONE combined admin role for simplicity. No sub-roles or permission levels at this stage. All admins have full access to all modules.

---

## Module 1: Admin Authentication & Dashboard

### 1.1 Admin Login
- Admin enters email and password
- System validates credentials
- If role is not 'admin' → access denied
- On success → issues Sanctum token, stores in browser (httpOnly cookie or localStorage)
- Redirects to Dashboard on success

### 1.2 Admin Logout
- Safely terminates the admin session
- Revokes the current Sanctum token on the server
- Redirects to Login screen

### 1.3 View Dashboard Summary
- Landing page after login
- Displays real-time platform statistics:
  - Total registered users (with Client / Worker breakdown)
  - Pending verification requests count (shown as an alert badge if > 0)
  - Active bookings count
  - Completed bookings this month
  - Open / unresolved reports count (shown as an alert badge if > 0)
  - Total job posts currently active
- Recent Activity feed:
  - Latest verification submissions
  - Latest booking activity
  - Latest filed reports
  - Latest user registrations
- Pending Verifications quick list:
  - Shows top 5 pending workers
  - Name, barangay, submission time
  - "Review" button links directly to their verification detail

---

## Module 2: Admin Account Management

### 2.1 Update Admin Profile
- Admin can update their own:
  - Full name
  - Contact number
  - Email address
  - Profile photo

### 2.2 Security Settings
- Change Password:
  - Requires current password
  - New password + confirmation
  - Minimum 8 characters
- Reset Forgotten Password:
  - Via email OTP (same flow as users)
- Two-Factor Authentication (2FA):
  - Toggle to enable or disable
  - When enabled, OTP sent to email on login
- Linked Accounts:
  - Shows whether account uses email/password or Google Sign-In
  - Google accounts show "Set a Password" instead of "Change Password"

### 2.3 View Login Activity
- List of recent login sessions:
  - Date and time
  - Device / browser
  - IP address
- "Log out this device" action per session (revokes that specific Sanctum token)

---

## Module 3: Worker Verification

### 3.1 View Pending Verification Requests
- Table/list of all workers with verification_status = 'pending'
- Columns: worker name, barangay, email, submission date, time elapsed since submission
- Sortable by submission date
- Searchable by worker name or email
- Pagination

### 3.2 Review Submitted Documents
- Admin clicks a pending request to open the verification detail view
- Shows all submitted documents side by side:
  - Government ID (image viewer)
  - Barangay Certificate / Clearance (image viewer)
  - Selfie holding ID (image viewer)
  - Skill Certificate (image viewer, if submitted)
- Shows worker's registered information:
  - Name, email, mobile number, barangay
- Image viewer supports zoom and full-screen

### 3.3 Approve Verification
- Admin clicks "Approve" button
- Confirmation dialog before action executes
- On approve:
  - verification_status set to 'approved'
  - trust_tier set to 'verified'
  - verified_by and verified_at recorded
  - Push notification sent to worker: "Your account has been verified!"
  - In-app notification created for worker
  - Action logged in admin_audit_logs

### 3.4 Reject Verification
- Admin clicks "Reject" button
- Required: admin must enter rejection remarks (e.g. "Barangay certificate image is unclear")
- On reject:
  - verification_status set to 'rejected'
  - Remarks saved and shown to worker
  - Push notification sent to worker
  - Worker can resubmit documents
  - Action logged in admin_audit_logs

### 3.5 Request Resubmission
- Admin can request specific documents be resubmitted without fully rejecting
- Admin enters remarks explaining what needs to be corrected
- Worker notified to resubmit

### 3.6 View Worker Verification Details (Post-Approval)
- Admin can view any worker's verification documents at any time even after approval
- Accessible from the User Management module
- Used during dispute investigations

### 3.7 Manage Worker Trust Tier
- Admin can manually update a worker's trust tier at any time:
  - verified → baseline (post-approval)
  - trusted → awarded for good track record
  - flagged → after upheld report
  - revoked → permanent ban
- Requires remarks when downgrading tier
- Worker notified of tier change
- Action logged in admin_audit_logs
- Revoked workers:
  - Cannot log in
  - Job posts hidden from feed
  - Cannot receive new bookings

---

## Module 4: User Account Management

### 4.1 View All User Accounts
- Full list of all registered users
- Filter options:
  - By role (Client / Worker / Admin)
  - By status (Active / Suspended)
  - By barangay
  - By verification status (workers only)
- Search by name or email
- Pagination (20 per page)
- Columns: name, email, role, barangay, registration date, status badge, verification badge (workers)

### 4.2 View User Profile Details
- Full profile view for any user:
  - Personal information
  - Barangay
  - Registration date
  - Last login date
  - Role
  - For workers: verification status, trust tier, job posts, completed jobs, average rating
  - Booking history summary
  - Filed reports summary

### 4.3 Suspend or Reactivate Account
- Admin can suspend any user account
- Suspended users cannot log in
- Requires reason for suspension
- Suspended users see a message on login: "Your account has been suspended. Contact support for assistance."
- Admin can reactivate a suspended account
- All actions logged in admin_audit_logs
- Notifications sent to affected user

### 4.4 Process Account / Data Deletion Requests
- Users can request account deletion from the mobile app
- Admin sees deletion requests in a queue
- Admin reviews and processes:
  - Deletes personal data (soft delete)
  - Anonymizes booking records
  - Removes profile photo and documents
- In compliance with RA 10173 (Data Privacy Act of the Philippines)

---

## Module 5: Job Post Oversight

### 5.1 View All Active Job Posts
- Table of all currently active job posts
- Columns: worker name, category, title, rate, barangay, posted date, status
- Filter by: category, barangay, verification status of worker, active/inactive
- Search by worker name or post title

### 5.2 View Job Post Detail
- Full view of a specific job post:
  - Worker information
  - Post title and description
  - Category
  - Rate amount and type
  - Availability status
  - Posted date
  - Number of bookings from this post

### 5.3 Deactivate / Remove Job Post
- Admin can deactivate any job post that violates platform policies
- Requires reason
- Worker notified of removal
- Action logged in admin_audit_logs

---

## Module 6: Booking Monitoring

### 6.1 View All Bookings
- Full list of all platform bookings
- Filter by:
  - Status (pending, accepted, active, completed, cancelled)
  - Date range
  - Service category
  - Barangay
- Search by booking code, client name, or worker name
- Columns: booking code, client, worker, category, scheduled date, status, created date
- Pagination

### 6.2 View Booking Detail
- Full detail of a specific booking:
  - Booking code (e.g. HB-2026-00001)
  - Client information
  - Worker information
  - Service category
  - Job post linked (if applicable)
  - Scheduled date and time
  - Notes from client
  - Status history / timeline
  - Starting rate from job post
  - Messages exchanged (read-only view)
  - Reports filed for this booking

### 6.3 Force Cancel a Booking
- Admin can force-cancel any active booking
- Required: reason for cancellation
- Both client and worker notified
- cancelled_by set to 'admin'
- Action logged in admin_audit_logs

---

## Module 7: Reports & Dispute Management

### 7.1 View Filed Reports
- List of all reports filed by clients or workers
- Filter by:
  - Status (under_review, resolved, dismissed)
  - Reason category
  - Date range
- Columns: report ID, filed by, reported user, reason, booking code, status, date filed
- Alert badge shows count of under_review reports

### 7.2 View Report Detail
- Full report view:
  - Who filed the report
  - Who was reported
  - Linked booking (if applicable)
  - Reason category
  - Description
  - Evidence photos (image viewer)
  - Booking context (messages, status history)
  - Both users' profiles and history
  - Previous reports involving either party

### 7.3 View Related Chat Logs
- Admin can view the full message history of the booking linked to a report
- Read-only — admin cannot send messages
- Used as evidence during dispute review

### 7.4 Resolve Dispute
- Admin selects a resolution action:
  - Warning issued (both parties notified, logged)
  - Account suspended (is_active = false on reported user)
  - Verification revoked (trust_tier = 'revoked' for workers)
  - No action taken (report dismissed)
- Required: admin remarks explaining decision
- Both parties notified of resolution outcome
- Action logged in admin_audit_logs
- Report status updated to 'resolved' or 'dismissed'

### 7.5 Track Dispute Resolution History
- View all past resolved disputes
- Filterable by resolution action type
- Shows which admin resolved each case
- Full audit trail per case

---

## Module 8: Ratings & Reviews Oversight

### 8.1 View All Ratings and Reviews
- Full list of all submitted ratings
- Filter by: score, date, worker name, client name
- Both directions shown: client → worker ratings, worker → client ratings

### 8.2 Flag or Remove Inappropriate Reviews
- Admin can remove any review that is:
  - Fake or manipulated
  - Abusive or offensive
  - Irrelevant to the booking
- Requires reason for removal
- Affected user notified
- Action logged in admin_audit_logs
- Worker's average_rating automatically recalculated after removal

---

## Module 9: Platform Settings

### 9.1 Manage Service Categories
- View all service categories
- Add new category:
  - Name
  - Icon identifier (for Flutter display)
- Edit existing category name or icon
- Deactivate a category (existing posts in that category remain but no new posts can be created in deactivated category)

### 9.2 Manage Report Reason Categories
- View current list of report reason options shown to users when filing a report
- Add new reason
- Edit existing reason label
- Deactivate a reason option

### 9.3 Manage Notification Templates
- View automated notification message templates
- Edit template text for:
  - Verification approved
  - Verification rejected
  - Booking accepted
  - Booking declined
  - Booking completed
  - Report resolved
  - Trust tier updated

### 9.4 Post Platform Announcements
- Admin can publish a system-wide announcement
- Announcement appears in all users' notification center
- Optional: set expiry date for announcement
- View history of past announcements

---

## Module 10: Audit Log

### 10.1 View Admin Action Logs
- Complete chronological record of all admin actions across all modules
- Columns: timestamp, admin name, action, target type, target ID, details
- Filter by:
  - Admin (who performed the action)
  - Action type
  - Date range
  - Target type (User, WorkerProfile, Booking, Report, JobPost)
- Read-only — cannot be modified or deleted
- Exported to CSV (future enhancement)

---

## Web Panel Technical Notes

### Authentication

```
Admin login issues a Sanctum Bearer token
Token stored in browser
All API requests include:
  Authorization: Bearer {token}
  Content-Type: application/json
  Accept: application/json
```

### API Base URL

```
Development: http://127.0.0.1:8000/api
             or http://[PM local IP]:8000/api
             or ngrok URL for remote dev
Production:  https://[railway-url]/api
             (future deployment)
```

### Protected Routes

```
All /api/admin/* endpoints require:
  - Valid Sanctum token
  - User role = 'admin'
  - Handled by AdminOnly middleware
```

### Real-Time Features (Web Panel)

```
Admin dashboard stats:
  Refreshed on page load
  No live push required for MVP

Future enhancement:
  WebSocket connection for real-time
  pending verification badge count
  and new report alerts
```