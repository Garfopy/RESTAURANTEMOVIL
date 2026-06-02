import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import dotenv from 'dotenv';

import { errorHandler, notFound } from './middleware/error.middleware';
import { authRouter } from './routes/auth.routes';
import { branchesRouter } from './routes/branches.routes';
import { menuRouter } from './routes/menu.routes';
import { ordersRouter } from './routes/orders.routes';
import { paymentsRouter } from './routes/payments.routes';
import { profileRouter } from './routes/profile.routes';
import { promotionsRouter } from './routes/promotions.routes';

dotenv.config();

export const app = express();

// Security
app.use(helmet());
app.use(cors({
  origin: [
    'http://localhost:19006',
    'http://localhost:8081',
    'exp://localhost:8081',
    /^https:\/\/.*\.expo\.dev$/,
  ],
  credentials: true,
}));

// Parsers
app.use(express.json({ limit: '5mb' }));
app.use(express.urlencoded({ extended: true }));

// Health check
app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'amare-api', version: '1.0.0' });
});

// Routes
app.use('/auth', authRouter);
app.use('/branches', branchesRouter);
app.use('/menu', menuRouter);
app.use('/orders', ordersRouter);
app.use('/payments', paymentsRouter);
app.use('/profile', profileRouter);
app.use('/promotions', promotionsRouter);

// 404 + Error handler
app.use(notFound);
app.use(errorHandler);
