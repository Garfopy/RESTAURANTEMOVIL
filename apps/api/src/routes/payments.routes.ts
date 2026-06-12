import { Router, Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { query, queryOne } from '../db';
import { requireAuth } from '../middleware/auth.middleware';
import { AppError } from '../middleware/error.middleware';
import { createPaymentIntent, retrievePaymentIntent } from '../services/StripeService';

export const paymentsRouter = Router();

// POST /payments/intent  — crear PaymentIntent para un pedido
paymentsRouter.post('/intent', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { restaurante_id, amount, currency, pedido_ref } = z
      .object({
        restaurante_id: z.number().int().positive(),
        amount: z.number().positive(),
        currency: z.string().length(3).default('mxn'),
        pedido_ref: z.string().max(100).optional(),
      })
      .parse(req.body);

    const pi = await createPaymentIntent(amount, currency, {
      restaurante_id: String(restaurante_id),
      usuario_id: String(req.user!.id),
      pedido_ref: pedido_ref ?? '',
    });

    res.json({ ok: true, data: pi });
  } catch (err) {
    next(err);
  }
});

// POST /payments/confirm  — confirmar pago y marcar ticket como pagado
paymentsRouter.post('/confirm', requireAuth, async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { payment_intent_id, pedido_id, metodo } = z
      .object({
        payment_intent_id: z.string().min(5),
        pedido_id: z.number().int().positive(),
        metodo: z.enum(['card', 'apple_pay', 'google_pay', 'cash']),
      })
      .parse(req.body);

    // Verificar que el pedido pertenece al usuario
    const pedido = await queryOne<{ id: number; restaurante_id: number; total: number }>(
      `SELECT id, restaurante_id, total
       FROM rest_pedidos
       WHERE id = ? AND mobile_usuario_id = ?`,
      [pedido_id, req.user!.id]
    );

    if (!pedido) throw new AppError('Pedido no encontrado', 404, 'NOT_FOUND');

    // Verificar estado del PaymentIntent en Stripe
    const pi = await retrievePaymentIntent(payment_intent_id);

    if (pi.status !== 'succeeded') {
      throw new AppError(
        `El pago no está completado (estado: ${pi.status})`,
        400,
        'PAYMENT_NOT_SUCCEEDED'
      );
    }

    // Actualizar pedido con el PI confirmado
    await query(
      `UPDATE rest_pedidos
       SET stripe_payment_intent_id = ?, estado = 'en_preparacion'
       WHERE id = ?`,
      [payment_intent_id, pedido_id]
    );

    // Intentar marcar ticket como pagado si existe
    const ticket = await queryOne<{ id: number }>(
      'SELECT id FROM rest_tickets WHERE pedido_id = ?',
      [pedido_id]
    );

    if (ticket) {
      await query(
        `UPDATE rest_tickets SET pagado = 1, metodo_pago = ?, fecha_pago = NOW() WHERE id = ?`,
        [metodo, ticket.id]
      );
    }

    res.json({ ok: true, data: { pedido_id, folio: `AM-${pedido_id}`, metodo_pago: metodo } });
  } catch (err) {
    next(err);
  }
});
