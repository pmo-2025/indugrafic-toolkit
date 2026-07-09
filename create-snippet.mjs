import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

const code = `// =============================================================
// Fix galería de producto WooCommerce — indugrafic.es
// 1) Tamaño miniaturas 300x300 (en vez de 100x100) → sin borrosidad
// 2) CSS forzando opacity:1 en miniaturas no seleccionadas
// =============================================================

// 1) Tamaño de miniatura de la galería del producto
add_filter( 'woocommerce_gallery_thumbnail_size', function() {
    return 'woocommerce_thumbnail'; // 300x300 (configurable en Apariencia → Personalizar → WooCommerce → Imágenes)
});

// 2) CSS en el head para anular opacidades de miniaturas no seleccionadas
add_action( 'wp_head', function() {
    if ( ! ( function_exists('is_product') && is_product() ) ) return;
    echo '<style id="fix-gallery-thumbs">
    /* Anular cualquier opacity sobre las miniaturas de la galeria de producto */
    .woocommerce-product-gallery .flex-control-thumbs li img,
    .woocommerce-product-gallery .flex-control-thumbs li img:not(.flex-active),
    .woocommerce-product-gallery .flex-control-nav li img,
    .woocommerce-product-gallery .woocommerce-product-gallery__image img,
    .woocommerce-product-gallery [data-thumb] img,
    .lg-thumb-item,
    .lg-thumb-item:not(.active),
    .royal-product-gallery-thumb img,
    .wpr-woo-product-thumbnails img,
    .swiper-slide-thumb-active img,
    .swiper-slide:not(.swiper-slide-thumb-active) img,
    .flex-active-slide img,
    .royal-woo-thumbs .swiper-slide img,
    .e-loop-item .woocommerce-product-gallery__image img {
        opacity: 1 !important;
        filter: none !important;
        -webkit-filter: none !important;
        mix-blend-mode: normal !important;
    }
    /* Eliminar cualquier ::before/::after que pinte una capa blanca sobre la miniatura */
    .woocommerce-product-gallery .flex-control-thumbs li::before,
    .woocommerce-product-gallery .flex-control-thumbs li::after,
    .woocommerce-product-gallery [data-thumb]::before,
    .woocommerce-product-gallery [data-thumb]::after,
    .swiper-slide:not(.swiper-slide-thumb-active)::before,
    .swiper-slide:not(.swiper-slide-thumb-active)::after,
    .lg-thumb-item:not(.active)::before,
    .lg-thumb-item:not(.active)::after,
    .royal-woo-thumbs .swiper-slide::before,
    .royal-woo-thumbs .swiper-slide::after {
        display: none !important;
        opacity: 0 !important;
        background: transparent !important;
    }
    /* Mejorar nitidez de render */
    .woocommerce-product-gallery img,
    .woocommerce-product-gallery [data-thumb] img,
    .royal-woo-thumbs img,
    .flex-control-thumbs img {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
    </style>';
}, 100);
`;

const body = {
  name: 'Fix galería de producto (sin capa blanca + miniaturas nítidas)',
  description: 'Cambia tamaño thumbnail de galería de 100x100 a 300x300 y anula opacity/overlay en miniaturas no seleccionadas. Solo se aplica en páginas de producto.',
  code,
  scope: 'global',
  active: false, // primero lo creamos desactivado para poder revisar
  priority: 11
};

const r = await fetch('https://indugrafic.es/wp-json/code-snippets/v1/snippets',{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body:Buffer.from(JSON.stringify(body),'utf8')
});
console.log('Create snippet:', r.status);
const d = await r.json();
console.log('  id:', d.id, '| name:', d.name, '| scope:', d.scope, '| active:', d.active);
fs.writeFileSync('snippet-created.json', JSON.stringify(d,null,2));
