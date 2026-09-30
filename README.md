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
| `wp-api-chrome.mjs` | Llama a la API REST desde una pestaña de Chrome, para saltar el reto anti-bots del hosting (ver abajo). |

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

## Acceso a la API: reto anti-bots del hosting

Desde septiembre de 2026 el hosting responde con una pantalla "Un momento…" (cookie `wssplashchk`) a cualquier petición que no venga de un navegador real, **incluida la API REST**. Por eso los scripts `*.mjs` que llaman a la API con `fetch` desde node ya no funcionan tal cual.

Solución sin tocar el hosting: abrir Chrome con `--remote-debugging-port=9222` y un perfil aparte, y lanzar las peticiones con `wp-api-chrome.mjs`, que las hace desde dentro de la pestaña. La otra opción es desactivar la protección anti-bots en el panel del hosting o dejar pasar `/wp-json/`.

---

## Google Analytics y cookies

Desde el 30/09/2026:

- **GA4 activo:** propiedad `indugrafic.es` en la cuenta de kitpmo@gmail.com, **ID de medición `G-WMYM0DHR2F`**, flujo web 15891669221.
- **Lo inserta Complianz** (versión gratuita 7.4.5, en Asistente → Estadísticas), no un script suelto. Solo se carga cuando el visitante pulsa Aceptar. Comprobado: antes de aceptar no hay `_ga` ni envíos a Google; después sí.
- No hay Consent Mode v2, porque en Complianz es de pago.
- **La etiqueta antigua `G-WHLXFJ3T2Q`** era de la web anterior y se cargaba sin consentimiento. La metía el **módulo Analytics de Rank Math**, que se ha desactivado ("Instalar código de Analytics"). Hay copia de su configuración en la opción `pmo_backup_rankmath_ga_20260930`. **No hay que volver a activarlo**: la web tendría dos etiquetas y una sin consentimiento.
- Pendiente: el nombre o la razón social del titular en el Aviso Legal (Complianz → Asistente → Información de la web).

---

## Dirección de destino de los emails

En `snippet-presupuesto.php`:

```php
$DEST_EMAIL = 'Indugrafic2@gmail.com';   // recibe todos los avisos del formulario
```

Es el buzón al que llega el aviso de cada solicitud y al que responden los clientes.
Los emails salen siempre desde esa misma cuenta (`From: Indugrafic <Indugrafic2@gmail.com>`): el dominio no tiene buzones propios, así que no se usa ningún `no-reply@indugrafic.es`. En el aviso interno, el `Reply-To` apunta al cliente para poder responderle directamente.

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
