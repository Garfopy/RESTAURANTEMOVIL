<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';
class RecurrenteController extends BaseController {
    public function __construct() { parent::__construct(); }
    public function index(?string $p = null): void {
        http_response_code(200);
        echo '<h2 style="font-family:sans-serif;padding:40px;color:#374151">Módulo en construcción: RecurrenteController</h2>';
    }
}
