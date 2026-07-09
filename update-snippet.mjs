import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

let code = fs.readFileSync('snippet-presupuesto.php','utf8');
code = code.replace(/^<\?php\s*/,'');

const body = { code };
const r = await fetch(`${base}/code-snippets/v1/snippets/7`,{
  method:'POST',
  headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
  body: Buffer.from(JSON.stringify(body),'utf8')
});
const d = await r.json();
console.log('Update snippet 7:', r.status, '| active:', d.active, '| error?:', d.code_error);
