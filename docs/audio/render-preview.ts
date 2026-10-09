import { chromium } from 'playwright';
const browser = await chromium.launch({ headless: true });
try {
 const page = await browser.newPage();
 const samples = await page.evaluate(async () => {
  const sr=44100, beat=60/104, bar=beat*4, length=bar*64;
  let ctx: OfflineAudioContext, master: GainNode, delay: DelayNode;
  const frames=Math.round(length*sr), left=new Float32Array(frames), right=new Float32Array(frames);
  const freq=(m:number)=>440*2**((m-69)/12);
  function tone(m:number,t:number,d:number,v:number,kind='marimba',pan=0) {
   t=Math.max(0,t);
   const env=ctx.createGain(), stereo=ctx.createStereoPanner();stereo.pan.value=pan;
   env.connect(stereo);stereo.connect(master);
   if(kind==='marimba'||kind==='bell')env.connect(delay);
   const attack=kind==='pad'?0.25:kind==='bass'?0.018:0.007;
   env.gain.setValueAtTime(0,t);env.gain.linearRampToValueAtTime(v,t+attack);
   env.gain.exponentialRampToValueAtTime(0.00001,t+d);
   const harmonics=kind==='marimba'?[[1,1],[3,0.18],[7,0.055]]:kind==='bell'?[[1,1],[2.01,0.18],[3.98,0.07]]:kind==='bass'?[[1,1],[2,0.15]]:[[1,1],[2,0.12]];
   for(const [ratio,level] of harmonics){
    const osc=ctx.createOscillator(), g=ctx.createGain();osc.type='sine';osc.frequency.value=freq(m)*ratio;g.gain.value=level;
    osc.connect(g);g.connect(env);osc.start(t);osc.stop(t+d+0.02);
   }
  }
  let seed=72;const rand=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
  function drum(kind:string,t:number,v:number){
   const gain=ctx.createGain();gain.connect(master);gain.gain.setValueAtTime(v,t);
   if(kind==='kick'){
    const osc=ctx.createOscillator();osc.frequency.setValueAtTime(112,t);osc.frequency.exponentialRampToValueAtTime(44,t+0.12);
    gain.gain.exponentialRampToValueAtTime(0.00001,t+0.22);osc.connect(gain);osc.start(t);osc.stop(t+0.23);
   }else if(kind==='wood'){
    const osc=ctx.createOscillator();osc.frequency.setValueAtTime(790,t);osc.frequency.exponentialRampToValueAtTime(380,t+0.025);
    gain.gain.exponentialRampToValueAtTime(0.00001,t+0.06);osc.connect(gain);osc.start(t);osc.stop(t+0.065);
   }else{
    const dur=kind==='brush'?0.11:0.035, buffer=ctx.createBuffer(1,Math.ceil(sr*dur),sr), data=buffer.getChannelData(0);
    for(let i=0;i<data.length;i++)data[i]=(rand()*2-1)*Math.exp(-i/(sr*dur/5));
    const noise=ctx.createBufferSource(),filter=ctx.createBiquadFilter();filter.type='highpass';filter.frequency.value=kind==='brush'?1700:6000;
    noise.buffer=buffer;noise.connect(filter);filter.connect(gain);noise.start(t);
   }
  }
  const chords=[ [48,60,64,67,71],[45,57,60,64,67],[41,57,60,64,69],[43,55,59,62,69], [50,57,60,65,69],[41,57,60,64,69],[48,55,60,64,67],[43,55,59,62,67] ];
  // Four composed two-bar phrases, with rests and answers between motifs.
  const phrases=[
   [[0,72],[0.75,76],[1.5,79],[2.75,76],[4.5,74],[5.25,72],[6.5,67]],
   [[0.5,69],[1,72],[2.5,76],[3,74],[4.25,72],[5.75,69],[6.5,67]],
   [[0,77],[1.25,76],[2,72],[3.25,69],[4.75,72],[5.5,74],[6.75,76]],
   [[0.5,74],[1.75,79],[2.5,77],[4,76],[4.75,74],[6,72]],
  ];
  for(let cycle=0;cycle<1;cycle++){
   seed=72;
   for(let b=0;b<64;b++){
    const section=Math.floor(b/8),local=b%8,base=0,chord=chords[b%8];
    ctx=new OfflineAudioContext(2,Math.ceil((bar+4)*sr),sr);
    master=ctx.createGain();master.gain.value=.65;master.connect(ctx.destination);
    delay=ctx.createDelay();delay.delayTime.value=beat*.75;
    const echo=ctx.createGain();echo.gain.value=.16;delay.connect(echo);echo.connect(master);
    const sparse=section===0||section===4||section===7;
    for(let i=1;i<chord.length;i++)tone(chord[i],base,bar*1.5,sparse?0.023:0.018,'pad',i%2?-.35:.35);
    const bassPattern=sparse?[[0,0],[2.5,7]]:[[0,0],[1.5,12],[2.75,7],[3.5,12]];
    for(const [pos,offset] of bassPattern)tone(chord[0]+offset,base+pos*beat,beat*0.7,0.11,'bass');
    if(section!==0&&section!==4&&section!==7){
     drum('kick',base,0.16);drum('kick',base+2*beat,0.11);
     drum('brush',base+beat,0.09);drum('wood',base+3*beat,0.085);
     for(const p of [0.5,1.5,2.5,3.5])drum('hat',base+p*beat+(rand()-.5)*0.008,0.05+rand()*0.025);
     if(local===7){drum('wood',base+3.25*beat,0.045);drum('wood',base+3.75*beat,0.04);}
    }else if(section===4){drum('wood',base+1.5*beat,0.05);drum('brush',base+3*beat,0.04);}
    if(b%2===0){
     const phrase=phrases[Math.floor(local/2)%4];
     const drop=section===0?local<4:section===4?local===0||local===4:section===7?local>=4:false;
     if(!drop)for(const [p,n] of phrase){
      const variation=section===3||section===6;
      const note=variation&&p>=4?n-12:n;
      tone(note,base+p*beat+(rand()-.5)*0.013,beat*(p%1?1.1:1.4),(sparse?0.068:0.10)*(0.88+rand()*0.2),'marimba',Math.sin(p)*0.25);
     }
    }
    if([2,3,5,6].includes(section)&&local%2===1)
     for(const [p,i] of [[0.75,2],[2.25,3],[3.5,1]])tone(chord[i]+12,base+p*beat,beat*.55,0.033,'marimba',-.4);
    if((section===3||section===6)&&local===6){tone(84,base+3*beat,beat*1.7,0.035,'bell',.35);tone(88,base+3.5*beat,beat*1.4,.026,'bell',-.3);}
    if(section===7&&local===6){tone(79,base+beat,beat*2,0.035,'bell');tone(76,base+2.5*beat,beat*2,.03,'bell');}
    const rendered=await ctx.startRendering(), l=rendered.getChannelData(0),r=rendered.getChannelData(1),offset=Math.round(b*bar*sr);
    for(let i=0;i<l.length;i++){const idx=(offset+i)%frames;left[idx]+=l[i];right[idx]+=r[i];}

   }
  }
  return {channels:[Array.from(left),Array.from(right)],seconds:length};
 });
 const [left,right]=samples.channels;let peak=0,sum=0;
 for(let i=0;i<left.length;i++){peak=Math.max(peak,Math.abs(left[i]),Math.abs(right[i]));sum+=left[i]**2+right[i]**2;}
 if(peak<0.001)throw new Error('Silent render');
 const scale=0.78/peak,data=Buffer.alloc(left.length*4);
 for(let i=0;i<left.length;i++){data.writeInt16LE(Math.round(left[i]*scale*32767),i*4);data.writeInt16LE(Math.round(right[i]*scale*32767),i*4+2);}
 const header=Buffer.alloc(44);header.write('RIFF');header.writeUInt32LE(data.length+36,4);header.write('WAVEfmt ',8);header.writeUInt32LE(16,16);header.writeUInt16LE(1,20);header.writeUInt16LE(2,22);header.writeUInt32LE(44100,24);header.writeUInt32LE(176400,28);header.writeUInt16LE(4,32);header.writeUInt16LE(16,34);header.write('data',36);header.writeUInt32LE(data.length,40);
 await Bun.write('/tmp/plummo-ambiance-v2.wav',Buffer.concat([header,data]));
 console.log(JSON.stringify({seconds:samples.seconds,peak,rms:Math.sqrt(sum/(left.length*2)),exportPeak:0.78}));
} finally{await browser.close();}
