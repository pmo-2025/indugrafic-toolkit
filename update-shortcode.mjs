import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

let base_code = fs.readFileSync('snippet-shortcode.php','utf8').replace(/^<\?php\s*/,'');
const form = fs.readFileSync('formulario-presupuesto.html','utf8');
const code = base_code.replace('__FORM_PLACEHOLDER__', form);

const r = await fetch(`${base}/code-snippets/v1/snippets/8`,{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body: Buffer.from(JSON.stringify({ code }),'utf8')
});
const d = await r.json();
console.log('Update snippet 8:', r.status, '| active:', d.active, '| error?:', JSON.stringify(d.code_error));
