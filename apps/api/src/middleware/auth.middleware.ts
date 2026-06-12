import { Request, Response, NextFunction } from 'express';
import crypto from 'crypto';
import { queryOne } from '../db';
import type { MobileUser } from '@amare/types';

interface MobileSesion {
  id: number;
  usuario_id: number;
  expires_at: string;
}

interface MobileUserRow extends MobileUser {
  password_hash?: string;
}

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace Express {
    interface Request {
      user?: MobileUser;
      sessionId?: number;
    }
  }
}

export async function requireAuth(
  req: Request,
  res: Response,
  next: NextFunction
): Promise<void> {
  const authHeader = req.headers.authorization;
  if (!authHeader?.startsWith('Bearer ')) {
    res.status(401).json({ ok: false, error: 'Token requerido', code: 'UNAUTHORIZED' });
    return;
  }

  const rawToken = authHeader.slice(7);
  const tokenHash = crypto.createHash('sha256').update(rawToken).digest('hex');

  const sesion = await queryOne<MobileSesion>(
    `SELECT id, usuario_id, expires_at
     FROM mobile_sesiones
     WHERE token_hash = ? AND activo = 1 AND expires_at > NOW()`,
    [tokenHash]
  );

  if (!sesion) {
    res.status(401).json({ ok: false, error: 'Token inválido o expirado', code: 'TOKEN_EXPIRED' });
    return;
  }

  const user = await queryOne<MobileUserRow>(
    `SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at
     FROM mobile_usuarios
     WHERE id = ? AND activo = 1`,
    [sesion.usuario_id]
  );

  if (!user) {
    res.status(401).json({ ok: false, error: 'Usuario no encontrado', code: 'USER_NOT_FOUND' });
    return;
  }

  // Actualizar ultimo_uso
  await pool_update_last_use(sesion.id);

  req.user = user;
  req.sessionId = sesion.id;
  next();
}

async function pool_update_last_use(sesionId: number): Promise<void> {
  try {
    const { pool } = await import('../db');
    await pool.execute(
      'UPDATE mobile_sesiones SET ultimo_uso = NOW() WHERE id = ?',
      [sesionId]
    );
  } catch {
    // No bloquear la petición si falla el update
  }
}
