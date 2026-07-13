import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

// 1. Obtener producto origen
const r = await fetch(base+'/wc/v3/products/1331',{headers:{Authorization:auth}});
const orig = await r.json();

// 2. Construir payload duplicado (draft)
const payload = {
  name: 'TEST · Producto de prueba (ficha nativa WC)',
  slug: 'test-producto-prueba-wc-nativo',
  type: 'simple',
  status: 'draft',
  featured: false,
  catalog_visibility: 'hidden',
  description: orig.description,
  short_description: orig.short_description,
  regular_price: '',
  categories: orig.categories?.map(c => ({id: c.id})) || [],
  tags: orig.tags?.map(t => ({id: t.id})) || [],
  images: orig.images?.map(img => ({id: img.id, src: img.src})) || [],
  attributes: orig.attributes || [],
  meta_data: [
    { key: '_indugrafic_test_product', value: '1' }
  ]
};

const c = await fetch(base+'/wc/v3/products',{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body: Buffer.from(JSON.stringify(payload),'utf8')
});
const nw = await c.json();
console.log('Duplicate product:', c.status, '| id:', nw.id);
if(!nw.id){console.log(JSON.stringify(nw).slice(0,600));process.exit(1);}
console.log('  name:',nw.name);
console.log('  slug:',nw.slug);
console.log('  status:',nw.status);
console.log('  permalink:',nw.permalink);
console.log('  images:',nw.images?.length);
console.log('  categories:',nw.categories?.map(c=>c.name).join(', '));

fs.writeFileSync('test-product-id.json', JSON.stringify({id:nw.id, permalink:nw.permalink, slug:nw.slug},null,2));
console.log('\n✅ Producto TEST creado con id',nw.id);
console.log('   URL:',nw.permalink);
console.log('   Estado: DRAFT (invisible en catálogo público)');
