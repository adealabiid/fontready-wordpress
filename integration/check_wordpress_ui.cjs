const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('static/wordpress.js','utf8');
function setup({preview=false,expired=false,code=200}={}){
 const nodes={};for(const id of ['wordpress-publish','wordpress-status','wordpress-connection','wordpress-license','wordpress-disconnect'])nodes[id]={value:'',checked:false,disabled:false,textContent:'',className:'',addEventListener(name,fn){this[name]=fn}};
 const calls=[];
 const context={document:{getElementById:id=>nodes[id]},previewOnly:preview,activeJob:{id:'test',state:'ready',expires_at:new Date(Date.now()+(expired?-1000:60000)).toISOString()},Date,URL,AbortSignal,JSON,Number,Array,Error,csrf:()=> 'csrf',api:async(path,options)=>{calls.push({kind:'api',path,options});return {version:1,fonts:[]}},fetch:async(path,options)=>{calls.push({kind:'wp',path,options});return {ok:code===200,status:code,json:async()=>code===200?{imported:1,families:['Example']}:{message:'Expired'}}}};
 vm.createContext(context);vm.runInContext(source,context);
 nodes['wordpress-connection'].value=JSON.stringify({endpoint:'https://site.example/wp-json/fontready/v1/fonts',key:'a'.repeat(64)});
 return {nodes,calls};
}
(async()=>{
 let checks=0;const check=(v,msg)=>{assert(v,msg);checks++};
 let x=setup();await x.nodes['wordpress-publish'].onclick();check(x.calls.length===0,'License confirmation required');
 x.nodes['wordpress-license'].checked=true;await x.nodes['wordpress-publish'].onclick();
 check(x.calls.length===2,'Bundle then publish');
 check(!JSON.stringify(x.calls[0]).includes('a'.repeat(64)),'Key never sent to Django');
 check(x.calls[1].options.headers.Authorization==='Bearer '+'a'.repeat(64),'Key only sent to WordPress');
 check(x.calls[1].options.credentials==='omit'&&x.calls[1].options.redirect==='error','No cookies or redirects');
 check(x.nodes['wordpress-status'].textContent.includes('Published to site.example'),'Confirmed success feedback');
 check(!x.nodes['wordpress-publish'].disabled,'Button restored');
 for(const endpoint of ['http://site.example/wp-json/fontready/v1/fonts','https://user:pass@site.example/wp-json/fontready/v1/fonts','https://site.example/elsewhere','https://site.example/wp-json/fontready/v1/fonts#secret']){
  x=setup();x.nodes['wordpress-license'].checked=true;x.nodes['wordpress-connection'].value=JSON.stringify({endpoint,key:'a'.repeat(64)});await x.nodes['wordpress-publish'].onclick();check(!x.calls.length,'Unsafe destination rejected');
 }
 x=setup();x.nodes['wordpress-license'].checked=true;x.nodes['wordpress-connection'].value=JSON.stringify({endpoint:'https://site.example/?rest_route=/fontready/v1/fonts',key:'a'.repeat(64)});await x.nodes['wordpress-publish'].onclick();check(x.calls.length===2,'Query style REST supported');
 x=setup({expired:true});x.nodes['wordpress-license'].checked=true;await x.nodes['wordpress-publish'].onclick();check(!x.calls.length,'Expired kit blocked');
 x=setup({preview:true});x.nodes['wordpress-license'].checked=true;await x.nodes['wordpress-publish'].onclick();check(!x.calls.length,'Static preview cannot publish');
 x=setup({code:401});x.nodes['wordpress-license'].checked=true;await x.nodes['wordpress-publish'].onclick();check(x.nodes['wordpress-connection'].value==='','Expired key cleared');
 x=setup();x.nodes['wordpress-disconnect'].onclick();check(x.nodes['wordpress-connection'].value==='','Disconnect clears key');
 console.log(`Passed ${checks} publishing UI contract checks.`);
})().catch(e=>{console.error(e);process.exit(1)});
