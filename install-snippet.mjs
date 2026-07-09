import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

// Leer el PHP tal cual, quitar la primera línea <?php porque Code Snippets no la quiere
let code = fs.readFileSync('snippet-presupuesto.php','utf8');
code = code.replace(/^<\?php\s*/,'');

const body = {
  name: 'Indugrafic — Endpoint /presupuesto (formulario multi-step)',
  desc: 'Registra POST /wp-json/indugrafic/v1/presupuesto. Recibe formulario de solicitud de presupuesto: valida, guarda archivo en /uploads/presupuestos y envía dos emails (admin + confirmación al comprador). Con honeypot, rate limit y validación MIME.',
  code,
  scope: 'global',
  active: true,
  priority: 10,
  tags: 'indugrafic,formulario,presupuesto',
};

const r = await fetch(`${base}/code-snippets/v1/snippets`,{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body: Buffer.from(JSON.stringify(body),'utf8')
});
const d = await r.json();
console.log('Create snippet:', r.status);
console.log('  id:', d.id, '| active:', d.active, '| error?:', d.code_error);
if (d.code_error) console.log('  code_error detail:', JSON.stringify(d.code_error));
