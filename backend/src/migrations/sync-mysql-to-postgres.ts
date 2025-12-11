/**
 * Migration script to sync data from MySQL to PostgreSQL
 * This is a one-time sync to port essential data
 */

import mysql from 'mysql2/promise';
import { pool, query } from '../config/database.js';
import dotenv from 'dotenv';

dotenv.config();

const mysqlConfig = {
  host: process.env.MYSQL_HOST || 'localhost',
  port: parseInt(process.env.MYSQL_PORT || '3306'),
  user: process.env.MYSQL_USER || 'root',
  password: process.env.MYSQL_PASSWORD || '',
  database: process.env.MYSQL_DB || 'staten_academy',
};

async function syncUsers() {
  console.log('📦 Syncing users from MySQL to PostgreSQL...');
  
  const mysqlConn = await mysql.createConnection(mysqlConfig);
  
  try {
    // Get users from MySQL
    const [users] = await mysqlConn.execute(
      'SELECT id, email, name, role, plan_id, assigned_teacher_id, learning_track FROM users'
    ) as any[];
    
    console.log(`Found ${users.length} users to sync`);
    
    // For now, we'll reference MySQL user IDs directly
    // In production, you might want to create a mapping table
    // Since PostgreSQL uses INTEGER for user_id (matching MySQL), this should work
    
    console.log('✅ Users will be referenced by ID (no duplication needed)');
    return users.length;
  } finally {
    await mysqlConn.end();
  }
}

async function syncSubscriptionPlans() {
  console.log('📦 Syncing subscription plans from MySQL to PostgreSQL...');
  
  const mysqlConn = await mysql.createConnection(mysqlConfig);
  
  try {
    // Get plans from MySQL
    const [plans] = await mysqlConn.execute(
      'SELECT id, name, price, track, one_on_one_classes_per_week, group_classes_per_month FROM subscription_plans WHERE is_active = 1'
    ) as any[];
    
    console.log(`Found ${plans.length} active plans`);
    
    // Store plan metadata in PostgreSQL for reference
    // We'll create entitlements based on plan_id when students purchase
    for (const plan of plans) {
      await query(
        `INSERT INTO lesson_types (slug, name, cost_in_entitlements, applicable_to, duration_minutes)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (slug) DO NOTHING`,
        [
          `plan_${plan.id}`,
          plan.name,
          JSON.stringify({
            one_on_one_classes: plan.one_on_one_classes_per_week || 0,
            group_classes: plan.group_classes_per_month || 0,
          }),
          plan.track ? [plan.track] : ['kids', 'adults', 'coding'],
          60, // Default 60 minutes
        ]
      );
    }
    
    console.log('✅ Plans synced');
    return plans.length;
  } finally {
    await mysqlConn.end();
  }
}

async function syncExistingLessons() {
  console.log('📦 Syncing existing lessons from MySQL to PostgreSQL...');
  
  const mysqlConn = await mysql.createConnection(mysqlConfig);
  
  try {
    // Get lessons from MySQL
    const [lessons] = await mysqlConn.execute(
      `SELECT id, teacher_id, student_id, lesson_date, start_time, end_time, status 
       FROM lessons 
       WHERE status IN ('scheduled', 'completed')`
    ) as any[];
    
    console.log(`Found ${lessons.length} lessons to sync`);
    
    let synced = 0;
    for (const lesson of lessons) {
      const startUtc = new Date(`${lesson.lesson_date}T${lesson.start_time}Z`);
      const endUtc = new Date(`${lesson.lesson_date}T${lesson.end_time}Z`);
      
      // Map status
      let status = 'confirmed';
      if (lesson.status === 'completed') status = 'completed';
      if (lesson.status === 'cancelled') status = 'cancelled';
      
      await query(
        `INSERT INTO classes (id, type, title, start_at_utc, end_at_utc, status, teacher_id, student_id, meta)
         VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, $6, $7, $8)
         ON CONFLICT DO NOTHING`,
        [
          'one_on_one',
          'Lesson',
          startUtc,
          endUtc,
          status,
          lesson.teacher_id,
          lesson.student_id,
          JSON.stringify({ mysql_lesson_id: lesson.id }),
        ]
      );
      synced++;
    }
    
    console.log(`✅ Synced ${synced} lessons`);
    return synced;
  } finally {
    await mysqlConn.end();
  }
}

async function createInitialEntitlementsForStudents() {
  console.log('📦 Creating initial entitlements for students with active plans...');
  
  const mysqlConn = await mysql.createConnection(mysqlConfig);
  
  try {
    // Get students with active plans
    const [students] = await mysqlConn.execute(
      `SELECT u.id, u.plan_id, sp.one_on_one_classes_per_week, sp.group_classes_per_month
       FROM users u
       JOIN subscription_plans sp ON u.plan_id = sp.id
       WHERE u.role = 'student' AND u.plan_id IS NOT NULL AND sp.is_active = 1`
    ) as any[];
    
    console.log(`Found ${students.length} students with active plans`);
    
    for (const student of students) {
      const now = new Date();
      const periodStart = new Date(now.getFullYear(), now.getMonth(), 1);
      const periodEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      
      // Create one-on-one class entitlements
      if (student.one_on_one_classes_per_week > 0) {
        await query(
          `INSERT INTO entitlements (student_id, type, quantity_total, quantity_remaining, period_start, period_end)
           VALUES ($1, $2, $3, $4, $5, $6)
           ON CONFLICT DO NOTHING`,
          [
            student.id,
            'one_on_one_class',
            student.one_on_one_classes_per_week * 4, // Approximate monthly (4 weeks)
            student.one_on_one_classes_per_week * 4,
            periodStart,
            periodEnd,
          ]
        );
      }
      
      // Create group class entitlements
      if (student.group_classes_per_month > 0) {
        await query(
          `INSERT INTO entitlements (student_id, type, quantity_total, quantity_remaining, period_start, period_end)
           VALUES ($1, $2, $3, $4, $5, $6)
           ON CONFLICT DO NOTHING`,
          [
            student.id,
            'group_class',
            student.group_classes_per_month,
            student.group_classes_per_month,
            periodStart,
            periodEnd,
          ]
        );
      }
    }
    
    console.log('✅ Initial entitlements created');
    return students.length;
  } finally {
    await mysqlConn.end();
  }
}

export async function runSync() {
  console.log('🚀 Starting MySQL to PostgreSQL sync...\n');
  
  try {
    // Verify PostgreSQL connection
    await query('SELECT NOW()');
    console.log('✅ PostgreSQL connection verified\n');
    
    const userCount = await syncUsers();
    const planCount = await syncSubscriptionPlans();
    const lessonCount = await syncExistingLessons();
    const entitlementCount = await createInitialEntitlementsForStudents();
    
    console.log('\n✅ Sync completed successfully!');
    console.log(`📊 Summary:
      - Users: ${userCount} (referenced by ID)
      - Plans: ${planCount}
      - Lessons: ${lessonCount}
      - Entitlements created: ${entitlementCount} students
    `);
  } catch (error) {
    console.error('❌ Sync failed:', error);
    throw error;
  }
}

// Run if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  runSync()
    .then(() => process.exit(0))
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
}

