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

/* 2.A - Atributos como bloques visuales (colores en chips, tamaños en pills)
 *       Se renderiza antes del bloque de pasos (priority 12). */
add_action('woocommerce_after_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $product = wc_get_product(get_the_ID());
    if (!$product) return;
    $attributes = $product->get_attributes();
    if (empty($attributes)) return;

    $out = '<div class="ig-atributos">';
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
}, 12);

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

/* 2.G - Sticky bar móvil con Llamar / WhatsApp / Pedir presupuesto */
add_action('wp_footer', function () {
    if (!indugrafic_is_test_product()) return;
    $prod_name = get_the_title();
    $wa_msg = rawurlencode('Hola, me interesa ' . $prod_name . '.');
    $out  = '<div class="ig-sticky-bar">';
    $out .= '<a href="tel:' . INDUGRAFIC_TEL . '" class="ig-sticky-item"><span>📞</span><span>Llamar</span></a>';
    $out .= '<a href="https://wa.me/' . INDUGRAFIC_WA . '?text=' . $wa_msg . '" target="_blank" rel="noopener" class="ig-sticky-item ig-sticky-wa"><span>💬</span><span>WhatsApp</span></a>';
    $out .= '<a href="#ig-nativo-formulario" class="ig-sticky-item ig-sticky-cta"><span>📝</span><span>Pedir presupuesto</span></a>';
    $out .= '</div>';
    echo $out;
});

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
}, 13);

/* 2. Pasos + formulario + sellos + botones WhatsApp/Llamar */
add_action('woocommerce_after_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $prod_name = get_the_title();
    $wa_msg = rawurlencode('Hola, me interesa ' . $prod_name . '. Quiero información antes de rellenar el formulario.');
    $out  = '<div class="ig-nativo-wrapper">';
    $out .= '<div class="ig-nativo-pasos">';
    $out .= '<h3>Como hacer tu pedido en 4 pasos</h3>';
    $out .= '<ol>';
    $out .= '<li><strong>Rellena el formulario</strong> y adjunta tu diseno vectorizado.</li>';
    $out .= '<li><strong>Recibiras un correo</strong> con el presupuesto y el diseno definitivo en menos de 24 horas laborables.</li>';
    $out .= '<li><strong>Acepta el diseno</strong>, realiza el pago y dinos la direccion de envio.</li>';
    $out .= '<li><strong>Lo recibiras en tu domicilio</strong> en 48 horas.</li>';
    $out .= '</ol></div>';
    $out .= '<div class="ig-nativo-cta-secundario">';
    $out .= '<span class="ig-nativo-cta-label">¿Prefieres hablar antes?</span>';
    $out .= '<a class="ig-nativo-cta-btn ig-nativo-cta-wa" href="https://wa.me/' . INDUGRAFIC_WA . '?text=' . $wa_msg . '" target="_blank" rel="noopener">💬 WhatsApp</a>';
    $out .= '<a class="ig-nativo-cta-btn ig-nativo-cta-tel" href="tel:' . INDUGRAFIC_TEL . '">📞 Llamar ' . INDUGRAFIC_TEL_LABEL . '</a>';
    $out .= '</div>';
    $out .= '<div class="ig-nativo-formulario" id="ig-nativo-formulario">' . do_shortcode('[indugrafic_presupuesto_form]') . '</div>';
    $out .= '<div class="ig-nativo-sellos">';
    $out .= '<div class="ig-sello">Dejanos tu pedido</div>';
    $out .= '<div class="ig-sello">Produccion 24-48 horas</div>';
    $out .= '<div class="ig-sello">Personalizado segun tu diseno</div>';
    $out .= '</div></div>';
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
    /* Limpiar floats de la galería WC para que nuestros bloques queden debajo */
    $css .= '.single-product .product .ig-atributos,.single-product .product .ig-nativo-wrapper,.single-product .woocommerce-tabs,.single-product .related.products,.single-product .up-sells.products{clear:both}';
    /* Atributos visuales */
    $css .= '.single-product .product .ig-atributos{max-width:900px;margin:32px auto 0;padding:32px 20px 0;display:flex;flex-direction:column;gap:20px}';
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
    /* Botones WhatsApp/Llamar */
    $css .= '.single-product .product .ig-nativo-cta-secundario{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;padding:16px;background:#fff;border:1px dashed #d1d5db;border-radius:8px;margin-bottom:20px}';
    $css .= '.single-product .product .ig-nativo-cta-label{font-size:.95rem;color:#4b5563;font-weight:500;margin-right:8px}';
    $css .= '.single-product .product .ig-nativo-cta-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:#1a1a1a;color:#fff!important;text-decoration:none!important;border-radius:6px;font-size:.95rem;font-weight:600;transition:.15s}';
    $css .= '.single-product .product .ig-nativo-cta-btn:hover{background:#fcb10e;color:#1a1a1a!important}';
    $css .= '.single-product .product .ig-nativo-cta-wa{background:#25d366}';
    $css .= '.single-product .product .ig-nativo-cta-wa:hover{background:#128c7e;color:#fff!important}';
    /* Sticky bar móvil */
    $css .= '.ig-sticky-bar{display:none}';
    $css .= '@media (max-width:768px){.ig-sticky-bar{display:flex;position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e5e7eb;box-shadow:0 -4px 12px rgba(0,0,0,.08);z-index:9999;padding:6px 4px}.ig-sticky-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 4px;color:#1a1a1a!important;text-decoration:none!important;font-size:.72rem;font-weight:600;line-height:1.1;text-align:center}.ig-sticky-item span:first-child{font-size:1.3rem;line-height:1}.ig-sticky-item.ig-sticky-wa{color:#25d366!important}.ig-sticky-item.ig-sticky-cta{color:#fcb10e!important}body.single-product{padding-bottom:64px!important}}';
    /* Original */
    $css .= '.single-product .product .ig-nativo-wrapper{max-width:900px;margin:40px auto;padding:0 20px}';
    $css .= '.ig-nativo-pasos{background:#fef8e7;border-left:5px solid #fcb10e;padding:24px 28px;margin-bottom:32px;border-radius:6px}';
    $css .= '.ig-nativo-pasos h3{margin:0 0 16px;font-size:1.35rem;color:#1a1a1a;font-weight:700}';
    $css .= '.ig-nativo-pasos ol{margin:0;padding-left:24px}';
    $css .= '.ig-nativo-pasos ol li{margin-bottom:10px;line-height:1.6;color:#333}';
    $css .= '.ig-nativo-formulario{background:#fff;padding:8px 0;margin-bottom:24px}';
    $css .= '.ig-nativo-sellos{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;padding:24px 20px;background:#f9f9f9;border-radius:8px;margin-bottom:20px}';
    $css .= '.ig-sello{display:flex;align-items:center;gap:8px;font-size:.95rem;color:#444}';
    $css .= '.single-product div.product .woocommerce-product-gallery{margin-bottom:24px}';
    $css .= '.single-product div.product .woocommerce-product-gallery__wrapper img{border-radius:8px}';
    $css .= '.single-product .product .product_title.entry-title{font-size:2rem;font-weight:700;color:#1a1a1a;margin-bottom:16px}';
    $css .= '.single-product .product .woocommerce-product-details__short-description{font-size:1.05rem;line-height:1.7;color:#333;margin-bottom:20px}';
    $css .= '.single-product .woocommerce-tabs .tabs{border-bottom:2px solid #fcb10e}';
    $css .= '.single-product .woocommerce-tabs .tabs li.active a{color:#fcb10e;font-weight:700}';
    $css .= '.single-product .woocommerce-tabs .panel{padding:24px;background:#fff}';
    $css .= '@media (max-width:768px){.single-product.woocommerce div.product,.single-product .woocommerce div.product{padding:24px 18px}.single-product .related.products,.single-product .up-sells.products{padding:24px 18px 40px;margin-top:32px}.ig-nativo-pasos{padding:18px 20px}.ig-nativo-sellos{flex-direction:column;align-items:flex-start;gap:12px}}';
    echo '<style id="ig-nativo-css">' . $css . '</style>';
}, 20);
