<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Helpers\ImageUploadHelper;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Services\PurchasedBalanceRefundService;

class AdminController
{
    public function socialPhotoQueue(): void
    {
        AuthMiddleware::requireAdmin();
        $status = strtolower(trim((string)($_GET['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            Response::validationError(['status' => ['Estado de moderacion invalido.']]);
        }

        $photos = Database::query(
            'SELECT spm.id, spm.user_id, spm.photo_url, spm.status, spm.review_notes,
                    spm.created_at, spm.reviewed_at, mu.nombre, mu.email
               FROM social_photo_moderation spm
               JOIN mobile_usuarios mu ON mu.id = spm.user_id
              WHERE spm.status = :status
              ORDER BY spm.created_at ASC
              LIMIT 200',
            [':status' => $status]
        );

        Response::success(['photos' => $photos]);
    }

    public function decideSocialPhoto(int $photoId): void
    {
        $admin = AuthMiddleware::requireAdmin();
        $input = ValidationMiddleware::getAllInput();
        $decision = strtolower(trim((string)($input['decision'] ?? '')));
        $notes = trim((string)($input['notes'] ?? ''));

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::validationError(['decision' => ['Usa approved o rejected.']]);
        }
        if ($decision === 'rejected' && strlen($notes) < 10) {
            Response::validationError(['notes' => ['Explica en al menos 10 caracteres por que la foto incumple las reglas.']]);
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        $photoUrl = '';
        $userId = 0;

        try {
            $statement = $pdo->prepare('SELECT * FROM social_photo_moderation WHERE id = :id FOR UPDATE');
            $statement->execute([':id' => $photoId]);
            $photo = $statement->fetch();
            if (!$photo) {
                $pdo->rollBack();
                Response::notFound('Foto pendiente no encontrada.');
            }
            if ((string)$photo['status'] !== 'pending') {
                $pdo->rollBack();
                Response::error('La foto ya fue revisada.', 409);
            }

            $photoUrl = (string)$photo['photo_url'];
            $userId = (int)$photo['user_id'];

            $userStatement = $pdo->prepare(
                'SELECT foto_url, social_photos_json FROM mobile_usuarios WHERE id = :id FOR UPDATE'
            );
            $userStatement->execute([':id' => $userId]);
            $user = $userStatement->fetch();
            if (!$user) {
                throw new \RuntimeException('El usuario de la foto ya no existe.');
            }

            $photos = json_decode((string)($user['social_photos_json'] ?? ''), true);
            $photos = is_array($photos) ? array_values(array_filter($photos, 'is_string')) : [];
            $legacyPhoto = trim((string)($user['foto_url'] ?? ''));
            if ($legacyPhoto !== '' && !in_array($legacyPhoto, $photos, true)) {
                array_unshift($photos, $legacyPhoto);
            }

            if ($decision === 'approved') {
                if (count($photos) >= 6 && !in_array($photoUrl, $photos, true)) {
                    throw new \DomainException('El perfil ya tiene seis fotos aprobadas.');
                }
                if (!in_array($photoUrl, $photos, true)) {
                    $photos[] = $photoUrl;
                }
                $photos = array_slice(array_values(array_unique($photos)), 0, 6);

                $updateUser = $pdo->prepare(
                    'UPDATE mobile_usuarios SET foto_url = :foto_url, social_photos_json = :photos, updated_at = NOW() WHERE id = :id'
                );
                $updateUser->execute([
                    ':foto_url' => $photos[0] ?? $photoUrl,
                    ':photos' => json_encode($photos, JSON_UNESCAPED_SLASHES),
                    ':id' => $userId,
                ]);
            } else {
                $photos = array_values(array_filter(
                    $photos,
                    static fn(string $url): bool => $url !== $photoUrl
                ));
                $updateUser = $pdo->prepare(
                    'UPDATE mobile_usuarios
                        SET foto_url = :foto_url,
                            social_photos_json = :photos,
                            activo = 0,
                            is_social_active = 0,
                            current_restaurante_id = NULL,
                            mesa = NULL,
                            social_updated_at = NOW(),
                            updated_at = NOW()
                      WHERE id = :id'
                );
                $updateUser->execute([
                    ':foto_url' => $photos[0] ?? null,
                    ':photos' => !empty($photos) ? json_encode($photos, JSON_UNESCAPED_SLASHES) : null,
                    ':id' => $userId,
                ]);
            }

            $updateModeration = $pdo->prepare(
                'UPDATE social_photo_moderation
                    SET status = :status, review_notes = :notes, reviewed_by = :reviewed_by,
                        reviewed_at = NOW()
                  WHERE id = :id'
            );
            $updateModeration->execute([
                ':status' => $decision,
                ':notes' => $notes !== '' ? substr($notes, 0, 500) : null,
                ':reviewed_by' => (int)($admin->id ?? 0) ?: null,
                ':id' => $photoId,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AdminController::decideSocialPhoto ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo revisar la foto.');
        }

        if ($decision === 'rejected' && $photoUrl !== '') {
            ImageUploadHelper::deleteLocalUploadFromUrl(
                $photoUrl,
                __DIR__ . '/../../uploads/',
                'social-' . $userId . '-'
            );
        }

        Response::success([
            'id' => $photoId,
            'status' => $decision,
            'account_suspended' => $decision === 'rejected',
        ], $decision === 'rejected' ? 'Foto retirada y cuenta suspendida.' : 'Foto revisada.');
    }

    public function refundPurchasedBalance(): void
    {
        $admin = AuthMiddleware::requireAdmin();
        $input = ValidationMiddleware::getAllInput();
        $userId = (int)($input['user_id'] ?? 0);
        $amount = round((float)($input['amount_mxn'] ?? 0), 2);
        $requestKey = trim((string)($input['request_key'] ?? ''));
        $reason = trim((string)($input['reason'] ?? 'Reembolso solicitado por administracion'));

        if ($userId <= 0 || $amount <= 0 || !preg_match('/^[A-Za-z0-9_-]{12,120}$/', $requestKey)) {
            Response::validationError(['refund' => ['Usuario, monto y request_key son obligatorios.']]);
        }

        try {
            $result = (new PurchasedBalanceRefundService())->refund(
                $userId,
                $amount,
                $requestKey,
                $reason,
                (int)($admin->id ?? 0) ?: null
            );
            Response::success($result, 'Reembolso de saldo comprado iniciado.');
        } catch (\InvalidArgumentException|\DomainException $exception) {
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            error_log('AdminController::refundPurchasedBalance ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo procesar el reembolso.');
        }
    }

    // GET /admin/users
    // Devuelve la lista de usuarios registrados en la app movil.
    // Util para el selector de usuario al crear/editar promociones desde la web admin.
    // Query params opcionales:
    //   search   (string) busca por nombre o email
    //   page     (int, default 1)
    //   per_page (int, default 50, max 200)
    public function users(): void
    {
        AuthMiddleware::requireAdmin();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
        $search  = trim($_GET['search'] ?? '');

        $offset = ($page - 1) * $perPage;

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (nombre LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT id, nombre, email, created_at
                FROM mobile_usuarios
                WHERE {$where}
                ORDER BY nombre ASC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $users = Database::query($sql, $params);

        $countSql    = "SELECT COUNT(*) as total FROM mobile_usuarios WHERE {$where}";
        $countParams = array_diff_key($params, [':limit' => '', ':offset' => '']);
        $countResult = Database::queryOne($countSql, $countParams);
        $total       = (int)($countResult['total'] ?? 0);

        Response::success([
            'users' => $users,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }
}
