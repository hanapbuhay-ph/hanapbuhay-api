# HanapBuhay — Deployment Guide
**For: Laravel Developer (PM)**

---

> Covers: local Laragon setup (already detailed in
> 01_LOCAL_SETUP_GUIDE.md), connecting teammates to
> your local backend, and the future move to Railway
> for a real production deployment.

---

## Current Stage: Local-Only Development

```
Right now, there is NO live production server.
Everything runs on the PM's laptop via Laragon.
Teammates (App Dev, Web Dev) connect to this local
instance while building. This is intentional —
zero cost during development/capstone building.

Railway deployment happens LATER, only when:
- Core features are stable
- Instructor/panel needs a live demo link
- Or team decides to move to continuous shared dev
```

---

## Option A: Same WiFi Network (Recommended for Team Working Sessions)

```
1. Find your local IP address:
   Windows: open cmd → run "ipconfig"
   → Look for "IPv4 Address" under your active
     network adapter (e.g. 192.168.1.5)

2. Make sure Laragon's Apache is running
   ("Start All" in Laragon panel)

3. Share this URL with teammates on the same WiFi:
   http://192.1681.5/hanapbuhay-api/public/api
   (replace with your actual IP)

4. Flutter Dev config (physical Android device):
   static const String apiBaseUrl =
     'http://192.168.1.5/hanapbuhay-api/public/api';

5. Flutter Dev config (Android EMULATOR — different!):
   static const String apiBaseUrl =
     'http://10.0.2.2/hanapbuhay-api/public/api';
   // 10.0.2.2 is the emulator's special alias
   // for "host machine's localhost" — NOT your
   // real IP, and NOT localhost either

6. Web Dev (React) config (.env or config file):
   REACT_APP_API_URL=
     http://192.168.1.5/hanapbuhay-api/public/api

Caveats:
- Your IP can change when you reconnect to WiFi —
  re-share if teammates suddenly can't connect
- Windows Firewall may prompt to allow Laragon/Apache
  through — click "Allow" for both Private and
  Public networks
- Everyone must be on the SAME WiFi network
  (a phone on mobile data will NOT work with this
  option — use ngrok instead)
```

---

## Option B: ngrok (Remote Teammates / Testing on Mobile Data)

```
1. Download ngrok: https://ngrok.com/download
2. Sign up for free account, get your authtoken
3. Configure once:
   ngrok config add-authtoken YOUR_TOKEN

4. With Laragon running (Apache on port 80), run:
   ngrok http 80

5. ngrok gives you a temporary public HTTPS URL:
   https://abc123.ngrok-free.app

6. Share this with remote teammates:
   https://abc123.ngrok-free.app/hanapbuhay-api/public/api
   (URL structure depends on Laragon's virtual host —
   if using hanapbuhay-api.test, you may need to
   tunnel that specific vhost. Simpler: tunnel port
   80 directly and keep using the /hanapbuhay-api/public/api
   path since Laragon serves multiple sites off one
   Apache instance)

7. Flutter Dev config (using ngrok):
   static const String apiBaseUrl =
     'https://abc123.ngrok-free.app/hanapbuhay-api/public/api';

8. Update .env on Laravel side while using ngrok:
   APP_URL=https://abc123.ngrok-free.app
   (needed so Google OAuth redirect and any
   generated URLs work correctly through the tunnel)

Important limitations:
- Free ngrok URL CHANGES every time you restart
  ngrok — must re-share the new URL each session
- Free tier has a request rate limit — fine for
  small team testing, not for real users
- Keep the ngrok terminal window open the entire
  time teammates need access — closing it kills
  the tunnel
- For Google OAuth to work through ngrok, you must
  add the ngrok callback URL to your Google Cloud
  Console's "Authorized redirect URIs" temporarily:
  https://abc123.ngrok-free.app/api/auth/google/callback
```

---

## Option C: Soketi Access for Remote Teammates

```
Soketi runs on port 6001 locally. If a teammate needs
real-time features (tracking, chat) over ngrok too:

1. Open a SECOND ngrok tunnel for Soketi:
   ngrok http 6001

2. Gives another temporary URL, e.g.:
   https://xyz789.ngrok-free.app

3. Update .env broadcasting config to point Echo
   config at this URL when testing remotely
   (Flutter/React Echo client config, not Laravel's
   own .env) — this is usually a frontend-side change,
   coordinate with App Dev / Web Dev directly since
   it's in their codebase, not yours

Note: running two ngrok tunnels simultaneously on
the free plan may require the free tier's simultaneous
tunnel limit — check ngrok dashboard if the second
tunnel fails to start.
```

---

## Future: Railway Deployment (When Ready for Live Demo)

```
Railway is the planned host for when a real,
always-on deployment is needed (panel demo day,
or public capstone defense).

High-level steps (execute only when the team
is ready — not needed for local development):

1. Push hanapbuhay-api to GitHub (already done
   via develop/main branch flow)

2. Sign up at railway.app (free tier available,
   usage-based after free credits)

3. New Project → Deploy from GitHub repo →
   select hanapbuhay-api

4. Add a MySQL database plugin in Railway
   (Railway provisions connection env vars
   automatically)

5. Set environment variables in Railway dashboard
   (mirror your local .env, but with):
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app.up.railway.app
   DB_* variables auto-filled by Railway's MySQL
     plugin — do not hardcode
   MAIL_*, GOOGLE_*, FIREBASE_* — same values as local

6. Railway needs a start command — typically:
   php artisan migrate --force &&
   php artisan serve --host=0.0.0.0 --port=$PORT
   (or use a proper Procfile/nixpacks config —
   revisit this exact command closer to deployment
   time, Railway's PHP buildpack conventions may
   require adjustment)

7. Soketi also needs to run somewhere — options:
   a) Deploy Soketi as a separate Railway service
      (small additional cost)
   b) Use a free-tier alternative like Pusher's
      actual free cloud tier (100 connections,
      200k messages/day) if self-hosting Soketi
      on Railway proves troublesome — would require
      switching Laravel broadcasting config from
      "custom Soketi host" to real Pusher cloud
      credentials (easy swap, same Pusher protocol)

8. Update Google OAuth authorized redirect URI to
   the Railway production URL

9. Update Flutter/React API base URLs to the Railway
   URL for the "production" build flavor (keep local/
   ngrok URLs for "development" build flavor — most
   teams keep both via environment-based config)

10. Test all critical flows end-to-end on the live
    Railway URL before the demo/defense date

This step should NOT be rushed — budget at least
a few days before any live demo to catch deployment-
specific issues (env var typos, CORS settings,
storage/file upload paths behaving differently in
production, etc.)
```

---

## CORS Reminder (Applies to All Options)

```
Since React (web) and Flutter (mobile) both call
this API from different origins, make sure
config/cors.php allows:

'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
// Tighten 'allowed_origins' to your actual
// React dev URL + Railway URL before final
// production deployment — '*' is fine for
// local/capstone development only
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

---

## Quick Reference: Which Option When

| Scenario | Use |
|---|---|
| Solo work on your laptop | `localhost` or `hanapbuhay-api.test` directly |
| Team working session, same room/WiFi | Option A (local IP) |
| Teammate working remotely | Option B (ngrok) |
| Teammate needs live tracking/chat remotely | Option B + Option C (two tunnels) |
| Instructor/panel demo day | Railway (Future section) |
```

---

