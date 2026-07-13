import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

let code = fs.readFileSync('snippet-hooks-nativo.php','utf8').replace(/^<\?php\s*/,'');

const r = await fetch(`${base}/code-snippets/v1/snippets/9`,{
  method:'POST',
  headers:{
    Authorization:auth,
    'Content-Type':'application/json; charset=utf-8',
    'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
  },
  body: Buffer.from(JSON.stringify({ code }),'utf8')
});
const d = await r.json();
console.log('Update snippet 9:', r.status, '| active:', d.active, '| error?:', JSON.stringify(d.code_error));
