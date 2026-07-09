<?php
/**
 * Indugrafic — Shortcode [indugrafic_presupuesto_form]
 * Devuelve el HTML del formulario multi-step de presupuesto.
 */
add_shortcode('indugrafic_presupuesto_form', function () {
    ob_start();
    ?>
__FORM_PLACEHOLDER__
    <?php
    return ob_get_clean();
});
