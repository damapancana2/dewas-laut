<?php
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();

if (empty($_SESSION['dewaslaut_csrf'])) {
    $_SESSION['dewaslaut_csrf'] = bin2hex(random_bytes(24));
}

$csrf = $_SESSION['dewaslaut_csrf'];

session_write_close();
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Monitoring &amp; Konfigurasi</title>

    <link rel="stylesheet" href="assets/style.css?v=DEWAS-LAUT-V1">

    <!-- Penataan khusus panel tes suara; cukup mengganti index.php. -->
    <style>
        .audio-test .section-head {
            margin-bottom: 18px;
        }

        .audio-test .audio-buttons {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            align-items: stretch;
        }

        .audio-test .audio-buttons button {
            width: 100%;
            min-width: 0;
            min-height: 56px;
            margin: 0;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1.4;
        }

        .audio-test #audio-message {
            margin: 18px 0 0;
            padding-top: 16px;
            border-top: 1px solid #e2eceb;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .audio-test #audio-state {
            display: block;
            margin-top: 8px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1100px) {
            .audio-test .audio-buttons {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .audio-test .audio-buttons {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <!-- SIDEBAR -->
    <aside>
        <div class="brand">
            <span>
                <br>
                <small>Monitoring &amp; Konfigurasi ESP32</small>
            </span>
        </div>

        

        <div class="nav">
            ⚙ &nbsp; Kalibrasi sensor
        </div>

        <div class="aside-foot">
            
            <br>
            <b id="station-name">Stasiun laut-001</b>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main>
        <!-- HEADER -->
        <header>
            <div>
                <span class="eyebrow">
                    Monitoring &amp; Konfigurasi
                </span>

                <h1></h1>
                <p></p>
            </div>

            <span class="pill" id="connection">
                Menghubungkan…
            </span>
        </header>

        <!-- INFORMASI KONEKSI -->
        <div class="notice" id="notice" role="status">
            Menunggu data perangkat.
        </div>

        <!-- RINGKASAN SENSOR A16 -->
        <div class="cards">
            <article class="metric">
                <span>JARAK SENSOR SEBELUM KALIBRASI</span>

                <h2 id="distance">—</h2>

                
            </article>

            <article class="metric primary">
                <span>TINGGI AIR TERKALIBRASI</span>

                <h2 id="height">—</h2>

                <p>Titik nol mengikuti pemasangan sensor</p>
            </article>

            <article class="metric">
                <span>STATUS TINGGI AIR</span>

                <h2 class="status-text" id="status">—</h2>

                <p id="relay">Relay: —</p>
                <p id="a16-level">Level: —</p>
            </article>
        </div>

        <!-- TES SUARA DFPLAYER -->
        <section class="panel audio-test">
            <div class="section-head">
                <div>
                    <span class="eyebrow">PENGUJIAN MANUAL</span>
                    <h3>Tes suara</h3>
                </div>

              
            </div>

            <div class="audio-buttons">
                <button type="button" data-track="4">
                    ▶ Suara Tes
                </button>
                <button type="button" data-track="1">
                    ▶ Tes Waspada
                </button>

                <button type="button" data-track="2">
                    ▶ Tes Siaga
                </button>

                <button type="button" data-track="3">
                    ▶ Tes Awas
                </button>
            </div>

            <p id="audio-message" role="status">
              
            </p>

            <small id="audio-state">
                Belum ada perintah tes.
            </small>
        </section>

        <div class="rain-cards sensor-summary">
            <article class="metric"><span>KECEPATAN ANGIN</span><h2 id="wind">—</h2><p id="wind-note"></p></article>
            <article class="metric"><span>TEKANAN UDARA</span><h2 id="pressure">—</h2></article>
            <article class="metric"><span>SUHU BMP180</span><h2 id="bmp-temp">—</h2></article>
        </div>

        <!-- GRAFIK A16 DAN PENGATURAN -->
        <div class="layout">
            <!-- GRAFIK TINGGI AIR -->
            <section class="panel">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">DATA SENSOR</span>
                        <h3>Tinggi air &amp; ambang peringatan</h3>
                    </div>

                   
                </div>

                <div class="chart-wrap">
                    <svg
                        id="chart"
                        viewBox="0 0 860 260"
                        role="img"
                        aria-label="Grafik tinggi air"
                    >
                        <text x="20" y="100">
                            Belum ada data
                        </text>
                    </svg>
                </div>

                <div class="graph-legend">
                    <span>
                        <i class="dot green"></i>
                        Tinggi air (cm)
                    </span>

                    <span>
                        <i class="dot yellow"></i>
                        Waspada
                    </span>

                    <span>
                        <i class="dot orange"></i>
                        Siaga
                    </span>

                    <span>
                        <i class="dot red"></i>
                        Awas
                    </span>
                </div>

                <p class="chart-note" id="a16-chart-note">
                    Skala mengikuti ambang pengaturan.
                    Nilai negatif digambar di garis 0.
                </p>

                <div class="probes">
                    <div>
                        PROBE WASPADA
                        <b id="p1">—</b>
                       
                    </div>

                    <div>
                        PROBE SIAGA
                        <b id="p2">—</b>
                       
                    </div>

                    <div>
                        PROBE AWAS
                        <b id="p3">—</b>
                       
                    </div>
                </div>
            </section>

            <!-- FORM KALIBRASI -->
            <section class="panel">
                <span class="eyebrow">PENGATURAN</span>

                <h3>Kalibrasi</h3>

                

                <form id="settings">
                    <label>
                        Tinggi pemasangan
                        <span>cm</span>

                        <input
                            name="height_cm"
                            type="number"
                            step="1"
                            required
                        >
                    </label>

                    <label>
                        Offset kalibrasi
                        <span>cm</span>

                        <input
                            name="offset_cm"
                            type="number"
                            step="1"
                            required
                        >
                    </label>

                    <div class="thresholds">
                        <label>
                            Waspada

                            <input
                                name="waspada_cm"
                                type="number"
                                step="1"
                                required
                            >
                        </label>

                        <label>
                            Siaga

                            <input
                                name="siaga_cm"
                                type="number"
                                step="1"
                                required
                            >
                        </label>

                        <label>
                            Awas

                            <input
                                name="awas_cm"
                                type="number"
                                step="1"
                                required
                            >
                        </label>
                    </div>

                    <label class="check">
                        <input name="enabled" type="checkbox">

                        Aktifkan 
                    </label>

                    <button>Simpan pengaturan</button>

                    <p id="save-message" role="status"></p>

                    <small id="sync">
                        Belum ada informasi sinkronisasi.
                    </small>
                </form>
            </section>
        </div>

        <!-- GRAFIK PROBE DAN KONDISI LINGKUNGAN -->
        <div class="chart-grid">
            <!-- GRAFIK LEVEL PROBE -->
            <section class="panel">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">VALIDASI AIR</span>
                        <h3>Grafik Water Level</h3>
                    </div>

                  
                </div>

                <div class="chart-wrap">
                    <svg
                        id="probe-chart"
                        viewBox="0 0 860 260"
                        role="img"
                        aria-label="Grafik level tiga probe"
                    >
                        <text x="30" y="110">
                            Menunggu data probe
                        </text>
                    </svg>
                </div>

                <div class="graph-legend">
                    <span>
                        <i class="dot yellow"></i>
                        Waspada
                    </span>

                    <span>
                        <i class="dot orange"></i>
                        Siaga
                    </span>

                    <span>
                        <i class="dot red"></i>
                        Awas
                    </span>
                </div>

               
            </section>

            <section class="panel">
                <div class="section-head"><h3>Grafik Angin</h3><span class="chart-badge blue" id="wind-unit"></span></div>
                <div class="chart-wrap"><svg id="wind-chart" viewBox="0 0 860 260" role="img" aria-label="Grafik Angin"></svg></div>
            </section>
            <section class="panel">
                <div class="section-head"><h3>Grafik Tekanan Udara</h3><span class="chart-badge blue" id="pressure-unit"></span></div>
                <div class="chart-wrap"><svg id="pressure-chart" viewBox="0 0 860 260" role="img" aria-label="Grafik Tekanan Udara"></svg></div>
            </section>
            <section class="panel">
                <div class="section-head"><h3>Grafik Suhu BMP180</h3><span class="chart-badge blue" id="bmp-temp-unit"></span></div>
                <div class="chart-wrap"><svg id="bmp-temp-chart" viewBox="0 0 860 260" role="img" aria-label="Grafik Suhu BMP180"></svg></div>
            </section>
        </div>

        <!-- TABEL RIWAYAT -->
        <section class="panel history">
            <div class="section-head">
                <h3>Riwayat pengukuran</h3>

                
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu WIB</th>
                            <th>Jarak asli</th>
                            <th>Tinggi air</th>
                            <th>Level A16</th>
                            <th>Alarm perangkat</th>
                            <th>Probe Waspada / Siaga / Awas</th>
                            <th>Angin</th>
                            <th>Tekanan udara</th>
                            <th>Suhu BMP180</th>
                            <th>Relay</th>
                        </tr>
                    </thead>

                    <tbody id="rows">
                        <tr>
                            <td colspan="10">
                                Belum ada data tersimpan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- FOOTER -->
        <footer>
            DEWAS LAUT

            <span id="updated">—</span>
        </footer>
    </main>

    <script>
        const CSRF = <?= json_encode($csrf) ?>;
    </script>

    <!-- Skrip terpadu agar pembaruan tidak tertahan cache assets/app.js. -->
    <script>
'use strict';
const $=id=>document.getElementById(id), form=$('settings');
const urlParams = new URLSearchParams(window.location.search);
const selectedDevice = urlParams.get('device_id') || 'laut-001';
$('station-name').textContent='Stasiun '+selectedDevice;
let filled=false, currentSettings=null;
const names=['NORMAL','WASPADA','SIAGA','AWAS'];
const val=n=>n===null||n===undefined?'—':Math.round(Number(n)).toString();
const probe=x=>x===null||x===undefined?'null':'Level '+x;
const cell=(row,text)=>{const c=document.createElement('td');c.textContent=text;row.appendChild(c);};
const time=s=>new Date(s.replace(' ','T')+'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta'});
async function api(action,options={}){const r=await fetch('api.php?action='+action+'&device_id='+encodeURIComponent(selectedDevice)+'&_='+Date.now(),{cache:'no-store',...options});const d=await r.json();if(!r.ok)throw Error(d.error||'HTTP '+r.status);return d;}
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
 const siaga=Number(settings.waspada_cm),waspada=Number(settings.siaga_cm),bahaya=Number(settings.awas_cm);
 const maximum=Math.ceil(bahaya+Math.max(10,(bahaya-waspada)*0.5));
 return {siaga,waspada,bahaya,maximum,ticks:[...new Set([0,siaga,waspada,bahaya,maximum])].sort((a,b)=>a-b)};
}
function chart(rows,settings){
 const data=chartData(rows),x=graphX(data),scale=a16Scale(settings);
 const y=v=>GRAPH.bottom-Math.max(0,Math.min(scale.maximum,v))/scale.maximum*(GRAPH.bottom-GRAPH.top);
 const bands=[{lo:0,hi:scale.siaga,color:'#edf7f3'},{lo:scale.siaga,hi:scale.waspada,color:'#fff8dc'},{lo:scale.waspada,hi:scale.bahaya,color:'#fff0df'},{lo:scale.bahaya,hi:scale.maximum,color:'#ffedeb'}];
 let content=bands.map(v=>`<rect x="${GRAPH.left}" y="${y(v.hi)}" width="${GRAPH.right-GRAPH.left}" height="${y(v.lo)-y(v.hi)}" fill="${v.color}"/>`).join('');
 content+=grid(scale.ticks,y,v=>val(v));
 for(const [v,name,color] of [[scale.siaga,'Waspada','#aa8d19'],[scale.waspada,'Siaga','#c67622'],[scale.bahaya,'Awas','#c14b4b']]){
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
 const series=[['probe_waspada_level',1,'#b39122','Waspada'],['probe_siaga_level',2,'#d57d26','Siaga'],['probe_awas_level',3,'#cb5656','Awas']];
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
const fmtSensor=(value,unit)=>Number.isFinite(value)?value.toLocaleString('id-ID',{maximumFractionDigits:2})+' '+unit:'—';
const windText=r=>r?.wind_valid?fmtSensor(r.wind_mps,'m/s'):fmtSensor(r?.wind_hz,'Hz');
function sensorChart(id,rows,key,unit,validKey){
 const data=chartData(rows),x=graphX(data);
 const values=data.map(r=>(!validKey||r[validKey])&&Number.isFinite(r[key])?r[key]:null);
 const points=values.filter(Number.isFinite);
 $(id+'-unit').textContent=unit;
 if(!points.length){$(id+'-chart').innerHTML=emptyChart('Belum ada data');return;}
 const min=Math.min(...points),max=Math.max(...points);
 const pad=max===min?Math.max(0.1,Math.abs(min)*0.01):(max-min)*0.1;
 const lo=min-pad,hi=max+pad,y=v=>GRAPH.bottom-(v-lo)/(hi-lo)*(GRAPH.bottom-GRAPH.top);
 const ticks=Array.from({length:5},(_,i)=>lo+i*(hi-lo)/4);
 let content=grid(ticks,y,v=>fmtSensor(v,'').trim());
 for(const path of lineSegments(data,values,x,y,false,true))content+=`<path d="${path}" fill="none" stroke="#3186af" stroke-width="2"/>`;
 values.forEach((v,i)=>{if(v!==null)content+=`<circle cx="${x(i)}" cy="${y(v)}" r="2" fill="#3186af"/>`;});
 $(id+'-chart').innerHTML=graphFrame(content,data,x);
}
function render(latest,history){const s=latest.settings,d=latest.data;currentSettings=s;if(!filled){for(const k of ['height_cm','offset_cm','waspada_cm','siaga_cm','awas_cm'])form.elements[k].value=s[k];form.elements.enabled.checked=s.enabled;filled=true;}
$('connection').textContent=latest.online?'● Perangkat online':'● Perangkat offline';$('connection').className='pill'+(latest.online?' online':'');
const fresh=latest.online&&d&&d.a16_valid;
$('distance').textContent=fresh?val(d.distance_mm/10)+' cm':'—';$('height').textContent=fresh&&d.height_cm!==null?val(d.height_cm)+' cm':'—';
$('status').textContent=!latest.online?'OFFLINE':!d.a16_valid?'DATA TIDAK VALID':!s.enabled?'KALIBRASI DULU':d.config_version!==s.version?'SINKRONISASI':d.calculated_status===0?'NORMAL':!d.validation_match?'BELUM COCOK':d.device_status!==d.calculated_status?'VALIDASI…':names[d.device_status];
$('a16-level').textContent='Level A16: '+(fresh&&s.enabled?names[d.calculated_status]:'—');
const live=latest.online?d:null;
$('wind').textContent=windText(live);
$('wind-note').textContent=live&&Number.isFinite(live.wind_hz)&&!live.wind_valid?'Frekuensi pulsa; faktor m/s belum tersedia.':'';
$('pressure').textContent=fmtSensor(live?.bmp180_valid?live.pressure_hpa:null,'hPa');
$('bmp-temp').textContent=fmtSensor(live?.bmp180_valid?live.bmp180_temp_c:null,'°C');
$('relay').textContent='Relay: '+(!latest.online?'tidak diketahui':d.relay_on?'AKTIF':'MATI');
['p1','p2','p3'].forEach((id,i)=>$(id).textContent=!latest.online?'—':probe(d[['probe_waspada_level','probe_siaga_level','probe_awas_level'][i]]));
$('notice').textContent=!d?'Belum ada data. Konfigurasikan alamat API pada ESP32.':!latest.online?'Perangkat offline. Riwayat tetap tersimpan; nilai lama tidak ditampilkan sebagai data langsung.':!d.a16_valid?'Komunikasi perangkat aktif, tetapi pembacaan A16 tidak valid.':!s.enabled?'Jarak asli tersedia. Ukur tinggi pemasangan, lalu simpan kalibrasi untuk mengaktifkan tinggi air dan alarm.':' '+time(d.received_at)+' ';
$('notice').className='notice'+(!latest.online||!d?.a16_valid?' error':'');
$('sync').textContent=d?(' '+d.config_version+(d.config_version===s.version?' · sinkron':' · menunggu ESP32 mengambil pengaturan')):'Belum ada konfirmasi perangkat.';
$('rows').replaceChildren();for(const r of history.rows){const tr=document.createElement('tr');cell(tr,time(r.received_at));cell(tr,r.a16_valid?val(r.distance_mm/10)+' cm':'Tidak valid');cell(tr,r.height_cm===null?(r.a16_valid?'Belum tersimpan':'—'):val(r.height_cm)+' cm');cell(tr,r.calculated_status===null?'—':names[r.calculated_status]);cell(tr,r.device_status===0?'Tidak aktif':names[r.device_status]);cell(tr,[r.probe_waspada_level,r.probe_siaga_level,r.probe_awas_level].map(probe).join(' / '));cell(tr,windText(r));cell(tr,fmtSensor(r.bmp180_valid?r.pressure_hpa:null,'hPa'));cell(tr,fmtSensor(r.bmp180_valid?r.bmp180_temp_c:null,'°C'));cell(tr,r.relay_on?'Aktif':'Mati');$('rows').appendChild(tr);}if(!history.rows.length){const tr=document.createElement('tr');cell(tr,'Belum ada rekaman.');tr.children[0].colSpan=10;$('rows').appendChild(tr);}chart(history.rows,s);probeChart(history.rows);const speed=history.rows.some(r=>r.wind_valid&&Number.isFinite(r.wind_mps));
sensorChart('wind',history.rows,speed?'wind_mps':'wind_hz',speed?'m/s':'Hz',speed?'wind_valid':null);
sensorChart('pressure',history.rows,'pressure_hpa','hPa','bmp180_valid');
sensorChart('bmp-temp',history.rows,'bmp180_temp_c','°C','bmp180_valid');const c=latest.audio_command;if(!c)$('audio-state').textContent='Belum ada perintah tes untuk '+selectedDevice+'.';if(c){$('audio-state').textContent='Tes #'+c.id+' · 000'+c.track+'.mp3 · '+(c.state==='processed'?'Perintah putar sudah diproses ESP32; dengarkan speaker untuk konfirmasi.':c.state==='expired'?'Kedaluwarsa, belum dikonfirmasi perangkat.':'Menunggu ESP32 mengambil perintah.');}
$('updated').textContent='Diperbarui '+new Date().toLocaleTimeString('id-ID');}
async function poll(){try{const [l,h]=await Promise.all([api('latest'),api('history')]);if(!l.build||!l.build.startsWith('DEWAS-LAUT'))throw Error('Versi API tidak cocok.');render(l,h);}catch(e){$('notice').textContent=e.message;$('notice').className='notice error';$('connection').textContent='Koneksi server terputus';$('connection').className='pill';for(const id of ['distance','height','status','p1','p2','p3','wind','pressure','bmp-temp','a16-level'])$(id).textContent='—';$('relay').textContent='Relay: tidak diketahui';$('wind-note').textContent='';}finally{setTimeout(poll,1000);}}
form.addEventListener('submit',async e=>{e.preventDefault();const d={device_id:selectedDevice};for(const k of ['height_cm','offset_cm','waspada_cm','siaga_cm','awas_cm'])d[k]=Number(form.elements[k].value);d.enabled=form.elements.enabled.checked;const b=form.querySelector('button');b.disabled=true;try{const r=await api('save',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(d)});$('save-message').textContent='Pengaturan '+selectedDevice+' tersimpan. Versi '+r.settings.version+'.';}catch(e){$('save-message').textContent=e.message;}finally{b.disabled=false;}});
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

    </script>
</body>
</html>