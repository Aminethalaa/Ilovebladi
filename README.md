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

### Associations (الجمعيات)
- Self-register, activated after super-admin verification
- Take charge of published complaints in their wilaya, resolve with after-photos
- Own points scale, badges and a dedicated leaderboard tab

### Super admin
- Global statistics console
- Create/delete commune admins, verify associations, block users
- Manage categories (add/disable) and communes (add under any wilaya)
- Oversee all complaints

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
