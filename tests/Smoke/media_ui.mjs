// Requires PUPPETEER_MODULE (package or file URL), CHROME_PATH, MEDIA_TEST_USER and MEDIA_TEST_PASSWORD.
// Existing media is read only: all successful mutation responses are intercepted.
const {default:puppeteer}=await import(process.env.PUPPETEER_MODULE || 'puppeteer-core');
if(!process.env.MEDIA_TEST_USER||!process.env.MEDIA_TEST_PASSWORD)throw new Error('Set MEDIA_TEST_USER and MEDIA_TEST_PASSWORD.');

import assert from 'node:assert/strict';
const browser=await puppeteer.launch({executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,pipe:true});
try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setViewport({width:1440,height:1000});
 await page.goto('http://127.0.0.1:8000/admin/media/',{waitUntil:'networkidle2',timeout:60000});
 if(await page.$('#username')){
  await page.type('#username',process.env.MEDIA_TEST_USER);await page.type('#password',process.env.MEDIA_TEST_PASSWORD);
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle2',timeout:60000}),page.click('[type=submit]')]);
 }
 await page.goto('http://127.0.0.1:8000/admin/media/',{waitUntil:'networkidle2',timeout:60000});
 console.log('URL',page.url());
 await page.waitForSelector('[data-edit]',{timeout:20000});
 const file=await page.$eval('[data-file]',e=>JSON.parse(e.dataset.file));
 await page.evaluate(()=>{window.__copies=[];Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async text=>window.__copies.push(text)}});});
 await page.click('[data-copy]');
 assert.equal(await page.evaluate(()=>window.__copies[0]),new URL(file.url,'http://127.0.0.1:8000').href);
 await page.click('[data-edit]');
 assert.equal(await page.$eval('#media-details',e=>e.open),true);
 assert.equal(await page.$eval('#media-alt',e=>e.value),file.alt||'');
 const security=await page.evaluate(async()=>{
  const card=document.querySelector('[data-file]'),results=[];
  for(const action of ['crop','resize']){
   const data=new FormData();data.set('token','invalid');data.set('width','10');data.set('height','10');
   const response=await fetch(card.dataset[action+'Url'],{method:'POST',body:data});results.push(response.status);
  }
  return results;
 });
 assert.deepEqual(security,[403,403]);
 let posts=0,fail=true;
 await page.setRequestInterception(true);
 page.on('request',req=>{
  if(req.method()==='POST'&&req.url().includes('/admin/media/')){
   posts++;
   req.respond({status:fail?422:200,contentType:'application/json',body:JSON.stringify(fail?{status:'error',message:'Lỗi kiểm thử an toàn'}:{status:'success',alt:'Mô tả kiểm thử',message:'Đã lưu'})});
  }else req.continue();
 });
 await page.click('[data-operation=alt] button');
 await page.waitForFunction(()=>document.querySelector('[data-detail-status]').textContent.includes('Lỗi kiểm thử'));
 fail=false;await page.click('[data-operation=alt] button');
 await page.waitForFunction(()=>document.querySelector('[data-detail-status]').textContent.includes('Đã lưu'));
 assert.equal(posts,2);
 await page.keyboard.press('Escape');
 await page.click('[data-delete]');
 assert.equal(await page.$eval('#media-confirm',e=>e.open),true);
 assert.match(await page.$eval('#media-confirm-description',e=>e.textContent),/Không thể hoàn tác/);
 await page.click('[data-cancel]');assert.equal(posts,2);
 fail=true;await page.click('[data-delete]');await page.click('[data-confirm]');
 await page.waitForFunction(()=>document.querySelector('[data-confirm-error]').textContent.includes('Lỗi kiểm thử'));
 assert.equal(await page.$eval('[data-confirm]',e=>e.disabled),false);
 await page.click('[data-cancel]');
 await page.click('[data-open-upload]');
 await page.$eval('[data-file-input]',input=>{
  const dt=new DataTransfer();dt.items.add(new File(['not-an-image'],'bad.txt',{type:'text/plain'}));input.files=dt.files;input.dispatchEvent(new Event('change'));
 });
 assert.match(await page.$eval('[data-upload-status]',e=>e.textContent),/không hợp lệ/);
 await page.keyboard.press('Escape');
 for(const width of [1440,768,390]){
  await page.setViewport({width,height:950});
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'overflow '+width);
 }
 await page.click('[data-edit]');
 if(process.env.AXE_PATH){
 await page.addScriptTag({path:process.env.AXE_PATH});
 const a=await page.evaluate(()=>axe.run('#media-details',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
 console.log('AXE',JSON.stringify(a.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))));
 assert.equal(a.violations.length,0);
 }
 console.log('ERRORS',errors);
 assert.deepEqual(errors,[]);
 console.log('PASS read-only browser actions; write responses mocked, no existing media changed.');
}finally{await browser.close();}
