# Indugrafic — WooCommerce toolkit

Herramientas y snippets creados para [indugrafic.es](https://indugrafic.es/):

1. **Fix galería producto** (junio 2026) — snippet PHP + CSS que corrigió la capa blanca sobre las miniaturas de la galería de WooCommerce y aumentó su resolución.
2. **Formulario de presupuesto multi-step** (julio 2026) — sistema completo de solicitud de presupuesto para productos personalizados (sellos, troqueles, grabados). Sustituye al carrito estándar de WooCommerce con un flujo de 4 pasos:
   1. Cliente rellena formulario en la ficha de producto + adjunta diseño vectorizado
   2. Indugrafic recibe email con todos los datos + adjunto
   3. Indugrafic envía presupuesto por email al cliente
   4. Cliente acepta, paga y recibe el pedido en 48 h

---

## Estructura

| Archivo | Qué es |
|---|---|
| `snippet-presupuesto.php` | Snippet Code Snippets #7. Registra el endpoint REST `POST /wp-json/indugrafic/v1/presupuesto`. Valida datos + archivo, envía dos emails (admin + confirmación al comprador). Con honeypot, rate limit por IP y validación MIME real. |
| `snippet-shortcode.php` | Snippet Code Snippets #8. Registra shortcode `[indugrafic_presupuesto_form]` que devuelve el HTML del formulario. |
| `formulario-presupuesto.html` | HTML + CSS + JS autocontenido del formulario multi-step. Se inyecta dentro del shortcode PHP. Detecta automáticamente producto/categoría por selectores del DOM (`.wpr-product-title`, `body.product_cat-*`, `body.postid-*`). |
| `create-popup.mjs` / `create-popup-v3.mjs` | Crea el popup Elementor #2401 vía REST con el shortcode dentro (backup por si algún día se quiere usar como popup en lugar de inline). |
| `install-snippet.mjs` | Sube `snippet-presupuesto.php` a Code Snippets vía REST. |
| `install-shortcode.mjs` | Sube `snippet-shortcode.php` con el HTML del formulario incrustado. |
| `update-snippet.mjs` / `update-shortcode.mjs` | Actualizan los snippets existentes cuando se modifica el código local. |
| `create-snippet.mjs` | Snippet original del fix de galería (junio 2026). |
| `inspect-gallery.mjs` / `probe.mjs` | Scripts de diagnóstico. |

---

## Requisitos en el WordPress

- WordPress reciente + WooCommerce activo
- Plugin **Code Snippets** activo (los snippets se cargan como PHP global)
- Elementor Pro (para el widget shortcode dentro de plantillas de producto)
- App Password de un usuario admin (para el REST API)

---

## Cómo desplegar en otra web

1. Copia `.env.example` a `.env` y rellena con las credenciales de la web destino
2. `node install-snippet.mjs` — sube el endpoint REST
3. `node install-shortcode.mjs` — sube el shortcode del formulario
4. Añade el shortcode `[indugrafic_presupuesto_form]` en la plantilla Single Product (widget Shortcode de Elementor)
5. Verifica que el endpoint responde: `curl -X POST https://tu-web/wp-json/indugrafic/v1/presupuesto` debe devolver `400` con lista de errores de validación
6. Tras cualquier cambio en `snippet-presupuesto.php`, ejecuta `node update-snippet.mjs` para aplicarlo en la web

---

## Dirección de destino de los emails

En `snippet-presupuesto.php`:

```php
$DEST_EMAIL = 'Indugrafic2@gmail.com';   // recibe todos los avisos del formulario
```

Es el buzón al que llega el aviso de cada solicitud y al que responden los clientes.
Los emails salen siempre desde `no-reply@indugrafic.es`.

---

## Endpoint REST

**URL**: `POST https://indugrafic.es/wp-json/indugrafic/v1/presupuesto`

**Campos esperados** (multipart/form-data):

- `nombre` * (string)
- `email` * (string)
- `telefono` * (string)
- `empresa` (string, opcional)
- `descripcion` * (textarea)
- `cantidad` * (string)
- `privacidad` * = `1`
- `archivo` (file, PDF/AI/EPS/SVG/PNG/JPG, máx 15 MB, opcional)
- `producto_id`, `producto_nombre`, `producto_url`, `categoria` (auto-rellenados por el JS)
- `website` (honeypot, debe estar vacío)

**Respuestas**:

- `200 { ok: true, mensaje }` — enviado correctamente
- `400 { error }` — validación fallida
- `429 { error }` — rate limit por IP (30 s)
- `500 { error }` — fallo en `wp_mail`

---

## Seguridad

- Honeypot invisible (campo `website`)
- Rate limit por IP (1 envío / 30 s)
- Whitelist estricta de extensiones + validación MIME real con `finfo`
- Directorio de subida (`/wp-content/uploads/presupuestos/`) protegido con `.htaccess` (sin ejecución PHP)
- Sanitización de todos los campos con funciones nativas de WordPress
