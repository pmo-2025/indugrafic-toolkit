import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL; const root = base.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

console.log('=== REST root ===');
const r0 = await fetch(`${root}`);
console.log('Status:', r0.status);
if(r0.ok){const j=await r0.json();console.log('  namespaces:',(j.namespaces||[]).join(', '));}

console.log('\n=== /users/me ===');
const me=await fetch(`${base}/users/me`,{headers:{Authorization:auth}});
console.log('Status:',me.status);
if(me.ok){const u=await me.json();console.log('  user:',u.name,'| id:',u.id,'| roles:',(u.roles||[]).join(','));}

console.log('\n=== Theme + plugins (homepage scan) ===');
const home = await fetch('https://indugrafic.es/').then(r=>r.text());
const theme = (home.match(/wp-content\/themes\/([^\/'"]+)/)||[])[1];
console.log('  Theme:', theme);
console.log('  WooCommerce:', /woocommerce/i.test(home)?'sí':'no');
console.log('  Elementor:', /elementor/i.test(home)?'sí':'no');
console.log('  Flatsome:', /flatsome/i.test(home)?'sí':'no');
console.log('  Astra:', /astra/i.test(home)?'sí':'no');
console.log('  WP-Rocket:', /wp-rocket/i.test(home)?'sí':'no');
console.log('  LiteSpeed:', /litespeed/i.test(home)?'sí':'no');
console.log('  Yoast:', /yoast/i.test(home)?'sí':'no');
console.log('  Rank Math:', /rank-math|rankmath/i.test(home)?'sí':'no');

console.log('\n=== Probe write ===');
const t=await fetch(`${base}/pages`,{method:'POST',headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},body:Buffer.from(JSON.stringify({title:'__test__',content:'t',status:'draft'}),'utf8')});
console.log('Create draft:',t.status);
if(t.ok){const d=await t.json();await fetch(`${base}/pages/${d.id}?force=true`,{method:'DELETE',headers:{Authorization:auth}});console.log('Delete OK id',d.id);}

console.log('\n=== Customizer / Theme mods ===');
const ts = await fetch(`${base}/themes?status=active`,{headers:{Authorization:auth}}).then(r=>r.json()).catch(e=>e.message);
console.log('Themes endpoint:', Array.isArray(ts)?(ts[0]?.stylesheet||'?'):ts);

console.log('\n=== Global styles endpoint (if FSE) ===');
const gs = await fetch(`${base}/global-styles/themes/`+ (Array.isArray(ts)?(ts[0]?.stylesheet||''):''),{headers:{Authorization:auth}}).then(r=>r.status);
console.log('global-styles:', gs);
