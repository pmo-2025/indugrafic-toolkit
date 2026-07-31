import fs from 'fs';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

const attr = (name, options) => ({name, visible:true, variation:false, options});

const targets = [
  { id:1331, name:'Sellos de madera', attributes:[
    attr('Colores',['Azul','Verde','Negro','Rojo']),
    attr('Tamaños',['10 × 10 cm','10 × 15 cm','12 × 12 cm','12 × 17 cm','15 × 15 cm','15 × 18 cm']),
  ]},
  { id:1333, name:'Sellos automáticos', attributes:[
    attr('Colores',['Azul','Verde','Negro','Rojo']),
    attr('Tamaños',['24 mm diámetro','31 mm diámetro','45 mm diámetro','50 mm diámetro','27 × 10 mm','38 × 14 mm','47 × 18 mm','59 × 23 mm','45 × 30 mm','50 × 40 mm','60 × 40 mm','76 × 37 mm']),
  ]},
  { id:1335, name:'Sellos metálicos', attributes:[
    attr('Tamaños',['25 mm diámetro','30 mm diámetro','40 mm diámetro']),
  ]},
  { id:1337, name:'Sellos en seco', attributes:[
    attr('Tamaños',['40 mm diámetro','50 mm diámetro','50 × 25 mm']),
  ]},
  // Troqueles (1339) y Grabados (1341): sin atributos, son a medida
];

async function put(p, attempt=1){
  try{
    const r = await fetch('https://indugrafic.es/wp-json/wc/v3/products/'+p.id, {
      method:'PUT',
      headers:{
        Authorization:auth,
        'Content-Type':'application/json; charset=utf-8',
        'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept':'application/json'
      },
      body: Buffer.from(JSON.stringify({attributes:p.attributes}),'utf8')
    });
    const d = await r.json();
    console.log(`[${p.id}] ${p.name} → ${r.status} · attrs: ${(d.attributes||[]).map(a=>a.name+'('+a.options.length+')').join(', ')}`);
  }catch(e){
    console.log(`[${p.id}] attempt ${attempt} error:`, e.message);
    if(attempt<3){await new Promise(r=>setTimeout(r,3000*attempt));return put(p, attempt+1);}
  }
}
for (const p of targets) {
  await put(p);
  await new Promise(r=>setTimeout(r,1500));
}
console.log('\n1339 Troqueles y 1341 Grabados: SIN atributos (a medida). Botón "Solicitar presupuesto" quedará habilitado directamente.');
