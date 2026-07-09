import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const html = await fetch(env.PRODUCT_URL).then(r=>r.text());
fs.writeFileSync('product-page.html', html);
console.log('Saved product-page.html ('+html.length+' chars)');

// 1) Detect gallery markup
console.log('\n=== Gallery elements ===');
const galleryClasses = [
  'woocommerce-product-gallery',
  'flex-control-thumbs',
  'flex-active',
  'flex-control-nav',
  'product-thumbnails',
  'elementor-image-gallery',
  'woocommerce-product-gallery__image',
  'wp-pswp-thumbnails',
  'image-thumb',
  'thumbnails'
];
for (const c of galleryClasses) {
  const matches = (html.match(new RegExp('class="[^"]*'+c+'[^"]*"','g'))||[]).slice(0,3);
  if (matches.length) {
    console.log(`\n  → "${c}" found:`);
    matches.forEach(m=>console.log('     '+m));
  }
}

// 2) Get the gallery section
const galleryStart = html.indexOf('woocommerce-product-gallery');
if (galleryStart > -1) {
  console.log('\n=== GALLERY HTML (excerpt) ===');
  console.log(html.substring(Math.max(0,galleryStart-200), galleryStart+3500));
}

// 3) Find thumb images
console.log('\n=== Image sources in gallery ===');
const imgs = [...html.matchAll(/<img\b[^>]*?(class="[^"]*(?:thumb|gallery|attachment)[^"]*"[^>]*)>/gi)].slice(0,8);
imgs.forEach((m,i)=>console.log(`  [${i}] ${m[0].slice(0,300)}`));
