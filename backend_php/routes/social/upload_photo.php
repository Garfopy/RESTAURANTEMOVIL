<?php
/**
 * POST /users/social-profile/photo
 * multipart/form-data con campo "file"
 */
$allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$maxSize = 5 * 1024 * 1024;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('No se recibi¨® el archivo.', 422);
}

$file = $_FILES['file'];
$origName = $file['name'] ?? 'photo.jpg';
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    throw new RuntimeException('Formato no soportado. Usa: jpg, jpeg, png, webp', 422);
}
if ($file['size'] > $maxSize) {
    throw new RuntimeException('La imagen no debe exceder 5 MB.', 422);
}

// Ruta real: public_html/api_asocial/backend_php/photos/
// Desde routes/social/upload_photo.php subimos 2 niveles hasta backend_php
$uploadDir = __DIR__ . '/../../photos';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$dest = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    throw new RuntimeException('No se pudo guardar la imagen.', 500);
}
chmod($dest, 0644);

$publicUrl = '/api_asocial/backend_php/photos/' . $filename;

$now = now_ts();
db_exec(
    "UPDATE mobile_usuarios SET foto_url = ?, updated_at = ? WHERE id = ?",
    [$publicUrl, $now, $userId]
);

$user = db_one(
    "SELECT id, nombre, foto_url, edad, sexualidad, genero, descripcion,
            intereses, que_busca, redes_sociales
       FROM mobile_usuarios WHERE id = ?",
    [$userId]
);

json_response([
    'user_id'         => (int)$user['id'],
    'nombre'          => $user['nombre'],
    'foto_url'        => $user['foto_url'],
    'edad'            => $user['edad'] !== null ? (int)$user['edad'] : null,
    'sexualidad'      => $user['sexualidad'],
    'genero'          => $user['genero'],
    'descripcion'     => $user['descripcion'],
    'intereses'       => $user['intereses'],
    'que_busca'       => $user['que_busca'] ?? null,
    'redes_sociales'  => $user['redes_sociales'],
    'has_social_profile' => has_social_profile($user),
]);