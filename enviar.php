<?php
/**
 * Recibe el formulario de contacto y lo entrega en me@miriameguianutricion.com
 * por SMTP de IONOS (smtp.ionos.es:587 STARTTLS + AUTH LOGIN).
 */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: contacto.html');
  exit;
}

$cfg = require __DIR__ . '/config.mail.php';
$wantsJson = (
  isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

function clean_line(string $value): string
{
  $value = str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $value);
  return trim($value);
}

function respond(bool $ok, string $message, int $code = 200): void
{
  global $wantsJson;
  if ($wantsJson) {
    http_response_code($ok ? $code : ($code >= 400 ? $code : 400));
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $dest = $ok ? 'contacto.html?enviado=1' : 'contacto.html?error=1';
  header('Location: ' . $dest);
  exit;
}

if (!empty($_POST['website'])) {
  respond(true, 'Consulta enviada.');
}

$motivos = ['Cita previa', 'Información', 'Paciente'];
$nombre = clean_line((string) ($_POST['nombre'] ?? ''));
$telefono = clean_line((string) ($_POST['telefono'] ?? ''));
$email = clean_line((string) ($_POST['email'] ?? ''));
$motivo = clean_line((string) ($_POST['motivo'] ?? 'Cita previa'));
$mensaje = trim(str_replace("\0", '', (string) ($_POST['mensaje'] ?? '')));
$privacidad = isset($_POST['privacidad']);

if ($nombre === '' || mb_strlen($nombre) > 120) {
  respond(false, 'Indica tu nombre.', 422);
}
if ($telefono === '' || mb_strlen($telefono) > 40) {
  respond(false, 'Indica un teléfono de contacto.', 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, 'El email no es válido.', 422);
}
if (!in_array($motivo, $motivos, true)) {
  $motivo = 'Cita previa';
}
if (!$privacidad) {
  respond(false, 'Debes aceptar el aviso legal.', 422);
}
if (mb_strlen($mensaje) > 4000) {
  respond(false, 'El mensaje es demasiado largo.', 422);
}

if (!is_string($cfg['password'] ?? null) || $cfg['password'] === '') {
  respond(false, 'El correo de la clínica aún no está configurado en el servidor.', 503);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$body = "Consulta desde miriameguianutricion.com\n\n"
  . "Nombre: {$nombre}\n"
  . "Teléfono: {$telefono}\n"
  . "Email: " . ($email !== '' ? $email : '(no indicado)') . "\n"
  . "Motivo: {$motivo}\n"
  . "IP: {$ip}\n"
  . "Fecha: " . gmdate('Y-m-d H:i:s') . " UTC\n\n"
  . ($mensaje !== '' ? $mensaje : '(sin mensaje)');

$subject = "Consulta web — {$motivo} — {$nombre}";
$reply = $email !== '' ? $email : $cfg['from'];

try {
  smtp_send($cfg, $subject, $body, $reply, $nombre);
} catch (Throwable $e) {
  respond(false, 'No se ha podido enviar el mensaje. Prueba por teléfono o WhatsApp.', 502);
}

respond(true, 'Consulta enviada. Te responderemos a la mayor brevedad.');

function smtp_read($fp): string
{
  $data = '';
  while (!feof($fp)) {
    $line = fgets($fp, 512);
    if ($line === false) {
      break;
    }
    $data .= $line;
    if (isset($line[3]) && $line[3] === ' ') {
      break;
    }
  }
  return $data;
}

function smtp_cmd($fp, string $cmd, string $expect): string
{
  fwrite($fp, $cmd . "\r\n");
  $resp = smtp_read($fp);
  if (!str_starts_with($resp, $expect)) {
    throw new RuntimeException('SMTP: ' . trim($resp));
  }
  return $resp;
}

function smtp_send(array $cfg, string $subject, string $body, string $reply, string $nombre): void
{
  $remote = 'tcp://' . $cfg['host'] . ':' . (int) $cfg['port'];
  $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
  if (!$fp) {
    throw new RuntimeException("No hay conexión SMTP ({$errno} {$errstr})");
  }
  stream_set_timeout($fp, 20);
  $greeting = smtp_read($fp);
  if (!str_starts_with($greeting, '220')) {
    fclose($fp);
    throw new RuntimeException('SMTP no disponible');
  }

  $ehlo = 'miriameguianutricion.com';
  smtp_cmd($fp, 'EHLO ' . $ehlo, '250');

  if (($cfg['encryption'] ?? 'tls') === 'tls') {
    smtp_cmd($fp, 'STARTTLS', '220');
    $crypto = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto) {
      fclose($fp);
      throw new RuntimeException('STARTTLS rechazado');
    }
    smtp_cmd($fp, 'EHLO ' . $ehlo, '250');
  }

  smtp_cmd($fp, 'AUTH LOGIN', '334');
  smtp_cmd($fp, base64_encode($cfg['username']), '334');
  smtp_cmd($fp, base64_encode($cfg['password']), '235');
  smtp_cmd($fp, 'MAIL FROM:<' . $cfg['from'] . '>', '250');
  smtp_cmd($fp, 'RCPT TO:<' . $cfg['to'] . '>', '250');
  smtp_cmd($fp, 'DATA', '354');

  $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $fromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
  $toName = '=?UTF-8?B?' . base64_encode($cfg['to_name']) . '?=';
  $replyName = '=?UTF-8?B?' . base64_encode($nombre) . '?=';
  $msgid = '<' . bin2hex(random_bytes(12)) . '@miriameguianutricion.com>';

  $headers = [
    'Date: ' . date('r'),
    'From: ' . $fromName . ' <' . $cfg['from'] . '>',
    'To: ' . $toName . ' <' . $cfg['to'] . '>',
    'Reply-To: ' . $replyName . ' <' . $reply . '>',
    'Subject: ' . $encodedSubject,
    'Message-ID: ' . $msgid,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: miriameguianutricion.com',
  ];

  $payload = implode("\r\n", $headers) . "\r\n\r\n"
    . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body))
    . "\r\n.";

  fwrite($fp, $payload . "\r\n");
  $dataResp = smtp_read($fp);
  if (!str_starts_with($dataResp, '250')) {
    fclose($fp);
    throw new RuntimeException('SMTP DATA: ' . trim($dataResp));
  }
  fwrite($fp, "QUIT\r\n");
  fclose($fp);
}
