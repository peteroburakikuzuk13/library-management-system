  Library Management System

A web-based Library Management System developed using PHP, MySQL, HTML, CSS, JavaScript and Bootstrap 5. The system helps librarians manage books, members, authors, categories and book loans efficiently.

1. Project Information

- Project Name:** Library Management System
- Course:** Database II – CAT 2
- Database:** MySQL (`library_db`)
- Backend:** PHP (PDO)
- Frontend:** HTML, CSS, JavaScript and Bootstrap 5
- Development Environment:** XAMPP and Visual Studio Code
- Version Control:** Git and GitHub

2. Main Features

The system provides the following functionalities:

- User Authentication:** Secure login and logout functionality.
- Dashboard:** Displays important library statistics and activities.
- Book Management:** Add, view, search, edit and delete books.
- **Member Management:** Register, view, update and manage library members.
- **Loan Management:** Issue books, track borrowed books and process returns.
- **Author Management:** Add and view book authors.
- **Category Management:** Create and view book categories.
- **Overdue Tracking:** Identify overdue books and calculate applicable fines.
- **Security:** Password hashing, prepared SQL statements, input validation and session protection.

## 3. Technologies Used

| Technology | Purpose |
|---|---|
| HTML | Structure of web pages |
| CSS | Styling and page design |
| Bootstrap 5 | Responsive interface |
| JavaScript | Interactive features |
| PHP | Server-side programming |
| MySQL | Database management |
| PDO | Database connectivity |
| XAMPP | Local development server |
| Git/GitHub | Version control and project hosting |

## 4. Project Folder Structure

```text
library-system/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── auth/
│   ├── login.php
│   └── logout.php
│
├── authors/
│   ├── create.php
│   └── index.php
│
├── books/
│   ├── create.php
│   ├── edit.php
│   ├── delete.php
│   └── index.php
│
├── categories/
│   ├── create.php
│   └── index.php
│
├── config/
│   └── database.php
│
├── dashboard/
│   └── index.php
│
├── database/
│   ├── library_db.sql
│   ├── queries.sql
│   └── erd.png
│
├── docs/
│   ├
