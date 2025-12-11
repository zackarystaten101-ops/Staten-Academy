/**
 * Migration runner - executes SQL migration files
 */

import { readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { pool, query } from '../config/database.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

async function runMigration(fileName: string) {
  const filePath = join(__dirname, '..', '..', 'migrations', fileName);
  console.log(`📄 Running migration: ${fileName}`);
  
  if (!existsSync(filePath)) {
    throw new Error(`Migration file not found: ${filePath}`);
  }
  
  const sql = readFileSync(filePath, 'utf-8');
  
  try {
    await query(sql);
    console.log(`✅ Migration ${fileName} completed successfully\n`);
  } catch (error: any) {
    if (error.code === '42P07') {
      // Table already exists - this is OK for migrations
      console.log(`⚠️  Migration ${fileName} skipped (tables already exist)\n`);
    } else if (error.code === '42P01') {
      // Relation does not exist - might need to create extension first
      console.error(`❌ Migration ${fileName} failed: Database objects missing. Make sure PostgreSQL is set up correctly.`);
      throw error;
    } else {
      console.error(`❌ Migration ${fileName} failed:`, error.message);
      console.error(`   Error code:`, error.code);
      throw error;
    }
  }
}

async function runMigrations() {
  console.log('🚀 Starting database migrations...\n');
  
  try {
    // Verify connection
    await query('SELECT NOW()');
    console.log('✅ Database connection verified\n');
    
    // Run migrations in order
    await runMigration('001_initial_schema.sql');
    
    console.log('✅ All migrations completed successfully!');
  } catch (error) {
    console.error('❌ Migration failed:', error);
    process.exit(1);
  } finally {
    await pool.end();
  }
}

runMigrations();

