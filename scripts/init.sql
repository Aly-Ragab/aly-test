CREATE TABLE IF NOT EXISTS coffee_beans (
   sku TEXT PRIMARY KEY,
   name TEXT NOT NULL,
   in_stock INTEGER NOT NULL,
   description TEXT NULL,
   origin TEXT,
   roast TEXT,
   tasting_score TEXT,
   flavor_notes TEXT,
   tags TEXT,
   variants TEXT
);
