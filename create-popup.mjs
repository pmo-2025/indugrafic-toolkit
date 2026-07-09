import fs from 'fs';
import crypto from 'crypto';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

const id7 = () => crypto.randomBytes(4).toString('hex').slice(0,7);
const formHtml = fs.readFileSync('formulario-presupuesto.html','utf8');

// Estructura Elementor JSON — Section → Column → HTML widget
const elementorData = [
  {
    id: id7(), elType: 'section',
    settings: { content_width: {unit:'px', size:640}, structure:'10', gap:'no', padding: {unit:'px', top:'0', right:'0', bottom:'0', left:'0', isLinked:true} },
    elements: [
      { id: id7(), elType: 'column',
        settings: { _column_size:100, _inline_size:null, padding:{unit:'px', top:'0', right:'0', bottom:'0', left:'0', isLinked:true} },
        elements: [
          { id: id7(), elType:'widget', widgetType:'html',
            settings: { html: formHtml }
          }
        ]
      }
    ]
  }
];

// Cleanup: eliminar tests previos
async function cleanup(){
  for (const testId of [2398, 2399]) {
    await fetch(base+'/wp/v2/elementor_library/'+testId+'?force=true',{method:'DELETE',headers:{Authorization:auth}}).catch(()=>{});
  }
}

async function main(){
  await cleanup();

  // Fase 1: Crear post con template_type=popup
  const create = await fetch(base+'/wp/v2/elementor_library',{
    method:'POST',
    headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
    body: Buffer.from(JSON.stringify({
      title: 'Popup — Pedir presupuesto',
      slug: 'popup-pedir-presupuesto',
      status: 'publish',
      meta: {
        _elementor_template_type: 'popup',
        _elementor_edit_mode: 'builder',
      }
    }),'utf8')
  });
  const j = await create.json();
  console.log('Fase 1 · Create popup:', create.status, '| id:', j.id);
  if (!j.id) { console.log(JSON.stringify(j)); return; }
  const popupId = j.id;

  // Fase 2: Update _elementor_data
  const upd = await fetch(base+'/wp/v2/elementor_library/'+popupId,{
    method:'POST',
    headers:{Authorization:auth,'Content-Type':'application/json; charset=utf-8'},
    body: Buffer.from(JSON.stringify({
      meta: {
        _elementor_data: JSON.stringify(elementorData),
      }
    }),'utf8')
  });
  const jud = await upd.json();
  console.log('Fase 2 · Update _elementor_data:', upd.status);
  const dataLen = (jud.meta?._elementor_data || '').length;
  console.log('  _elementor_data length:', dataLen);

  fs.writeFileSync('popup-id.json', JSON.stringify({id:popupId, slug:jud.slug, link:jud.link}, null, 2));
  console.log('\n✅ Popup creado. ID:', popupId);
  console.log('   Título: Popup — Pedir presupuesto');
  console.log('   Editor URL:', 'https://indugrafic.es/wp-admin/post.php?post='+popupId+'&action=elementor');
}

await main();
