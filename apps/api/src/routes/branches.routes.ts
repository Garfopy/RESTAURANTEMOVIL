import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { query, queryOne } from '../db';
import { sortByDistance } from '../services/LocationService';
import type { Sucursal } from '@amare/types';

export const branchesRouter = Router();

// GET /branches  — todas las sucursales activas
branchesRouter.get('/', async (_req: Request, res: Response, next: NextFunction) => {
  try {
    const branches = await query<Sucursal>(
      `SELECT id, nombre, slug, descripcion, lat, lng,
              imagen_banner, telefono, horarios_json,
              mesas_habilitadas, reservas_habilitadas, activo
       FROM rest_restaurantes
       WHERE activo = 1
       ORDER BY nombre`
    );
    res.json({ ok: true, data: branches });
  } catch (err) {
    next(err);
  }
});

// GET /branches/nearest?lat=&lng=  — ordenadas por distancia Haversine
branchesRouter.get('/nearest', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { lat, lng } = z
      .object({
        lat: z.coerce.number().min(-90).max(90),
        lng: z.coerce.number().min(-180).max(180),
      })
      .parse(req.query);

    const all = await query<Sucursal>(
      `SELECT id, nombre, slug, descripcion, lat, lng,
              imagen_banner, telefono, horarios_json,
              mesas_habilitadas, reservas_habilitadas, activo
       FROM rest_restaurantes WHERE activo = 1`
    );

    const sorted = sortByDistance(all as (Sucursal & { lat: number | null; lng: number | null })[], lat, lng);
    res.json({ ok: true, data: sorted });
  } catch (err) {
    next(err);
  }
});

// GET /branches/:id
branchesRouter.get('/:id', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const id = parseInt(req.params.id);
    if (isNaN(id)) {
      res.status(400).json({ ok: false, error: 'ID inválido', code: 'INVALID_ID' });
      return;
    }

    const branch = await queryOne<Sucursal>(
      `SELECT id, nombre, slug, descripcion, lat, lng,
              imagen_banner, telefono, horarios_json,
              mesas_habilitadas, reservas_habilitadas, activo
       FROM rest_restaurantes WHERE id = ? AND activo = 1`,
      [id]
    );

    if (!branch) {
      res.status(404).json({ ok: false, error: 'Sucursal no encontrada', code: 'NOT_FOUND' });
      return;
    }

    res.json({ ok: true, data: branch });
  } catch (err) {
    next(err);
  }
});
