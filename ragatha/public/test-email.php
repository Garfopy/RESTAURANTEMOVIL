<?php
/**
 * Script de diagnóstico para envío de emails
 * Probar directamente la función mail() de PHP
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de envío de email - CarniHub</h2>";

// ── CONFIGURACIÓN ──
$emailDestino = "test@ejemplo.com"; // CAMBIAR POR TU EMAIL REAL
$emailRemitente = "noreply@idactivos.digital";
$nombreRemitente = "CarniHub Test";

// ── TEST 1: mail() básico ──
echo "<h3>Test 1: mail() básico (sin HTML)</h3>";

$subject1 = "Test CarniHub - Email Basico";
$message1 = "Este es un email de prueba desde CarniHub.\n\nSi recibes este mensaje, la función mail() funciona correctamente.";
$headers1 = "From: $emailRemitente";

$enviado1 = @mail($emailDestino, $subject1, $message1, $headers1);

echo $enviado1
    ? "✅ mail() retornó TRUE - Verifica tu bandeja de entrada<br>"
    : "❌ mail() retornó FALSE - No se pudo enviar<br>";

if (!$enviado1) {
    $lastError = error_get_last();
    echo "Error PHP: " . ($lastError['message'] ?? 'Sin error capturado') . "<br>";
}

echo "<hr>";

// ── TEST 2: mail() con headers completos (HTML) ──
echo "<h3>Test 2: mail() con HTML y headers completos</h3>";

$subject2 = "Test CarniHub - Email HTML";

$boundary = md5(uniqid(time()));

$headers2 = [
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'From: ' . $nombreRemitente . ' <' . $emailRemitente . '>',
    'Reply-To: ' . $emailRemitente,
];

$htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9fafb; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #dc2626; }
        .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 Email HTML de prueba</h1>
        <p>Si ves este email con estilos, significa que mail() funciona correctamente con HTML.</p>
        <p><strong>Credenciales de prueba:</strong></p>
        <ul>
            <li>Email: usuario@ejemplo.com</li>
            <li>Password: Test1234!@</li>
            <li>FTP Username: carnihub_test_1234</li>
        </ul>
        <a href="https://idactivos.digital/carnihub/auth/login" class="button">Iniciar sesión</a>
        <hr style="margin-top: 30px; border: none; border-top: 1px solid #e5e7eb;">
        <p style="font-size: 12px; color: #6b7280;">Este es un email de prueba de CarniHub</p>
    </div>
</body>
</html>
';

$textBody = "Email de prueba desde CarniHub\n\nSi recibes este mensaje, mail() funciona con HTML.";

$message2 = "--$boundary\r\n";
$message2 .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message2 .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message2 .= $textBody . "\r\n";
$message2 .= "--$boundary\r\n";
$message2 .= "Content-Type: text/html; charset=UTF-8\r\n";
$message2 .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message2 .= $htmlBody . "\r\n";
$message2 .= "--$boundary--";

$enviado2 = @mail($emailDestino, $subject2, $message2, implode("\r\n", $headers2));

echo $enviado2
    ? "✅ mail() HTML retornó TRUE - Verifica tu bandeja de entrada<br>"
    : "❌ mail() HTML retornó FALSE - No se pudo enviar<br>";

if (!$enviado2) {
    $lastError = error_get_last();
    echo "Error PHP: " . ($lastError['message'] ?? 'Sin error capturado') . "<br>";
}

echo "<hr>";

// ── INFO DEL SERVIDOR ──
echo "<h3>Información del servidor</h3>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>sendmail_path:</strong> " . ini_get('sendmail_path') . "<br>";
echo "<strong>SMTP (Windows):</strong> " . ini_get('SMTP') . "<br>";
echo "<strong>smtp_port (Windows):</strong> " . ini_get('smtp_port') . "<br>";
echo "<strong>mail.add_x_header:</strong> " . (ini_get('mail.add_x_header') ? 'ON' : 'OFF') . "<br>";
echo "<strong>mail.log:</strong> " . (ini_get('mail.log') ?: 'No configurado') . "<br>";

echo "<hr>";
echo "<p><strong>INSTRUCCIONES:</strong></p>";
echo "<ol>";
echo "<li>Cambia <code>\$emailDestino</code> en la línea 13 por tu email real</li>";
echo "<li>Recarga esta página</li>";
echo "<li>Verifica tu bandeja de entrada (y spam)</li>";
echo "<li>Si no llega nada, probablemente necesites PHPMailer con SMTP real</li>";
echo "</ol>";

echo "<hr>";
echo "<p style='color:#dc2626;font-weight:bold'>⚠️ IMPORTANTE: Elimina este archivo después de hacer las pruebas (seguridad)</p>";
?>
