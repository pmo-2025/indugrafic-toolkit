// Llama a la API REST de indugrafic.es desde una pestaña de Chrome.
// El hosting muestra una pantalla anti-bots ("Un momento…") a curl y a node, pero un Chrome
// real la pasa, y un fetch hecho dentro de esa pestaña lleva ya la cookie del reto.
//
// 1. Abrir Chrome con depuración y un perfil aparte (Chrome no deja hacerlo en el perfil normal):
//    "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --user-data-dir=%TEMP%\chrome-indugrafic https://indugrafic.es/
// 2. node wp-api-chrome.mjs GET    /wp/v2/plugins
//    node wp-api-chrome.mjs GET    /code-snippets/v1/snippets
//    node wp-api-chrome.mjs POST   /code-snippets/v1/snippets cuerpo.json
//    node wp-api-chrome.mjs DELETE /code-snippets/v1/snippets/13
// Credenciales del .env (WP_USER + WP_APP_PASSWORD).
import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');
const [method = 'GET', ruta = '/', bodyFile] = process.argv.slice(2);
const body = bodyFile ? fs.readFileSync(bodyFile, 'utf8') : null;

const tabs = await (await fetch('http://127.0.0.1:9222/json')).json();
let tab = tabs.find(t => t.type === 'page' && t.url.startsWith('https://indugrafic.es'));
if (!tab) {
  tab = await (await fetch('http://127.0.0.1:9222/json/new?https://indugrafic.es/robots.txt', { method: 'PUT' })).json();
  await new Promise(r => setTimeout(r, 8000));   // dar tiempo al reto anti-bots
}
const ws = new WebSocket(tab.webSocketDebuggerUrl);
let id = 0; const pend = {};
ws.onmessage = e => { const m = JSON.parse(e.data); if (pend[m.id]) { pend[m.id](m); delete pend[m.id]; } };
await new Promise(r => ws.onopen = r);
const send = (m, p = {}) => new Promise(r => { const i = ++id; pend[i] = r; ws.send(JSON.stringify({ id: i, method: m, params: p })); });

const expr = `(async()=>{const r=await fetch(${JSON.stringify('/wp-json' + ruta)},{method:${JSON.stringify(method)},credentials:'same-origin',
  headers:{Authorization:${JSON.stringify(auth)},'Content-Type':'application/json'},body:${body ? JSON.stringify(body) : 'undefined'}});
  return r.status+'\\n'+await r.text();})()`;
const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
console.log(r.result?.result?.value ?? JSON.stringify(r));
ws.close(); setTimeout(() => process.exit(0), 200);
