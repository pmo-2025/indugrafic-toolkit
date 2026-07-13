import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

let code = fs.readFileSync('snippet-hooks-nativo.php','utf8').replace(/^<\?php\s*/,'');

const body = {
  name: 'Indugrafic — Hooks ficha nativa WC (TEST id 2418)',
  desc: 'Ejecuta hooks de WooCommerce solo en el producto de test id 2418. Oculta precio + carrito, añade texto de 4 pasos + formulario [indugrafic_presupuesto_form] + sellos de confianza + CSS inline. Para migrar toda la tienda a ficha nativa, cambiar la constante INDUGRAFIC_TEST_ID a null.',
  code,
  scope: 'global',
  active: true,
  priority: 10,
  tags: 'indugrafic,woocommerce,nativo,hooks,test',
};

const r = await fetch(`${base}/code-snippets/v1/snippets`,{
  method:'POST',
  headers:{
    Authorization:auth,
    'Content-Type':'application/json; charset=utf-8',
    'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Referer':'https://indugrafic.es/wp-admin/'
  },
  body: Buffer.from(JSON.stringify(body),'utf8')
});
const d = await r.json();
console.log('Create hooks snippet:', r.status, '| id:', d.id, '| active:', d.active, '| error?:', JSON.stringify(d.code_error));
