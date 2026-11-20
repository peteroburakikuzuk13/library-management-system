-- =====================================================================
--  Library Management System  -  Database II, CAT 2
--  File    : database/queries.sql
--  Purpose : JOIN queries (Q1-Q5) and aggregate reports (Q6-Q9)
--  Run     : phpMyAdmin > library_db > SQL tab > paste one query at a time
-- =====================================================================

USE library_db;

-- ---------------------------------------------------------------------
-- Q1 (JOIN) Books currently on loan, with the member who has each one.
--      Joins loans -> books -> members.
-- ---------------------------------------------------------------------
SELECT b.title,
       b.isbn,
       m.member_number,
       m.full_name      AS borrowed_by,
       l.date_issued,
       l.due_date
FROM   loans   AS l
JOIN   books   AS b ON b.book_id   = l.book_id
JOIN   members AS m ON m.member_id = l.member_id
WHERE  l.date_returned IS NULL
ORDER  BY l.due_date, b.title;

-- ---------------------------------------------------------------------
-- Q2 (JOIN) Overdue loans, with days overdue and the fine owed.
--      Fine rate here is 500 per day, the same rate the web app uses.
-- ---------------------------------------------------------------------
SELECT m.full_name                         AS member,
       m.phone,
       b.title,
       l.due_date,
       DATEDIFF(CURDATE(), l.due_date)     AS days_overdue,
       DATEDIFF(CURDATE(), l.due_date) * 500 AS fine_owed
FROM   loans   AS l
JOIN   members AS m ON m.member_id = l.member_id
JOIN   books   AS b ON b.book_id   = l.book_id
WHERE  l.date_returned IS NULL
  AND  l.due_date < CURDATE()
ORDER  BY days_overdue DESC;

-- ---------------------------------------------------------------------
-- Q3 (JOIN) Full catalogue: every book with its author and category.
-- ---------------------------------------------------------------------
SELECT b.title,
       a.name        AS author,
       a.country     AS author_country,
       c.name        AS category,
       b.publisher,
       b.year_published,
       CONCAT(b.available_copies, ' of ', b.total_copies) AS copies_available
FROM   books      AS b
JOIN   authors    AS a ON a.author_id   = b.author_id
JOIN   categories AS c ON c.category_id = b.category_id
ORDER  BY c.name, b.title;

-- ---------------------------------------------------------------------
-- Q4 (JOIN) Loan history of one member (change the member number).
-- ---------------------------------------------------------------------
SELECT m.full_name,
       b.title,
       l.date_issued,
       l.due_date,
       l.date_returned,
       CASE
         WHEN l.date_returned IS NOT NULL THEN 'Returned'
         WHEN l.due_date < CURDATE()      THEN 'Overdue'
         ELSE 'On loan'
       END AS loan_status
FROM   members AS m
JOIN   loans   AS l ON l.member_id = m.member_id
JOIN   books   AS b ON b.book_id   = l.book_id
WHERE  m.member_number = 'LM-0001'
ORDER  BY l.date_issued DESC;

-- ---------------------------------------------------------------------
-- Q5 (JOIN) Which librarian issued how many loans (loans -> users).
-- ---------------------------------------------------------------------
SELECT u.full_name AS librarian,
       u.role,
       COUNT(l.loan_id) AS loans_issued
FROM   users AS u
LEFT   JOIN loans AS l ON l.issued_by = u.user_id
GROUP  BY u.user_id, u.full_name, u.role
ORDER  BY loans_issued DESC;

-- ---------------------------------------------------------------------
-- Q6 (AGGREGATE) Number of loans per category.
--      LEFT JOINs keep categories that have never been borrowed (count 0).
-- ---------------------------------------------------------------------
SELECT c.name AS category,
       COUNT(l.loan_id) AS total_loans
FROM   categories AS c
LEFT   JOIN books AS b ON b.category_id = c.category_id
LEFT   JOIN loans AS l ON l.book_id     = b.book_id
GROUP  BY c.category_id, c.name
ORDER  BY total_loans DESC, c.name;

-- ---------------------------------------------------------------------
-- Q7 (AGGREGATE) Most borrowed books (top 5), using COUNT and GROUP BY.
-- ---------------------------------------------------------------------
SELECT b.title,
       a.name AS author,
       COUNT(l.loan_id) AS times_borrowed
FROM   books   AS b
JOIN   authors AS a ON a.author_id = b.author_id
JOIN   loans   AS l ON l.book_id   = b.book_id
GROUP  BY b.book_id, b.title, a.name
ORDER  BY times_borrowed DESC, b.title
LIMIT  5;

-- ---------------------------------------------------------------------
-- Q8 (AGGREGATE) Stock summary per category using SUM.
--      How many copies exist, how many are on the shelf, how many are out.
-- ---------------------------------------------------------------------
SELECT c.name                                  AS category,
       COUNT(b.book_id)                        AS titles,
       COALESCE(SUM(b.total_copies), 0)        AS total_copies,
       COALESCE(SUM(b.available_copies), 0)    AS on_shelf,
       COALESCE(SUM(b.total_copies - b.available_copies), 0) AS out_on_loan
FROM   categories AS c
LEFT   JOIN books AS b ON b.category_id = c.category_id
GROUP  BY c.category_id, c.name
ORDER  BY total_copies DESC;

-- ---------------------------------------------------------------------
-- Q9 (AGGREGATE + HAVING) Members who currently hold 2 or more books.
-- ---------------------------------------------------------------------
SELECT m.member_number,
       m.full_name,
       COUNT(*) AS books_held
FROM   members AS m
JOIN   loans   AS l ON l.member_id = m.member_id
WHERE  l.date_returned IS NULL
GROUP  BY m.member_id, m.member_number, m.full_name
HAVING COUNT(*) >= 2
ORDER  BY books_held DESC, m.full_name;
