-- Migrate from amount_cents INTEGER to amount REAL
-- Add a new column (amount REAL) on the expenses table and populate it from the amount_cents columns
-- Remove the old amount_cents columns

BEGIN TRANSACTION;
ALTER TABLE expenses ADD COLUMN amount REAL;
UPDATE expenses SET amount = amount_cents / 100.0;
ALTER TABLE expenses DROP COLUMN amount_cents;
COMMIT;