<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestConfigController extends BaseController
{
    private RestauranteModel $model;

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $db = \Database::getInstance();
            $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getSucursalColumn(): ?string
    {
        if ($this->hasColumn('rest_restaurantes', 'sucursal_id')) {
            return 'sucursal_id';
        }
        if ($this->hasColumn('rest_restaurantes', 'sucursal_carnihub_id')) {
            return 'sucursal_carnihub_id';
        }
        return null;
    }

    private function forzarCarniHubActivo(int $restauranteId): void
    {
        if (!$this->hasColumn('carnihub_api_config', 'activo')) {
            return;
        }

        try {
            $db = \Database::getInstance();
            $stmt = $db->prepare(
                "UPDATE carnihub_api_config
                 SET activo = 1
                 WHERE restaurante_id = ? AND COALESCE(activo, 0) = 0"
            );
            $stmt->execute([$restauranteId]);
        } catch (\Throwable $e) {
            // No bloquear la pantalla por un fail de hardening.
        }
    }

    private function isBloqueadoPorCarniHub(int $restauranteId, array $cfgCarniHub = []): bool
    {
        $rest = $this->model->find($restauranteId);
        if (!$rest) {
            return false;
        }

        $empresaProveedorId = (int)($rest['empresa_proveedor_id'] ?? 0);
        $colSucursal = $this->getSucursalColumn();
        $sucursalId = $colSucursal ? (int)($rest[$colSucursal] ?? 0) : 0;

        // Solo bloquear si hay vinculación estructural directa (sucursal o empresa proveedora).
        // Las credenciales de API para sincronizar productos NO bloquean la edición del restaurante.
        return $empresaProveedorId > 0 || $sucursalId > 0;
    }

    private function sincronizarDatosDesdeCarniHub(int $restauranteId): bool
    {
        $colSucursal = $this->getSucursalColumn();
        if (!$colSucursal) {
            return false;
        }

        try {
            $db = \Database::getInstance();

            $stmtRest = $db->prepare("SELECT {$colSucursal} AS sucursal_ref FROM rest_restaurantes WHERE id = ? LIMIT 1");
            $stmtRest->execute([$restauranteId]);
            $rest = $stmtRest->fetch(\PDO::FETCH_ASSOC) ?: [];
            $sucursalId = (int)($rest['sucursal_ref'] ?? 0);
            if ($sucursalId <= 0) {
                return false;
            }

            $stmtSuc = $db->prepare(
                "SELECT nombre, direccion, telefono, lat, lng
                 FROM sucursales
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmtSuc->execute([$sucursalId]);
            $sucursal = $stmtSuc->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (!$sucursal) {
                return false;
            }

            $update = [
                'nombre'    => trim((string)($sucursal['nombre'] ?? '')),
                'direccion' => trim((string)($sucursal['direccion'] ?? '')),
                'telefono'  => trim((string)($sucursal['telefono'] ?? '')),
                'lat'       => $sucursal['lat'] !== null && $sucursal['lat'] !== '' ? (float)$sucursal['lat'] : null,
                'lng'       => $sucursal['lng'] !== null && $sucursal['lng'] !== '' ? (float)$sucursal['lng'] : null,
            ];

            // Conserva nombre previo si la sucursal no trae uno válido.
            if ($update['nombre'] === '') {
                unset($update['nombre']);
            }

            if (empty($update)) {
                return false;
            }

            $this->model->update($restauranteId, $update);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normaliza texto de formularios para evitar mojibake (ej. QuerÃ©taro)
     * y guardar siempre UTF-8 válido.
     */
    private function normalizeUtf8Input(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        // Repara texto ya mojibakeado por doble conversión de encoding.
        if (preg_match('/Ã.|Â.|â./u', $value)) {
            $fixed = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
            if ($fixed !== false && mb_check_encoding($fixed, 'UTF-8')) {
                $value = $fixed;
            }
        }

        return $value;
    }

    private function normalizeHexColor(?string $value, string $fallback): string
    {
        $value = trim((string)$value);
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $fallback;
    }

    private function guardarColoresEnGlobalSettings(
        string $colorPrimario,
        string $colorSecundario,
        ?string $appBackgroundColor = null,
        ?string $appButtonColor = null,
        ?string $appButtonTextColor = null
    ): void
    {
        try {
            $db = \Database::getInstance();
            $hasGrupo = $this->hasColumn('global_settings', 'grupo');
            $hasTipo = $this->hasColumn('global_settings', 'tipo');
            $hasEtiqueta = $this->hasColumn('global_settings', 'etiqueta');

            $settings = [
                ['color_primary', '#C8102E', $colorPrimario, 'Color primario'],
                ['color_secondary', '#1f2937', $colorSecundario, 'Color secundario'],
            ];
            if ($appBackgroundColor !== null) {
                $settings[] = ['app_background_color', '#FFFFFF', $appBackgroundColor, 'Fondo de app'];
            }
            if ($appButtonColor !== null) {
                $settings[] = ['app_button_color', '#C8102E', $appButtonColor, 'Botones de app'];
            }
            if ($appButtonTextColor !== null) {
                $settings[] = ['app_button_text_color', '#FFFFFF', $appButtonTextColor, 'Texto de botones de app'];
            }

            $upsert = $db->prepare(
                "INSERT INTO global_settings (clave, valor)
                 VALUES (:clave, :valor)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
            );

            foreach ($settings as [$clave, $default, $valor, $etiqueta]) {
                if ($valor === '') {
                    $valor = $default;
                }
                $upsert->execute([':clave' => $clave, ':valor' => $valor]);

                $sets = [];
                $params = [':clave' => $clave];
                if ($hasGrupo) {
                    $sets[] = 'grupo = :grupo';
                    $params[':grupo'] = 'estilos';
                }
                if ($hasTipo) {
                    $sets[] = 'tipo = :tipo';
                    $params[':tipo'] = 'color';
                }
                if ($hasEtiqueta) {
                    $sets[] = 'etiqueta = :etiqueta';
                    $params[':etiqueta'] = $etiqueta;
                }

                if ($sets) {
                    $meta = $db->prepare(
                        'UPDATE global_settings SET ' . implode(', ', $sets) . ' WHERE clave = :clave'
                    );
                    $meta->execute($params);
                }
            }

            $deleteAliases = $db->prepare(
                "DELETE FROM global_settings WHERE clave IN ('color_primario', 'color_secundario')"
            );
            $deleteAliases->execute();
        } catch (\Throwable $e) {
            error_log('[RestConfig] No se pudieron guardar colores en global_settings: ' . $e->getMessage());
        }
    }

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestauranteModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $restaurante   = $this->model->find($restauranteId);
        $flash         = $this->getFlash();

        // Google Maps API key from global_settings (superadmin-configured)
        $mapsApiKey = '';
        // Payment / Stripe config from global_settings
        $cfgPagos = [];
        $cfgCarniHub = [];
        try {
            $db   = \Database::getInstance();
            $stmt = $db->prepare("SELECT valor FROM global_settings WHERE clave = 'google_maps_key' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $mapsApiKey = $row['valor'] ?? '';

            // Pagos (comensales + app móvil)
            $clavesPagos = ['stripe_public_key','stripe_secret_key','metodos_pago_habilitados',
                            'notif_email_pago','notif_email_pago_destino',
                            'tipos_entrega_habilitados','metodos_pago_app_habilitados',
                            'costo_envio_app','pedido_minimo_app',
                            'app_background_color','app_button_color','app_button_text_color',
                            'amare_api_url','amare_api_token','amare_token_expirado','amare_email'];
            foreach ($clavesPagos as $clave) {
                $s2 = $db->prepare("SELECT valor FROM global_settings WHERE clave = :c LIMIT 1");
                $s2->execute([':c' => $clave]);
                $r2 = $s2->fetch(\PDO::FETCH_ASSOC);
                $cfgPagos[$clave] = $r2['valor'] ?? '';
            }

            // CarniHub API config
            $s3 = $db->prepare("SELECT * FROM carnihub_api_config WHERE restaurante_id = :rid LIMIT 1");
            $s3->execute([':rid' => $restauranteId]);
            $cfgCarniHub = $s3->fetch(\PDO::FETCH_ASSOC) ?: [];

        } catch (\Exception $e) { /* tables may not exist */ }

        $bloqueadoPorCarniHub = $this->isBloqueadoPorCarniHub($restauranteId, $cfgCarniHub);
        if ($bloqueadoPorCarniHub) {
            $this->forzarCarniHubActivo($restauranteId);
            $this->sincronizarDatosDesdeCarniHub($restauranteId);
            $restaurante = $this->model->find($restauranteId);
        }

        $pageTitle  = 'Configuración del Restaurante';
        $activeMenu = 'rest_config';
        $this->render('restaurante/config/index',
            compact('restaurante','flash','pageTitle','activeMenu','mapsApiKey','cfgPagos','cfgCarniHub','bloqueadoPorCarniHub'));
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-config/index');
        $restauranteId = $this->restauranteId();

        $cfgCarniHub = [];
        try {
            $dbLock = \Database::getInstance();
            $stmtLock = $dbLock->prepare("SELECT * FROM carnihub_api_config WHERE restaurante_id = :rid LIMIT 1");
            $stmtLock->execute([':rid' => $restauranteId]);
            $cfgCarniHub = $stmtLock->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $cfgCarniHub = [];
        }

        if ($this->isBloqueadoPorCarniHub($restauranteId, $cfgCarniHub)) {
            $this->forzarCarniHubActivo($restauranteId);
            $this->sincronizarDatosDesdeCarniHub($restauranteId);
            $this->flash('success', 'Configuración bloqueada: este local está vinculado a CarniHub y sus datos se sincronizan automáticamente.');
            $this->redirect('rest-config/index');
            return;
        }

        $nombre      = trim((string)$this->normalizeUtf8Input($this->post('nombre', '')));
        $descripcion = $this->normalizeUtf8Input($this->post('descripcion'));
        $telefono    = $this->normalizeUtf8Input($this->post('telefono'));
        $direccion   = $this->normalizeUtf8Input($this->post('direccion'));
        $colorPrimario = $this->normalizeHexColor($this->post('color_primario'), '#C8102E');
        $colorSecundario = $this->normalizeHexColor($this->post('color_secundario'), '#1F2937');
        $appBackgroundColor = $this->normalizeHexColor($this->post('app_background_color'), '#FFFFFF');
        $appButtonColor = $this->normalizeHexColor($this->post('app_button_color'), $colorPrimario);
        $appButtonTextColor = $this->normalizeHexColor($this->post('app_button_text_color'), '#FFFFFF');

        $base = [
            'nombre'          => $nombre,
            'descripcion'     => $descripcion,
            'telefono'        => $telefono,
            'direccion'       => $direccion,
            'color_primario'  => $colorPrimario,
            'color_secundario'=> $colorSecundario,
            'horario_apertura'=> $this->post('horario_apertura') ?: null,
            'horario_cierre'  => $this->post('horario_cierre') ?: null,
        ];

        $modos = [
            'mesas_habilitadas'       => $this->post('mesas_habilitadas')       ? 1 : 0,
            'reservas_habilitadas'    => $this->post('reservas_habilitadas')    ? 1 : 0,
            'portero_habilitado'      => $this->post('portero_habilitado')      ? 1 : 0,
            'requiere_login_comensal' => $this->post('requiere_login_comensal') ? 1 : 0,
            'propinas_sugeridas'      => trim($this->post('propinas_sugeridas', '0,10,15,20')) ?: '0,10,15,20',
            'horarios_json'           => $this->post('horarios_json') ?: null,
        ];

        // Logo upload
        if (!empty($_FILES['logo']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','svg'];
            if (in_array($ext, $allowed)) {
                $filename = 'rest_logo_' . $restauranteId . '_' . time() . '.' . $ext;
                $dest     = ROOT_PATH . '/public/uploads/restaurantes/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $base['logo'] = 'public/uploads/restaurantes/' . $filename;
                }
            }
        }

        // Banner upload
        if (!empty($_FILES['imagen_banner']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['imagen_banner']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'rest_banner_' . $restauranteId . '_' . time() . '.' . $ext;
                $dest     = ROOT_PATH . '/public/uploads/restaurantes/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['imagen_banner']['tmp_name'], $dest)) {
                    $base['imagen_banner'] = 'public/uploads/restaurantes/' . $filename;
                }
            }
        }

        try {
            $this->model->update($restauranteId, $base);
            $this->guardarColoresEnGlobalSettings($colorPrimario, $colorSecundario, $appBackgroundColor, $appButtonColor, $appButtonTextColor);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (isset($base['imagen_banner']) && stripos($msg, "Unknown column 'imagen_banner'") !== false) {
                unset($base['imagen_banner']);
                $this->model->update($restauranteId, $base);
                $this->guardarColoresEnGlobalSettings($colorPrimario, $colorSecundario, $appBackgroundColor, $appButtonColor, $appButtonTextColor);
            } else {
                throw $e;
            }
        }

        // Migration-026 fields (may not exist on older installs — skip silently)
        try {
            $this->model->update($restauranteId, $modos);
        } catch (PDOException $e) {
            // Columns from migration 026 not yet applied — ignore
        }

        // ── Configuración de pagos (global_settings) ──────────────
        $clavesPagos = ['stripe_public_key','stripe_secret_key','metodos_pago_habilitados',
                        'notif_email_pago','notif_email_pago_destino'];
        try {
            $db = \Database::getInstance();
            // Métodos habilitados — construir array desde checkboxes
            $metodosPost = $this->post('metodos_pago_habilitados', []);
            if (!is_array($metodosPost)) $metodosPost = [];
            $metodosValidos = ['efectivo','tarjeta','transferencia','paypal'];
            $metodosPost = array_filter($metodosPost, fn($m) => in_array($m, $metodosValidos));
            if (empty($metodosPost)) $metodosPost = ['efectivo']; // siempre al menos efectivo

            $valoresPagos = [
                'stripe_public_key'       => trim((string)$this->post('stripe_public_key', '')),
                'stripe_secret_key'       => trim((string)$this->post('stripe_secret_key', '')),
                'metodos_pago_habilitados'=> json_encode(array_values($metodosPost)),
                'notif_email_pago'        => $this->post('notif_email_pago') ? '1' : '0',
                'notif_email_pago_destino'=> trim((string)$this->post('notif_email_pago_destino', '')),
            ];

            foreach ($valoresPagos as $clave => $valor) {
                // No sobreescribir la secret key si se dejó vacía (para no borrar la existente)
                if ($clave === 'stripe_secret_key' && $valor === '') continue;

                $upsert = $db->prepare(
                    "INSERT INTO global_settings (clave, valor, grupo) VALUES (:c, :v, 'pagos')
                     ON DUPLICATE KEY UPDATE valor = :v2"
                );
                $upsert->execute([':c' => $clave, ':v' => $valor, ':v2' => $valor]);
            }
        } catch (\Exception $e) { /* global_settings may not have pagos cols yet */ }

        // ── Config App Móvil (tipos de entrega + métodos de pago) ──
        try {
            $db = \Database::getInstance();

            // Tipos de entrega
            $tiposEntregaPost = $this->post('tipos_entrega_habilitados', []);
            if (!is_array($tiposEntregaPost)) $tiposEntregaPost = [];
            $tiposValidos = ['delivery','pickup','eat_in'];
            $tiposEntregaPost = array_values(array_filter($tiposEntregaPost, fn($t) => in_array($t, $tiposValidos)));
            if (empty($tiposEntregaPost)) $tiposEntregaPost = ['delivery','pickup'];

            $upsertTipos = $db->prepare(
                "INSERT INTO global_settings (clave, valor, grupo) VALUES ('tipos_entrega_habilitados', :v, 'pagos')
                 ON DUPLICATE KEY UPDATE valor = :v2"
            );
            $upsertTipos->execute([':v' => json_encode($tiposEntregaPost), ':v2' => json_encode($tiposEntregaPost)]);

            // Métodos de pago app móvil
            $metodosAppPost = $this->post('metodos_pago_app_habilitados', []);
            if (!is_array($metodosAppPost)) $metodosAppPost = [];
            $metodosAppValidos = ['card','cash','apple_pay','google_pay'];
            $metodosAppPost = array_values(array_filter($metodosAppPost, fn($m) => in_array($m, $metodosAppValidos)));
            if (empty($metodosAppPost)) $metodosAppPost = ['card','cash'];

            $upsertMetodosApp = $db->prepare(
                "INSERT INTO global_settings (clave, valor, grupo) VALUES ('metodos_pago_app_habilitados', :v, 'pagos')
                 ON DUPLICATE KEY UPDATE valor = :v2"
            );
            $upsertMetodosApp->execute([':v' => json_encode($metodosAppPost), ':v2' => json_encode($metodosAppPost)]);

            // Costo de envío y pedido mínimo
            $costoEnvio = trim((string)$this->post('costo_envio_app', '0'));
            $pedidoMinimo = trim((string)$this->post('pedido_minimo_app', '0'));

            $upsertCosto = $db->prepare(
                "INSERT INTO global_settings (clave, valor, grupo) VALUES ('costo_envio_app', :v, 'pagos')
                 ON DUPLICATE KEY UPDATE valor = :v2"
            );
            $upsertCosto->execute([':v' => $costoEnvio, ':v2' => $costoEnvio]);

            $upsertMinimo = $db->prepare(
                "INSERT INTO global_settings (clave, valor, grupo) VALUES ('pedido_minimo_app', :v, 'pagos')
                 ON DUPLICATE KEY UPDATE valor = :v2"
            );
            $upsertMinimo->execute([':v' => $pedidoMinimo, ':v2' => $pedidoMinimo]);

            // URL y token de Amare-App API
            $amareUrl = trim((string)$this->post('amare_api_url', ''));
            $amareToken = trim((string)$this->post('amare_api_token', ''));

            if ($amareUrl !== '') {
                $upsertUrl = $db->prepare(
                    "INSERT INTO global_settings (clave, valor, grupo) VALUES ('amare_api_url', :v, 'pagos')
                     ON DUPLICATE KEY UPDATE valor = :v2"
                );
                $upsertUrl->execute([':v' => $amareUrl, ':v2' => $amareUrl]);
            }

            if ($amareToken !== '' && !str_starts_with($amareToken, '•') && $amareToken !== '••••••••••••') {
                // No sobreescribir el token si se dejó enmascarado (bullets)
                $upsertToken = $db->prepare(
                    "INSERT INTO global_settings (clave, valor, grupo) VALUES ('amare_api_token', :v, 'pagos')
                     ON DUPLICATE KEY UPDATE valor = :v2"
                );
                $upsertToken->execute([':v' => $amareToken, ':v2' => $amareToken]);

                // Limpiar flag de expiración al guardar token nuevo
                $clearExp = $db->prepare(
                    "DELETE FROM global_settings WHERE clave = 'amare_token_expirado' AND grupo = 'pagos'"
                );
                $clearExp->execute();
            }

            // ── Sincronizar con API Amare-App ──
            // Obtener token real de BD (no el valor enmascarado del form)
            $tokenReal = $amareToken;
            if ($amareToken === '' || str_starts_with($amareToken, '•') || $amareToken === '••••••••••••') {
                $stmtTok = $db->prepare("SELECT valor FROM global_settings WHERE clave = 'amare_api_token' AND grupo = 'pagos' LIMIT 1");
                $stmtTok->execute();
                $tokenReal = $stmtTok->fetchColumn() ?: '';
            }
            if ($amareUrl !== '' && $tokenReal !== '') {
                $this->syncConAmareApp(
                    $restauranteId,
                    $amareUrl,
                    $tokenReal,
                    $tiposEntregaPost,
                    $metodosAppPost,
                    $costoEnvio,
                    $pedidoMinimo,
                    [
                        'background_color' => $appBackgroundColor,
                        'button_color' => $appButtonColor,
                        'button_text_color' => $appButtonTextColor,
                    ]
                );
            }
        } catch (\Exception $e) {
            // No bloquear si falla la sincronización con Amare
            error_log('[RestConfig] Error guardando config app móvil: ' . $e->getMessage());
        }

        // ── CarniHub API config ────────────────────────────────────
        $chMetodoPago    = $this->post('ch_metodo_pago', 'transferencia');
        $chMetodoPago    = in_array($chMetodoPago, ['stripe','paypal','transferencia'], true) ? $chMetodoPago : 'transferencia';
        $chInstrucciones = trim((string)$this->post('ch_instrucciones_transferencia', ''));
        try {
            $db = \Database::getInstance();
            $chRow = $db->prepare("SELECT id FROM carnihub_api_config WHERE restaurante_id = :rid LIMIT 1");
            $chRow->execute([':rid' => $restauranteId]);
            if ($chRow->fetchColumn()) {
                $upd = $db->prepare(
                    "UPDATE carnihub_api_config
                     SET metodo_pago = :mp, instrucciones_transferencia = :ins
                     WHERE restaurante_id = :rid"
                );
                $upd->execute([':mp' => $chMetodoPago, ':ins' => $chInstrucciones ?: null, ':rid' => $restauranteId]);
            }
        } catch (\Exception $e) { /* columns may not exist yet */ }

        $this->flash('success', 'Configuración guardada.');
        $this->redirect('rest-config/index');
    }

    /**
     * GET /rest-config/setupCardCarniHub
     * Crea un SetupIntent (usage=off_session) para guardar la tarjeta del restaurante.
     * Devuelve JSON { ok, clientSecret }.
     */
    public function setupCardCarniHub(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        try {
            require_once ROOT_PATH . '/app/models/ConfigModel.php';
            $cfg       = new ConfigModel();
            $stripeKey = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== ''
                ? STRIPE_SECRET_KEY
                : $cfg->get('stripe_secret_key', '');
            if (empty($stripeKey)) throw new \RuntimeException('Stripe no configurado. Agrega las claves en Configuración.');

            \Stripe\Stripe::setApiKey($stripeKey);

            $db  = \Database::getInstance();

            // Obtener o crear Customer Stripe para este restaurante
            $row = $db->prepare("SELECT stripe_customer_id FROM carnihub_api_config WHERE restaurante_id = ? LIMIT 1");
            $row->execute([$restauranteId]);
            $chRow      = $row->fetch(\PDO::FETCH_ASSOC) ?: [];
            $customerId = $chRow['stripe_customer_id'] ?? null;

            if (!$customerId) {
                $rRow = $db->prepare("SELECT nombre FROM rest_restaurantes WHERE id = ? LIMIT 1");
                $rRow->execute([$restauranteId]);
                $rData    = $rRow->fetch(\PDO::FETCH_ASSOC) ?: [];
                $customer = \Stripe\Customer::create([
                    'metadata' => ['restaurante_id' => (string)$restauranteId, 'nombre' => $rData['nombre'] ?? ''],
                ]);
                $customerId = $customer->id;
                $db->prepare("UPDATE carnihub_api_config SET stripe_customer_id = ? WHERE restaurante_id = ?")
                   ->execute([$customerId, $restauranteId]);
            }

            $intent = \Stripe\SetupIntent::create([
                'customer' => $customerId,
                'usage'    => 'off_session',
                'metadata' => ['restaurante_id' => (string)$restauranteId],
            ]);

            $this->json(['ok' => true, 'clientSecret' => $intent->client_secret]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /rest-config/guardarTarjetaCarniHub
     * Guarda el PaymentMethod resultante del SetupIntent en la BD.
     * Body: payment_method_id (pm_...)
     */
    public function guardarTarjetaCarniHub(?string $p = null): void
    {
        if (!$this->isPost()) { $this->json(['ok' => false, 'error' => 'POST requerido'], 405); return; }
        $restauranteId = $this->restauranteId();
        $pmId          = trim((string)$this->post('payment_method_id', ''));

        if (empty($pmId) || strpos($pmId, 'pm_') !== 0) {
            $this->json(['ok' => false, 'error' => 'ID de método de pago inválido']);
            return;
        }

        try {
            require_once ROOT_PATH . '/app/models/ConfigModel.php';
            $cfg       = new ConfigModel();
            $stripeKey = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== ''
                ? STRIPE_SECRET_KEY
                : $cfg->get('stripe_secret_key', '');
            if (empty($stripeKey)) throw new \RuntimeException('Stripe no configurado');

            \Stripe\Stripe::setApiKey($stripeKey);

            $pm    = \Stripe\PaymentMethod::retrieve($pmId);
            $last4 = $pm->card->last4 ?? null;

            $db = \Database::getInstance();
            $db->prepare(
                "UPDATE carnihub_api_config
                 SET stripe_payment_method_id = ?, stripe_card_last4 = ?
                 WHERE restaurante_id = ?"
            )->execute([$pmId, $last4, $restauranteId]);

            $this->json(['ok' => true, 'last4' => $last4]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Sincroniza la configuración de app móvil con la API de Amare-App.
     * Llama PUT /branches/{id}/config con el token Bearer.
     */
    private function syncConAmareApp(
        int $restauranteId,
        string $apiUrl,
        string $token,
        array $tiposEntrega,
        array $metodosPago,
        string $costoEnvio,
        string $pedidoMinimo,
        array $appColors = []
    ): void {
        $url = rtrim($apiUrl, '/') . '/branches/' . $restauranteId . '/config';

        $payload = json_encode([
            'tipos_entrega' => $tiposEntrega,
            'metodos_pago'  => $metodosPago,
            'costo_envio'   => (float)$costoEnvio,
            'pedido_minimo' => (float)$pedidoMinimo,
            'theme'         => [
                'background_color' => $appColors['background_color'] ?? '#FFFFFF',
                'button_color' => $appColors['button_color'] ?? '#C8102E',
                'button_text_color' => $appColors['button_text_color'] ?? '#FFFFFF',
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[syncConAmareApp] cURL error: ' . $error);
            $this->flash('error', 'Configuración guardada localmente, pero no se pudo sincronizar con la app móvil. Verifica la URL y el token.');
            return;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[syncConAmareApp] HTTP ' . $httpCode . ' — ' . $response);

            // Si es 401, el token expiró o es inválido — marcar en BD
            if ($httpCode === 401) {
                try {
                    $db = \Database::getInstance();
                    $db->prepare(
                        "INSERT INTO global_settings (clave, valor, grupo) VALUES ('amare_token_expirado', '1', 'pagos')
                         ON DUPLICATE KEY UPDATE valor = '1'"
                    )->execute();
                } catch (\Throwable $e) {
                    error_log('[syncConAmareApp] No se pudo marcar token como expirado: ' . $e->getMessage());
                }
                $this->flash('error', 'El token de conexión con la app móvil ha expirado. Vuelve a conectar en la sección "Conexión con API Amare-App".');
            } else {
                $this->flash('error', 'Configuración guardada localmente, pero la app móvil respondió con error HTTP ' . $httpCode . '.');
            }
            return;
        }

        // Sincronización exitosa — limpiar flag de expiración si existía
        try {
            $db = \Database::getInstance();
            $db->prepare(
                "DELETE FROM global_settings WHERE clave = 'amare_token_expirado' AND grupo = 'pagos'"
            )->execute();
        } catch (\Throwable $e) {
            // no crítico
        }

        $this->flash('success', 'Configuración guardada y sincronizada con la app móvil.');
    }

    public function qr(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $restaurante   = $this->model->find($restauranteId);
        $pageTitle     = 'QR del local';
        $activeMenu    = 'rest_qr';
        $this->render('restaurante/config/qr', compact('restaurante','pageTitle','activeMenu'));
    }
}
