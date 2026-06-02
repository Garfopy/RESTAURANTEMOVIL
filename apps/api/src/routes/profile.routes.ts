import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { query, queryOne } from '../db';
import { requireAuth } from '../middleware/auth.middleware';
import { AppError } from '../middleware/error.middleware';
import type { MobileUser, Direccion } from '@amare/types';

export const profileRouter = Router();

// Todos los endpoints requieren auth
profileRouter.use(requireAuth);

// GET /profile
profileRouter.get('/', (req: Request, res: Response) => {
  res.json({ ok: true, data: req.user });
});

// PUT /profile
profileRouter.put('/', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { nombre, telefono } = z
      .object({
        nombre: z.string().min(2).max(200).optional(),
        telefono: z.string().max(20).optional(),
      })
      .parse(req.body);

    if (!nombre && !telefono) {
      res.json({ ok: true, data: req.user });
      return;
    }

    const sets: string[] = [];
    const vals: unknown[] = [];
    if (nombre) { sets.push('nombre = ?'); vals.push(nombre); }
    if (telefono) { sets.push('telefono = ?'); vals.push(telefono); }
    vals.push(req.user!.id);

    await query(
      `UPDATE mobile_usuarios SET ${sets.join(', ')} WHERE id = ?`,
      vals
    );

    const updated = await queryOne<MobileUser>(
      'SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at FROM mobile_usuarios WHERE id = ?',
      [req.user!.id]
    );

    res.json({ ok: true, data: updated });
  } catch (err) {
    next(err);
  }
});

// GET /profile/addresses
profileRouter.get('/addresses', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const addresses = await query<Direccion>(
      `SELECT * FROM mobile_direcciones
       WHERE usuario_id = ? AND activo = 1
       ORDER BY es_principal DESC, created_at DESC`,
      [req.user!.id]
    );
    res.json({ ok: true, data: addresses });
  } catch (err) {
    next(err);
  }
});

// POST /profile/addresses
profileRouter.post('/addresses', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const data = z
      .object({
        alias:            z.string().max(80).default('Casa'),
        calle:            z.string().min(1).max(300),
        numero:           z.string().max(20).optional(),
        colonia:          z.string().max(150).optional(),
        ciudad:           z.string().max(150),
        estado_provincia: z.string().max(100).optional(),
        cp:               z.string().max(10).optional(),
        lat:              z.number().optional(),
        lng:              z.number().optional(),
        instrucciones:    z.string().max(500).optional(),
        es_principal:     z.boolean().default(false),
      })
      .parse(req.body);

    if (data.es_principal) {
      await query(
        'UPDATE mobile_direcciones SET es_principal = 0 WHERE usuario_id = ?',
        [req.user!.id]
      );
    }

    const result = await query(
      `INSERT INTO mobile_direcciones
         (usuario_id, alias, calle, numero, colonia, ciudad, estado_provincia, cp,
          lat, lng, instrucciones, es_principal)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        req.user!.id, data.alias, data.calle, data.numero ?? null,
        data.colonia ?? null, data.ciudad, data.estado_provincia ?? null,
        data.cp ?? null, data.lat ?? null, data.lng ?? null,
        data.instrucciones ?? null, data.es_principal ? 1 : 0,
      ]
    );
    const insertId = (result as unknown as { insertId: number }).insertId;
    const dir = await queryOne<Direccion>('SELECT * FROM mobile_direcciones WHERE id = ?', [insertId]);
    res.status(201).json({ ok: true, data: dir });
  } catch (err) {
    next(err);
  }
});

// DELETE /profile/addresses/:id
profileRouter.delete('/addresses/:id', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const dirId = parseInt(req.params.id);
    const dir = await queryOne<Direccion>(
      'SELECT id FROM mobile_direcciones WHERE id = ? AND usuario_id = ?',
      [dirId, req.user!.id]
    );
    if (!dir) throw new AppError('Dirección no encontrada', 404, 'NOT_FOUND');
    await query('UPDATE mobile_direcciones SET activo = 0 WHERE id = ?', [dirId]);
    res.json({ ok: true });
  } catch (err) {
    next(err);
  }
});

// GET /profile/favorites
profileRouter.get('/favorites', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const favorites = await query(
      `SELECT p.id, p.nombre, p.precio, p.imagen, p.descripcion,
              c.nombre AS categoria_nombre
       FROM mobile_favoritos f
       JOIN rest_platillos p ON p.id = f.platillo_id
       LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
       WHERE f.usuario_id = ? AND p.activo = 1
       ORDER BY f.created_at DESC`,
      [req.user!.id]
    );
    res.json({ ok: true, data: favorites });
  } catch (err) {
    next(err);
  }
});

// POST /profile/favorites/:platilloId  — toggle
profileRouter.post('/favorites/:platilloId', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const platilloId = parseInt(req.params.platilloId);
    const existing = await queryOne(
      'SELECT id FROM mobile_favoritos WHERE usuario_id = ? AND platillo_id = ?',
      [req.user!.id, platilloId]
    );

    if (existing) {
      await query(
        'DELETE FROM mobile_favoritos WHERE usuario_id = ? AND platillo_id = ?',
        [req.user!.id, platilloId]
      );
      res.json({ ok: true, favorito: false });
    } else {
      await query(
        'INSERT INTO mobile_favoritos (usuario_id, platillo_id) VALUES (?, ?)',
        [req.user!.id, platilloId]
      );
      res.json({ ok: true, favorito: true });
    }
  } catch (err) {
    next(err);
  }
});
