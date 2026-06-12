import { Router, Request, Response, NextFunction } from 'express';
import { queryOne } from '../db';

export const promotionsRouter = Router();

// GET /promotions  — lee el banner desde global_settings
promotionsRouter.get('/', async (_req: Request, res: Response, next: NextFunction) => {
  try {
    const settings = await queryOne<{ valor: string }>(
      `SELECT valor FROM global_settings WHERE clave = 'mobile_promotions'`
    );

    if (!settings) {
      res.json({ ok: true, data: [] });
      return;
    }

    try {
      const promotions = JSON.parse(settings.valor);
      res.json({ ok: true, data: Array.isArray(promotions) ? promotions : [] });
    } catch {
      res.json({ ok: true, data: [] });
    }
  } catch (err) {
    next(err);
  }
});
