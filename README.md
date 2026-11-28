# ONE OFFICE – One Stop Government Services Facilitation Center

Hal Xafiis – Adeegyo Badan. Website (PHP + MySQL + HTML/CSS/JS) oo leh admin panel iyo nidaam codsiyo + Tracking Number.

**Demo (GitHub Pages):** https://salahudinmohamed1614-creator.github.io/One-office/
**Admin panel demo (xog tijaabo ah):** https://salahudinmohamed1614-creator.github.io/One-office/admin/login.html · dashboard: https://salahudinmohamed1614-creator.github.io/One-office/admin/index.html
**Backend (dhab ah):** `http://localhost/oneoffice/admin/` (XAMPP) · code: [`admin/`](admin/)
Demo-gu waa HTML taagan (`docs/`): foomamka, tracking-ka iyo kaydinta admin-ka waxay ku shaqeeyaan nooca PHP oo keliya.
Xogta admin demo-ga waa been-abuur (database gaar ah `one_office_demo`), xog macmiil oo dhab ah ma galayso GitHub.

Dib u dhis demo-ga:
1. Bogagga dadweynaha: `php tools/build-demo.php http://localhost/oneoffice`
2. Admin demo: `$env:DB_NAME='one_office_demo'; php -S 127.0.0.1:8089` kadib (isla `DB_NAME`) `php tools/build-admin-demo.php http://127.0.0.1:8089`

## Shuruudaha
- PHP 8.1+ (extensions: `pdo_mysql`, `mbstring`, `fileinfo`)
- MySQL 5.7+ ama MariaDB 10.3+
- Apache (XAMPP / cPanel hosting)

## Bogagga website-ka
| Bog | Shaqo |
|---|---|
| `index.php` | Bogga hore: hero, hordhac, 6 qaybood oo adeeg, 5-ta talaabo, Business Advisory (10), lacagaha, diaspora, FAQ, xiriir |
| `about.php` | Cidda aan nahay, waxa aan qabanno iyo waxa aanan qabanin, qiyamka, 4-ta tiir |
| `services.php` | Dhammaan adeegyada (raadin toos ah) + badhanka "Codso adeeggan" |
| `request.php` | Foomka codsiga, wuxuu bixiyaa Tracking Number (tusaale `OO-261126-AB12`) |
| `track.php` | Raadinta codsiga: Tracking Number + telefoon, heerka, lacagaha, taariikhda |

## Admin (`/admin/`)
| Qayb | Shaqo |
|---|---|
| Dashboard | Codsiyada (cusub, socda, diyaar, la gaarsiiyay), lacagaha (billed / paid / outstanding), garaaf 14 maalmood, adeegyada ugu badan |
| Service requests | Liis + raadin, beddel heerka (qoraal macmiilka u muuqda), lacagaha, qoraal gudaha ah, daabac, codsi cusub (walk-in / telefoon) |
| Service categories / Services / FAQ / Testimonials | Ku dar, wax ka beddel, qari, tirtir |
| Site settings | Qoraallada, xiriirka, WhatsApp, fur/xir codsiyada online-ka |
| Messages / Change password | Fariimaha foomka xiriirka, beddelka password-ka |

Heerarka codsiga: Received → Preparing → (Action needed) → Submitted to institution → Ready → Delivered (ama Cancelled).

## Rakibid (XAMPP)
1. Folder-ka u koobi garee `C:\xampp\htdocs\oneoffice`
2. Hubi `config.php` (default: `root`, password madhan, DB `one_office`)
3. Fur `http://localhost/oneoffice/install.php`, samee akoonka admin-ka, kadibna **tirtir `install.php`**

## Online (cPanel)
Eeg [DEPLOY-CPANEL.md](DEPLOY-CPANEL.md): PHP 8.1+, database, upload zip, `config.php`, `install.php`.

## Amniga
- PDO prepared statements, `htmlspecialchars`, CSRF token foom kasta, bcrypt
- Xannibaad login: 5 isku day oo khaldan → 15 daqiiqo
- Raadinta codsiga waxay u baahan tahay telefoonka saxda ah (15 isku day oo khaldan → 15 daqiiqo)
- Honeypot + throttle foomamka, `.htaccess` wuxuu xannibayaa `config.php`, `includes/`, `database/`

## Qaab-dhismeedka
```
index.php about.php services.php request.php track.php contact.php
includes/bootstrap.php   Shaqooyinka guud (DB, CSRF, statuses, tracking number)
includes/site.php        Header/footer-ka bogagga dadweynaha
admin/                   Qaybta maamulka (requests.php = codsiyada)
assets/img               logo.png, logo-mark.png, favicon.png
database/schema.sql, seed.sql
```
