import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

// Cleanup popup previo si existe
await fetch(base+'/wp/v2/elementor_library/2400?force=true',{method:'DELETE',headers:{Authorization:auth}}).catch(()=>{});

// Componer el snippet con el form inline
let base_code = fs.readFileSync('snippet-shortcode.php','utf8').replace(/^<\?php\s*/,'');
const form = fs.readFileSync('formulario-presupuesto.html','utf8');
const code = base_code.replace('__FORM_PLACEHOLDER__', form);

const body = {
  name: 'Indugrafic — Shortcode [indugrafic_presupuesto_form]',
  desc: 'Registra el shortcode [indugrafic_presupuesto_form] que devuelve el formulario multi-step de presupuesto (HTML+CSS+JS autocontenido). Usar dentro de widget HTML de Elementor o cualquier página/post.',
  code,
  scope: 'global',
  active: true,
  priority: 10,
  tags: 'indugrafic,shortcode,formulario',
};

const r = await fetch(`${base}/code-snippets/v1/snippets`,{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body: Buffer.from(JSON.stringify(body),'utf8')
});
const d = await r.json();
console.log('Create shortcode snippet:', r.status, '| id:', d.id, '| active:', d.active, '| error?:', JSON.stringify(d.code_error));
