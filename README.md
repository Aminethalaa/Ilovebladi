# بلديتي — Baladiyati 🇩🇿

A modern, mobile-first, bilingual (العربية / Français) citizen complaint platform for Algeria,
built in **plain PHP + MySQL** so it deploys on any **cPanel shared hosting** — no Composer,
no SSH, no framework required.

Citizens report local problems (photo + GPS + category), commune (baladiya) admins review and
fix them with **before/after photos**, verified **associations (جمعيات)** can take charge of
published issues and fix them too, and everyone competes through **points, badges, levels and
public leaderboards** for communes, wilayas, citizens and associations.

---

## ✨ Features

### Citizens (المواطنون)
- Report a problem: photo, GPS location (browser geolocation or tap-the-map, Leaflet + OpenStreetMap), category, commune
- Follow the complaint lifecycle: pending review → published → in progress → resolved → closed
- "J'ai le même problème / عندي نفس المشكل" upvotes that raise complaint priority
- Confirm the fix after seeing the after-photo — or reopen with a reason
- Points, 5 levels (مواطن جديد → بطل البلدية) and badges
- In-app notification bell + email notifications

### Commune admins (مشرفو البلديات)
- Review queue: approve or reject (written reason required, shown to the reporter)
- Start works, resolve with a mandatory **after photo**, close cases
- Commune performance score /100 (70% resolution rate + 30% speed) and admin badges

### Everyone can fix — البلدية، الجمعيات والمواطنون
- **Volunteer citizens** can take charge of a published complaint, repair it and submit an
  after-photo; the commune admin validates the fix before it counts. Volunteer points and
  badges (مصلح متطوع، بطل الحي) reward them.
- **Associations (الجمعيات)** self-register (activated after super-admin verification), take
  charge of complaints in their wilaya and resolve directly with after-photos. Own points
  scale, badges and a dedicated leaderboard tab.

### Closing incentives
- Confirming a fix pays a bigger bonus (+15) with a celebration message; volunteer/association
  fixers get their reward when the case is confirmed closed.
- If a complaint stays "resolved" for 3+ days without confirmation, the reporter automatically
  receives a reminder (in-app + email + push) — no cron needed, it runs lazily on page views.
- Closer badges (مؤكّد الإصلاح، غالق المشاكل) and commune/wilaya scores that weigh
  confirmed-closed complaints higher than merely resolved ones.

### Tree planting — غرس الأشجار 🌳
- Commune admins and verified associations launch **planting campaigns** with a tree goal
  (and optional deadline); a public progress bar tracks each campaign.
- Citizens and associations log their planted trees with a photo, count and GPS position.
- **Community verification**: a contribution counts once 2 members confirm it (or instantly
  when the campaign creator / an admin confirms). 3 points per approved tree, planter badges
  (غارس، صديق البيئة، حارس الغابة), and a total-trees counter on the landing page.

### Super admin
- Global statistics console
- Create/delete commune admins, verify associations, block users
- Manage categories (add/disable) and communes (add under any wilaya)
- Oversee all complaints
- **White-label branding (هوية الموقع)**: change the site name, description, homepage
  headline (all bilingual), upload a logo (the PWA/notification icon is generated from it
  automatically), pick the three theme colors with a live color picker, and set footer
  contact info — so the same platform can be deployed for any commune, association or
  community under its own identity. Empty fields fall back to the Baladiyati defaults.
- **Feature modules**: switch whole sections (complaints, tree campaigns, leaderboard,
  association sign-up) on or off from the site-identity page.
- **Association domains (مجالات الجمعيات)**: manage the activity categories associations can
  belong to (add/disable).

### 🤝 Association directory (دليل الجمعيات)
- Public, searchable directory of verified associations with logo, activity domains
  (environment, solidarity, culture, sport, health…), commune/wilaya, and a short description.
- Filter by domain (category chips), wilaya/commune, and free-text name search.
- Each association has a public profile page: full description, all domains, contact block
  (email, phone, website, Facebook, address, founding year), activity stats (fixes, tree
  campaigns, trees, points), badges, and their recent resolved complaints and campaigns.
- Associations edit their own logo, contact info and domains from their profile page.
- The directory tables auto-create (and the domain list auto-seeds) on first use — existing
  databases need **no migration**.

**Uploaded images are auto-resized** on upload (photos capped at 1600px, logos at 512px)
to keep storage and page loads light on shared hosting; PWA icons are always generated at
exactly 192×192 and 512×512.

### 📲 PWA + Push notifications
- Installable app on Android, iPhone and PC (manifest + service worker + offline page).
  Chrome/Edge show an "Install app" button; on iPhone use Safari → Share → *Add to Home Screen*.
- Real Web Push notifications (VAPID + RFC 8291 encryption implemented in plain PHP —
  no Composer needed). Users opt in with an explicit "Enable notifications" button on their
  dashboard; complaint status changes then reach their phone/PC even with the site closed.
- Admins get a **notification composer** (📣 button on their dashboard): title, text,
  optional image and click-link, sent to all subscribed residents of their commune.
  The super admin can broadcast to everyone or to one wilaya.
- Contextual permissions: notifications are requested only on that button tap, GPS only when
  tapping "my location", camera only when tapping the photo field.
- Requirements: **HTTPS** (enable AutoSSL in cPanel) and the PHP **openssl** + **curl**
  extensions (present on virtually every host). Push tables and VAPID keys are created
  automatically on first use — existing databases need no migration.
- iPhone note: web push requires iOS 16.4+ and the app must be added to the Home Screen first.

### Public
- Landing page focused on live statistics (animated counters, category bars, top communes, latest before/after fixes)
- Complaints browser with map + filters (wilaya → commune cascade, category, status)
- Leaderboards: communes, wilayas, citizens, associations
- Full Arabic RTL + French LTR with a one-click language switcher

---

## 🚀 Deployment on cPanel (5 minutes)

1. **Upload the files**
   Compress this folder into a `.zip`, upload it via **cPanel → File Manager** into
   `public_html/` (or a subfolder), and extract. Make sure the `uploads/` folder is writable
   (permissions `755` are usually fine on cPanel since PHP runs as your user).

2. **Create the database**
   In **cPanel → MySQL® Databases**: create a database, create a user, add the user to the
   database with **ALL PRIVILEGES**.

3. **Import the schema**
   Open **phpMyAdmin**, select your new database, go to **Import**, and import `database.sql`.
   This creates all tables and seeds the 58 wilayas, main communes, categories and badges.

4. **Configure the app**
   Edit `includes/config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   (on cPanel the names are usually prefixed, e.g. `cpaneluser_baladiyati`).

5. **Create the super admin**
   Visit `https://your-domain.tld/install.php`, create the super-admin account,
   then **delete `install.php`** from the server.

That's it. Log in as super admin, add commune admins from the dashboard, and the platform is live.

### Troubleshooting a blank page / HTTP 500

1. **Open `https://your-domain.tld/checkup.php`** — it diagnoses the usual causes itself
   (PHP version, missing extensions, wrong DB credentials, missing tables, folder permissions).
2. The #1 cause is an **old PHP version**: in cPanel open **MultiPHP Manager** (or
   **Select PHP Version**), select your domain and choose **PHP 8.1+** (7.4 minimum).
3. Make sure the extensions **pdo_mysql** and **mbstring** are enabled
   (cPanel → Select PHP Version → Extensions). Usually they are by default.
4. The real error message is in **cPanel → Metrics → Errors**, or in an `error_log`
   file created next to the failing script in File Manager.
5. Delete `checkup.php` (and `install.php`) once the site is running.

### Notes
- **Communes list**: the seed contains every wilaya's chef-lieu plus the main communes of
  Alger, Oran and Constantine. Add the rest of your communes from
  **Super admin → البلديات (التقسيم) / Communes**.
- **Email deliverability**: notifications use PHP `mail()`. On shared hosting these can land in
  spam; in-app notifications always work. For better delivery, create a `noreply@your-domain`
  mailbox in cPanel and keep `MAIL_FROM` (in `config.php`) on your own domain.
- **PHP version**: requires PHP 8.0+ (cPanel → Select PHP Version). Tested with PHP 8.4.
- **Maps**: Leaflet + OpenStreetMap via CDN — free, no API key needed.

---

## 🗂️ Project structure

```
├── index.php                 # Public landing page with statistics
├── complaints.php            # Public browser: map + filters + cards
├── complaint.php             # Complaint detail: timeline, upvote, confirm/reopen, association take-charge
├── leaderboard.php           # Communes / wilayas / citizens / associations rankings
├── login.php / register.php  # Auth (citizen or association account)
├── install.php               # One-time super-admin creation (delete after use)
├── database.sql              # Schema + seed (wilayas, communes, categories, badges)
├── api/                      # JSON: commune cascade, map GeoJSON
├── dashboard/                # Citizen & association: overview, new complaint, profile, notifications
├── admin/                    # Commune admin: queue + complaint processing
├── superadmin/               # Global console: admins, users, categories, communes, complaints
├── includes/                 # config, db, auth (sessions/CSRF), functions (points/badges/uploads/notify)
│   └── lang/                 # ar.php / fr.php translations
├── assets/                   # style.css (RTL-first, Algerian identity), app.js
└── uploads/                  # Complaint photos (script execution blocked)
```

## 🔒 Security
- PDO prepared statements everywhere, `password_hash()` (bcrypt), CSRF tokens on every POST
- Session hardening (httponly, SameSite, regeneration at login)
- Upload validation (mime + size + `getimagesize`), randomized filenames, script execution
  disabled in `uploads/`, `includes/` and `database.sql` blocked by `.htaccess`
- Role-based access control: citizen / association / commune admin (scoped to their commune) / super admin

## 🎮 Points reference

| Action | Points |
|---|---|
| Your complaint is approved & published | +10 |
| Your complaint is resolved | +25 |
| You confirm the fix | +5 |
| You upvote someone's complaint | +2 (reporter gets +1) |
| Association takes charge | +5 |
| Association submits a fix | +10 |
| Association's fix is confirmed/closed | +30 |

Commune score /100 = 70 × resolution rate + 30 × speed (full marks ≤ 3 days average).
