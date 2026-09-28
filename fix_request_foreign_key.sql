-- Fix Request Table Foreign Key Constraint
-- The issue: Requests table has a foreign key on isbn that references Books.isbn
-- This prevents users from requesting NEW books that don't exist yet in the Books table
-- Solution: Drop the foreign key constraint since Requests are for books that may not exist yet

USE iit_shelf;

-- Drop the foreign key constraint on isbn
ALTER TABLE Requests DROP FOREIGN KEY fk_req_isbn;

-- Keep the index for performance but remove the constraint
-- The isbn column can now contain values that don't exist in Books table
-- This is correct behavior for a book REQUEST system

-- Verify the constraint was removed
SHOW CREATE TABLE Requests\G
