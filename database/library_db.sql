-- =====================================================================
--  Library Management System  -  Database II, CAT 2
--  File    : database/library_db.sql
--  Purpose : creates the database, creates all 6 tables, inserts sample data
--  Engine  : MySQL / MariaDB (XAMPP), InnoDB so foreign keys and transactions work
--  Re-run  : safe. Tables are dropped and rebuilt, so the file rebuilds from nothing.
--
--  Test accounts (also listed in README.md):
--      admin      / admin123        (role: admin)
--      librarian  / librarian123    (role: librarian)
--  These are fake, classroom-only passwords. Only their bcrypt hashes are stored.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS library_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE library_db;

SET NAMES utf8mb4;

-- Drop children first so foreign keys never block the drop.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS categories;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  1. categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  category_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)   NOT NULL,
  description  VARCHAR(255)  NULL DEFAULT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  2. authors
-- ---------------------------------------------------------------------
CREATE TABLE authors (
  author_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120)  NOT NULL,
  country    VARCHAR(80)   NOT NULL DEFAULT 'Unknown',
  PRIMARY KEY (author_id),
  UNIQUE KEY uq_authors_name_country (name, country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  3. users  (librarians who log in)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)   NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,
  full_name      VARCHAR(120)  NOT NULL,
  role           ENUM('admin','librarian') NOT NULL DEFAULT 'librarian',
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  4. members  (people who borrow books)
-- ---------------------------------------------------------------------
CREATE TABLE members (
  member_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  member_number  VARCHAR(20)   NOT NULL,
  full_name      VARCHAR(120)  NOT NULL,
  email          VARCHAR(150)  NOT NULL,
  phone          VARCHAR(20)   NOT NULL,
  status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
  date_joined    DATE          NOT NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (member_id),
  UNIQUE KEY uq_members_number (member_number),
  UNIQUE KEY uq_members_email  (email),
  KEY idx_members_name (full_name),
  CONSTRAINT chk_members_email CHECK (email LIKE '%_@_%._%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  5. books
--     available_copies is kept in step with loans by the PHP code, inside
--     a transaction (see loans/issue.php and loans/return.php).
-- ---------------------------------------------------------------------
CREATE TABLE books (
  book_id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  isbn              VARCHAR(13)        NOT NULL,
  title             VARCHAR(200)       NOT NULL,
  author_id         INT UNSIGNED       NOT NULL,
  category_id       INT UNSIGNED       NOT NULL,
  publisher         VARCHAR(120)       NOT NULL,
  year_published    SMALLINT UNSIGNED  NOT NULL,
  total_copies      SMALLINT UNSIGNED  NOT NULL DEFAULT 1,
  available_copies  SMALLINT UNSIGNED  NOT NULL DEFAULT 1,
  created_at        TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (book_id),
  UNIQUE KEY uq_books_isbn (isbn),
  KEY idx_books_title (title),
  KEY idx_books_author (author_id),
  KEY idx_books_category (category_id),
  CONSTRAINT fk_books_author   FOREIGN KEY (author_id)   REFERENCES authors (author_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_books_year      CHECK (year_published BETWEEN 1000 AND 2100),
  CONSTRAINT chk_books_total     CHECK (total_copies >= 1),
  CONSTRAINT chk_books_available CHECK (available_copies <= total_copies)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  6. loans
--     date_returned IS NULL  ->  the book is still out.
--     Loans of a deleted book or member go with it (CASCADE). The PHP code
--     refuses the delete first if any of those loans are still unreturned.
-- ---------------------------------------------------------------------
CREATE TABLE loans (
  loan_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  member_id      INT UNSIGNED  NOT NULL,
  book_id        INT UNSIGNED  NOT NULL,
  issued_by      INT UNSIGNED  NULL DEFAULT NULL,
  date_issued    DATE          NOT NULL,
  due_date       DATE          NOT NULL,
  date_returned  DATE          NULL DEFAULT NULL,
  PRIMARY KEY (loan_id),
  KEY idx_loans_member (member_id),
  KEY idx_loans_book   (book_id),
  KEY idx_loans_issuer (issued_by),
  KEY idx_loans_status (date_returned, due_date),
  CONSTRAINT fk_loans_member FOREIGN KEY (member_id) REFERENCES members (member_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_loans_book   FOREIGN KEY (book_id)   REFERENCES books (book_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_loans_user   FOREIGN KEY (issued_by) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_loans_due      CHECK (due_date >= date_issued),
  CONSTRAINT chk_loans_returned CHECK (date_returned IS NULL OR date_returned >= date_issued)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
--  SAMPLE DATA
-- =====================================================================

-- Users (passwords are bcrypt hashes made with PHP password_hash)
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin',     '$2y$10$vlh5AQvfwPkaCxYmckkWw.NnNJrUm8WEGLVWifWLedWx/.YmDMlVC', 'Head Librarian',    'admin'),
('librarian', '$2y$10$IdZXPkUHRGuf9431xfQd5uY7vIbYaQ2jsRyZ9oF7ocSReOLBQJTO2', 'Desk Librarian',    'librarian');

-- Categories (6)
INSERT INTO categories (name, description) VALUES
('Fiction',          'Novels and short stories'),
('Classics',         'Literary works that have stood the test of time'),
('Computer Science', 'Programming, databases and software engineering'),
('History',          'World history and the story of human societies'),
('Science',          'Physics, cosmology and the natural world'),
('Biography',        'Life stories and memoirs');

-- Authors (11)
INSERT INTO authors (name, country) VALUES
('Chinua Achebe',              'Nigeria'),
('Chimamanda Ngozi Adichie',   'Nigeria'),
('Ngũgĩ wa Thiong''o',         'Kenya'),
('Jane Austen',                'United Kingdom'),
('George Orwell',              'United Kingdom'),
('Robert C. Martin',           'United States'),
('Yuval Noah Harari',          'Israel'),
('Abdulrazak Gurnah',          'Tanzania'),
('Ramez Elmasri',              'United States'),
('Nelson Mandela',             'South Africa'),
('Stephen Hawking',            'United Kingdom');

-- Books (16). available_copies is recalculated from the loans below.
INSERT INTO books (isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies) VALUES
('9780385474542', 'Things Fall Apart',                1,  2, 'Anchor Books',      1994, 4, 4),
('9780385474559', 'No Longer at Ease',                1,  1, 'Anchor Books',      1994, 3, 3),
('9781400095209', 'Half of a Yellow Sun',             2,  1, 'Anchor Books',      2007, 3, 3),
('9780307455925', 'Americanah',                       2,  1, 'Anchor Books',      2014, 3, 3),
('9780143106869', 'A Grain of Wheat',                 3,  2, 'Penguin Books',     2012, 2, 2),
('9780141439518', 'Pride and Prejudice',              4,  2, 'Penguin Classics',  2002, 5, 5),
('9780451524935', 'Nineteen Eighty-Four',             5,  1, 'Signet Classics',   1961, 4, 4),
('9780451526342', 'Animal Farm',                      5,  2, 'Signet Classics',   1996, 3, 3),
('9780132350884', 'Clean Code',                       6,  3, 'Prentice Hall',     2008, 3, 3),
('9780137081073', 'The Clean Coder',                  6,  3, 'Prentice Hall',     2011, 1, 1),
('9780133970777', 'Fundamentals of Database Systems', 9,  3, 'Pearson',           2016, 5, 5),
('9780062316110', 'Sapiens',                          7,  4, 'Harper',            2015, 4, 4),
('9780062464316', 'Homo Deus',                        7,  4, 'Harper',            2017, 2, 2),
('9780553380163', 'A Brief History of Time',          11, 5, 'Bantam',            1998, 3, 3),
('9780143023883', 'Paradise',                         8,  1, 'Penguin Books',     2004, 2, 2),
('9780316548182', 'Long Walk to Freedom',             10, 6, 'Back Bay Books',    1995, 2, 2);

-- Members (12). Two are suspended (Irene, Kevin).
INSERT INTO members (member_number, full_name, email, phone, status, date_joined) VALUES
('LM-0001', 'Amina Nakato',       'amina.nakato@example.com',       '0700 000 101', 'active',    '2025-09-01'),
('LM-0002', 'Brian Okello',       'brian.okello@example.com',       '0700 000 102', 'active',    '2025-09-03'),
('LM-0003', 'Carol Atim',         'carol.atim@example.com',         '0700 000 103', 'active',    '2025-09-10'),
('LM-0004', 'David Mugisha',      'david.mugisha@example.com',      '0700 000 104', 'active',    '2025-10-02'),
('LM-0005', 'Esther Namukasa',    'esther.namukasa@example.com',    '0700 000 105', 'active',    '2025-10-15'),
('LM-0006', 'Felix Ssemakula',    'felix.ssemakula@example.com',    '0700 000 106', 'active',    '2025-11-05'),
('LM-0007', 'Grace Akello',       'grace.akello@example.com',       '0700 000 107', 'active',    '2026-01-12'),
('LM-0008', 'Hassan Kiggundu',    'hassan.kiggundu@example.com',    '0700 000 108', 'active',    '2026-01-20'),
('LM-0009', 'Irene Achieng',      'irene.achieng@example.com',      '0700 000 109', 'suspended', '2026-02-02'),
('LM-0010', 'Joseph Tumusiime',   'joseph.tumusiime@example.com',   '0700 000 110', 'active',    '2026-02-18'),
('LM-0011', 'Kevin Ochieng',      'kevin.ochieng@example.com',      '0700 000 111', 'suspended', '2026-03-04'),
('LM-0012', 'Lydia Nabirye',      'lydia.nabirye@example.com',      '0700 000 112', 'active',    '2026-03-21');

-- Loans (16). Dates are relative to the day you import, so the overdue and
-- on-time loans stay correct whenever the file is run.
--   returned : loans 1-6, 15, 16   still out : loans 7-14
--   overdue  : loans 11, 12, 13   (due date already passed, not returned)
INSERT INTO loans (member_id, book_id, issued_by, date_issued, due_date, date_returned) VALUES
-- returned (some on time, some late)
( 1,  1, 1, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 26 DAY), DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
( 2,  3, 1, DATE_SUB(CURDATE(), INTERVAL 35 DAY), DATE_SUB(CURDATE(), INTERVAL 21 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
( 3,  6, 2, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
( 4,  9, 2, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
( 5, 12, 1, DATE_SUB(CURDATE(), INTERVAL 22 DAY), DATE_SUB(CURDATE(), INTERVAL  8 DAY), DATE_SUB(CURDATE(), INTERVAL  9 DAY)),
( 6, 11, 2, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL  4 DAY), DATE_SUB(CURDATE(), INTERVAL  5 DAY)),
-- still out, not yet due
( 7,  7, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL  2 DAY), NULL),
( 8, 10, 2, DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_ADD(CURDATE(), INTERVAL  3 DAY), NULL),
(10,  4, 1, DATE_SUB(CURDATE(), INTERVAL  5 DAY), DATE_ADD(CURDATE(), INTERVAL  9 DAY), NULL),
(12, 14, 2, DATE_SUB(CURDATE(), INTERVAL  3 DAY), DATE_ADD(CURDATE(), INTERVAL 11 DAY), NULL),
-- still out and overdue
( 1,  8, 1, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), NULL),
( 3,  2, 2, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL  6 DAY), NULL),
( 4, 13, 1, DATE_SUB(CURDATE(), INTERVAL 17 DAY), DATE_SUB(CURDATE(), INTERVAL  3 DAY), NULL),
-- one more still out, not yet due
( 3, 16, 2, DATE_SUB(CURDATE(), INTERVAL  2 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), NULL),
-- older returned loans
( 5,  1, 1, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 46 DAY), DATE_SUB(CURDATE(), INTERVAL 47 DAY)),
( 9,  9, 2, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 36 DAY), DATE_SUB(CURDATE(), INTERVAL 38 DAY));

-- Make available_copies match the loans that are still out.
UPDATE books b
   SET b.available_copies = b.total_copies - (
         SELECT COUNT(*) FROM loans l
          WHERE l.book_id = b.book_id AND l.date_returned IS NULL
       );
