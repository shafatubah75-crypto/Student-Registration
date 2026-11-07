# Lab 01 — Student Registration (PHP + MySQL/MariaDB)

## Setup
1. Import `database.sql` (phpMyAdmin → SQL tab, or `mysql -u root -p < database.sql`)
2. Edit credentials in the `db()` function at the top of `index.php` if needed.
3. Put this folder in `htdocs` and open http://localhost/lab01/index.php (or run `php -S localhost:8000`)
4. Extension: `index.php?view=list` (searchable list)

## Validation rules (server-side, PHP)
| Field | Rule |
|---|---|
| Full name | Required, trimmed, 3–80 chars, letters (any language) plus space . ' - ; must start with a letter |
| Email | Required, valid format (`FILTER_VALIDATE_EMAIL`), stored lowercase, unique (pre-check + UNIQUE index) |
| Telephone | Required, exactly 9 digits, no spaces/symbols (assumed local format, e.g. 771234567) |
| Date of birth | Required, real calendar date `YYYY-MM-DD`, strictly in the past, not before 1900 |
| Programme / Level | Required, must match the server-side whitelist |
| Gender | Optional; if sent, must be Female or Male (assumption: preview showed "Option A/B") |
| Policy | Must be ticked |

## Security
- PDO prepared statements with real (non-emulated) prepares; no SQL string concatenation.
- Output escaped with `htmlspecialchars`; CSRF token in session; DB errors logged, generic message shown.
- Success shown only if `rowCount() === 1`.

## Assumptions
- Gender is optional (not listed as mandatory); options are Female/Male.
- Email uniqueness is case-insensitive (lowercased).

## Test cases (fill in Actual + attach screenshots)
| # | Input | Expected | Actual |
|---|---|---|---|
| 1 | Valid: Amina Yusuf / amina@example.com / 771234567 / 2003-04-12 / Software Engineering / Year 3 / policy ticked | Success message, 1 new row | |
| 2 | Everything blank | Required messages on all mandatory fields; no row | |
| 3 | Name `A1` | "Name must be 3–80 characters" | |
| 4 | Email `amina@` | "Enter a valid email address" | |
| 5 | Telephone `12345` or `77123456a` | "exactly 9 digits" | |
| 6 | DOB in the future (e.g. 2030-01-01) | "must be in the past" | |
| 7 | Re-submit email from test 1 | "already registered"; row count unchanged | |
| 8 | Name `Robert'); DROP TABLE students;--` | Rejected by validation; table intact | |
| 9 | Stop MySQL, submit valid data | Friendly failure message, no success | |

## Database evidence
```sql
SELECT id, full_name, email, telephone, date_of_birth, programme, level, gender, submitted_at FROM students;
SELECT email, COUNT(*) FROM students GROUP BY email HAVING COUNT(*) > 1;  -- expect 0 rows
```
Use only fake data.
