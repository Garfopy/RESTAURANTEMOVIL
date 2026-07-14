-- Desactiva tokens push antiguos por usuario/plataforma.
-- Conserva activo el registro mas reciente para evitar envios duplicados al reinstalar la app.

UPDATE mobile_push_tokens stale
JOIN mobile_push_tokens fresh
  ON fresh.usuario_id = stale.usuario_id
 AND COALESCE(fresh.platform, '') = COALESCE(stale.platform, '')
 AND fresh.enabled = 1
 AND stale.enabled = 1
 AND fresh.id <> stale.id
 AND (
      COALESCE(fresh.last_seen_at, fresh.updated_at, fresh.created_at) >
      COALESCE(stale.last_seen_at, stale.updated_at, stale.created_at)
      OR (
        COALESCE(fresh.last_seen_at, fresh.updated_at, fresh.created_at) =
        COALESCE(stale.last_seen_at, stale.updated_at, stale.created_at)
        AND fresh.id > stale.id
      )
 )
   SET stale.enabled = 0,
       stale.updated_at = NOW();
