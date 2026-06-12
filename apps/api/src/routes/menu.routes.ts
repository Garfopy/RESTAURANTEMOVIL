import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { query, queryOne } from '../db';
import type { Categoria, Platillo, Modificador } from '@amare/types';

export const menuRouter = Router();

// GET /menu/:restauranteId/categories
menuRouter.get('/:restauranteId/categories', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const restauranteId = parseInt(req.params.restauranteId);
    const categorias = await query<Categoria>(
      `SELECT c.id, c.nombre, c.descripcion, c.imagen, c.orden, c.activo,
              COUNT(p.id) as total_platillos
       FROM rest_categorias_menu c
       LEFT JOIN rest_platillos p ON p.categoria_id = c.id
         AND p.disponible = 1 AND p.activo = 1
         AND p.restaurante_id = c.restaurante_id
       WHERE c.restaurante_id = ? AND c.activo = 1
       GROUP BY c.id
       ORDER BY c.orden, c.nombre`,
      [restauranteId]
    );
    res.json({ ok: true, data: categorias });
  } catch (err) {
    next(err);
  }
});

// GET /menu/:restauranteId/dishes?categoria_id=&q=&orden=
menuRouter.get('/:restauranteId/dishes', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const restauranteId = parseInt(req.params.restauranteId);
    const { categoria_id, q, orden } = z
      .object({
        categoria_id: z.coerce.number().optional(),
        q: z.string().max(100).optional(),
        orden: z.enum(['precio_asc', 'precio_desc', 'nombre', 'populares']).optional(),
      })
      .parse(req.query);

    const conditions: string[] = ['p.restaurante_id = ?', 'p.disponible = 1', 'p.activo = 1'];
    const params: unknown[] = [restauranteId];

    if (categoria_id) {
      conditions.push('p.categoria_id = ?');
      params.push(categoria_id);
    }

    if (q) {
      conditions.push('(p.nombre LIKE ? OR p.descripcion LIKE ?)');
      const like = `%${q}%`;
      params.push(like, like);
    }

    let orderBy = 'p.nombre ASC';
    if (orden === 'precio_asc') orderBy = 'p.precio ASC';
    else if (orden === 'precio_desc') orderBy = 'p.precio DESC';
    else if (orden === 'populares') orderBy = 'total_pedidos DESC';

    const platillos = await query<Platillo>(
      `SELECT p.id, p.restaurante_id, p.categoria_id,
              c.nombre AS categoria_nombre,
              p.nombre, p.descripcion,
              p.precio, p.imagen, p.tiempo_preparacion_min,
              p.disponible, p.activo,
              0 AS tiene_receta,
              COALESCE(SUM(pi.cantidad), 0) AS total_pedidos
       FROM rest_platillos p
       LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
       LEFT JOIN rest_pedido_items pi ON pi.platillo_id = p.id
       WHERE ${conditions.join(' AND ')}
       GROUP BY p.id
       ORDER BY ${orderBy}`,
      params
    );

    res.json({ ok: true, data: platillos });
  } catch (err) {
    next(err);
  }
});

// GET /menu/:restauranteId/featured  — top 10 más pedidos
menuRouter.get('/:restauranteId/featured', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const restauranteId = parseInt(req.params.restauranteId);
    const items = await query<Platillo>(
      `SELECT p.id, p.restaurante_id, p.categoria_id,
              c.nombre AS categoria_nombre,
              p.nombre, p.descripcion,
              p.precio, p.imagen, p.tiempo_preparacion_min,
              p.disponible, p.activo,
              0 AS tiene_receta,
              COALESCE(SUM(pi.cantidad), 0) AS total_pedidos
       FROM rest_platillos p
       LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
       LEFT JOIN rest_pedido_items pi ON pi.platillo_id = p.id
       WHERE p.restaurante_id = ? AND p.disponible = 1 AND p.activo = 1
       GROUP BY p.id
       ORDER BY total_pedidos DESC
       LIMIT 10`,
      [restauranteId]
    );
    res.json({ ok: true, data: items });
  } catch (err) {
    next(err);
  }
});

// GET /menu/:restauranteId/dishes/:id  — detalle con modificadores
menuRouter.get('/:restauranteId/dishes/:id', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const restauranteId = parseInt(req.params.restauranteId);
    const platilloId = parseInt(req.params.id);

    const platillo = await queryOne<Platillo>(
      `SELECT p.id, p.restaurante_id, p.categoria_id,
              c.nombre AS categoria_nombre,
              p.nombre, p.descripcion,
              p.precio, p.imagen, p.tiempo_preparacion_min,
              p.disponible, p.activo,
              0 AS tiene_receta
       FROM rest_platillos p
       LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
       WHERE p.id = ? AND p.restaurante_id = ? AND p.activo = 1`,
      [platilloId, restauranteId]
    );

    if (!platillo) {
      res.status(404).json({ ok: false, error: 'Platillo no encontrado', code: 'NOT_FOUND' });
      return;
    }

    // Paso 1: obtener la receta del platillo
    const receta = await queryOne<{ id: number }>(
      `SELECT id FROM rest_recetas WHERE platillo_id = ?`,
      [platilloId]
    );

    if (receta) {
      // Paso 2: ingredientes con precio_extra > 0 → extras seleccionables
      const ingredientes = await query<{
        id: number;
        ingrediente_nombre: string;
        precio_extra: number;
        codigo_display: string | null;
      }>(
        `SELECT ri.id,
                i.nombre AS ingrediente_nombre,
                COALESCE(ri.precio_extra, 0) AS precio_extra,
                ri.codigo_display
         FROM rest_receta_ingredientes ri
         JOIN rest_ingredientes i ON i.id = ri.ingrediente_id
         WHERE ri.receta_id = ? AND ri.precio_extra > 0
         ORDER BY i.nombre`,
        [receta.id]
      );

      const modificadores: Modificador[] = ingredientes.length > 0
        ? [{
            id: receta.id,
            nombre: 'Extras',
            tipo: 'checkbox',
            requerido: false,
            min_selecciones: 0,
            max_selecciones: ingredientes.length,
            opciones: ingredientes.map((ri) => ({
              id: ri.id,
              modificador_id: receta.id,
              nombre: ri.codigo_display ?? ri.ingrediente_nombre,
              precio_extra: ri.precio_extra,
              activo: true,
            })),
          }]
        : [];

      res.json({ ok: true, data: { ...platillo, tiene_receta: true, modificadores } });
    } else {
      res.json({ ok: true, data: { ...platillo, tiene_receta: false, modificadores: [] } });
    }
  } catch (err) {
    next(err);
  }
});
