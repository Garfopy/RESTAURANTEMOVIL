<?php
/**
 * GET /branches
 * Extraído de la lógica de BranchController y Branch Model
 */

// 1. Usamos exactamente la misma consulta del modelo Branch de tu compañero
$sql = "SELECT id, nombre, slug, descripcion, lat, lng,
               imagen_banner, telefono, horarios_json,
               mesas_habilitadas, reservas_habilitadas, activo
        FROM rest_restaurantes 
        WHERE activo = 1 
        ORDER BY nombre";

// 2. Ejecutamos la consulta usando tu función actual de base de datos
$branches = db_all($sql);

// 3. Devolvemos la respuesta con la misma estructura que tenía el BranchController
// (Tu compañero lo envolvía en un objeto ['branches' => ...], así que el frontend seguro espera esto)
json_response([
    'branches' => $branches
]);