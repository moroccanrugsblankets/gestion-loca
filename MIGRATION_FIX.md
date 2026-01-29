# Migration Fix - SQL Statement Splitting

## Problem
The migration runner (`run-migrations.php`) was failing when processing migration file `003_create_email_templates_table.sql` with the following error:

```
✗ Error: SQLSTATE[42000]: Syntax error or access violation: 1064 
You have an error in your SQL syntax; check the manual that corresponds to 
your MySQL server version for the right syntax to use near 'line-height: 1.6' at line 1
```

## Root Cause
The original implementation split SQL statements by semicolons using a simple `explode(';', $sql)`, which did not respect SQL string literals. 

The migration file contained INSERT statements with HTML email templates that included CSS styling (e.g., `line-height: 1.6`). When the splitter encountered semicolons within these string literals, it incorrectly split the SQL statement, creating invalid SQL fragments.

## Solution
Implemented a proper SQL statement parser (`splitSqlStatements()` function) that:

1. **Respects string literals**: Tracks when the parser is inside single or double-quoted strings
2. **Handles escape sequences**: Properly handles escaped quotes within strings
3. **Excludes SQL comments**: Skips SQL line comments (lines starting with `--`) while preserving token boundaries
4. **Only splits on statement-ending semicolons**: Only treats semicolons outside of string literals as statement delimiters

## Changes Made
Modified `/run-migrations.php`:
- Added `splitSqlStatements()` function (lines 11-77)
- Replaced simple `explode(';', $sql)` with `splitSqlStatements($sql)` (line 145)

## Testing
Created a test script that validates the splitter correctly handles the problematic migration file:
- Confirmed the migration file is split into 2 statements (CREATE TABLE and INSERT)
- Verified that CSS content like 'line-height: 1.6' remains within the INSERT statement
- Ensured no invalid SQL fragments are created

## Impact
This fix allows migrations containing complex multi-line SQL statements with embedded HTML, CSS, or other content to be executed correctly without syntax errors.
