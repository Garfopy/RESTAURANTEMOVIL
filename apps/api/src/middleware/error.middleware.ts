import { Request, Response, NextFunction } from 'express';
import { ZodError } from 'zod';

export function errorHandler(
  err: unknown,
  _req: Request,
  res: Response,
  _next: NextFunction
): void {
  if (err instanceof ZodError) {
    res.status(400).json({
      ok: false,
      error: 'Datos inválidos',
      code: 'VALIDATION_ERROR',
      details: err.errors.map((e) => ({ field: e.path.join('.'), message: e.message })),
    });
    return;
  }

  if (err instanceof AppError) {
    res.status(err.statusCode).json({
      ok: false,
      error: err.message,
      code: err.code,
    });
    return;
  }

  console.error('[ERROR]', err);
  res.status(500).json({
    ok: false,
    error: 'Error interno del servidor',
    code: 'INTERNAL_ERROR',
  });
}

export class AppError extends Error {
  constructor(
    message: string,
    public statusCode: number = 400,
    public code: string = 'BAD_REQUEST'
  ) {
    super(message);
    this.name = 'AppError';
  }
}

export function notFound(_req: Request, res: Response): void {
  res.status(404).json({ ok: false, error: 'Ruta no encontrada', code: 'NOT_FOUND' });
}
