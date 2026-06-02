import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

export const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '3306'),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'carnihub',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  timezone: '+00:00',
  charset: 'utf8mb4',
});

export async function query<T = unknown>(
  sql: string,
  params?: mysql.QueryValues
): Promise<T[]> {
  const [rows] = await pool.execute(sql, params as mysql.ExecuteValues);
  return rows as T[];
}

export async function queryOne<T = unknown>(
  sql: string,
  params?: mysql.QueryValues
): Promise<T | null> {
  const rows = await query<T>(sql, params);
  return rows[0] ?? null;
}
