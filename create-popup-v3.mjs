import fs from 'fs';
import crypto from 'crypto';
const env = Object.fromEntries(fs.readFileSync('.env','utf8').split(/\r?\n/).filter(Boolean).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const base = env.API_BASE_URL.replace('/wp/v2','');
const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');

const id7 = () => crypto.randomBytes(4).toString('hex').slice(0,7);

// Estructura Elementor con solo el shortcode
const elementorData = [
  {
    id: id7(), elType: 'section',
    settings: { content_width: {unit:'px', size:640}, structure:'10', gap:'no' },
    elements: [
      { id: id7(), elType: 'column',
        settings: { _column_size:100, _inline_size:null },
        elements: [
          { id: id7(), elType:'widget', widgetType:'shortcode',
            settings: { shortcode: '[indugrafic_presupuesto_form]' }
          }
        ]
      }
    ]
  }
];

// Crear en una sola llamada esta vez, con el meta pequeño
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
      _elementor_data: JSON.stringify(elementorData),
    }
  }),'utf8')
});
const j = await create.json();
console.log('Create popup:', create.status, '| id:', j.id);
if (!j.id) { console.log(JSON.stringify(j).slice(0,800)); process.exit(1); }
console.log('  template_type:', j.meta?._elementor_template_type);
console.log('  edit_mode:', j.meta?._elementor_edit_mode);
console.log('  data length:', (j.meta?._elementor_data||'').length);
console.log('  editor URL: https://indugrafic.es/wp-admin/post.php?post='+j.id+'&action=elementor');
fs.writeFileSync('popup-id.json', JSON.stringify({id:j.id, slug:j.slug}, null, 2));
