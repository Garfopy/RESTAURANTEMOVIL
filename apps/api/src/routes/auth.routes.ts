import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import crypto from 'crypto';
import bcrypt from 'bcryptjs';
import { query, queryOne } from '../db';
import { verifyGoogleToken } from '../services/GoogleAuthService';
import { requireAuth } from '../middleware/auth.middleware';
import { AppError } from '../middleware/error.middleware';
import type { MobileUser, Sesion } from '@amare/types';

export const authRouter = Router();

const BCRYPT_ROUNDS = parseInt(process.env.BCRYPT_ROUNDS || '12');
const TOKEN_EXPIRY_HOURS = parseInt(process.env.TOKEN_EXPIRY_HOURS || '720');

function generateToken(): { raw: string; hash: string } {
  const raw = crypto.randomBytes(40).toString('hex');
  const hash = crypto.createHash('sha256').update(raw).digest('hex');
  return { raw, hash };
}

async function createSession(
  usuarioId: number,
  deviceInfo?: string,
  platform?: string
): Promise<string> {
  const { raw, hash } = generateToken();
  const expiresAt = new Date(Date.now() + TOKEN_EXPIRY_HOURS * 3600 * 1000)
    .toISOString()
    .slice(0, 19)
    .replace('T', ' ');

  await query(
    `INSERT INTO mobile_sesiones (usuario_id, token_hash, device_info, platform, expires_at)
     VALUES (?, ?, ?, ?, ?)`,
    [usuarioId, hash, deviceInfo ?? null, platform ?? null, expiresAt]
  );

  return raw;
}

// POST /auth/google
authRouter.post('/google', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { id_token, device_info, platform } = z
      .object({
        id_token: z.string().min(10),
        device_info: z.string().optional(),
        platform: z.enum(['ios', 'android']).optional(),
      })
      .parse(req.body);

    const googleInfo = await verifyGoogleToken(id_token);

    let user = await queryOne<MobileUser & { id: number }>(
      'SELECT * FROM mobile_usuarios WHERE google_id = ? AND activo = 1',
      [googleInfo.sub]
    );

    if (!user) {
      // Buscar por email (podría ya existir con registro manual)
      user = await queryOne<MobileUser & { id: number }>(
        'SELECT * FROM mobile_usuarios WHERE email = ? AND activo = 1',
        [googleInfo.email]
      );

      if (user) {
        // Vincular cuenta existente con Google
        await query('UPDATE mobile_usuarios SET google_id = ?, foto_url = ? WHERE id = ?', [
          googleInfo.sub,
          googleInfo.picture,
          user.id,
        ]);
      } else {
        // Crear usuario nuevo
        const result = await query<{ insertId: number }>(
          `INSERT INTO mobile_usuarios (nombre, email, google_id, foto_url)
           VALUES (?, ?, ?, ?)`,
          [googleInfo.name, googleInfo.email, googleInfo.sub, googleInfo.picture]
        );
        const insertId = (result as unknown as { insertId: number }).insertId;
        user = await queryOne<MobileUser & { id: number }>(
          'SELECT * FROM mobile_usuarios WHERE id = ?',
          [insertId]
        );
      }
    }

    if (!user) throw new AppError('No se pudo crear el usuario', 500, 'USER_CREATE_FAILED');

    const token = await createSession(user.id, device_info, platform);

    const sesion: Sesion = {
      token,
      user,
      expires_at: new Date(Date.now() + TOKEN_EXPIRY_HOURS * 3600 * 1000).toISOString(),
    };

    res.json({ ok: true, data: sesion });
  } catch (err) {
    next(err);
  }
});

// POST /auth/register
authRouter.post('/register', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { nombre, email, password, telefono } = z
      .object({
        nombre: z.string().min(2).max(200),
        email: z.string().email(),
        password: z.string().min(8).max(100),
        telefono: z.string().max(20).optional(),
      })
      .parse(req.body);

    const existing = await queryOne('SELECT id FROM mobile_usuarios WHERE email = ?', [email]);
    if (existing) throw new AppError('El email ya está registrado', 409, 'EMAIL_EXISTS');

    const hash = await bcrypt.hash(password, BCRYPT_ROUNDS);
    const result = await query(
      `INSERT INTO mobile_usuarios (nombre, email, password_hash, telefono)
       VALUES (?, ?, ?, ?)`,
      [nombre, email, hash, telefono ?? null]
    );
    const insertId = (result as unknown as { insertId: number }).insertId;

    const user = await queryOne<MobileUser & { id: number }>(
      'SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at FROM mobile_usuarios WHERE id = ?',
      [insertId]
    );

    const token = await createSession(insertId);
    const sesion: Sesion = {
      token,
      user: user!,
      expires_at: new Date(Date.now() + TOKEN_EXPIRY_HOURS * 3600 * 1000).toISOString(),
    };

    res.status(201).json({ ok: true, data: sesion });
  } catch (err) {
    next(err);
  }
});

// POST /auth/email (login)
authRouter.post('/email', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { email, password, device_info, platform } = z
      .object({
        email: z.string().email(),
        password: z.string().min(1),
        device_info: z.string().optional(),
        platform: z.enum(['ios', 'android']).optional(),
      })
      .parse(req.body);

    const user = await queryOne<MobileUser & { id: number; password_hash: string | null }>(
      `SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at, password_hash
       FROM mobile_usuarios WHERE email = ?`,
      [email]
    );

    const GENERIC_ERROR = 'Email o contraseña incorrectos';

    if (!user?.activo) throw new AppError(GENERIC_ERROR, 401, 'INVALID_CREDENTIALS');
    if (!user.password_hash) throw new AppError(GENERIC_ERROR, 401, 'INVALID_CREDENTIALS');

    const valid = await bcrypt.compare(password, user.password_hash);
    if (!valid) throw new AppError(GENERIC_ERROR, 401, 'INVALID_CREDENTIALS');

    const token = await createSession(user.id, device_info, platform);
    const { password_hash: _ph, ...safeUser } = user;

    res.json({
      ok: true,
      data: {
        token,
        user: safeUser,
        expires_at: new Date(Date.now() + TOKEN_EXPIRY_HOURS * 3600 * 1000).toISOString(),
      },
    });
  } catch (err) {
    next(err);
  }
});

// POST /auth/apple (placeholder 501)
authRouter.post('/apple', (_req: Request, res: Response) => {
  res.status(501).json({ ok: false, error: 'Apple Sign-In no está habilitado aún', code: 'NOT_IMPLEMENTED' });
});

// GET /auth/me
authRouter.get('/me', requireAuth, (req: Request, res: Response) => {
  res.json({ ok: true, data: req.user });
});

// POST /auth/logout
authRouter.post('/logout', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    await query('UPDATE mobile_sesiones SET activo = 0 WHERE id = ?', [req.sessionId]);
    res.json({ ok: true });
  } catch (err) {
    next(err);
  }
});
