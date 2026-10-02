# FRESH SCENTS — Mfumo wa Ndani wa Duka (Internal Shop Management System)
**Kwa matumizi ya Msimamizi na Wafanyakazi TU. Hakuna sehemu ya wateja/tovuti ya mauzo.**

Mfumo huu una sehemu tatu:
- `frontend/` — Faili za `.html`, `.css`, na `.js` (huu ndio Admin Panel unaouona kwenye browser)
- `backend/` — PHP API (JSON) inayoongea na database
- `database/` — Muundo wa Database (MySQL)

Frontend (HTML/JS) inaongea na Backend (PHP) kwa njia ya `fetch()` — kila kitufe/fomu kwenye ukurasa inatuma ombi kwenda `backend/api/*.php` na kupokea majibu ya JSON.

---

## 1. MAHITAJI
- PHP 7.4+ (na PDO MySQL extension)
- MySQL / MariaDB
- Apache/Nginx (au XAMPP/WAMP/Laragon kwa kompyuta yako, au hosting kama InfinityFree)

## 2. JINSI YA KUSAKINISHA

### Hatua 1 — Weka Faili
Weka folda nzima ya `fresh-scents` kwenye root ya server yako (mfano `htdocs/fresh-scents/`), ukihakikisha `frontend/`, `backend/`, na `database/` ziko kando kando (siblings).

### Hatua 2 — Tengeneza Database
Fungua phpMyAdmin, tengeneza database mpya (au tumia iliyopo kwenye hosting yako), kisha ingiza (Import) faili `database/schema.sql`. Hii itaunda majedwali yote pamoja na bidhaa chache za mfano.

**Kwenye hosting za bure (InfinityFree n.k.):** hosting hizo hazikuruhusu `CREATE DATABASE` kwa SQL — database lazima iwe imeshaundwa kwenye Control Panel kabla. Fungua `database/schema.sql`, futa mistari miwili ya kwanza (`CREATE DATABASE...` na `USE...`), kisha ingiza faili iliyobaki kwenye database uliyoshaunda.

### Hatua 3 — Unganisha Database
Fungua `backend/config/db.php`, weka taarifa sahihi za database yako:
```php
define('DB_HOST', 'localhost');   // au hostname ya hosting yako
define('DB_PORT', '3306');
define('DB_NAME', 'jina_la_database_yako');
define('DB_USER', 'mtumiaji_wako');
define('DB_PASS', 'password_yako');
```

### Hatua 4 — Sakinisha Akaunti ya Kwanza ya Msimamizi Mkuu
Fungua kwenye browser:
```
http://domain-yako/fresh-scents/frontend/install.html
```
Jaza taarifa za duka na akaunti ya Msimamizi Mkuu (Super Admin) ikiwemo swali la usalama.

### Hatua 5 — Ingia
```
http://domain-yako/fresh-scents/frontend/login.html
```

---

## 3. MUUNDO WA FAILI

```
fresh-scents/
├── frontend/
│   ├── css/admin.css
│   ├── js/api.js       (fetch helpers)
│   ├── js/app.js       (sidebar/topbar, auth guard, role filtering)
│   ├── images/logo.jpeg
│   ├── install.html, login.html, forgot-password.html, logout.html
│   ├── dashboard.html, products.html, pos.html, damages.html
│   ├── stock.html, reports.html, finance.html
│   ├── employees.html, payroll.html
│   └── admins.html, settings.html, profile.html
├── backend/
│   ├── api/            (JSON endpoints kwa kila ukurasa)
│   ├── config/db.php
│   ├── includes/functions.php
│   └── uploads/products/ (picha za bidhaa zilizopakiwa)
└── database/schema.sql
```

---

## 4. VIPENGELE VYA MFUMO

### Dashibodi
- **Ripoti ya Leo**: Mauzo ya Leo, Manukato Yaliyouzwa Leo, Matumizi ya Leo, Faida Halisi ya Leo (kwa kila mtumiaji)
- Muhtasari wa jumla, mauzo ya hivi karibuni, bidhaa zinazokaribia kuisha (kwa Admin)

### Bidhaa (Manukato) — Admin/Msimamizi tu
- Kuongeza/kuhariri/kufuta bidhaa, kila bidhaa ikiwa na Ukubwa (ML) na Bei nyingi

### Rekodi Mauzo — Wote (Admin na Mfanyakazi)
- Rekodi mauzo yanayofanyika dukani: chagua bidhaa+ukubwa, idadi, njia ya malipo; stock hupungua moja kwa moja

### Uharibifu / Muda Kuisha — Wote
- Rekodi bidhaa iliyoharibika au imemaliza muda; stock hupungua, hasara inahesabiwa

### Stock — Wote (kuona), Admin (kuongeza)

### Ripoti — Admin/Msimamizi tu
- **Leo, Wiki Hii, Mwezi Huu, Mwaka Huu, na Kipindi Maalum (Custom)**

### Matumizi — Wote (kuongeza), Admin (kuona ripoti kamili)

### Malipo ya Wafanyakazi, Wafanyakazi — Admin/Msimamizi tu
### Watumiaji wa Mfumo, Mipangilio — Msimamizi Mkuu tu

---

## 5. VYEO (ROLES)
| Cheo | Ruhusa |
|---|---|
| **Msimamizi Mkuu** | Ufikiaji kamili, ikiwemo kusimamia watumiaji wengine |
| **Msimamizi** | Ufikiaji kamili isipokuwa kusimamia watumiaji/mipangilio |
| **Mfanyakazi (Worker)** | Rekodi Mauzo, Uharibifu, Matumizi (kuongeza tu), kuona Stock |

---

## 6. USALAMA
- Password zote zinahifadhiwa kwa usimbaji (bcrypt hashing)
- Kila mtumiaji ana Swali la Usalama la kurejesha password (`forgot-password.html`)
- `install.html` inafanya kazi mpaka admin wa kwanza aundwe; baada ya hapo inaonyesha "tayari imesakinishwa"
- Kila ukurasa wa API una ulinzi wa session (`require_login_api`) na baadhi zina ulinzi wa cheo (`require_role_api`)

---
© FRESH SCENTS — Mfumo wa Ndani wa Duka
