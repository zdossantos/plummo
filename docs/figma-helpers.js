// Helpers for editable Plummo Figma mockups. Injected after STATE in each script.
const page = await figma.getNodeByIdAsync(STATE.pageId);
await figma.setCurrentPageAsync(page);
await Promise.all(['Regular', 'SemiBold', 'Bold'].map(style => figma.loadFontAsync({family:'Poppins', style})));
const createdNodeIds = [], mutatedNodeIds = [], roots = [], links = [];
const variables = {};
for (const [mode, values] of Object.entries(STATE.tokens)) {
  if (typeof values === 'object') {
    variables[mode] = {};
    for (const [key, id] of Object.entries(values)) variables[mode][key] = await figma.variables.getVariableByIdAsync(id);
  } else variables[mode] = await figma.variables.getVariableByIdAsync(values);
}
function remember(n) {createdNodeIds.push(n.id); return n;}
function paint(key, mode='light') {return [figma.variables.setBoundVariableForPaint({type:'SOLID',color:{r:0,g:0,b:0}},'color',variables[mode][key])];}
function radius(n, size=16) {for(const field of ['topLeftRadius','topRightRadius','bottomLeftRadius','bottomRightRadius']) n.setBoundVariable(field,variables['radius'+size]);}
function box(parent, name, w, h, key=null, mode='light', dir='VERTICAL', gap=12, pad=0) {
  const n=remember(figma.createAutoLayout(dir));n.name=name;parent.appendChild(n);n.resize(w,h);
  n.primaryAxisSizingMode='FIXED';n.counterAxisSizingMode='FIXED';n.primaryAxisAlignItems='MIN';n.counterAxisAlignItems='MIN';
  n.fills=key?paint(key,mode):[];n.itemSpacing=gap;
  if(variables['gap'+gap])n.setBoundVariable('itemSpacing',variables['gap'+gap]);
  for(const field of ['paddingTop','paddingBottom','paddingLeft','paddingRight']){n[field]=pad;if(pad===24)n.setBoundVariable(field,variables.pad24);}
  return n;
}
function txt(parent, value, style='body', width=300, key='text', mode='light', name=null) {
  const n=remember(figma.createText());parent.appendChild(n);n.name=name||value.slice(0,48);n.textStyleId=STATE.styles[style];n.characters=value;n.fills=paint(key,mode);n.textAutoResize='HEIGHT';n.resize(width,n.height);return n;
}
function fixedText(parent,value,size,width,key='text',mode='light',bold=false){const n=txt(parent,value,'body',width,key,mode);n.fontName={family:'Poppins',style:bold?'Bold':'Regular'};n.fontSize=size;n.lineHeight={unit:'PIXELS',value:size*1.35};return n;}
function absolute(n,x,y){n.layoutPositioning='ABSOLUTE';n.x=x;n.y=y;return n;}
function rule(parent,w,mode='light'){const n=remember(figma.createRectangle());parent.appendChild(n);n.resize(w,1);n.fills=paint('border',mode);return n;}
function mascot(parent,size){const n=remember(figma.createRectangle());parent.appendChild(n);n.name='Mascotte fournie';n.resize(size,size);n.cornerRadius=16;n.fills=[{type:'IMAGE',imageHash:STATE.imageHash,scaleMode:'FIT'}];return n;}
const components={};
if(STATE.componentIds)for(const [key,id]of Object.entries(STATE.componentIds))components[key]=await figma.getNodeByIdAsync(id);
function instance(parent,key,w,h){const n=components[key].createInstance();parent.appendChild(n);n.resize(w,h);remember(n);for(const c of n.findAll(()=>true))createdNodeIds.push(c.id);return n;}
function replace(n,name,value){const t=n.findOne(c=>c.type==='TEXT'&&c.name===name);if(t)t.characters=value;return t;}
function button(parent,label,w=342,kind='Primary',mode='light',state='Default',target=null){
  const n=instance(parent,'Button/'+mode+'/'+kind+'/'+state,w,52);replace(n,'Label',label);n.name=label;
  if(target)links.push({id:n.id,target});return n;
}
function choice(parent,label,detail='',w=342,h=76,mode='light',state='Default',target=null){
  const n=instance(parent,'Choice/'+mode+'/'+state,w,h);replace(n,'Label',label);replace(n,'Detail',detail);n.name=label;
  if(target)links.push({id:n.id,target});return n;
}
function field(parent,label,value,w=342,mode='light',state='Default',h=76){const n=instance(parent,'Field/'+mode+'/'+state,w,h);replace(n,'Label',label);replace(n,'Value',value);return n;}
function info(parent,title,detail,w=342,mode='light',tone='soft',h=100){const n=box(parent,title,w,h,tone,mode,'VERTICAL',8,16);radius(n);txt(n,title,'label',w-32,'text',mode);if(detail)txt(n,detail,'small',w-32,'muted',mode);return n;}
function avatar(parent,name,w=72,mode='light'){const n=instance(parent,'Avatar/'+mode,w,w+28);replace(n,'Name',name);return n;}
function phone(name,title,subtitle='',mode='light',index=0){
  const n=box(page,name,390,844,'bg',mode,'VERTICAL',16,24);n.x=400+(index%6)*510;n.y=400+Math.floor(index/6)*1060;n.clipsContent=true;n.overflowDirection='VERTICAL';roots.push({id:n.id,name,mode});
  const head=box(n,'Identité et commandes',342,32,null,mode,'HORIZONTAL',12);txt(head,'plummo','label',190,'accent',mode);const side=txt(head,'Salon K7M2','small',140,'muted',mode);side.textAlignHorizontal='RIGHT';
  txt(n,title,'phoneTitle',342,'text',mode);if(subtitle)txt(n,subtitle,'small',342,'muted',mode);
  const body=box(n,'Action du joueur',342,620,null,mode,'VERTICAL',12);body.primaryAxisSizingMode='AUTO';body.layoutSizingVertical='HUG';return {frame:n,body,mode};
}
function chat(parent,mode='light',w=342){info(parent,'À toi de patienter','Tu peux envoyer une bulle à ton Plummo.',w,mode,'soft',78);field(parent,'Ta bulle · 0 / 80','Écris un message…',w,mode);button(parent,'Envoyer la bulle',w,'Secondary',mode);}
function tv(name,title,sub='',index=0,mode='dark'){
  const n=box(page,name,1440,810,'bg',mode,'VERTICAL',24,48);n.x=400+(index%3)*1620;n.y=400+Math.floor(index/3)*1060;n.clipsContent=true;roots.push({id:n.id,name,mode});
  const head=box(n,'Bandeau',1344,50,null,mode,'HORIZONTAL',24);txt(head,'plummo','tvLabel',330,'accent',mode);txt(head,sub,'tvLabel',700,'muted',mode);const c=txt(head,'K7M2','tvLabel',250,'muted',mode);c.textAlignHorizontal='RIGHT';
  txt(n,title,'tvTitle',1300,'text',mode);
  const body=box(n,'Scène',1344,448,null,mode,'VERTICAL',24);return {frame:n,body,mode};
}
function roster(frame,mode='dark',winner=null){
  const r=box(frame,'Plummos · toujours en bas à droite',680,122,null,mode,'HORIZONTAL',12);absolute(r,712,664);
  ['Léa','Sami','Jules','Nora','Lou','Alex','Mia','Noé'].forEach(name=>{const a=avatar(r,name,72,mode);if(name===winner){a.y=-14;}});return r;
}
function tvChoice(parent,label,detail='',w=640,h=92,mode='dark',state='Default'){
  const n=choice(parent,label,detail,w,h,mode,state);const t=n.findOne(c=>c.type==='TEXT'&&c.name==='Label');t.fontSize=24;t.lineHeight={unit:'PIXELS',value:32};const d=n.findOne(c=>c.type==='TEXT'&&c.name==='Detail');d.fontSize=18;d.lineHeight={unit:'PIXELS',value:24};return n;
}
function drawing(parent,w,h,mode='light'){
  const f=box(parent,'Dessin partagé · exemple de manche',w,h,'surface',mode);radius(f);
  const svg='<svg width="560" height="360" viewBox="0 0 560 360" xmlns="http://www.w3.org/2000/svg"><g fill="none" stroke="#643B86" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"><path d="M190 280L370 280L370 174L190 174Z M164 178L280 82L397 178 M258 280L258 218L300 218L300 280 M315 196L349 196L349 231L315 231Z M318 116L318 66L345 66L345 139"/></g></svg>';
  const v=remember(figma.createNodeFromSvg(svg));f.appendChild(v);v.name='Tracé de joueur : maison';v.resize(Math.min(w-24,560),Math.min(h-24,360));absolute(v,(w-v.width)/2,(h-v.height)/2);for(const c of v.findAll(()=>true))createdNodeIds.push(c.id);return f;
}
function report(extra={}){const grouped={};for(const id of new Set(createdNodeIds)){const cut=id.lastIndexOf(':')+1;const prefix=id.slice(0,cut);(grouped[prefix]??=[]).push(id.slice(cut));}return {createdNodeIdsByPrefix:grouped,idEncoding:'concatenate each prefix and suffix to recover every created node ID',mutatedNodeIds,roots,links,...extra};}
