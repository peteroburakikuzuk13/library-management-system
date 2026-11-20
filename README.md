**GitHub repository:** https://github.com/YOUR-GROUP/library-system  *(replace with your group's repository link before submitting)*

# Library Management System

Database II, CAT 2. A librarian runs the whole library from a web browser: books, members, loans, authors and categories. Every record lives in a MySQL database. Built with PHP (PDO), MySQL/MariaDB, HTML, CSS, JavaScript and a locally bundled copy of Bootstrap 5, so it works without internet access.

**Group:** *(group name)*  
**Members:** *(names and registration numbers, see also docs/report.pdf)*

---

## 1. Test accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | admin |
| `librarian` | `librarian123` | librarian |

These are fake, classroom-only passwords. The database stores only their bcrypt hashes (made with `password_hash()`), never the passwords themselves.

---

## 2. Run it on a fresh XAMPP (Windows)

You need about ten minutes. Follow the steps in order.

1. **Install XAMPP.** Download the newest installer from <https://www.apachefriends.org> and install it in the default folder, `C:\xampp`. Use the newest version (see "Security notes" below).
2. **Start the servers.** Open the *XAMPP Control Panel* and press **Start** next to **Apache** and next to **MySQL**. Both names should turn green. If Apache will not start, another program is using port 80 or 443 (Skype, IIS and other web servers are common culprits). Close that program, or change Apache's port in *Config > httpd.conf* and then use `http://localhost:YOURPORT/` in the addresses below.
3. **Copy the project.** Put the whole `library-system` folder inside `C:\xampp\htdocs\`. The result must be `C:\xampp\htdocs\library-system\index.php`.
4. **Create the database.** Open <http://localhost/phpmyadmin>, click **New**, type the name `library_db`, choose the collation `utf8mb4_general_ci` and press **Create**.
5. **Import the data.** Click `library_db` in the left list, open the **Import** tab, choose the file `database/library_db.sql` from the project folder, and press **Import** at the bottom. You should see a green success message and six tables: `authors`, `books`, `categories`, `loans`, `members`, `users`.
   *The file can be imported again at any time. It rebuilds the tables and the sample data from nothing.*
6. **Check the connection file.** Open `config/database.php`. XAMPP's default MySQL login is user `root` with an empty password, which is what the file already uses. Change `$pass` only if you set a MySQL password.
7. **Open the system.** Go to <http://localhost/library-system> and log in with a test account from section 1.

### If something does not work

| Symptom | What to do |
| --- | --- |
| "Database connection failed" page | MySQL is not started in the XAMPP Control Panel, the database is not named exactly `library_db`, or the password in `config/database.php` is wrong. |
| "Not Found" page | The folder is not at `C:\xampp\htdocs\library-system`, or the folder name has a different spelling. |
| Import stops with an error | Make sure you imported into XAMPP's MySQL (not another server) and that the file is the unmodified `database/library_db.sql`. |
| Dates look a day off | The timezone is set in `includes/functions.php` (`APP_TIMEZONE`). Change it to your own zone. |
| Page is blank or says "Something went wrong" | Real error details are written to `C:\xampp\apache\logs\error.log`. They are never shown on screen on purpose. |

WAMP works too, but the lecturer supports XAMPP only. If you use WAMP, the folder is `C:\wamp64\www\library-system` and the steps are otherwise the same.

---

## 3. What the system does

| Area | Features |
| --- | --- |
| **Login** | Login page, logout link, PHP sessions, every page protected by `includes/auth_check.php`, lock-out for 60 seconds after 5 wrong passwords |
| **Books** | Add, list, search (title, author, ISBN, publisher), filter (category, availability), edit, delete with confirmation |
| **Members** | Add, list, search (name, number, email, phone), filter by status, edit (including suspend), delete with confirmation |
| **Loans** | Issue a book, list with search and filter (on loan, overdue, returned), mark as returned |
| **Authors and categories** | List and add |
| **Dashboard** | Key numbers, overdue loans, loans due in the next 3 days, titles per category |
| **Bonus** | Dashboard cards, pagination (10 per page), CSV export on books, members and loans, overdue list with automatic fine (500 per late day, set in `includes/functions.php`) |

### Business rules (all enforced in PHP, inside database transactions)

1. A book with zero available copies cannot be issued.
2. Issuing reduces available copies by one. Returning increases them by one. The loan record and the copy count change together or not at all.
3. A member cannot borrow the same book twice at the same time.
4. A suspended member cannot borrow.
5. The due date is set automatically: issue date plus 14 days.
6. A book with unreturned loans, or a member with unreturned books, cannot be deleted.

When a book or member that only has *returned* loans is deleted, their old loan records are deleted with them (the confirmation message says how many).

---

## 4. Folder structure

```
library-system/
├── index.php            redirects to login or dashboard
├── README.md            this file
├── config/database.php  the one and only database connection
├── database/            library_db.sql, queries.sql, erd.png
├── includes/            header, navbar, footer, auth_check, functions (shared parts)
├── auth/                login.php, logout.php
├── dashboard/           index.php
├── books/               index, create, edit, delete
├── members/             index, create, edit, delete
├── loans/               index, issue, return
├── authors/             index, create
├── categories/          index, create
├── assets/              css/, js/, images/
└── docs/                report.pdf, screenshots/
```

Every entity has its own folder. The header, navigation, footer and login check live only in `includes/`. The database connection is written once in `config/database.php` and loaded with `require_once`.

---

## 5. Database files

* `database/library_db.sql` creates the database, the 6 tables with keys and constraints, and the sample data (16 books, 11 authors, 6 categories, 12 members, 16 loans: 8 returned, 8 still out, 3 of them overdue).
* `database/queries.sql` holds 5 JOIN queries and 4 aggregate reports. Paste one at a time into the **SQL** tab of phpMyAdmin.
* `database/erd.png` is the Entity Relationship Diagram.

---

## 6. Security notes

* **Update PHP.** On 21 October 2026 the PHP team released emergency fixes for two critical remote code execution flaws, CVE-2026-109618 and CVE-2026-109619. The fixed versions are 8.5.12, 8.4.27, 8.3.36 and 8.2.35. Install the newest XAMPP, then check your version: in the XAMPP Control Panel press **Shell** and type `php -v`. Compare it with <https://www.php.net/downloads> and do not use an old XAMPP copy from a flash drive.
* **Keep it local.** Run the project on `localhost` only. Do not expose XAMPP to the internet. There is no `phpinfo()` file in this project.
* **No SQL from user input.** Every query that uses user input is a prepared statement (PDO, real prepared statements, `ATTR_EMULATE_PREPARES` off).
* **Server-side validation** on every form, even though the browser checks too.
* **Passwords** are stored with `password_hash()` and checked with `password_verify()`.
* **Escaping.** Everything printed from the database or from the user goes through `htmlspecialchars()` (the helper `e()`).
* **CSRF tokens** protect every form that changes data. Delete, return and issue requests only work as POST with a valid token.
* **Friendly errors.** Raw PHP and SQL errors are never shown. They go to the PHP error log.

---

## 7. AI use declaration

*(Complete this section to match the policy your lecturer gave. The brief requires every use of AI tools to be declared in the report, and every group member must understand every line they submit.)*
