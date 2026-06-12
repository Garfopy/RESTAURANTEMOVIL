import { app } from './app';
import { pool } from './db';

const PORT = parseInt(process.env.PORT || '3001');

async function start(): Promise<void> {
  // Verificar conexión a la DB
  try {
    const conn = await pool.getConnection();
    conn.release();
    console.log('✅ DB conectada correctamente');
  } catch (err) {
    console.error('❌ No se pudo conectar a la DB:', err);
    process.exit(1);
  }

  app.listen(PORT, () => {
    console.log(`🚀 Amare API escuchando en http://localhost:${PORT}`);
  });
}

start();
