<?php
/**
 * Indugrafic — Hooks para ficha WC nativa
 * Limitado al producto TEST id 2418.
 */

const INDUGRAFIC_TEST_ID = 2418;

/* Datos de contacto (edita aquí si cambian) */
const INDUGRAFIC_TEL = '+34605679244';
const INDUGRAFIC_TEL_LABEL = '605 67 92 44';
const INDUGRAFIC_WA = '34605679244';

function indugrafic_is_test_product() {
    if (!function_exists('is_product') || !is_product()) return false;
    if (INDUGRAFIC_TEST_ID === null) return true;
    return (int) get_the_ID() === INDUGRAFIC_TEST_ID;
}

/* 0.A - Interceptar la opción wpr_product_single_conditions solo cuando estamos
 *       en el producto de test. Royal lee esta opción con get_option y decide si
 *       aplica su plantilla. Si devolvemos "[]", cree que no hay condiciones y no
 *       toca el render → WooCommerce nativo. */
add_filter('pre_option_wpr_product_single_conditions', function ($pre_value) {
    if (function_exists('is_product') && is_product() && (int) get_the_ID() === INDUGRAFIC_TEST_ID) {
        return '[]';
    }
    return $pre_value;
});

/* 0.B - Backup por si Royal cachea la opción: template_include con priority
 *       superior a la 12 de Royal (convert_to_canvas). */
add_filter('template_include', function ($template) {
    if (function_exists('is_product') && is_product() && (int) get_the_ID() === INDUGRAFIC_TEST_ID) {
        if (defined('WC_ABSPATH')) {
            $wc_tpl = WC_ABSPATH . 'templates/single-product.php';
            if (file_exists($wc_tpl)) return $wc_tpl;
        }
        if (function_exists('WC')) {
            $wc_tpl = WC()->plugin_path() . '/templates/single-product.php';
            if (file_exists($wc_tpl)) return $wc_tpl;
        }
    }
    return $template;
}, 20);

/* 0.C - También bloqueamos el filter de Elementor por si aplica plantilla. */
add_filter('elementor/theme/get_location_templates', function ($templates, $location) {
    if ($location !== 'single' && $location !== 'single-product') return $templates;
    if (!is_singular('product')) return $templates;
    if ((int) get_the_ID() !== INDUGRAFIC_TEST_ID) return $templates;
    return [];
}, 20, 2);

/* 1.PRE - Quitar precio y availability de OG/Twitter Cards (Rank Math + genérico) */
add_filter('rank_math/opengraph/twitter/label1', function ($v) { return indugrafic_is_test_product() ? false : $v; });
add_filter('rank_math/opengraph/twitter/data1',  function ($v) { return indugrafic_is_test_product() ? false : $v; });
add_filter('rank_math/opengraph/twitter/label2', function ($v) { return indugrafic_is_test_product() ? false : $v; });
add_filter('rank_math/opengraph/twitter/data2',  function ($v) { return indugrafic_is_test_product() ? false : $v; });
add_filter('rank_math/snippet/rich_snippet_product_entity', function ($entity) {
    if (indugrafic_is_test_product()) { unset($entity['offers']); }
    return $entity;
});
add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    if (indugrafic_is_test_product()) { unset($markup['offers']); }
    return $markup;
}, 10, 2);

/* 1.PRE2 - Fallback: output buffer que limpia los meta twitter:label1/data1 con "Precio" */
add_action('template_redirect', function () {
    if (!indugrafic_is_test_product()) return;
    ob_start(function ($buffer) {
        $buffer = preg_replace('#<meta\s+name="twitter:label1"[^>]*content="Precio"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+name="twitter:data1"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+name="twitter:label2"[^>]*content="Disponibilidad"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+name="twitter:data2"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+property="product:price:amount"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+property="product:price:currency"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+property="og:price:amount"[^>]*/?>\s*#i', '', $buffer);
        $buffer = preg_replace('#<meta\s+property="og:price:currency"[^>]*/?>\s*#i', '', $buffer);
        return $buffer;
    });
}, 1);

/* 1. Ocultar precio + add-to-cart + ratings */
add_action('woocommerce_before_single_product', function () {
    if (!indugrafic_is_test_product()) return;
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
}, 5);

/* Mapa nombre de color -> hex (para pintar círculos) */
function indugrafic_color_hex($name) {
    $map = [
        'azul'      => '#1e40af',
        'azul marino' => '#0a2540',
        'verde'     => '#059669',
        'verde oscuro' => '#065f46',
        'negro'     => '#111827',
        'rojo'      => '#dc2626',
        'rojo intenso' => '#991b1b',
        'blanco'    => '#f8fafc',
        'amarillo'  => '#fbbf24',
        'naranja'   => '#f97316',
        'marron'    => '#78350f',
        'marrón'    => '#78350f',
        'gris'      => '#6b7280',
        'plata'     => '#cbd5e1',
        'oro'       => '#d4af37',
        'dorado'    => '#d4af37',
        'morado'    => '#7c3aed',
        'violeta'   => '#7c3aed',
        'rosa'      => '#ec4899',
        'turquesa'  => '#14b8a6',
    ];
    $key = strtolower(trim(remove_accents($name)));
    return $map[$key] ?? '#9ca3af';
}

/* 2.A - Atributos como chips DENTRO de la columna derecha (summary) */
add_action('woocommerce_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $product = wc_get_product(get_the_ID());
    if (!$product) return;
    $attributes = $product->get_attributes();
    if (empty($attributes)) return;

    $out = '<div class="ig-atributos ig-in-summary">';
    foreach ($attributes as $attr) {
        $name = wc_attribute_label($attr->get_name());
        $values = $attr->is_taxonomy()
            ? wp_get_post_terms(get_the_ID(), $attr->get_name(), ['fields' => 'names'])
            : $attr->get_options();
        if (empty($values) || is_wp_error($values)) continue;

        $is_color = (stripos($name, 'color') !== false);
        $is_size  = (stripos($name, 'tama') !== false || stripos($name, 'medida') !== false || stripos($name, 'size') !== false);

        $out .= '<div class="ig-attr ig-attr-required' . ($is_color ? ' ig-attr-color' : '') . ($is_size ? ' ig-attr-size' : '') . '" data-ig-group="' . esc_attr($is_color ? 'color' : ($is_size ? 'tamano' : sanitize_title($name))) . '">';
        $out .= '<h4 class="ig-attr-label">' . esc_html($name) . ' disponibles <span class="ig-attr-req">*</span> <span class="ig-attr-hint">Selecciona uno para poder enviar el formulario</span></h4>';
        $out .= '<div class="ig-attr-items">';
        foreach ($values as $val) {
            $val = trim($val);
            if ($is_color) {
                $hex = indugrafic_color_hex($val);
                $out .= '<button type="button" class="ig-chip ig-chip-color" data-ig-chip="color" data-ig-value="' . esc_attr($val) . '"><span class="ig-dot" style="background:' . esc_attr($hex) . '"></span>' . esc_html($val) . '</button>';
            } elseif ($is_size) {
                $out .= '<button type="button" class="ig-chip ig-chip-size" data-ig-chip="tamano" data-ig-value="' . esc_attr($val) . '">' . esc_html($val) . '</button>';
            } else {
                $out .= '<span class="ig-chip">' . esc_html($val) . '</span>';
            }
        }
        $out .= '</div></div>';
    }
    $out .= '</div>';
    echo $out;
}, 25);

/* 2.A2 - Bloque "Cómo hacer tu pedido en 4 pasos" en la columna derecha */
add_action('woocommerce_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $out  = '<div class="ig-nativo-pasos ig-in-summary">';
    $out .= '<h3>Cómo hacer tu pedido en 4 pasos</h3>';
    $out .= '<ol>';
    $out .= '<li><strong>Rellena el formulario</strong> y adjunta tu diseño vectorizado.</li>';
    $out .= '<li><strong>Recibirás un correo</strong> con el presupuesto y el diseño definitivo en menos de 24 horas laborables.</li>';
    $out .= '<li><strong>Acepta el diseño</strong>, realiza el pago y dinos la dirección de envío.</li>';
    $out .= '<li><strong>Lo recibirás en tu domicilio</strong> en 48 horas.</li>';
    $out .= '</ol></div>';
    echo $out;
}, 30);

/* 2.A3 - Bloque CTA (Solicitar presupuesto + WhatsApp + Llamar) */
add_action('woocommerce_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $prod_name = get_the_title();
    $wa_msg = rawurlencode('Hola, me interesa ' . $prod_name . '. Quiero información antes de rellenar el formulario.');
    $out  = '<div class="ig-cta-block ig-in-summary">';
    $out .= '<button type="button" class="ig-cta-primary" id="igOpenModal" disabled data-tooltip="Selecciona color y tamaño para continuar">📝 Solicitar presupuesto</button>';
    $out .= '<div class="ig-cta-secondary">';
    $out .= '<a class="ig-cta-btn ig-cta-wa" href="https://wa.me/' . INDUGRAFIC_WA . '?text=' . $wa_msg . '" target="_blank" rel="noopener">💬 WhatsApp</a>';
    $out .= '<a class="ig-cta-btn ig-cta-tel" href="tel:' . INDUGRAFIC_TEL . '">📞 Llamar</a>';
    $out .= '</div></div>';
    echo $out;
}, 33);

/* 2.A4 - Sellos de confianza AHORA DEBAJO DE LA GALERÍA (columna izquierda)
 *        En vez de single_product_summary (columna derecha), usamos
 *        before_single_product_summary priority 25 (después de la galería que va en 20). */
add_action('woocommerce_before_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $out  = '<div class="ig-nativo-sellos ig-under-gallery">';
    $out .= '<div class="ig-sello">❓ Déjanos tu pedido</div>';
    $out .= '<div class="ig-sello">⏱ Producción 24-48 horas</div>';
    $out .= '<div class="ig-sello">🎨 Personalizado según tu diseño</div>';
    $out .= '</div>';
    echo $out;
}, 25);

/* 2.A.bis - Ocultar la pestaña "Información adicional" (ya movida arriba como bloques) */
add_filter('woocommerce_product_tabs', function ($tabs) {
    if (!indugrafic_is_test_product()) return $tabs;
    unset($tabs['additional_information']);
    return $tabs;
}, 98);

/* 2.D - Mover las pestañas (Descripción / Valoraciones) a DESPUÉS del formulario,
 *       ANTES de productos relacionados. WooCommerce las pinta en priority 10
 *       por defecto; las movemos a priority 17. */
add_action('wp', function () {
    if (!indugrafic_is_test_product()) return;
    remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
    add_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 17);
}, 20);

/* 2.F - Breadcrumbs (Inicio > Tienda > Categoría > Producto) al inicio de la ficha */
add_action('woocommerce_before_single_product', function () {
    if (!indugrafic_is_test_product()) return;
    if (function_exists('woocommerce_breadcrumb')) {
        echo '<div class="ig-breadcrumb-wrap">';
        woocommerce_breadcrumb([
            'delimiter'   => ' <span class="ig-bc-sep">›</span> ',
            'wrap_before' => '<nav class="ig-breadcrumb woocommerce-breadcrumb">',
            'wrap_after'  => '</nav>',
        ]);
        echo '</div>';
    }
}, 20);

/* 2.G - Sticky bar móvil eliminada — los CTAs ya están en la columna derecha */

/* 2.E - FAQs colapsables genéricas con Schema FAQPage (priority 13, entre chips y pasos) */
add_action('woocommerce_after_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $faqs = [
        [ '¿Cuánto tarda mi pedido?', 'Recibirás el presupuesto en menos de 24 horas laborables. Tras la aceptación y el pago, producimos y enviamos tu pedido para que lo tengas en 48 horas.' ],
        [ '¿Qué archivos necesito enviar?', 'Preferimos vectoriales (PDF, AI, EPS o SVG) para conseguir el máximo detalle. También aceptamos PNG y JPG en alta resolución. Máximo 15 MB por archivo.' ],
        [ '¿Puedo pedir un tamaño o color personalizado?', 'Sí. Además de nuestros tamaños y colores estándar, adaptamos medidas y acabados a medida. Indícalo en el campo "Descripción" del formulario.' ],
        [ '¿Cómo hago el pago?', 'Una vez aceptas el diseño y presupuesto, te enviamos un enlace de pago seguro o los datos de transferencia. Empezamos a producir en cuanto se confirma el pago.' ],
        [ '¿Realizáis envíos a toda España?', 'Sí, envíos a toda España peninsular en 48 horas laborables. Para Baleares, Canarias, Ceuta y Melilla el plazo puede variar; te lo indicamos en el presupuesto.' ],
        [ '¿Puedo pedir modificaciones al presupuesto o al diseño?', 'Por supuesto. Puedes pedir ajustes al presupuesto o al diseño hasta que lo apruebes. Solo empezamos a producir cuando das el OK final.' ],
    ];
    $out  = '<div class="ig-faqs">';
    $out .= '<h3 class="ig-faqs-title">Preguntas frecuentes</h3>';
    foreach ($faqs as $i => $qa) {
        list($q, $a) = $qa;
        $out .= '<details class="ig-faq"' . ($i === 0 ? ' open' : '') . '>';
        $out .= '<summary class="ig-faq-q">' . esc_html($q) . '</summary>';
        $out .= '<div class="ig-faq-a">' . esc_html($a) . '</div>';
        $out .= '</details>';
    }
    $out .= '</div>';
    // Schema FAQPage
    $entities = [];
    foreach ($faqs as $qa) {
        $entities[] = [
            '@type' => 'Question',
            'name'  => $qa[0],
            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $qa[1] ]
        ];
    }
    $schema = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities ];
    $out .= '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
    echo $out;
}, 18);

/* 2. Modal con el formulario + JS de control del botón "Solicitar presupuesto" */
add_action('wp_footer', function () {
    if (!indugrafic_is_test_product()) return;
    $out  = '<div class="ig-modal" id="igModal" aria-hidden="true" role="dialog" aria-modal="true">';
    $out .= '<div class="ig-modal-overlay" data-ig-modal-close></div>';
    $out .= '<div class="ig-modal-panel">';
    $out .= '<button type="button" class="ig-modal-close" data-ig-modal-close aria-label="Cerrar">✕</button>';
    $out .= '<div class="ig-modal-body">' . do_shortcode('[indugrafic_presupuesto_form]') . '</div>';
    $out .= '</div></div>';
    // JS de control
    $out .= '<script>(function(){';
    $out .= 'var modal=document.getElementById("igModal");';
    $out .= 'var openBtn=document.getElementById("igOpenModal");';
    $out .= 'if(!modal||!openBtn)return;';
    // Función: comprobar chips y (des)habilitar botón
    $out .= 'function updateOpenBtn(){var groups={};document.querySelectorAll("[data-ig-chip]").forEach(function(c){var t=c.getAttribute("data-ig-chip");if(!groups[t])groups[t]=false;if(c.classList.contains("is-selected"))groups[t]=true;});var missing=Object.keys(groups).filter(function(t){return !groups[t];});if(missing.length===0){openBtn.disabled=false;openBtn.removeAttribute("data-tooltip");}else{openBtn.disabled=true;var map={color:"un color",tamano:"un tamaño",diametro:"un diámetro",material:"un material"};openBtn.setAttribute("data-tooltip","Selecciona "+missing.map(function(t){return map[t]||t}).join(" y "));}}';
    // Inicial + al clicar cualquier chip
    $out .= 'updateOpenBtn();document.addEventListener("click",function(e){if(e.target.closest("[data-ig-chip]"))setTimeout(updateOpenBtn,10);});';
    // Abrir modal
    $out .= 'openBtn.addEventListener("click",function(){if(openBtn.disabled)return;modal.setAttribute("aria-hidden","false");document.body.classList.add("ig-modal-open");});';
    // Cerrar modal
    $out .= 'function closeModal(){modal.setAttribute("aria-hidden","true");document.body.classList.remove("ig-modal-open");}';
    $out .= 'modal.querySelectorAll("[data-ig-modal-close]").forEach(function(el){el.addEventListener("click",closeModal);});';
    $out .= 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.getAttribute("aria-hidden")==="false")closeModal();});';
    $out .= '})();</script>';
    echo $out;
}, 15);

/* 2.B - Productos relacionados: mostrar el resto de productos (no por categoría) */
add_filter('woocommerce_related_products', function ($related_posts, $product_id, $args) {
    if ((int) $product_id !== INDUGRAFIC_TEST_ID) return $related_posts;
    $ids = wc_get_products([
        'status'   => 'publish',
        'limit'    => 6,
        'exclude'  => [(int) $product_id],
        'return'   => 'ids',
        'orderby'  => 'rand',
    ]);
    return is_array($ids) ? $ids : [];
}, 10, 3);

/* 2.C - Aumentar máximo de productos relacionados que WC intenta traer */
add_filter('woocommerce_output_related_products_args', function ($args) {
    if (!indugrafic_is_test_product()) return $args;
    $args['posts_per_page'] = 6;
    $args['columns']        = 3;
    return $args;
});

/* 3. CSS inline */
add_action('wp_head', function () {
    if (!indugrafic_is_test_product()) return;
    /* Centrado y padding globales del contenedor de producto */
    $css  = '.single-product.woocommerce div.product,.single-product .woocommerce div.product{max-width:1200px;margin:0 auto;padding:48px 40px}';
    $css .= '.single-product .related.products,.single-product .up-sells.products{max-width:1200px;margin:60px auto 0;padding:40px 40px 60px;border-top:1px solid #ececec}';
    $css .= '.single-product .related.products h2,.single-product .up-sells h2{font-size:1.5rem;color:#1a1a1a;margin-bottom:24px}';
    /* Clear both solo para lo que va abajo full-width (FAQs, tabs, related) */
    $css .= '.single-product .woocommerce-tabs,.single-product .related.products,.single-product .up-sells.products,.single-product .product .ig-faqs{clear:both}';
    /* Bloques dentro del summary (columna derecha) — sin float, ancho auto */
    $css .= '.single-product .product .ig-in-summary{margin:20px 0 0;max-width:none;padding:0}';
    /* Atributos visuales */
    $css .= '.single-product .product .ig-atributos{display:flex;flex-direction:column;gap:16px}';
    $css .= '.single-product .product .ig-attr-label{font-size:.82rem;color:#6b7280;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin:0 0 10px}';
    $css .= '.single-product .product .ig-attr-items{display:flex;flex-wrap:wrap;gap:10px}';
    $css .= '.single-product .product .ig-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:#fff;border:1.5px solid #e5e7eb;border-radius:999px;font-size:.9rem;color:#1a1a1a;font-weight:500;transition:.15s}';
    $css .= '.single-product .product .ig-chip:hover{border-color:#fcb10e;background:#fef8e7}';
    $css .= '.single-product .product .ig-chip-size{padding:8px 16px;font-variant-numeric:tabular-nums}';
    $css .= '.single-product .product .ig-dot{width:16px;height:16px;border-radius:50%;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12);flex-shrink:0}';
    /* Chips clicables */
    $css .= '.single-product .product button.ig-chip{cursor:pointer;font-family:inherit}';
    $css .= '.single-product .product .ig-chip.is-selected{border-color:#fcb10e;background:#fef3c7;box-shadow:0 0 0 2px rgba(252,177,14,.25)}';
    $css .= '.single-product .product .ig-attr-req{color:#dc2626;font-weight:800;margin-left:2px}';
    $css .= '.single-product .product .ig-attr-hint{display:block;text-transform:none;font-weight:500;letter-spacing:0;color:#9ca3af;font-size:.78rem;margin-top:2px}';
    $css .= '.single-product .product .ig-attr.ig-missing{animation:igShake .35s;padding:12px;background:#fee2e2;border-radius:8px;margin:-12px -12px 0}';
    $css .= '.single-product .product .ig-attr.ig-missing .ig-attr-label{color:#dc2626}';
    $css .= '@keyframes igShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}';
    /* Breadcrumbs */
    $css .= '.single-product .ig-breadcrumb-wrap{max-width:1200px;margin:0 auto;padding:8px 40px 0;font-size:.88rem;color:#6b7280}';
    $css .= '.single-product .ig-breadcrumb a{color:#6b7280;text-decoration:none}';
    $css .= '.single-product .ig-breadcrumb a:hover{color:#fcb10e}';
    $css .= '.single-product .ig-bc-sep{color:#d1d5db;margin:0 4px}';
    /* FAQs */
    $css .= '.single-product .product .ig-faqs{max-width:900px;margin:32px auto 0;padding:0 20px}';
    $css .= '.single-product .product .ig-faqs-title{font-size:1.35rem;font-weight:700;color:#1a1a1a;margin:0 0 16px}';
    $css .= '.single-product .product .ig-faq{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;overflow:hidden}';
    $css .= '.single-product .product .ig-faq[open]{border-color:#fcb10e}';
    $css .= '.single-product .product .ig-faq-q{cursor:pointer;padding:14px 18px;font-weight:600;color:#1a1a1a;list-style:none;position:relative;padding-right:44px;user-select:none}';
    $css .= '.single-product .product .ig-faq-q::-webkit-details-marker{display:none}';
    $css .= '.single-product .product .ig-faq-q::after{content:"+";position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:1.4rem;color:#fcb10e;font-weight:400;transition:.15s}';
    $css .= '.single-product .product .ig-faq[open] .ig-faq-q::after{content:"−"}';
    $css .= '.single-product .product .ig-faq-a{padding:0 18px 16px;color:#4b5563;line-height:1.6}';
    /* Bloque de 4 pasos (dentro del summary ahora) */
    $css .= '.single-product .product .ig-nativo-pasos{background:#fef8e7;border-left:5px solid #fcb10e;padding:18px 20px;border-radius:6px}';
    $css .= '.single-product .product .ig-nativo-pasos h3{margin:0 0 12px;font-size:1.1rem;color:#1a1a1a;font-weight:700}';
    $css .= '.single-product .product .ig-nativo-pasos ol{margin:0;padding-left:22px}';
    $css .= '.single-product .product .ig-nativo-pasos ol li{margin-bottom:8px;line-height:1.5;color:#333;font-size:.92rem}';
    /* CTA bloque: Solicitar arriba, WA+Llamar debajo */
    $css .= '.single-product .product .ig-cta-block{display:flex;flex-direction:column;gap:10px}';
    $css .= '.single-product .product .ig-cta-primary{display:block;width:100%;padding:16px 20px;background:#fcb10e;color:#1a1a1a;border:none;border-radius:8px;font-size:1.1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s;position:relative}';
    $css .= '.single-product .product .ig-cta-primary:hover:not([disabled]){background:#d99700;transform:translateY(-1px);box-shadow:0 4px 12px rgba(252,177,14,.35)}';
    $css .= '.single-product .product .ig-cta-primary[disabled]{background:#e5e7eb;color:#9ca3af;cursor:not-allowed;box-shadow:none}';
    $css .= '.single-product .product .ig-cta-primary[disabled][data-tooltip]:hover::after{content:attr(data-tooltip);position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;padding:6px 12px;border-radius:4px;font-size:.82rem;font-weight:500;white-space:nowrap;pointer-events:none;z-index:100}';
    $css .= '.single-product .product .ig-cta-secondary{display:flex;gap:8px}';
    $css .= '.single-product .product .ig-cta-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px 14px;color:#fff!important;text-decoration:none!important;border-radius:6px;font-size:.95rem;font-weight:600;transition:.15s}';
    $css .= '.single-product .product .ig-cta-wa{background:#25d366}';
    $css .= '.single-product .product .ig-cta-wa:hover{background:#128c7e}';
    $css .= '.single-product .product .ig-cta-tel{background:#1a1a1a}';
    $css .= '.single-product .product .ig-cta-tel:hover{background:#374151}';
    /* Sellos de confianza — debajo de la galería en columna izquierda */
    $css .= '.single-product .product .ig-nativo-sellos{display:flex;flex-direction:column;gap:8px;padding:16px;background:#f9f9f9;border-radius:8px}';
    $css .= '.single-product .product .ig-sello{display:flex;align-items:center;gap:8px;font-size:.9rem;color:#4b5563}';
    $css .= '.single-product .product .ig-nativo-sellos.ig-under-gallery{float:left;clear:left;width:48%;margin-top:20px}';
    $css .= '@media (max-width:768px){.single-product .product .ig-nativo-sellos.ig-under-gallery{float:none;width:100%;margin:16px 0}}';
    /* MODAL formulario */
    $css .= '.ig-modal{display:none;position:fixed;inset:0;z-index:99999}';
    $css .= '.ig-modal[aria-hidden="false"]{display:block}';
    $css .= '.ig-modal-overlay{position:absolute;inset:0;background:rgba(15,23,42,.75);backdrop-filter:blur(3px)}';
    $css .= '.ig-modal-panel{position:relative;max-width:640px;width:calc(100% - 32px);max-height:calc(100vh - 40px);margin:20px auto;background:#fff;border-radius:12px;box-shadow:0 25px 60px -20px rgba(0,0,0,.35);overflow-y:auto;top:50%;transform:translateY(-50%)}';
    $css .= '.ig-modal-close{position:absolute;top:12px;right:12px;background:#f4f4f5;border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:1.1rem;color:#4b5563;z-index:2;display:flex;align-items:center;justify-content:center;transition:.15s}';
    $css .= '.ig-modal-close:hover{background:#e5e7eb;color:#1a1a1a;transform:rotate(90deg)}';
    $css .= '.ig-modal-body{padding:0}';
    $css .= 'body.ig-modal-open{overflow:hidden}';
    $css .= '@media (max-width:640px){.ig-modal-panel{width:calc(100% - 16px);margin:8px auto;max-height:calc(100vh - 16px);top:0;transform:none}}';
    $css .= '.single-product div.product .woocommerce-product-gallery{margin-bottom:24px}';
    $css .= '.single-product div.product .woocommerce-product-gallery__wrapper img{border-radius:8px}';
    $css .= '.single-product .product .product_title.entry-title{font-size:2rem;font-weight:700;color:#1a1a1a;margin-bottom:16px}';
    $css .= '.single-product .product .woocommerce-product-details__short-description{font-size:1.05rem;line-height:1.7;color:#333;margin-bottom:20px}';
    $css .= '.single-product .woocommerce-tabs .tabs{border-bottom:2px solid #fcb10e}';
    $css .= '.single-product .woocommerce-tabs .tabs li.active a{color:#fcb10e;font-weight:700}';
    $css .= '.single-product .woocommerce-tabs .panel{padding:24px;background:#fff}';
    $css .= '@media (max-width:768px){.single-product.woocommerce div.product,.single-product .woocommerce div.product{padding:24px 18px}.single-product .related.products,.single-product .up-sells.products{padding:24px 18px 40px;margin-top:32px}}';
    echo '<style id="ig-nativo-css">' . $css . '</style>';
}, 20);
