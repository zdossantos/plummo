// Préparation locale : pas encore exécutée dans Figma.
// STATE contient les identifiants des seules maquettes Plummo créées dans cette session.
// Exécuter une surface à la fois. Aucun appel réseau, aucune lecture de comptes ou autre fichier.
const TARGET = 'phone'; // phone, tv ou admin
const page = await figma.getNodeByIdAsync(STATE.pages[TARGET]);
await figma.setCurrentPageAsync(page);
await Promise.all(['Regular','SemiBold','Bold'].map(style=>figma.loadFontAsync({family:'Poppins',style})));
const index=Object.fromEntries(STATE[TARGET].roots.map(r=>[r.name,r.id]));
const affected=[];
async function editText(rootId,before,after){const r=await figma.getNodeByIdAsync(rootId);for(const n of r.findAll(n=>n.type==='TEXT'&&n.characters===before)){n.characters=after;affected.push(n.id);}}
if(TARGET==='phone'){
  await editText('3225:245','3 phrases','1 tour');
  await editText('3225:245','Nombre de manches à confirmer','De 1 à 5 tours · 1 tour = 1 phrase commune');
  await editText('3225:559','Ta suite · 34 / 150','Ta suite · 35 / 150');
  // Les récapitulatifs complémentaires définis dans figma-phone-complements.js
  // sont ajoutés avant de relier les branches Blind test, Dessin et Phrase.
}
if(TARGET==='tv'){
  await editText('3219:3028','Quel film met en scène le personnage de WALL·E ?','Quel film met en scène WALL·E ?');
  for(const r of STATE.tv.roots){const f=await figma.getNodeByIdAsync(r.id);for(const n of f.findAll(n=>n.type==='INSTANCE'&&/^[A-H] ·/.test(n.name))){n.resize(n.width,96);affected.push(n.id);if(n.parent.type==='FRAME')n.parent.resize(n.parent.width,96);}}
}
// Composants : une ligne vide n’occupe pas d’espace dans un choix sans sous-titre.
const pageFrames=STATE[TARGET].roots;
for(const r of pageFrames){const f=await figma.getNodeByIdAsync(r.id);for(const t of f.findAll(n=>n.type==='TEXT'&&n.name==='Detail'&&n.characters==='')){t.visible=false;affected.push(t.id);}}
for(const link of STATE[TARGET].links){const destination=index[link.target];if(!destination)continue;const n=await figma.getNodeByIdAsync(link.id);await n.setReactionsAsync([{trigger:{type:'ON_CLICK'},actions:[{type:'NODE',destinationId:destination,navigation:'NAVIGATE',transition:{type:'DISSOLVE',easing:'EASE_OUT',duration:0.2},preserveScrollPosition:false}]}]);affected.push(n.id);}
if(TARGET==='phone')page.flowStartingPoints=[{nodeId:'3225:2',name:'Rejoindre et lancer'},{nodeId:'3225:345',name:'Blind test · huit choix'},{nodeId:'3225:420',name:'Dessin · dessinateur'},{nodeId:'3225:473',name:'Dessin · devineur'},{nodeId:'3225:559',name:'Phrase à compléter'},{nodeId:'3225:810',name:'Continuer après l’objectif'}];
if(TARGET==='tv')page.flowStartingPoints=[{nodeId:'3219:1954',name:'Salon et Quiz'},{nodeId:'3219:3121',name:'Blind test'},{nodeId:'3219:3216',name:'Dessin'},{nodeId:'3219:3363',name:'Phrase à compléter'}];
if(TARGET==='admin')page.flowStartingPoints=[{nodeId:'3231:2',name:'Contenus et packs'},{nodeId:'3231:401',name:'Importer CSV / Excel'}];
// Contrôle de débordement : un cadre mobile peut défiler ; les choix doivent rester lisibles.
const overflows=[];
for(const r of pageFrames){const f=await figma.getNodeByIdAsync(r.id);for(const n of f.findAll(n=>n.type==='TEXT'&&n.visible)){if(n.parent.type==='INSTANCE'&&n.y+n.height>n.parent.height-n.parent.paddingBottom+1)overflows.push({root:r.name,id:n.id,text:n.characters,height:n.height,parentHeight:n.parent.height});}}
print(JSON.stringify({surface:TARGET,affectedNodeIds:affected,overflowChecks:overflows}));
