<?php
/**
 * CONFIGURACIÓN DEL CORREO DEL FORMULARIO DE CONTACTO
 * =====================================================
 * Buzón de destino: me@miriameguianutricion.com (IONOS)
 *
 * IMPORTANTE – PASOS PARA QUE FUNCIONE EN IONOS:
 * 1. Entra en tu panel de IONOS → Correo → Buzones.
 * 2. Localiza el buzón me@miriameguianutricion.com
 * 3. Copia la CONTRASEÑA de ese buzón.
 * 4. Pégala abajo entre las comillas de 'password'.
 * 5. Sube este archivo (config.mail.php) junto con el resto de la web.
 *
 * Nunca subas este archivo a GitHub o sitios públicos con la contraseña puesta.
 * El archivo está protegido por .htaccess para que nadie pueda verlo desde el navegador.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.mail.php') {
  http_response_code(403);
  exit;
}

return [
  // Servidor SMTP de IONOS (recomendado)
  'host'       => 'smtp.ionos.es',
  'port'       => 587,          // STARTTLS (alternativa: 465 con 'ssl')
  'encryption' => 'tls',        // 'tls' para puerto 587 | 'ssl' para puerto 465

  // Credenciales del buzón
  'username'   => 'me@miriameguianutricion.com',
  'password'   => '',           // ← AQUÍ VA LA CONTRASEÑA DEL BUZÓN

  // Remitente y destinatario (deben ser del mismo dominio en IONOS)
  'from'       => 'me@miriameguianutricion.com',
  'from_name'  => 'Web Miriam Eguía Nutrición',
  'to'         => 'me@miriameguianutricion.com',
  'to_name'    => 'Dra. Miriam Eguía Llosa',
];
