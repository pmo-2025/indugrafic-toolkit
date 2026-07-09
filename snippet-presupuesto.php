<?php
/**
 * Indugrafic — Endpoint de solicitud de presupuesto
 * Registra POST /wp-json/indugrafic/v1/presupuesto
 * Valida datos + archivo, guarda archivo, envía dos emails.
 */

add_action('rest_api_init', function () {
    register_rest_route('indugrafic/v1', '/presupuesto', [
        'methods'  => 'POST',
        'callback' => 'indugrafic_presupuesto_handler',
        'permission_callback' => '__return_true', // público
    ]);
});

function indugrafic_presupuesto_handler(WP_REST_Request $request) {
    // ⚙️ MODO: cambiar a false cuando esté todo probado para enviar a producción
    $MODO_TEST = true;
    $DEST_EMAIL = $MODO_TEST ? 'agenciapmonline2@gmail.com' : 'info@indugrafic.es';
    $ALLOWED_MIME = [
        'application/pdf', 'application/postscript', 'application/illustrator',
        'image/svg+xml', 'image/png', 'image/jpeg', 'image/jpg'
    ];
    $ALLOWED_EXT = ['pdf','ai','eps','svg','png','jpg','jpeg'];
    $MAX_SIZE = 15 * 1024 * 1024; // 15 MB

    // Rate limit muy básico por IP (1 envío / 30s)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $trans_key = 'indugrafic_pres_' . md5($ip);
    if (get_transient($trans_key)) {
        return new WP_REST_Response(['error' => 'Espera unos segundos antes de reenviar.'], 429);
    }
    set_transient($trans_key, 1, 30);

    // Honeypot (campo oculto que debe estar vacío)
    $honeypot = $request->get_param('website') ?? '';
    if (!empty($honeypot)) {
        return new WP_REST_Response(['ok' => true], 200); // finge éxito, ignora
    }

    // Campos de texto
    $nombre       = sanitize_text_field($request->get_param('nombre') ?? '');
    $email        = sanitize_email($request->get_param('email') ?? '');
    $telefono     = sanitize_text_field($request->get_param('telefono') ?? '');
    $empresa      = sanitize_text_field($request->get_param('empresa') ?? '');
    $descripcion  = sanitize_textarea_field($request->get_param('descripcion') ?? '');
    $cantidad     = sanitize_text_field($request->get_param('cantidad') ?? '');
    $producto_id  = intval($request->get_param('producto_id') ?? 0);
    $producto_nombre = sanitize_text_field($request->get_param('producto_nombre') ?? '');
    $producto_url = esc_url_raw($request->get_param('producto_url') ?? '');
    $categoria    = sanitize_text_field($request->get_param('categoria') ?? '');
    $privacidad   = $request->get_param('privacidad') ?? '';

    // Validaciones
    $errores = [];
    if (empty($nombre)) $errores[] = 'Falta el nombre.';
    if (!is_email($email)) $errores[] = 'Email no válido.';
    if (empty($telefono)) $errores[] = 'Falta el teléfono.';
    if (empty($descripcion)) $errores[] = 'Falta la descripción del pedido.';
    if (empty($cantidad)) $errores[] = 'Falta la cantidad.';
    if ($privacidad !== '1' && $privacidad !== 'on' && $privacidad !== 'true') {
        $errores[] = 'Debes aceptar la política de privacidad.';
    }

    // Archivo (opcional pero recomendado)
    $adjunto_path = '';
    $adjunto_url = '';
    $file = $request->get_file_params()['archivo'] ?? null;
    if ($file && !empty($file['tmp_name'])) {
        // Validar tamaño
        if ($file['size'] > $MAX_SIZE) {
            $errores[] = 'El archivo supera los 15 MB.';
        }
        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT)) {
            $errores[] = 'Formato no permitido. Sube PDF, AI, EPS, SVG, PNG o JPG.';
        }
        // Validar MIME real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_mime = $finfo->file($file['tmp_name']);
        if (!in_array($real_mime, $ALLOWED_MIME) && $real_mime !== 'application/octet-stream') {
            $errores[] = 'Tipo de archivo no válido.';
        }
        if (empty($errores)) {
            // Guardar en /wp-content/uploads/presupuestos/
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'presupuestos';
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
                // Protege el directorio contra ejecución
                @file_put_contents($target_dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|sh)$\">\nDeny from all\n</FilesMatch>\n");
                @file_put_contents($target_dir . '/index.html', '');
            }
            $safe_name = date('Ymd-His') . '-' . wp_generate_password(6, false) . '.' . $ext;
            $adjunto_path = trailingslashit($target_dir) . $safe_name;
            if (!move_uploaded_file($file['tmp_name'], $adjunto_path)) {
                $errores[] = 'No se pudo guardar el archivo.';
                $adjunto_path = '';
            } else {
                $adjunto_url = trailingslashit($upload_dir['baseurl']) . 'presupuestos/' . $safe_name;
            }
        }
    }

    if (!empty($errores)) {
        return new WP_REST_Response(['error' => implode(' ', $errores)], 400);
    }

    // Construir email al admin
    $asunto_admin = sprintf('Nuevo presupuesto — %s — %s',
        $producto_nombre ?: 'Sin producto',
        $nombre
    );
    $cuerpo_admin  = "<html><body style=\"font-family:Arial,sans-serif;color:#111;\">";
    $cuerpo_admin .= "<h2 style=\"color:#c00;margin:0 0 16px;\">Nueva solicitud de presupuesto</h2>";
    $cuerpo_admin .= "<h3 style=\"margin:12px 0 4px;\">Producto solicitado</h3>";
    $cuerpo_admin .= "<p><strong>{$producto_nombre}</strong>";
    if ($categoria) $cuerpo_admin .= " · Categoría: {$categoria}";
    if ($producto_url) $cuerpo_admin .= "<br><a href=\"{$producto_url}\">{$producto_url}</a>";
    $cuerpo_admin .= "</p>";
    $cuerpo_admin .= "<h3 style=\"margin:16px 0 4px;\">Datos del cliente</h3>";
    $cuerpo_admin .= "<p>";
    $cuerpo_admin .= "<strong>Nombre:</strong> {$nombre}<br>";
    $cuerpo_admin .= "<strong>Email:</strong> <a href=\"mailto:{$email}\">{$email}</a><br>";
    $cuerpo_admin .= "<strong>Teléfono:</strong> {$telefono}<br>";
    if ($empresa) $cuerpo_admin .= "<strong>Empresa:</strong> {$empresa}<br>";
    $cuerpo_admin .= "</p>";
    $cuerpo_admin .= "<h3 style=\"margin:16px 0 4px;\">Pedido</h3>";
    $cuerpo_admin .= "<p><strong>Cantidad:</strong> {$cantidad}</p>";
    $cuerpo_admin .= "<p><strong>Descripción / medidas / acabado:</strong><br>" . nl2br(esc_html($descripcion)) . "</p>";
    if ($adjunto_url) {
        $cuerpo_admin .= "<h3 style=\"margin:16px 0 4px;\">Archivo adjunto</h3>";
        $cuerpo_admin .= "<p><a href=\"{$adjunto_url}\">{$adjunto_url}</a></p>";
    } else {
        $cuerpo_admin .= "<p><em>No se adjuntó archivo.</em></p>";
    }
    $cuerpo_admin .= "<hr><p style=\"color:#666;font-size:12px;\">Enviado desde el formulario de presupuesto de indugrafic.es · IP {$ip}</p>";
    $cuerpo_admin .= "</body></html>";

    $headers_admin = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Indugrafic Web <no-reply@indugrafic.es>',
        'Reply-To: ' . $nombre . ' <' . $email . '>',
    ];
    $adjuntos_mail = $adjunto_path ? [$adjunto_path] : [];
    $ok_admin = wp_mail($DEST_EMAIL, $asunto_admin, $cuerpo_admin, $headers_admin, $adjuntos_mail);

    // Email confirmación al comprador
    $asunto_cli = 'Hemos recibido tu solicitud · Indugrafic';
    $cuerpo_cli  = "<html><body style=\"font-family:Arial,sans-serif;color:#111;\">";
    $cuerpo_cli .= "<p>Hola {$nombre},</p>";
    $cuerpo_cli .= "<p>Hemos recibido tu solicitud de presupuesto";
    if ($producto_nombre) $cuerpo_cli .= " para <strong>{$producto_nombre}</strong>";
    $cuerpo_cli .= ".</p>";
    $cuerpo_cli .= "<p>En <strong>menos de 24 horas laborables</strong> te enviaremos un email con el presupuesto y el diseño definitivo.</p>";
    $cuerpo_cli .= "<p>Cuando lo aceptes, coordinaremos el pago y recibirás el pedido en las <strong>48 horas siguientes</strong>.</p>";
    $cuerpo_cli .= "<p style=\"margin-top:24px;\">Un saludo,<br>El equipo de Indugrafic</p>";
    $cuerpo_cli .= "<hr><p style=\"color:#666;font-size:12px;\">Si no has solicitado este presupuesto, ignora este mensaje.</p>";
    $cuerpo_cli .= "</body></html>";

    $headers_cli = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Indugrafic <' . $DEST_EMAIL . '>',
    ];
    wp_mail($email, $asunto_cli, $cuerpo_cli, $headers_cli);

    if (!$ok_admin) {
        return new WP_REST_Response(['error' => 'No se pudo enviar el email. Inténtalo de nuevo o llámanos.'], 500);
    }

    return new WP_REST_Response([
        'ok' => true,
        'mensaje' => 'Solicitud enviada. Te contestaremos en menos de 24 horas.'
    ], 200);
}
