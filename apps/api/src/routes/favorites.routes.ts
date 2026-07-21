import { Router, Request, Response, NextFunction } from 'express';
import { query, queryOne } from '../db';
import { requireAuth } from '../middleware/auth.middleware';

export const favoritesRouter = Router();

// Todos los favoritos requieren autenticación
favoritesRouter.use(requireAuth);

// GET /favorites - Obtener lista de favoritos del usuario
favoritesRouter.get('/', async (req: Request, res: Response, next: NextFunction) => {
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

// POST /favorites/:platilloId - Alternar (Toggle) favorito
favoritesRouter.post('/:platilloId', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const platilloId = parseInt(req.params.platilloId);
    const existing = await queryOne<{ id: number }>(
      'SELECT id FROM mobile_favoritos WHERE usuario_id = ? AND platillo_id = ?',
      [req.user!.id, platilloId]
    );

    if (existing) {
      await query('DELETE FROM mobile_favoritos WHERE id = ?', [existing.id]);
      res.json({ ok: true, favorito: false });
    } else {
      await query('INSERT INTO mobile_favoritos (usuario_id, platillo_id) VALUES (?, ?)', [req.user!.id, platilloId]);
      res.json({ ok: true, favorito: true });
    }
  } catch (err) {
    next(err);
  }
});
