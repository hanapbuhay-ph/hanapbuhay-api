# HanapBuhay — App Client Wireframe Specifications **Document Type:** Client Mobile UI Specification **Platform:** Flutter (Android + iOS) **Audience:** App Developer, UI/UX Designer **Last Updated:** September 2026

---

## How to Use This Document

This document describes the layout, components, and behavior of every screen in the Client experience of the HanapBuhay mobile app.

This document also contains the full specs for ALL SHARED SCREENS (Login, Registration, Onboarding, Chat, Notifications, etc.) since those screens are referenced from both the Client and Worker wireframe documents.

Each screen description includes:
- Purpose
- Layout Zones (top to bottom)
- Key Elements
- Interactions / Navigation
- States (empty, loading, error)

---

## Design System Reference

```
Frame size:       375 x 812px (iPhone 14 standard)
Background:       #F5F5F5
Card style:       White (#FFFFFF), borderRadius 16px
                  BoxShadow: color #1A000000,
                  blurRadius 8, offset Offset(0,2)
Primary green:    #2E9B2E
Dark green:       #1D7A37
Font headings:    Poppins SemiBold / Bold
Font body:        Inter Regular / Medium / SemiBold
Button height:    52px, borderRadius 12px
Input height:     50px, borderRadius 10px
Screen padding:   16px horizontal
Bottom nav:       64px height + safe area
```

---

## Client Bottom Navigation

```
Tab 1: Home          🏠  → Client Home Feed
Tab 2: Bookings      📅  → Booking History
Tab 3: Messages      💬  → Chat Inbox
Tab 4: Notifications 🔔  → Notification Center
Tab 5: Profile       👤  → Profile Tab
```

Active tab: icon + label in #2E9B2E Inactive: icon + label in #9E9E9E Background: white, top border 1px #E0E0E0

---

## SECTION 0: SHARED SCREENS
## (Used by Both Client and Worker)

---

## SCREEN S1: Splash Screen **Route:** / (initial)

### Purpose Brief branded loading screen while app initializes and checks auth status.

### Layout
```
┌─────────────────────────────────────┐
│                                     │
│  Full screen gradient background    │
│  Top: #4CAF50 → Bottom: #1D7A37     │
│                                     │
│  CENTER (vertically + horizontally):│
│                                     │
│  [HanapBuhay Logo 120x120]          │
│  assets/images/hanapbuhay_logo.png  │
│                                     │
│  "HanapBuhay"                       │
│  Poppins Bold 28px white            │
│                                     │
│  "Your Community Skilled            │
│   Worker Marketplace"               │
│  Inter Regular 14px white 85% opacity│
│  textAlign: center                  │
│                                     │
│  BOTTOM (padding bottom 48px):      │
│  CircularProgressIndicator          │
│  white, strokeWidth 2               │
│  "Loading..." Inter Regular 12px    │
│  white 70% opacity                  │
│                                     │
└─────────────────────────────────────┘
```

### Behavior
```
On app launch:
  Show splash for 2 seconds minimum
  Check SharedPreferences for token:

  If token exists:
    Verify token with GET /api/user
    If valid → role-appropriate Home Screen
    If invalid (401) → Login Screen
    Clear invalid token from storage

  If no token:
    Check hasSeenOnboarding bool:
    True → Login Screen
    False → Onboarding Screen

No user interaction on this screen.
Transition: fade animation to next screen.
```

---

## SCREEN S2: Onboarding Screen **Route:** /onboarding

### Purpose 4 swipeable slides introducing HanapBuhay shown only on first launch.

### Layout
```
┌─────────────────────────────────────┐
│                                     │
│  [Skip] text button (top right)     │
│  Inter Medium 14px #6B6B6B          │
│  Hidden on slide 4                  │
│                                     │
│  TOP 60% — ILLUSTRATION AREA:       │
│  Background: #F5F5F5                │
│  Center:                            │
│  [Slide illustration 280x280]       │
│  assets/images/onboarding_X.png     │
│                                     │
│  BOTTOM 40% — WHITE CARD:           │
│  borderRadius: topLeft 28,          │
│                topRight 28          │
│  Padding: 28px horizontal, 32px     │
│                                     │
│  Slide headline                     │
│  Poppins SemiBold 22px #1A1A1A      │
│  textAlign: center                  │
│                                     │
│  Slide subtitle                     │
│  Inter Regular 14px #6B6B6B         │
│  textAlign: center                  │
│  lineHeight: 1.5                    │
│                                     │
│  SizedBox height 28                 │
│                                     │
│  SmoothPageIndicator                │
│  active: #2E9B2E size 10 width 24   │
│  inactive: #E0E0E0 size 8           │
│  centered                           │
│                                     │
│  SizedBox height 24                 │
│                                     │
│  [Next] or [Get Started] button     │
│  Full width green, height 52px      │
│  borderRadius 12px                  │
│                                     │
└─────────────────────────────────────┘
```

### Slide Content
```
Slide 1:
  Image: onboarding_1.png
  Headline: "Welcome to HanapBuhay"
  Subtitle: "Find skilled workers in your
  community — verified and trusted through
  your local barangay."

Slide 2:
  Image: onboarding_2.png
  Headline: "Find the Right Worker"
  Subtitle: "Book electricians, plumbers,
  tutors, cleaners, and more — right in
  your neighborhood."

Slide 3:
  Image: onboarding_3.png
  Headline: "Offer Your Skills"
  Subtitle: "Offer your skills, grow your
  reputation, and earn from what you
  do best."

Slide 4:
  Image: onboarding_4.png
  Headline: "Safe & Verified"
  Subtitle: "Every worker is barangay-document
  verified — so you always know who
  you're hiring."
  Button: "Get Started" (not "Next")
  Skip button: hidden
```

### Interactions
```
Swipe left/right: changes slide
Next button: animates to next slide
Get Started (slide 4):
  → Save hasSeenOnboarding = true
    in SharedPreferences
  → Navigate to Login Screen

Skip button:
  → Save hasSeenOnboarding = true
  → Navigate to Login Screen
```

---

## SCREEN S3: Login Screen **Route:** /login

### Purpose Authenticate returning users. Entry point for both Google and manual login.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ SafeArea                            │
│ SingleChildScrollView               │
│ Padding: 16px horizontal            │
│                                     │
│ SizedBox height: 48                 │
│                                     │
│ CENTER TOP SECTION:                 │
│ [HanapBuhay logo 80x80]             │
│ assets/images/hanapbuhay_logo.png   │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ "Welcome Back"                      │
│ Poppins Bold 26px #1A1A1A center    │
│                                     │
│ SizedBox height: 8                  │
│                                     │
│ "Log in to your HanapBuhay account" │
│ Inter Regular 14px #6B6B6B center   │
│                                     │
│ SizedBox height: 32                 │
│                                     │
│ ┌─── GOOGLE BUTTON ───────────────┐ │
│ │ [Google logo 22x22] "Continue   │ │
│ │  with Google"                   │ │
│ │ White bg, border #E0E0E0,       │ │
│ │ borderRadius 12, height 52      │ │
│ │ shadow                          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ ─── DIVIDER ROW ───                 │
│ [Expanded Divider] "or" [Divider]   │
│ Inter Regular 13px #9E9E9E          │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ ┌─── MANUAL LOGIN CARD ───────────┐ │
│ │ White, borderRadius 16, shadow  │ │
│ │ Padding: 20px                   │ │
│ │                                 │ │
│ │ Email Address label             │ │
│ │ [Email input field]             │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Password label                  │ │
│ │ [Password field + eye toggle]   │ │
│ │                                 │ │
│ │ SizedBox height: 8              │ │
│ │                                 │ │
│ │ "Forgot Password?" right-align  │ │
│ │ Inter SemiBold 13px #2E9B2E     │ │
│ │ Tap → Forgot Password Screen    │ │
│ │                                 │ │
│ │ SizedBox height: 20             │ │
│ │                                 │ │
│ │ [Log In] green button           │ │
│ │ Full width, height 52px         │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ CENTER ROW:                         │
│ "Don't have an account? " +         │
│ "Sign Up" (green, SemiBold)         │
│ Tap Sign Up → Role Selection Screen │
│                                     │
│ SizedBox height: 40                 │
└─────────────────────────────────────┘
```

### Critical Constraints
```
"Forgot Password?" link:
  ONLY appears under the manual login form
  NEVER near the Google button

Google button:
  ALWAYS above the "or" divider
  ALWAYS above the manual form card

"Continue with Google" button style:
  Follows Google's official branding:
  White background
  Google logo on left (NOT colored G)
  "Continue with Google" dark gray text
  No green coloring on this button
```

### Interactions
```
Google button tap:
  → Trigger google_sign_in package
  → On success (new user):
    POST /api/auth/google
    body: { google_token, role: null }
    If is_new_user = true:
      → Role Selection Screen
  → On success (existing user):
    POST /api/auth/google
    → Store Sanctum token securely
    → Role-appropriate Home Screen
  → On error: red snackbar with message

Log In button tap:
  → Validate both fields
  → POST /api/auth/login
    body: { email, password }
  → On success:
    Store token in FlutterSecureStorage
    Store role in SharedPreferences
    Navigate to role-appropriate Home
  → On 401: "Invalid email or password"
    error shown under password field
  → On 403: "Please verify your email first"
    + resend verification option shown
  → Loading: button shows spinner

Forgot Password? tap:
  → Forgot Password Screen

Sign Up tap:
  → Role Selection Screen
```

---

## SCREEN S4: Role Selection Screen **Route:** /register/role

### Purpose First step of registration. User selects Client or Worker role.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back arrow] (goes to Login)      │
│                                     │
│ SizedBox height: 32                 │
│                                     │
│ "How will you use HanapBuhay?"      │
│ Poppins SemiBold 22px #1A1A1A center│
│                                     │
│ SizedBox height: 8                  │
│                                     │
│ "Choose your role to get started"   │
│ Inter Regular 14px #6B6B6B center   │
│                                     │
│ SizedBox height: 40                 │
│                                     │
│ ┌─── CLIENT CARD ─────────────────┐ │
│ │ Padding: 20px all sides         │ │
│ │ borderRadius 16px               │ │
│ │ Border: 1.5px solid (see below) │ │
│ │                                 │ │
│ │ ROW:                            │ │
│ │ [Icon box 48x48]  [Text]  [○]   │ │
│ │                                 │ │
│ │ Icon box:                       │ │
│ │   bg: #E8F5E9, radius 12        │ │
│ │   Icon: search/person, #2E9B2E  │ │
│ │                                 │ │
│ │ Text column:                    │ │
│ │   "I want to hire"              │ │
│ │   Poppins SemiBold 16px #1A1A1A │ │
│ │   "Find and book skilled workers│ │
│ │   near you"                     │ │
│ │   Inter Regular 13px #6B6B6B    │ │
│ │                                 │ │
│ │ Radio circle (22x22):           │ │
│ │   Selected: filled #2E9B2E +    │ │
│ │             white checkmark     │ │
│ │   Unselected: border #E0E0E0    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ ┌─── WORKER CARD ─────────────────┐ │
│ │ Same structure as Client card   │ │
│ │ Icon: handyman/tools, #2E9B2E   │ │
│ │ Title: "I want to work"         │ │
│ │ Subtitle: "Offer your skills    │ │
│ │ and earn in your community"     │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Spacer()                            │
│                                     │
│ [Continue] button                   │
│ Green when role selected            │
│ Gray (#E0E0E0) when nothing selected│
│ Full width, height 52px             │
│                                     │
│ SizedBox height: 32                 │
└─────────────────────────────────────┘
```

### Card Selected State
```
Border: 2px solid #2E9B2E
BoxShadow: color #262E9B2E,
           blurRadius 12,
           offset Offset(0,4)
Background: white (unchanged)
Radio: filled green circle + checkmark
```

### Card Unselected State
```
Border: 1px solid #E0E0E0
No shadow
Background: white
Radio: empty circle with gray border
```

### Interactions
```
Tap either card:
  → Selects that role
  → Other card deselects
  → Continue button becomes green

Continue tap (with role selected):
  → Navigate to Registration Screen
    passing selected role as parameter

Continue tap (no role selected):
  → Nothing happens (button disabled)
```

---

## SCREEN S5: Registration Screen **Route:** /register/details **Parameter:** role (client or worker)

### Purpose Collect user account information.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Create Account"        │
│ AppBar white bg, no elevation       │
│                                     │
│ SingleChildScrollView               │
│ Padding: 16px                       │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ ROLE BADGE (centered):              │
│ Rounded pill: #E8F5E9 bg            │
│ [Category icon 14px] +              │
│ "Signing up as: Client" OR "Worker" │
│ Inter Medium 12px #2E9B2E           │
│ + "Change" tappable text            │
│   → goes back to Role Selection     │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ ┌─── FORM CARD ───────────────────┐ │
│ │ White card, borderRadius 16     │ │
│ │ Padding: 20px                   │ │
│ │                                 │ │
│ │ Full Name *                     │ │
│ │ [Text input]                    │ │
│ │ hint: "Enter your full name"    │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Mobile Number *                 │ │
│ │ [Phone input]                   │ │
│ │ hint: "09XXXXXXXXX"             │ │
│ │ keyboard: phone                 │ │
│ │ caption below field:            │ │
│ │ "Used for booking coordination  │ │
│ │  only." Inter Regular 11px gray │ │
│ │ NO asterisk, NO verification    │ │
│ │ note                            │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Email Address *                 │ │
│ │ [Email input]                   │ │
│ │ hint: "Enter your email"        │ │
│ │ keyboard: emailAddress          │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Barangay *                      │ │
│ │ [Dropdown selector]             │ │
│ │ Lists all 20 Trinidad barangays │ │
│ │ from GET /api/barangays         │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Password *                      │ │
│ │ [Password input + eye toggle]   │ │
│ │ hint: "Minimum 8 characters"    │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Confirm Password *              │ │
│ │ [Password input + eye toggle]   │ │
│ │ hint: "Re-enter your password"  │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ TERMS ROW:                          │
│ [Checkbox] "I agree to the "        │
│ "Terms of Service" (green, tappable)│
│ " and "                             │
│ "Privacy Policy" (green, tappable)  │
│ Inter Regular 13px                  │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ [Create Account] button             │
│ Green when terms checked            │
│ Gray when unchecked (disabled)      │
│ Full width, height 52px             │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ CENTER: "Already have an account?   │
│ Log In" (Log In = green SemiBold)   │
│ Tap → Login Screen                  │
│                                     │
│ SizedBox height: 32                 │
└─────────────────────────────────────┘
```

### Validation Rules
```
Full Name:
  Required, min 2 words
  Error: "Please enter your full name"

Mobile Number:
  Required, Philippine format
  09XXXXXXXXX or +639XXXXXXXXX
  Error: "Enter a valid Philippine
         mobile number"
  NO OTP verification on this field

Email:
  Required, valid email format, unique
  Error: "This email is already registered"
         or "Enter a valid email address"

Barangay:
  Required, must be from the dropdown
  Error: "Please select your barangay"

Password:
  Required, min 8 characters
  Error: "Password must be at least
         8 characters"

Confirm Password:
  Must match Password field
  Error: "Passwords do not match"
```

### Interactions
```
Create Account tap (terms checked, valid):
  Show loading spinner on button
  POST /api/auth/register
  body: {
    name, email, password,
    password_confirmation,
    mobile_number, role, barangay_id
  }
  On 201: → Email Verification Screen
           passing email as parameter
  On 422: Show inline field errors
  On 500: Red snackbar error message
```

---

## SCREEN S6: Email Verification Screen **Route:** /register/verify **Parameter:** email (string) **Manual sign-up only — Google users skip this**

### Purpose 6-digit OTP verification sent to user's email.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back arrow]                      │
│                                     │
│ SafeArea, Center, Padding 24px      │
│                                     │
│ Icon(Icons.mark_email_unread)       │
│ size: 72, color: #2E9B2E            │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ "Check Your Email"                  │
│ Poppins SemiBold 22px center        │
│                                     │
│ SizedBox height: 12                 │
│                                     │
│ "We sent a verification code to"    │
│ Inter Regular 14px #6B6B6B center   │
│ "[email address]"                   │
│ Inter SemiBold 14px #1A1A1A center  │
│                                     │
│ SizedBox height: 40                 │
│                                     │
│ OTP INPUT ROW (6 boxes):            │
│ Row, mainAxis: center               │
│ 6x SizedBox(width:46, height:56):   │
│   TextFormField                     │
│   textAlign: center                 │
│   keyboard: number                  │
│   maxLength: 1                      │
│   fontSize: Poppins SemiBold 22px   │
│   border: OutlineInputBorder        │
│   borderRadius: 12px                │
│   normal border: #E0E0E0 1px        │
│   focused border: #2E9B2E 1.5px     │
│   counterText: '' (hide counter)    │
│ Gap between boxes: 8px              │
│ Auto-focus next box on input        │
│ Auto-focus prev box on backspace    │
│                                     │
│ SizedBox height: 32                 │
│                                     │
│ [Verify Email] green button         │
│ Full width, height 52px             │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ ROW center:                         │
│ "Didn't receive the code? "         │
│ Inter Regular 14px #6B6B6B          │
│ If can resend:                      │
│   "Resend Code"                     │
│   Inter SemiBold 14px #2E9B2E       │
│ If timer active:                    │
│   "Resend in 60s"                   │
│   Inter SemiBold 14px #9E9E9E       │
│   (countdown from 60 to 0)          │
│                                     │
└─────────────────────────────────────┘
```

### Critical Note on OTP
```
This is EMAIL OTP — NOT SMS.
The instructional text must say
"email address" never "phone number".
The code is sent via Laravel Mail + Brevo.
Code expires in 10 minutes.
Timer shown is resend cooldown (60 seconds)
not expiry timer.
```

### Interactions
```
Verify button tap:
  Combine 6 box values into one string
  If length < 6:
    Show snackbar: "Please enter the
    complete verification code"
  If length = 6:
    Show loading spinner
    POST /api/auth/verify-otp
    body: { email, code }
    On 200:
      Store token in FlutterSecureStorage
      Store role in SharedPreferences
      Navigate to role-appropriate Home Screen
    On 422:
      Red error below OTP boxes:
      "Invalid or expired verification code"
      Clear all boxes, refocus first box

Resend Code tap (when enabled):
  POST /api/auth/email/resend-otp
  body: { email }
  Reset timer to 60
  Disable Resend button
  Green snackbar: "Code resent to your email"
```

---

## SCREEN S7: Complete Your Profile Screen **Route:** /register/complete-profile **Google Sign-In new users only**

### Purpose Collect missing info after Google sign-in. Google provides name, email, photo — but not mobile number or barangay.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ AppBar: "Complete Your Profile"     │
│ Poppins SemiBold, white bg          │
│                                     │
│ SingleChildScrollView, padding 16px │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ "Almost there! Just a few more      │
│  details to get started."           │
│ Inter Regular 14px #6B6B6B center   │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ PROFILE PHOTO (centered):           │
│ Stack:                              │
│   CircleAvatar radius 52:           │
│     CachedNetworkImage if Google    │
│     photo available                 │
│     OR default person icon          │
│     bg: #E8F5E9                     │
│   Positioned bottom:0 right:0:      │
│     CircleAvatar radius 16:         │
│       bg: #2E9B2E                   │
│       Icon: camera_alt 14px white   │
│     GestureDetector → image picker  │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ ┌─── FORM CARD ───────────────────┐ │
│ │ White card, borderRadius 16     │ │
│ │ Padding: 20px                   │ │
│ │                                 │ │
│ │ Full Name                       │ │
│ │ [Text input]                    │ │
│ │ Pre-filled from Google          │ │
│ │ Editable                        │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Email Address                   │ │
│ │ [Greyed out container]          │ │
│ │ Non-editable, #F0F0F0 bg        │ │
│ │ Shows Google email              │ │
│ │ Lock icon (suffix)              │ │
│ │ Below field caption:            │ │
│ │   [Google logo 14x14]           │ │
│ │   "Provided by Google"          │ │
│ │   Inter Regular 11px #9E9E9E    │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Barangay *                      │ │
│ │ [Dropdown selector]             │ │
│ │ 20 Trinidad barangays           │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Mobile Number *                 │ │
│ │ [Phone input]                   │ │
│ │ hint: "09XXXXXXXXX"             │ │
│ │ caption: "Used for booking      │ │
│ │ coordination only."             │ │
│ │ Inter Regular 11px #9E9E9E      │ │
│ │ NO OTP verification note        │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ [Continue] green button             │
│ Full width, height 52px             │
│                                     │
│ SizedBox height: 32                 │
└─────────────────────────────────────┘
```

### Interactions
```
Camera icon tap:
  Bottom sheet picker:
    [Take Photo]
    [Choose from Gallery]
  Updates profile photo preview

Continue tap:
  Validate: name not empty,
            barangay selected,
            mobile not empty
  POST /api/auth/google/complete-profile
  body: { name, barangay_id,
          mobile_number, role,
          profile_photo (if changed) }
  On success:
    Store token (already issued in S3 flow)
    → Role-appropriate Home Screen
  No OTP step after this screen
```

---

## SCREEN S8: Forgot Password Screen **Route:** /forgot-password **Three internal states on one screen**

### Purpose Password recovery via email OTP.

### State 1 — Enter Email
```
┌─────────────────────────────────────┐
│ ← [Back]                            │
│                                     │
│ SafeArea, Center, Padding 24px      │
│                                     │
│ Icon(Icons.lock_reset_rounded)      │
│ size: 64, color: #2E9B2E            │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ "Forgot Password?"                  │
│ Poppins SemiBold 22px center        │
│                                     │
│ SizedBox height: 12                 │
│                                     │
│ "Enter your email and we'll send    │
│  you a reset code."                 │
│ Inter Regular 14px #6B6B6B center   │
│                                     │
│ SizedBox height: 32                 │
│                                     │
│ Email Address label                 │
│ [Email input field]                 │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ [Send Reset Code] green button      │
│ Full width, height 52px             │
└─────────────────────────────────────┘
```

### State 2 — OTP Verification
```
Same screen, state changes to:

Icon: mail icon, green
Title: "Check Your Email"
Subtitle: "We sent a password reset
code to [email]"
(NOTE: says email — NOT phone number)

Same 6-box OTP input as S6
[Verify Code] button
Resend timer + Resend Code link
```

### State 3 — New Password
```
Same screen, state changes to:

Icon: lock_open_rounded, green
Title: "Set New Password"
Subtitle: "Choose a strong new password
for your account"

[New Password] field
[Confirm New Password] field

[Reset Password] green button

On success:
  Green snackbar: "Password reset
  successfully!"
  → Login Screen (replace navigation)
  Token NOT issued here —
  user must log in with new password
```

---

## SCREEN S9: Security Settings Screen **Route:** /settings/security **Accessed from:** Profile tab

### Purpose Password management, 2FA, and login activity.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Security Settings"     │
│                                     │
│ Background: #F5F5F5                 │
│ Padding: 16px                       │
│                                     │
│ SECTION LABEL: "PASSWORD"          │
│ Inter SemiBold 12px #9E9E9E         │
│ letterSpacing: 1.2                  │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ ListTile row:                   │ │
│ │ [🔒 icon in green circle]       │ │
│ │ "Change Password"               │ │
│ │  OR "Set a Password"            │ │
│ │  (Google accounts)              │ │
│ │ subtitle: "Update your          │ │
│ │ current password"               │ │
│ │ Trailing: chevron right         │ │
│ │ Tap → Change Password sheet     │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ SECTION LABEL: "LINKED ACCOUNTS"   │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ ListTile row (non-tappable):    │ │
│ │ [🔗 icon in green circle]       │ │
│ │ "Linked Accounts"               │ │
│ │ subtitle:                       │ │
│ │   Google account: shows email   │ │
│ │   Manual account: "Email /      │ │
│ │   Password login active"        │ │
│ │ Trailing: Google logo (if Google)│ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ SECTION LABEL:                      │
│ "TWO-FACTOR AUTHENTICATION"         │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ ListTile row:                   │ │
│ │ [✓ icon in green circle]        │ │
│ │ "Two-Factor Authentication"     │ │
│ │ subtitle: enabled/disabled text │ │
│ │ Trailing: Switch widget         │ │
│ │   activeColor: #2E9B2E          │ │
│ │ Toggle: API call to update 2FA  │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 20                 │
│                                     │
│ SECTION LABEL: "LOGIN ACTIVITY"    │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ ListTile row:                   │ │
│ │ [📱 icon in green circle]       │ │
│ │ "Login Activity"                │ │
│ │ subtitle: "View recent logins   │ │
│ │ and manage active sessions"     │ │
│ │ Trailing: chevron right         │ │
│ │ Tap → Login Activity sheet      │ │
│ └─────────────────────────────────┘ │
│                                     │
└─────────────────────────────────────┘
```

### Change Password Bottom Sheet
```
showModalBottomSheet

Title: "Change Password"
Poppins SemiBold 18px

[Current Password] field (isPassword: true)
SizedBox height: 16
[New Password] field (isPassword: true)
SizedBox height: 16
[Confirm New Password] field (isPassword: true)
SizedBox height: 24
[Update Password] green button full width

For Google accounts:
  No "Current Password" field
  Only New + Confirm
  Button label: "Set Password"
```

### Login Activity Bottom Sheet
```
showModalBottomSheet + DraggableScrollable

Title: "Login Activity"
Poppins SemiBold 18px

List of sessions from API:
  Each session row:
    [📱 or 💻 device icon]
    Column:
      Device name: Inter Medium 14px
      "Today 9:00 AM · [IP]"
        Inter Regular 12px #6B6B6B
      "Current session" tag (if current)
        green pill
    Trailing (non-current sessions):
      TextButton: "Log out"
      Inter SemiBold 13px #D32F2F
      Tap: DELETE /api/user/sessions/{id}
           Then refresh list
```

---

## SECTION 1: CLIENT-SPECIFIC SCREENS

---

## SCREEN C1: Client Home Feed **Route:** /client/home **Bottom nav tab:** Home (active)

### Purpose Main discovery screen — scrollable feed of worker job posts sorted by distance.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ SafeArea                            │
│                                     │
│ TOP BAR ROW:                        │
│ "Hi, [Name]! 👋"    [🔔 bell]       │
│ Poppins SemiBold 20px               │
│                                     │
│ SizedBox height: 12                 │
│                                     │
│ SEARCH BAR ROW:                     │
│ [🔍 Search workers...] [⚙️ Filters] │
│ Search bar: white, borderRadius 12  │
│ border 1px #E0E0E0, height 50px     │
│ Tap search → Browse by Category     │
│ Tap Filters → Advanced Filters sheet│
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ FEED HEADER ROW:                    │
│ "Discover Workers"     [All ▾]      │
│ Poppins SemiBold 16px  quick filter │
│                                     │
│ QUICK FILTER CHIPS:                 │
│ [All] [Verified ✓] [Unverified]     │
│ Row, scrollable if needed           │
│ Active chip: green bg, white text   │
│ Inactive chip: white bg, #6B6B6B    │
│ borderRadius: 999 (pill)            │
│ padding: h:16, v:8                  │
│                                     │
│ SizedBox height: 12                 │
│                                     │
│ SCROLLABLE FEED (below):            │
│ ListView.builder                    │
│ [Job Post Card 1]                   │
│ [Job Post Card 2]                   │
│ [Job Post Card 3]                   │
│ ...                                 │
│                                     │
│ Loading: Shimmer skeleton cards     │
│ Empty state: see below              │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

### Job Post Feed Card
```
White card, borderRadius 16px, shadow
Padding: 16px
Margin bottom: 12px

TOP ROW:
  CircleAvatar 52x52
    (CachedNetworkImage or placeholder)
  SizedBox width: 12
  Column (Expanded):
    Worker name: Inter SemiBold 15px #1A1A1A
    Category: Inter Regular 13px #6B6B6B
  TRUST BADGE (right-aligned):
    🛡️ Verified → green pill
    ⭐ Trusted → blue pill
    ⚠️ Unverified → amber pill

SizedBox height: 10

POST TITLE:
  Post title text
  Inter SemiBold 14px #1A1A1A
  Max 2 lines (overflow: ellipsis)

SizedBox height: 6

DESCRIPTION PREVIEW:
  Inter Regular 13px #6B6B6B
  Max 2 lines

POST MEDIA PREVIEW:
  If images exist, show the first image below the header
  Full card-width aspect ratio: 4:3
  Cached image with placeholder and loading state
  If more than one image exists, show a small image-count indicator

SizedBox height: 10

BOTTOM ROW:
  Left group:
    📍 "Barangay Name · ~X.X km"
    Inter Regular 12px #6B6B6B
  Right group:
    "From ₱X/[period]"
    Inter SemiBold 13px #2E9B2E

  Availability dot (far right):
    Green = Available Now
    Amber = By Schedule
    Gray = Offline

Tap post content or media: → Full Job Post Detail Screen
                           passing job_post_id
Tap worker photo or name: → Worker Profile Screen
                            passing worker_profile_id
```

## SCREEN C1.1: Full Job Post Detail Screen **Route:** /client/posts/{postId} **Accessed from:** Client Home Feed card

### Purpose
Shows one worker service post in full, including all post images and the booking action.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]                    [...]   │
│                                     │
│ WORKER HEADER                       │
│ [avatar] Worker name [trust badge]  │
│ Category · Barangay · ~X.X km       │
│ Tap header → Worker Profile         │
│                                     │
│ POST CONTENT                        │
│ Post title                          │
│ Complete description                 │
│ From ₱X/[period] · availability    │
│                                     │
│ POST IMAGES                         │
│ ListView.builder                     │
│ [full-width image 1]                │
│ [full-width image 2]                │
│ ...                                 │
│                                     │
│ [Book This Service]                 │
│ Persistent bottom action            │
└─────────────────────────────────────┘
```

### Behavior
```
Load: GET /api/posts/{postId}
Images: render in display_order using cached network images
No images: omit the media section without leaving a blank region
Image tap: open the image in a full-screen viewer with vertical paging
Book button: → Send Booking Request Screen
  passes job_post_id, worker_profile_id, and service_category_id
Inactive post: show "This post is no longer available" and disable booking
Not found: show a message and return action to the previous screen
```

### Advanced Filters Bottom Sheet
```
showModalBottomSheet, isScrollControlled: true
DraggableScrollableSheet

Header:
  "Filters" Poppins SemiBold 18px
  "Reset" text button right (#2E9B2E)

Service Category (multi-select):
  "Category" label
  Wrap of category chips
  Tap to select/deselect
  Selected: green bg, white text
  Unselected: white bg, border

SizedBox height: 20

Barangay:
  "Barangay" label
  Dropdown selector
  Options: All + 20 Trinidad barangays

SizedBox height: 20

Rate Type:
  "Rate Type" label
  Wrap of rate type chips:
  [All][Hourly][Daily][Weekly]
  [Monthly][Per Session][Per Project]

SizedBox height: 20

Availability:
  "Availability" label
  [All] [Available Now] chips

SizedBox height: 32

[Apply Filters] green button full width
height 52px
```

### Empty State (no results)
```
Center column:
  Icon: 🔍 (large, gray)
  "No workers found"
  Poppins SemiBold 16px #1A1A1A
  "Try adjusting your filters or
  check back later."
  Inter Regular 14px #6B6B6B
  [Clear Filters] green outlined button
```

---

## SCREEN C2: Browse by Category Screen **Route:** /client/browse **Accessed from:** Search bar tap

### Purpose Client selects a service category to browse all workers offering that service.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Browse Workers"        │
│                                     │
│ "What service do you need?"         │
│ Poppins SemiBold 18px               │
│                                     │
│ SizedBox height: 16                 │
│                                     │
│ CATEGORY GRID:                      │
│ GridView, 2 columns                 │
│ crossAxisSpacing: 12                │
│ mainAxisSpacing: 12                 │
│                                     │
│ Each category card:                 │
│ White card, borderRadius 16px       │
│ Padding: 20px                       │
│ Column center:                      │
│   Category icon (40x40)             │
│   in green tint circle (56x56)      │
│   SizedBox height: 12               │
│   Category name                     │
│   Inter SemiBold 13px #1A1A1A       │
│   center                            │
│                                     │
│ Tap category card:                  │
│ → Category Results Screen           │
│   (filtered worker list)            │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN C3: Category Results Screen **Route:** /client/browse/{categoryId} **Parameter:** categoryId, categoryName

### Purpose Shows all workers offering the selected service category.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "[Category Name]"       │
│                                     │
│ FILTER ROW:                         │
│ [Barangay ▾] [Verified only toggle] │
│                                     │
│ Results count:                      │
│ "12 workers available"              │
│ Inter Regular 13px #6B6B6B          │
│                                     │
│ SCROLLABLE WORKER LIST:             │
│ [Worker Card 1]                     │
│ [Worker Card 2]                     │
│ ...                                 │
│                                     │
│ Empty state if no results           │
│                                     │
└─────────────────────────────────────┘
```

### Worker Card (Category Results)
```
White card, borderRadius 16px, shadow
Padding: 16px
Margin bottom: 12px

ROW:
  CircleAvatar 52x52
  Column (Expanded):
    Name: Inter SemiBold 15px #1A1A1A
    "⭐ 4.8 (23 reviews)"
      Inter Regular 13px #6B6B6B
    "📍 Poblacion · ~1.2 km"
      Inter Regular 12px #6B6B6B
    "From ₱300/session"
      Inter SemiBold 13px #2E9B2E
  RIGHT:
    Trust badge pill
    Availability dot

Tap card → Worker Profile Screen
```

---

## SCREEN C4: Worker Profile Screen **Route:** /client/worker/{workerProfileId} **Accessed from:** Feed card tap, Category results card tap

### Purpose Full worker profile — client reviews before deciding to book.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]                            │
│                                     │
│ HEADER SECTION:                     │
│ Green gradient bg (#2E9B2E → #1D7A37)│
│ Padding: 24px                       │
│                                     │
│   CircleAvatar 80x80                │
│   white border 3px                  │
│                                     │
│   Worker Name                       │
│   Poppins SemiBold 20px white       │
│                                     │
│   Trust badge pill (white outlined) │
│   e.g. "🛡️ Barangay Verified"       │
│                                     │
│   "⭐ 4.8 · 23 reviews · 45 jobs"   │
│   Inter Regular 13px white 85%      │
│                                     │
│   "📍 Poblacion · ~1.2 km"          │
│   Inter Regular 13px white 85%      │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ UNVERIFIED WARNING BANNER:          │
│ (shown only if worker unverified)   │
│ Amber bg, padding 12px 16px         │
│ "⚠️ This worker is not yet          │
│ barangay-verified."                 │
│ Inter Medium 13px #1A1A1A           │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ TAB ROW:                            │
│ [ About ] [ Posts ] [ Reviews ]     │
│ Tab indicator: green underline      │
│ Active tab text: #2E9B2E SemiBold   │
│                                     │
│ TAB CONTENT (changes per tab):      │
│                                     │
│ ABOUT TAB:                          │
│   Availability status row:          │
│     Dot + "Available Now" / "Busy"  │
│   SizedBox height: 16               │
│   Bio text (Inter Regular 14px)     │
│   "No bio yet" if empty             │
│                                     │
│ POSTS TAB:                          │
│   List of worker's active job posts │
│   Each post card:                   │
│     Category tag + Post title       │
│     Description preview             │
│     "From ₱X/[period]"             │
│     Availability status             │
│     [Book This Service] green button│
│     full width                      │
│     Tap → Booking Request Screen    │
│            passing job_post_id      │
│                                     │
│ REVIEWS TAB:                        │
│   Average rating display:           │
│     Large "4.8" + star visual       │
│     "Based on 23 reviews"           │
│   Review list:                      │
│     Each review:                    │
│       Reviewer name + date          │
│       Star rating (filled stars)    │
│       Comment text                  │
│   Paginated (load more on scroll)   │
│   "No reviews yet" empty state      │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN C5: Send Booking Request Screen **Route:** /client/bookings/create **Parameters:** workerProfileId, jobPostId

### Purpose Client submits a booking request for a specific worker job post.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Book Service"          │
│                                     │
│ UNVERIFIED WARNING MODAL:           │
│ (appears BEFORE form if unverified) │
│ Dialog/Modal:                       │
│   "⚠️ Book Unverified Worker?"      │
│   Poppins SemiBold 18px             │
│   "This worker has not submitted    │
│   barangay verification documents. │
│   HanapBuhay cannot guarantee      │
│   their identity at this time."    │
│   Inter Regular 14px #6B6B6B        │
│   [Find Verified Worker] outlined   │
│   [Proceed Anyway] green            │
│   (form only opens after Proceed)   │
│                                     │
│ ─── FORM (after warning cleared) ──│
│                                     │
│ ┌─── WORKER SUMMARY CARD ─────────┐ │
│ │ Avatar 48x48                    │ │
│ │ Worker name + verified badge    │ │
│ │ Job post title (gray)           │ │
│ │ "Starting from ₱X/[period]"     │ │
│ │ Inter SemiBold 13px #2E9B2E     │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── BOOKING FORM CARD ───────────┐ │
│ │                                 │ │
│ │ Service Category (non-editable) │ │
│ │ Pre-filled from job post        │ │
│ │ Gray bg, lock icon              │ │
│ │                                 │ │
│ │ Preferred Date *                │ │
│ │ [Date picker field]             │ │
│ │ Calendar icon suffix            │ │
│ │ Min date: tomorrow              │ │
│ │                                 │ │
│ │ Preferred Time *                │ │
│ │ [Time picker field]             │ │
│ │ Clock icon suffix               │ │
│ │                                 │ │
│ │ Notes (Optional)                │ │
│ │ [Multi-line text area]          │ │
│ │ "e.g. 2 aircon units, 1HP each" │ │
│ │ Max 300 characters              │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Send Booking Request]              │
│ Green button, full width, height 52 │
│                                     │
└─────────────────────────────────────┘
```

### Interactions
```
Send Request tap:
  Validate: date selected, time selected
  POST /api/bookings
  body: {
    worker_id,
    job_post_id,
    service_category_id,
    scheduled_at,
    notes
  }
  On 201:
    Success dialog:
      "Request Sent! ✅"
      "You'll be notified once
      [Worker Name] responds."
      [View My Bookings] button
    → Booking History Screen

  On error:
    Red snackbar with error
```

---

## SCREEN C6: Booking History Screen **Route:** /client/bookings **Bottom nav tab:** Bookings (active)

### Purpose Client views all their bookings by status.

### Layout
```
┌─────────────────────────────────────┐
│ "My Bookings"                       │
│ Poppins SemiBold 22px               │
│                                     │
│ [Upcoming][Ongoing][Completed][Cancelled]│
│ Segmented tab row                   │
│ Active: green underline + text      │
│                                     │
│ Scrollable list of booking cards    │
│                                     │
│ [Booking Card 1]                    │
│ [Booking Card 2]                    │
│ ...                                 │
│                                     │
│ Empty state per tab                 │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

### Booking Card (Client)
```
White card, borderRadius 16px, shadow
Padding: 16px

TOP ROW:
  Worker avatar 40x40
  Worker name Inter SemiBold 14px
  RIGHT: Status badge pill

MIDDLE ROW:
  Category chip
  "📅 [Date] at [Time]"
  Inter Medium 13px

BOOKING CODE ROW:
  "#HB-2026-00001"
  Inter Regular 12px #9E9E9E

Tap → Booking Detail Screen
```

---

## SCREEN C7: Booking Detail Screen **Route:** /client/bookings/{id}

### Purpose Full booking detail with all client actions.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "#HB-2026-00001"        │
│            [Status badge]           │
│                                     │
│ ┌─── WORKER CARD ─────────────────┐ │
│ │ Avatar 52px | Worker Name       │ │
│ │ Verified badge | Barangay       │ │
│ │                    [💬 Message] │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── BOOKING INFO ────────────────┐ │
│ │ Service Category                │ │
│ │ Job Post Title (if linked)      │ │
│ │ Starting Rate                   │ │
│ │ 📅 [Date and time]              │ │
│ │ 📝 [Client notes]               │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── STATUS TIMELINE ─────────────┐ │
│ │ ●────●────●────●                │ │
│ │ Pend Accp Actv Done             │ │
│ │ (timestamps at each step)       │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── MAP CARD ────────────────────┐ │
│ │ (shown when Accepted or Active) │ │
│ │ Map preview thumbnail           │ │
│ │ "View Map & Track Location →"   │ │
│ │ Tap → Map Screen                │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── ACTION AREA ─────────────────┐ │
│ │ PENDING:                        │ │
│ │   [Cancel Request] red outlined │ │
│ │                                 │ │
│ │ ACCEPTED:                       │ │
│ │   Map card visible above        │ │
│ │   No additional buttons         │ │
│ │                                 │ │
│ │ ACTIVE:                         │ │
│ │   [Confirm Job Completed] green │ │
│ │                                 │ │
│ │ COMPLETED (not yet reviewed):   │ │
│ │   [Rate & Review Worker] green  │ │
│ │                                 │ │
│ │ COMPLETED (reviewed):           │ │
│ │   Submitted review shown        │ │
│ │   Star score + comment          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [File a Report] small text link     │
│ #D32F2F, center                     │
│ (visible on all active statuses)    │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN C8: Map Screen (Client View) **Route:** /client/bookings/{id}/map

### Purpose Client views both location pins and optionally starts live GPS sharing.

### Layout
```
Same structure as Worker Map Screen (W9)
but from client's perspective:

Blue pin (🔵): Client's barangay (own)
Green pin (🟢): Worker's barangay (other party)

"I'm on my way" button:
  Starts CLIENT's GPS tracking
  Worker sees blue pin move on their map

"I've Arrived ✓" button:
  Stops client GPS tracking
  Worker notified: "Client has arrived!"

If worker is tracking (worker tapped
"I'm on my way" on their side):
  Green pin becomes animated/moving
  Shows worker's real-time GPS
  Client sees worker approaching

Map labels:
  Blue pin label: "Your Location"
  Green pin label: "Worker Location"
```

---

## SCREEN C9: Rate & Review Screen **Route:** /client/bookings/{id}/rate

### Purpose Client rates the worker after job completion.

### Layout
```
Same structure as Worker Rate Client
Screen (W10) but:

Title: "Rate Worker"
Question: "How was your experience
with [Worker Name]?"
Subtitle: "Your review helps other
clients in the community."

Stars rate the WORKER
Comment about the worker's service

On submit:
  POST /api/ratings
  body: { booking_id, score, comment }
  Worker's average_rating recalculated
  Worker notified of new review
```

---

## SCREEN C10: File a Report Screen **Route:** /client/report/create

### Purpose Client files a complaint against a worker.

### Layout
```
Same structure as Worker File Report
Screen (W13) but reason options are:

No-show (worker didn't appear)
Unsatisfactory Work
Misconduct
Unsafe Environment
Abusive Behavior
False Information
Other
```

---

## SCREEN C11: Report Status Screen **Route:** /client/reports

### Purpose Client tracks status of their filed reports.

### Layout
```
Same structure as Worker Report Status
Screen (W14) but shows reports
filed by the client against workers.
```

---

## SCREEN C12: Client Profile Tab **Route:** /client/profile **Bottom nav tab:** Profile (active)

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ "My Profile"                        │
│ Poppins SemiBold 22px               │
│                                     │
│ ┌─── PROFILE SUMMARY CARD ────────┐ │
│ │ [Avatar 72px]                   │ │
│ │ Client Name Poppins SemiBold    │ │
│ │ [Client] role badge             │ │
│ │ Barangay name                   │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ACCOUNT SECTION:                    │
│ ┌─────────────────────────────────┐ │
│ │ 👤 Edit Profile            →    │ │
│ │ 🔒 Security Settings       →    │ │
│ │ 🔔 Notification Preferences →   │ │
│ │ 📋 My Reports              →    │ │
│ │ ❓ Help / FAQ              →    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Log Out] red text centered         │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

---

## SHARED UTILITY SCREENS

---

## SCREEN SH1: Chat Inbox **Route:** /messages **Bottom nav tab:** Messages (active)

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ "Messages"                          │
│ Poppins SemiBold 22px               │
│                                     │
│ Scrollable conversation list        │
│                                     │
│ ┌─── CONVERSATION ROW ────────────┐ │
│ │ Avatar 52x52                    │ │
│ │ Column (Expanded):              │ │
│ │   Other party name SemiBold     │ │
│ │   Booking reference tag         │ │
│ │   Last message preview          │ │
│ │   (truncated 1 line)            │ │
│ │   Inter Regular 13px #6B6B6B    │ │
│ │ RIGHT:                          │ │
│ │   Timestamp                     │ │
│ │   Inter Regular 11px #9E9E9E    │ │
│ │   Unread count badge (if any)   │ │
│ │   green circle, white number    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Sorted by most recent message       │
│                                     │
│ Empty state:                        │
│   "No messages yet"                 │
│   "Your conversations will appear   │
│   here after a booking is accepted" │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

---

## SCREEN SH2: Chat Thread Screen **Route:** /messages/{bookingId}

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   [Other party name]       │
│            [Booking ref chip]       │
│                                     │
│ ┌─── BOOKING CONTEXT CARD ────────┐ │
│ │ Booking code | Category | Status│ │
│ │ Compact info strip               │ │
│ └─────────────────────────────────┘ │
│                                     │
│ MESSAGES AREA (scrollable):         │
│ Reverse sorted (newest at bottom)   │
│                                     │
│ OWN MESSAGES (right side):          │
│   Green bubble, white text          │
│   borderRadius: all 16, bottomRight 4│
│   Timestamp below, right            │
│   Double tick read receipt          │
│                                     │
│ OTHER PARTY (left side):            │
│   White bubble, #1A1A1A text        │
│   border 1px #E0E0E0                │
│   borderRadius: all 16, bottomLeft 4│
│   Timestamp below, left             │
│                                     │
│ DATE SEPARATORS:                    │
│   "Today" / "Yesterday" / date      │
│   Centered, gray pill               │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ INPUT BAR (pinned bottom):          │
│ White bg, border top 1px #E0E0E0    │
│ Padding: 8px                        │
│ ROW:                                │
│   [📎 attach icon]                  │
│   [Text input field Expanded]       │
│   [➤ send icon (green when text)]   │
│                                     │
└─────────────────────────────────────┘
```

### Real-Time Behavior
```
Messages delivered via WebSocket:
  Channel: private-booking.{bookingId}
  Event: NewMessage
  Payload: { sender_id, content, created_at }

On send:
  POST /api/messages/{bookingId}
  body: { content }
  Message appears immediately (optimistic UI)
  Confirmed when server responds

On receive:
  New bubble appears at bottom
  Scroll to bottom automatically
  Mark as read (all unread → read)
  POST /api/messages/{bookingId}/read

Attachment:
  Image picker → upload to server
  Shows image bubble in chat
```

---

## SCREEN SH3: Notification Center **Route:** /notifications **Bottom nav tab:** Notifications (active)

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ "Notifications"                     │
│ Poppins SemiBold 22px               │
│            [Mark all read] text btn │
│            Inter SemiBold 13px green│
│                                     │
│ "Today" section label               │
│ Inter SemiBold 12px #9E9E9E         │
│                                     │
│ Notification rows:                  │
│                                     │
│ ┌─── NOTIFICATION ROW ────────────┐ │
│ │ [Icon circle 40x40]             │ │
│ │   Type-specific icon + color    │ │
│ │ Column (Expanded):              │ │
│ │   Title: Inter SemiBold 14px    │ │
│ │   Body: Inter Regular 13px gray │ │
│ │   Time: Inter Regular 11px gray │ │
│ │ LEFT DOT (if unread):           │ │
│ │   8x8 green circle              │ │
│ └─────────────────────────────────┘ │
│                                     │
│ "Earlier" section label             │
│                                     │
│ More notification rows...           │
│                                     │
│ Empty state:                        │
│   Bell icon (large, gray)           │
│   "No notifications yet"            │
│   "We'll notify you about bookings, │
│   verification updates, and more."  │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

### Notification Icon Colors
```
Booking request     → blue circle
Booking accepted    → green circle
Booking completed   → green circle
Booking cancelled   → red circle
Verification update → amber circle
New message         → blue circle
New review          → star gold circle
Report resolved     → green circle
Announcement        → gray circle
Trust tier update   → amber circle
```

---

## SCREEN SH4: Edit Profile Screen **Route:** /profile/edit

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Edit Profile"          │
│                                     │
│ SingleChildScrollView, padding 16px │
│                                     │
│ PROFILE PHOTO (centered):           │
│ Stack:                              │
│   CircleAvatar radius 52            │
│   Positioned camera icon overlay    │
│   (same as Complete Profile screen) │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ ┌─── FORM CARD ───────────────────┐ │
│ │ Full Name                       │ │
│ │ [Text input, pre-filled]        │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Mobile Number                   │ │
│ │ [Phone input, pre-filled]       │ │
│ │ caption: "Used for booking      │ │
│ │ coordination only."             │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Email Address                   │ │
│ │ [Greyed out, non-editable]      │ │
│ │ (email cannot be changed)       │ │
│ │                                 │ │
│ │ SizedBox height: 16             │ │
│ │                                 │ │
│ │ Barangay                        │ │
│ │ [Dropdown, pre-selected]        │ │
│ │ 20 Trinidad barangays           │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SizedBox height: 24                 │
│                                     │
│ [Save Changes] green button         │
│ Full width, height 52px             │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN SH5: Notification Preferences **Route:** /settings/notifications

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Notifications"         │
│                                     │
│ SECTION: "NOTIFICATION TYPES"      │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ Booking Updates   [Switch]      │ │
│ │ Messages          [Switch]      │ │
│ │ Announcements     [Switch]      │ │
│ │ (Workers also get:)             │ │
│ │ Verification Updates [Switch]   │ │
│ │ Trust Tier Updates   [Switch]   │ │
│ └─────────────────────────────────┘ │
│                                     │
│ SECTION: "DELIVERY METHOD"         │
│                                     │
│ ┌─── WHITE CARD ──────────────────┐ │
│ │ In-App Notifications            │ │
│ │ [Always On — cannot disable]    │ │
│ │                                 │ │
│ │ Push Notifications [Switch]     │ │
│ │ subtitle: "Receive alerts       │ │
│ │ even when app is closed"        │ │
│ └─────────────────────────────────┘ │
│                                     │
│ All switches: activeColor #2E9B2E   │
│ Changes saved automatically         │
│ (no Save button needed)             │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN SH6: Help / FAQ Screen **Route:** /help

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Help & FAQ"            │
│                                     │
│ [🔍 Search FAQs...]                 │
│ Search bar, white, rounded          │
│                                     │
│ ACCORDION FAQ LIST:                 │
│                                     │
│ SECTION: Account & Registration     │
│ ┌─── FAQ ITEM ────────────────────┐ │
│ │ "How do I change my barangay?" ▾│ │
│ │ [Expanded answer text]          │ │
│ └─────────────────────────────────┘ │
│ [More FAQ items...]                 │
│                                     │
│ SECTION: Bookings                   │
│ [FAQ items...]                      │
│                                     │
│ SECTION: Safety & Trust             │
│ [FAQ items...]                      │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ "Still need help?"                  │
│ Poppins SemiBold 16px center        │
│                                     │
│ [Contact Support] green outlined    │
│ Full width, height 52px             │
│ Opens email link or contact form    │
│                                     │
└─────────────────────────────────────┘
```

### FAQ Accordion Item
```
White card row:
  Question text: Inter SemiBold 14px
  Trailing: chevron (rotates on expand)

Expanded state:
  Answer text: Inter Regular 14px #6B6B6B
  lineHeight: 1.5
  Padding: 16px bottom

AnimatedContainer for smooth
expand/collapse animation
```

---

## Client Flow Summary

```
First-time registration:
  Splash → Onboarding → Login →
  Role Selection → Registration →
  Email Verification → Client Home Feed

Google sign-in (new user):
  Login → Google picker →
  Role Selection → Complete Profile →
  Client Home Feed

Discovering and booking a worker:
  Client Home Feed →
  Tap feed card →
  Worker Profile →
  Tap "Book This Service" on a post →
  (Warning modal if unverified) →
  Booking Request Form →
  Send Request →
  Wait for worker acceptance →
  Notification: "Booking accepted!" →
  Chat with worker if needed →
  Map screen → "I'm on my way" (optional) →
  Track worker on map (if worker tracking) →
  Worker marks job started →
  "Confirm Job Completed" button appears →
  Confirm → Rate & Review

Browsing by category:
  Search bar → Browse by Category →
  Select category → Worker list →
  Tap worker → Worker Profile →
  Book from Posts tab
```

---

## Input Field Standard Spec (Used across all screens)

```
Label:
  Inter SemiBold 13px #1A1A1A
  SizedBox height 8 below label

Container:
  Background: white (disabled: #F0F0F0)
  Border: 1px solid #E0E0E0
  Border focused: 1.5px solid #2E9B2E
  Border error: 1.5px solid #D32F2F
  Border radius: 10px
  Height: 50px
  Padding horizontal: 16px

Placeholder text:
  Inter Regular 14px #9E9E9E

Input text:
  Inter Regular 14px #1A1A1A

Error text (below field):
  Inter Regular 12px #D32F2F
  SizedBox height 4 above error

Password field suffix:
  Eye icon: toggle obscureText
  visibility / visibility_off icons
  color: #9E9E9E
```