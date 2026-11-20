'use strict';
const $=id=>document.getElementById(id), form=$('settings');
const selectedDevice='banjir-001';
$('station-name').textContent='Stasiun '+selectedDevice;
let filled=false, currentSettings=null;
const names=['NORMAL','SIAGA','WASPADA','BAHAYA'];
const val=n=>n===null||n===undefined?'—':Math.round(Number(n)).toString();
const probe=x=>x===null||x===undefined?'null':'Level '+x;
const cell=(row,text)=>{const c=document.createElement('td');c.textContent=text;row.appendChild(c);};
const time=s=>new Date(s.replace(' ','T')+'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta'});
async function api(action,options={}){const r=await fetch('api.php?action='+action+'&device_id='+encodeURIComponent(selectedDevice),{cache:'no-store',...options});const d=await r.json();if(!r.ok)throw Error(d.error||'HTTP '+r.status);return d;}
// Graph coordinates are display-only; raw measurements remain unchanged.
const GRAPH={left:62,right:790,top:25,bottom:210,width:860,height:260};
const escSVG=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&apos;'}[c]));
const stamp=r=>Date.parse(r.received_at.replace(' ','T')+'Z');
function chartData(rows){return [...rows].reverse();}
function graphX(rows){
 const ts=rows.map(stamp),first=ts[0],last=ts[ts.length-1];
 return i=>rows.length>1&&last>first?GRAPH.left+(ts[i]-first)/(last-first)*(GRAPH.right-GRAPH.left):(GRAPH.left+GRAPH.right)/2;
}
function graphTime(rows,x){
 if(!rows.length)return '';
 const indices=[...new Set([0,Math.floor((rows.length-1)/2),rows.length-1])];
 return indices.map(i=>`<text x="${x(i)}" y="244" text-anchor="${i===0?'start':i===rows.length-1?'end':'middle'}">${escSVG(new Date(stamp(rows[i])).toLocaleTimeString('id-ID',{timeZone:'Asia/Jakarta',hour12:false}))}</text>`).join('');
}
function grid(ticks,y,label){return ticks.map(v=>`<line x1="${GRAPH.left}" x2="${GRAPH.right}" y1="${y(v)}" y2="${y(v)}" stroke="#e4edef"/><text x="${GRAPH.left-12}" y="${y(v)+4}" text-anchor="end">${escSVG(label(v))}</text>`).join('');}
function lineSegments(rows,values,x,y,step=false,breakBoot=false){
 let segments=[],path='';
 for(let i=0;i<rows.length;i++){
  const value=values[i];
  if(value===null||!Number.isFinite(value)){if(path)segments.push(path);path='';continue;}
  const gap=i>0&&(stamp(rows[i])-stamp(rows[i-1])>10000||(breakBoot&&rows[i].boot_id!==rows[i-1].boot_id));
  if(gap&&path){segments.push(path);path='';}
  const xx=x(i).toFixed(2),yy=y(value).toFixed(2);
  path+=path?(step?` H${xx} V${yy}`:` L${xx} ${yy}`):`M${xx} ${yy}`;
 }
 if(path)segments.push(path);return segments;
}
function graphFrame(content,rows,x){return content+graphTime(rows,x);}
function emptyChart(message){return `<text x="${(GRAPH.left+GRAPH.right)/2}" y="120" text-anchor="middle">${escSVG(message)}</text>`;}
function a16Scale(settings){
 const siaga=Number(settings.siaga_cm),waspada=Number(settings.waspada_cm),bahaya=Number(settings.bahaya_cm);
 const maximum=Math.ceil(bahaya+Math.max(10,(bahaya-waspada)*0.5));
 return {siaga,waspada,bahaya,maximum,ticks:[...new Set([0,siaga,waspada,bahaya,maximum])].sort((a,b)=>a-b)};
}
function chart(rows,settings){
 const data=chartData(rows),x=graphX(data),scale=a16Scale(settings);
 const y=v=>GRAPH.bottom-Math.max(0,Math.min(scale.maximum,v))/scale.maximum*(GRAPH.bottom-GRAPH.top);
 const bands=[{lo:0,hi:scale.siaga,color:'#edf7f3'},{lo:scale.siaga,hi:scale.waspada,color:'#fff8dc'},{lo:scale.waspada,hi:scale.bahaya,color:'#fff0df'},{lo:scale.bahaya,hi:scale.maximum,color:'#ffedeb'}];
 let content=bands.map(v=>`<rect x="${GRAPH.left}" y="${y(v.hi)}" width="${GRAPH.right-GRAPH.left}" height="${y(v.lo)-y(v.hi)}" fill="${v.color}"/>`).join('');
 content+=grid(scale.ticks,y,v=>val(v));
 for(const [v,name,color] of [[scale.siaga,'Siaga','#aa8d19'],[scale.waspada,'Waspada','#c67622'],[scale.bahaya,'Bahaya','#c14b4b']]){
  content+=`<line x1="${GRAPH.left}" x2="${GRAPH.right}" y1="${y(v)}" y2="${y(v)}" stroke="${color}" stroke-dasharray="5 5"/><text x="${GRAPH.right+8}" y="${y(v)+4}" style="fill:${color};font-size:10px">${name}</text>`;
 }
 const values=data.map(r=>r.a16_valid&&r.height_cm!==null?Number(r.height_cm):null);
 content+=lineSegments(data,values,x,y).map(p=>`<path d="${p}" fill="none" stroke="#116c68" stroke-width="2.5" stroke-linejoin="round"/>`).join('');
 let negatives=0,above=0;
 for(let i=0;i<data.length;i++){
  if(values[i]===null)continue;
  const v=values[i];if(v<0)negatives++;if(v>scale.maximum)above++;
  const title=`${time(data[i].received_at)} WIB | tinggi asli ${val(v)} cm${v<0?' | titik ditaruh pada garis 0':v>scale.maximum?' | di atas batas skala':''}`;
  content+=`<circle cx="${x(i)}" cy="${y(v)}" r="${v<0||v>scale.maximum?4:2.3}" fill="${v<0?'#64778d':v>scale.maximum?'#c34c47':'#116c68'}"><title>${escSVG(title)}</title></circle>`;
 }
 if(!values.some(v=>v!==null))content+=emptyChart('Belum ada tinggi air terkalibrasi');
 $('chart').innerHTML=graphFrame(content,data,x);
 $('a16-chart-note').textContent=`${negatives?' ':''}${above?' '+above+' titik di atas skala ditandai pada batas atas.':''}`;
}
function probeChart(rows){
 const data=chartData(rows),x=graphX(data),y=v=>GRAPH.bottom-v/3*(GRAPH.bottom-GRAPH.top);
 let content=grid([0,1,2,3],y,v=>v===0?'null':'Level '+v);
 const series=[['probe_siaga_level',1,'#b39122','Siaga'],['probe_waspada_level',2,'#d57d26','Waspada'],['probe_bahaya_level',3,'#cb5656','Bahaya']];
 for(const [key,level,color,name] of series){
  const values=data.map(r=>r[key]===null||r[key]===undefined?0:Number(r[key]));
  content+=lineSegments(data,values,x,y,true).map(p=>`<path d="${p}" fill="none" stroke="${color}" stroke-width="2" opacity="0.85"/>`).join('');
  for(let i=0;i<data.length;i++){
   content+=`<circle cx="${x(i)}" cy="${y(values[i])}" r="${values[i]?3:2}" fill="${color}" opacity="${values[i]?1:.45}"><title>${escSVG(time(data[i].received_at)+' WIB | Probe '+name+': '+(values[i]?'Level '+level:'null'))}</title></circle>`;
  }
 }
 if(!data.length)content+=emptyChart('Belum ada rekaman probe');
 $('probe-chart').innerHTML=graphFrame(content,data,x);
}
function niceCeiling(v){if(v<=1)return 1;const power=10**Math.floor(Math.log10(v));const n=v/power;return (n<=1?1:n<=2?2:n<=5?5:10)*power;}
function rainChart(rows,settings){
 const data=chartData(rows),x=graphX(data),inMM=Number(settings.mm_per_tip)>0;
 const key=inMM?'rain_mm':'rain_tips_total',unit=inMM?'mm':'tip';
 const values=data.map(r=>r[key]===null||r[key]===undefined?null:Number(r[key]));
 const valid=values.filter(v=>v!==null&&Number.isFinite(v));
 const maximum=niceCeiling(Math.max(1,...valid));
 const ticks=[...new Set([0,Math.round(maximum/4),Math.round(maximum/2),Math.round(maximum*3/4),maximum])];
 const y=v=>GRAPH.bottom-Math.max(0,v)/maximum*(GRAPH.bottom-GRAPH.top);
 let content=grid(ticks,y,v=>val(v));
 const paths=lineSegments(data,values,x,y,true,true);
 for(const p of paths){
  const first=p.match(/^M([\d.]+) /),last=[...p.matchAll(/(?:M|L|H)([\d.]+)/g)].pop();
  if(first&&last)content+=`<path d="${p} L${last[1]} ${GRAPH.bottom} L${first[1]} ${GRAPH.bottom} Z" fill="#e1f0fb"/>`;
 }
 content+=paths.map(p=>`<path d="${p}" fill="none" stroke="#2879ad" stroke-width="2.5"/>`).join('');
 for(let i=0;i<data.length;i++)if(values[i]!==null){
  content+=`<circle cx="${x(i)}" cy="${y(values[i])}" r="2.5" fill="#2879ad"><title>${escSVG(time(data[i].received_at)+' WIB | '+val(values[i])+' '+unit+' | total sejak boot')}</title></circle>`;
 }
 if(!valid.length)content+=emptyChart('Belum ada data hujan');
 $('rain-chart').innerHTML=graphFrame(content,data,x);
 $('rain-chart-unit').textContent=inMM?'Curah hujan (mm)':'Jumlah tip';
 $('rain-chart-note').textContent=inMM?'Kumulatif sejak alat menyala. Garis dipisah saat ESP32 restart.':'Spesifikasi mm/tip belum ditetapkan; grafik menampilkan tip asli, bukan perkiraan mm.';
}
// Nol tetap ditampilkan; data kosong tidak diubah menjadi nol.
function vibrationValue(record){
 if(!record || !(record.vibration_sampled===true || record.vibration_sampled===1))return null;
 const value=record.vibration_value;
 if(typeof value!=='number' || !Number.isFinite(value) || value<0 || value>65535)return null;
 return Math.round(value);
}
function renderVibration(latest){
 const node=$('vibration'),state=$('vibration-state');
 if(!node || !state)return;
 const record=latest.data,value=vibrationValue(record);
 const measuredAge=record&&record.vibration_age_ms;
 const serverAge=record&&Number(record.age_seconds??0);
 const fresh=latest.online && value!==null && typeof measuredAge==='number' &&
  Number.isFinite(measuredAge) && measuredAge>=0 && Number.isFinite(serverAge) &&
  measuredAge+Math.max(0,serverAge)*1000<=2500;
 node.textContent=fresh?val(value):'—';
 state.textContent=!latest.online?'Perangkat offline.':value===null?
  'Belum ada pengukuran getaran.':!fresh?'Menunggu pengukuran terbaru.':
  'Pengukuran terbaru · '+val(record.vibration_samples)+' sampel';
}
function render(latest,history){renderVibration(latest);const s=latest.settings,d=latest.data;currentSettings=s;if(!filled){for(const k of ['height_cm','offset_cm','siaga_cm','waspada_cm','bahaya_cm'])form.elements[k].value=s[k];form.elements.enabled.checked=s.enabled;filled=true;}
$('connection').textContent=latest.online?'● Perangkat online':'● Perangkat offline';$('connection').className='pill'+(latest.online?' online':'');
const fresh=latest.online&&d&&d.a16_valid;
$('distance').textContent=fresh?val(d.distance_mm/10)+' cm':'—';$('height').textContent=fresh&&d.height_cm!==null?val(d.height_cm)+' cm':'—';
$('status').textContent=!latest.online?'OFFLINE':!d.a16_valid?'DATA TIDAK VALID':!s.enabled?'KALIBRASI DULU':d.config_version!==s.version?'SINKRONISASI':d.calculated_status===0?'NORMAL':!d.validation_match?'BELUM COCOK':d.device_status!==d.calculated_status?'VALIDASI…':names[d.device_status];
$('a16-level').textContent='Level A16: '+(fresh&&s.enabled?names[d.calculated_status]:'—');
$('rain').textContent=!latest.online?'—':d.rain_mm===null?'Belum tersedia':val(d.rain_mm)+' mm';
$('intensity').textContent=!latest.online?'—':d.rain_intensity_mmh===null?'Belum tersedia':val(d.rain_intensity_mmh)+' mm/jam';
$('tips').textContent=latest.online?'Jumlah tip: '+d.rain_tips_total+(s.mm_per_tip===0?' · ':''):'';
$('relay').textContent='Relay: '+(!latest.online?'tidak diketahui':d.relay_on?'AKTIF':'MATI');
['p1','p2','p3'].forEach((id,i)=>$(id).textContent=!latest.online?'—':probe(d[['probe_siaga_level','probe_waspada_level','probe_bahaya_level'][i]]));
$('notice').textContent=!d?'Belum ada data. Konfigurasikan alamat API pada ESP32.':!latest.online?'Perangkat offline. Riwayat tetap tersimpan; nilai lama tidak ditampilkan sebagai data langsung.':!d.a16_valid?'Komunikasi perangkat aktif, tetapi pembacaan A16 tidak valid.':!s.enabled?'Jarak asli tersedia. Ukur tinggi pemasangan, lalu simpan kalibrasi untuk mengaktifkan tinggi air dan alarm.':' '+time(d.received_at)+' ';
$('notice').className='notice'+(!latest.online||!d?.a16_valid?' error':'');
$('sync').textContent=d?(' '+d.config_version+(d.config_version===s.version?' · sinkron':' · menunggu ESP32 mengambil pengaturan')):'Belum ada konfirmasi perangkat.';
$('rows').replaceChildren();for(const r of history.rows){const tr=document.createElement('tr');cell(tr,time(r.received_at));cell(tr,r.a16_valid?val(r.distance_mm/10)+' cm':'Tidak valid');cell(tr,r.height_cm===null?(r.a16_valid?'Belum tersimpan':'—'):val(r.height_cm)+' cm');cell(tr,r.calculated_status===null?'—':names[r.calculated_status]);cell(tr,r.device_status===0?'Tidak aktif':names[r.device_status]);cell(tr,[r.probe_siaga_level,r.probe_waspada_level,r.probe_bahaya_level].map(probe).join(' / '));cell(tr,r.rain_mm===null?'Spesifikasi belum diisi':val(r.rain_mm)+' mm');cell(tr,vibrationValue(r)===null?'—':val(vibrationValue(r)));cell(tr,r.relay_on?'Aktif':'Mati');$('rows').appendChild(tr);}if(!history.rows.length){const tr=document.createElement('tr');cell(tr,'Belum ada rekaman.');tr.children[0].colSpan=9;$('rows').appendChild(tr);}chart(history.rows,s);probeChart(history.rows);rainChart(history.rows,s);const c=latest.audio_command;if(!c)$('audio-state').textContent='Belum ada perintah tes untuk '+selectedDevice+'.';if(c){$('audio-state').textContent='Tes #'+c.id+' · 000'+c.track+'.mp3 · '+(c.state==='processed'?'Perintah putar sudah diproses ESP32; dengarkan speaker untuk konfirmasi.':c.state==='expired'?'Kedaluwarsa, belum dikonfirmasi perangkat.':'Menunggu ESP32 mengambil perintah.');}
$('updated').textContent='Diperbarui '+new Date().toLocaleTimeString('id-ID');}
async function poll(){try{const [l,h]=await Promise.all([api('latest'),api('history')]);if(l.build!=='DEWAS-V10')throw Error('Versi API masih lama. Timpa file di folder dewasbanjir, lalu Ctrl+F5.');render(l,h);}catch(e){$('notice').textContent=e.message;$('notice').className='notice error';$('connection').textContent='Koneksi server terputus';$('connection').className='pill';for(const id of ['distance','height','status','p1','p2','p3','rain','intensity','a16-level'])$(id).textContent='—';$('relay').textContent='Relay: tidak diketahui';if($('vibration'))$('vibration').textContent='—';if($('vibration-state'))$('vibration-state').textContent='Koneksi server terputus.';}finally{setTimeout(poll,1000);}}
form.addEventListener('submit',async e=>{e.preventDefault();const d={};for(const k of ['height_cm','offset_cm','siaga_cm','waspada_cm','bahaya_cm'])d[k]=Number(form.elements[k].value);d.enabled=form.elements.enabled.checked;const b=form.querySelector('button');b.disabled=true;try{const r=await api('save',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(d)});$('save-message').textContent='Pengaturan '+selectedDevice+' tersimpan. Versi '+r.settings.version+'.';}catch(e){$('save-message').textContent=e.message;}finally{b.disabled=false;}});
poll();

for(const button of document.querySelectorAll('[data-track]')){
 button.addEventListener('click',async()=>{
  const buttons=document.querySelectorAll('[data-track]');buttons.forEach(b=>b.disabled=true);
  try{
   const d=await api('test_audio',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({track:Number(button.dataset.track),device_id:selectedDevice})});
   $('audio-message').textContent=d.message+' Nomor #'+d.id;
  }catch(e){$('audio-message').textContent=e.message;}
  finally{buttons.forEach(b=>b.disabled=false);}
 });
}
