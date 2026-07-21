import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { query, queryOne, pool } from '../db';
import { requireAuth } from '../middleware/auth.middleware';
import { AppError } from '../middleware/error.middleware';
import type { Pedido, TrackingEvent, EstadoPedido } from '@amare/types';

export const ordersRouter = Router();

const TRACKING_LABELS: Record<EstadoPedido, { label: string; descripcion: string }> = {
  pendiente:      { label: 'Pedido recibido',    descripcion: 'Tu pedido fue recibido y está en cola' },
  en_preparacion: { label: 'En preparación',     descripcion: 'La cocina está preparando tu pedido' },
  listo:          { label: 'Listo',              descripcion: 'Tu pedido está listo para entrega' },
  en_camino:      { label: 'En camino',          descripcion: 'Tu pedido va en camino' },
  entregado:      { label: 'Entregado',          descripcion: '¡Pedido entregado con éxito!' },
  cancelado:      { label: 'Cancelado',          descripcion: 'El pedido fue cancelado' },
};

const ESTADO_ORDEN: EstadoPedido[] = [
  'pendiente', 'en_preparacion', 'listo', 'en_camino', 'entregado',
];

// POST /orders  — crear pedido
ordersRouter.post('/', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const payload = z
      .object({
        restaurante_id: z.number().int().positive(),
        tipo_pedido: z.enum(['delivery', 'pickup', 'eat_in']),
        items: z
          .array(
            z.object({
              platillo_id: z.number().int().positive(),
              cantidad: z.number().int().min(1),
              precio_unit: z.number().positive(),
              notas: z.string().max(300).optional(),
              modificadores: z
                .array(
                  z.object({
                    modificador_id: z.number(),
                    opcion_ids: z.array(z.number()),
                  })
                )
                .optional(),
            })
          )
          .min(1),
        direccion_id: z.number().int().positive().optional(),
        mesa_id: z.number().int().positive().optional(),
        notas: z.string().max(500).optional(),
        payment_intent_id: z.string().optional(),
      })
      .parse(req.body);

    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      const subtotal = payload.items.reduce(
        (sum, item) => sum + item.precio_unit * item.cantidad, 0
      );
      const total = subtotal; // sin cargo por envío por ahora

      // Crear registro en rest_visitas (tipo='mobile', qr_code generado con UUID)
      const [visitaResult] = await conn.execute(
        `INSERT INTO rest_visitas (restaurante_id, qr_code, tipo, estado)
         VALUES (?, CONCAT('mob-', REPLACE(UUID(), '-', ''), '-', UNIX_TIMESTAMP()), 'mobile', 'activa')`,
        [payload.restaurante_id]
      );
      const visitaId = (visitaResult as { insertId: number }).insertId;

      // Crear pedido
      const randomSuffix = Math.floor(1000 + Math.random() * 9000);
      const folio = `AM-${Date.now().toString().slice(-6)}${randomSuffix}`;
      const [pedidoResult] = await conn.execute(
        `INSERT INTO rest_pedidos
           (restaurante_id, visita_id, folio, estado, subtotal, total,
            tipo_pedido, mobile_usuario_id, mesa_id, notas,
            stripe_payment_intent_id, created_at)
         VALUES (?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?, ?, NOW())`,
        [
          payload.restaurante_id,
          visitaId,
          folio,
          subtotal,
          total,
          payload.tipo_pedido,
          req.user!.id,
          payload.mesa_id ?? null,
          payload.notas ?? null,
          payload.payment_intent_id ?? null,
        ]
      );
      const pedidoId = (pedidoResult as { insertId: number }).insertId;

      // Insertar items
      for (const item of payload.items) {
        await conn.execute(
          `INSERT INTO rest_pedido_items
             (pedido_id, platillo_id, cantidad, precio_unit, notas, estado)
           VALUES (?, ?, ?, ?, ?, 'pendiente')`,
          [
            pedidoId,
            item.platillo_id,
            item.cantidad,
            item.precio_unit,
            item.notas ?? null,
          ]
        );
      }

      await conn.commit();

      const pedido = await queryOne<Pedido>(
        `SELECT p.*, r.nombre AS restaurante_nombre
         FROM rest_pedidos p
         JOIN rest_restaurantes r ON r.id = p.restaurante_id
         WHERE p.id = ?`,
        [pedidoId]
      );

      res.status(201).json({ ok: true, data: pedido });
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  } catch (err) {
    next(err);
  }
});

// GET /orders  — historial paginado
ordersRouter.get('/', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { page = '1', limit = '20' } = req.query as { page?: string; limit?: string };
    const offset = (parseInt(page) - 1) * parseInt(limit);

    const pedidos = await query<Pedido>(
      `SELECT p.id, p.folio, p.estado, p.subtotal, p.total,
              p.tipo_pedido, p.created_at,
              r.nombre AS restaurante_nombre
       FROM rest_pedidos p
       JOIN rest_restaurantes r ON r.id = p.restaurante_id
       WHERE p.mobile_usuario_id = ?
       ORDER BY p.created_at DESC
       LIMIT ? OFFSET ?`,
      [req.user!.id, parseInt(limit), offset]
    );

    res.json({ ok: true, data: pedidos, page: parseInt(page) });
  } catch (err) {
    next(err);
  }
});

// GET /orders/:id  — detalle completo
ordersRouter.get('/:id', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const pedidoId = parseInt(req.params.id);
    const pedido = await queryOne<Pedido>(
      `SELECT p.*, r.nombre AS restaurante_nombre
       FROM rest_pedidos p
       JOIN rest_restaurantes r ON r.id = p.restaurante_id
       WHERE p.id = ? AND p.mobile_usuario_id = ?`,
      [pedidoId, req.user!.id]
    );

    if (!pedido) throw new AppError('Pedido no encontrado', 404, 'NOT_FOUND');

    const items = await query(
      `SELECT pi.id, pi.platillo_id, pl.nombre AS platillo_nombre,
              pl.imagen AS platillo_imagen,
              pi.cantidad, pi.precio_unit, pi.notas,
              pi.exclusiones, pi.extras, pi.estado,
              (pi.cantidad * pi.precio_unit) AS subtotal
       FROM rest_pedido_items pi
       JOIN rest_platillos pl ON pl.id = pi.platillo_id
       WHERE pi.pedido_id = ?`,
      [pedidoId]
    );

    res.json({ ok: true, data: { ...pedido, items } });
  } catch (err) {
    next(err);
  }
});

// GET /orders/:id/tracking
ordersRouter.get('/:id/tracking', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const pedidoId = parseInt(req.params.id);
    const pedido = await queryOne<{ id: number; estado: EstadoPedido; created_at: string }>(
      `SELECT id, estado, created_at
       FROM rest_pedidos
       WHERE id = ? AND mobile_usuario_id = ?`,
      [pedidoId, req.user!.id]
    );

    if (!pedido) throw new AppError('Pedido no encontrado', 404, 'NOT_FOUND');

    const estadoIndex = ESTADO_ORDEN.indexOf(pedido.estado);
    const tracking: TrackingEvent[] = ESTADO_ORDEN.map((estado, idx) => ({
      estado,
      label: TRACKING_LABELS[estado].label,
      descripcion: TRACKING_LABELS[estado].descripcion,
      completado: idx < estadoIndex,
      en_curso: idx === estadoIndex,
      timestamp: idx <= estadoIndex ? pedido.created_at : null,
    }));

    res.json({ ok: true, data: { estado: pedido.estado, steps: tracking } });
  } catch (err) {
    next(err);
  }
});
