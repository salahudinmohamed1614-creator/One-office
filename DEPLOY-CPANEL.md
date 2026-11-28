# ONE OFFICE – Ku rakibidda cPanel (online)

Hagahan wuxuu sharxayaa sida website-ka ONE OFFICE (PHP + MySQL) loogu shubo hosting cPanel leh, si website-ka iyo admin panel-ka (dashboard) ay online ugu shaqeeyaan.

## Waxa aad u baahan tahay
- Hosting cPanel leh (Hostinger, Namecheap, HostGator, iwm.) iyo domain ama subdomain.
- PHP **8.1 ama ka sareeya**.
- Faylka **`oneoffice-cpanel.zip`**.

## 1. Dooro nooca PHP
cPanel → **Select PHP Version** (ama **MultiPHP Manager**) → dooro **PHP 8.1, 8.2 ama 8.3**.
Hubi in extensions-kan ay shidan yihiin: `pdo_mysql`, `mbstring`, `fileinfo`.

## 2. Samee database-ka
1. cPanel → **MySQL® Databases**.
2. **Create New Database**: magac sii, tusaale `oneoffice`. Magaca buuxa wuxuu noqonayaa wax la mid ah `cpuser_oneoffice`.
3. **Add New User**: samee isticmaale, tusaale `cpuser_ooadmin`, oo leh password adag. Password-ka meel ammaan ah ku qor.
4. **Add User To Database**: isticmaalaha ku dar database-ka, kadibna dooro **ALL PRIVILEGES**.

## 3. Upload-garee faylasha
1. cPanel → **File Manager** → fur `public_html`. Haddii aad subdomain isticmaalayso, fur folder-ka subdomain-ka.
2. **Upload** → dooro `oneoffice-cpanel.zip`.
3. Midig-guji zip-ka → **Extract**, kadibna tirtir zip-ka.
4. Riix **Settings** → calaamadee **Show Hidden Files**. Hubi inay jiraan `.htaccess` iyo `uploads/.htaccess`.

## 4. Wax ka beddel `config.php`
File Manager → midig-guji `config.php` → **Edit**. Beddel afartan qiimo oo keliya:

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');            // cPanel: badanaa localhost
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cpuser_oneoffice');     // magaca buuxa ee database-ka
define('DB_USER', getenv('DB_USER') ?: 'cpuser_ooadmin');       // isticmaalaha database-ka
define('DB_PASS', getenv('DB_PASS') ?: 'PASSWORD-KAAGA');       // password-ka isticmaalaha
```

> Password-ka **ha geynin GitHub**. Faylka `config.php` ee server-ka oo keliya ku qor.
> Haddii aad faylka ku beddesho kombiyuutarkaaga, u kaydi **UTF-8 (without BOM)**. BOM wuxuu keenaa khaladka "strict_types declaration must be the very first statement".

## 5. Rakib
1. Browser-ka ku fur: `https://domainkaaga.com/install.php`.
2. Samee akoonka admin-ka: username iyo password (ugu yaraan 8 xaraf).
3. **Muhiim:** File Manager ka **tirtir `install.php`**.

## 6. Hubi
| Bog | Link |
|---|---|
| Website-ka | `https://domainkaaga.com/` |
| Admin login | `https://domainkaaga.com/admin/` |

Admin panel → **Site settings**: geli telefoonka, email-ka, cinwaanka iyo WhatsApp-ka dhabta ah. Kuwa hadda ku jira waa tijaabo.

## 7. SSL (https)
cPanel → **SSL/TLS Status** → **Run AutoSSL**. Kadib website-ka ku fur `https://`.

## Dhibaatooyinka caadiga ah
| Dhibaato | Xal |
|---|---|
| "ONE OFFICE needs PHP 8.1 or newer" | Tallaabada 1: dooro PHP 8.1+ |
| "Database connection failed" | Hubi `config.php`: magaca buuxa (`cpuser_...`), user-ka iyo password-ka. Hubi in user-ka loo daray database-ka (ALL PRIVILEGES). |
| 500 Internal Server Error | Hubi in `.htaccess` la soo raray. Haddii ay sii socoto, fur cPanel → **Errors** ama `error_log`. |
| 403 marka la furo `config.php` | Waa sax: waa la xannibay si amni ah. |
| Sawirradu ma soo baxaan (upload) | `uploads/` permissions u dhig **755**. |

## Kaydka (backup)
Ka hor isbeddel weyn: cPanel → **Backup** ama **phpMyAdmin → Export** si aad u kaydiso database-ka.
