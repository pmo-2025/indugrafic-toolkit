<?php
/**
 * Indugrafic — Hooks para ficha WC nativa
 * Limitado al producto TEST id 2418.
 */

const INDUGRAFIC_TEST_ID = 2418;

function indugrafic_is_test_product() {
    if (!function_exists('is_product') || !is_product()) return false;
    if (INDUGRAFIC_TEST_ID === null) return true;
    return (int) get_the_ID() === INDUGRAFIC_TEST_ID;
}

/* 0. Forzar que Elementor y Royal Addons NO apliquen su Single Product template
 *    para el producto de test. Devolvemos array vacío → sin template Elementor → WC nativo. */
add_filter('elementor/theme/get_location_templates', function ($templates, $location) {
    if ($location !== 'single' && $location !== 'single-product') return $templates;
    if (!is_singular('product')) return $templates;
    if ((int) get_the_ID() !== INDUGRAFIC_TEST_ID) return $templates;
    return [];
}, 20, 2);

/* Backup: si Royal Addons usa su propio filter, también lo interceptamos. */
add_filter('wpr_woo_builder_template_id', function ($id) {
    if (is_singular('product') && (int) get_the_ID() === INDUGRAFIC_TEST_ID) return 0;
    return $id;
});

/* 1. Ocultar precio + add-to-cart + ratings */
add_action('woocommerce_before_single_product', function () {
    if (!indugrafic_is_test_product()) return;
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
}, 5);

/* 2. Pasos + formulario + sellos */
add_action('woocommerce_after_single_product_summary', function () {
    if (!indugrafic_is_test_product()) return;
    $out  = '<div class="ig-nativo-wrapper">';
    $out .= '<div class="ig-nativo-pasos">';
    $out .= '<h3>Como hacer tu pedido en 4 pasos</h3>';
    $out .= '<ol>';
    $out .= '<li><strong>Rellena el formulario</strong> y adjunta tu diseno vectorizado.</li>';
    $out .= '<li><strong>Recibiras un correo</strong> con el presupuesto y el diseno definitivo en menos de 24 horas laborables.</li>';
    $out .= '<li><strong>Acepta el diseno</strong>, realiza el pago y dinos la direccion de envio.</li>';
    $out .= '<li><strong>Lo recibiras en tu domicilio</strong> en 48 horas.</li>';
    $out .= '</ol></div>';
    $out .= '<div class="ig-nativo-formulario">' . do_shortcode('[indugrafic_presupuesto_form]') . '</div>';
    $out .= '<div class="ig-nativo-sellos">';
    $out .= '<div class="ig-sello">Dejanos tu pedido</div>';
    $out .= '<div class="ig-sello">Produccion 24-48 horas</div>';
    $out .= '<div class="ig-sello">Personalizado segun tu diseno</div>';
    $out .= '</div></div>';
    echo $out;
}, 15);

/* 3. CSS inline */
add_action('wp_head', function () {
    if (!indugrafic_is_test_product()) return;
    $css  = '.single-product .product .ig-nativo-wrapper{max-width:900px;margin:40px auto;padding:0 20px}';
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
    $css .= '@media (max-width:768px){.ig-nativo-pasos{padding:18px 20px}.ig-nativo-sellos{flex-direction:column;align-items:flex-start;gap:12px}}';
    echo '<style id="ig-nativo-css">' . $css . '</style>';
}, 20);
