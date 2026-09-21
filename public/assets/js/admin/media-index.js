const root = document.querySelector('[data-media-manager]');
if (root) {
 const $ = (s, scope = root) => scope.querySelector(s);
 const details = $('#media-details'), confirmDialog = $('#media-confirm'), upload = $('#media-upload');
 let current, busy = false, uploaded = false, queue = [];
 const status = (text, error = false, box = $('[data-status]')) => { box.textContent = text; box.dataset.error = String(error); };
 const setBusy = value => {
  busy = value;
  for (const el of root.querySelectorAll('dialog button, dialog input, dialog select, dialog textarea')) el.disabled = value;
 };
 async function request(url, data) {
  const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 60000);
  try {
   const response = await fetch(url, {method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
   if (response.redirected) throw new Error('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.');
   let result;
   try { result = await response.json(); } catch { throw new Error('Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.'); }
   if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Không thể hoàn tất thao tác.');
   return result;
  } catch (error) {
   if (error.name === 'AbortError') throw new Error('Chưa nhận được phản hồi. Hãy cập nhật thư viện để kiểm tra trước khi thử lại.');
   throw error;
  } finally { clearTimeout(timer); }
 }
 const formData = values => {
  const data = new FormData(); data.set('token',root.dataset.token);
  Object.entries(values).forEach(([key,value])=>data.set(key,value)); return data;
 };
 async function copy(url, box, button) {
  if(button.disabled)return;
  clearTimeout(button.copyTimer);
  button.dataset.originalLabel ||= button.textContent;
  button.disabled=true;button.textContent='Đang chép…';
  const feedback=(label,state)=>{button.textContent=label;button.dataset.copyState=state;button.setAttribute('aria-label',label);};

  const absolute = new URL(url,location.origin).href;
  try {
   try { if (!navigator.clipboard) throw new Error(); await navigator.clipboard.writeText(absolute); }
   catch {
    const input = document.createElement('textarea'); input.value = absolute;
    const active = document.activeElement;
    (details.open ? details : root).append(input); input.select();
    const success = document.execCommand('copy'); input.remove(); active?.focus();
    if (!success) throw new Error();
   }
   feedback('✓ Đã sao chép','success');
  } catch { feedback('Chép thất bại','error');status('Không thể truy cập clipboard. Mở chi tiết ảnh để sao chép đường dẫn thủ công.',true,box); }
  finally {button.disabled=false;button.copyTimer=setTimeout(()=>{button.textContent=button.dataset.originalLabel;delete button.dataset.copyState;button.removeAttribute('aria-label');},3000);}

 }
 function openDetails(card, editing) {
  current = card;
  const file = JSON.parse(card.dataset.file);
  $('[data-full-image]').src = file.url; $('[data-full-image]').alt = file.alt || file.filename;
  $('[data-file-info]').textContent = file.filename+' · '+(file.width>0&&file.height>0?file.width+' × '+file.height+' px · ':'')+file.size_formatted;
  $('[data-open-original]').href = file.url; $('[data-url]').value = new URL(file.url,location.origin).href;
  status('',false,$('[data-detail-status]'));
  for (const section of details.querySelectorAll('details')) section.open = false;
  const alt = $('#media-alt'); if (alt) alt.value = file.alt || '';
  for (const action of ['resize','crop']) {
   const form = $('[data-operation="'+action+'"]');
   if (form) { form.reset(); form.elements.width.value=file.width; form.elements.height.value=file.height;
    if (action==='crop') {form.elements.x.value=0;form.elements.y.value=0;}
   }
  }
  const move = $('[data-operation=move]');
  if (move) {move.reset();move.elements.targetFolder.value=file.filename.includes('/')?file.filename.substring(0,file.filename.lastIndexOf('/')):'';}
  details.showModal();
  (editing && alt ? alt : $('[data-close]',details)).focus();
 }
 function confirmAction(title, description, action) {
  $('#media-confirm-title').textContent=title; $('#media-confirm-description').textContent=description;
  $('[data-confirm-error]').textContent=''; confirmDialog.showModal();
  const submit=$('[data-confirm]');
  submit.onclick=async()=>{
   if(busy)return; setBusy(true);
   try { await action(); confirmDialog.close(); }
   catch(error) { $('[data-confirm-error]').textContent=error.message; }
   finally {setBusy(false);}
  };
 }
 root.addEventListener('click',event=>{
  const button=event.target.closest('button'); if(!button||busy)return;
  const card=button.closest('[data-file]');
  if(card){
   const file=JSON.parse(card.dataset.file);
   if(button.hasAttribute('data-copy'))copy(file.url,undefined,button);
   if(button.hasAttribute('data-preview')||button.hasAttribute('data-edit'))openDetails(card,button.hasAttribute('data-edit'));
   if(button.hasAttribute('data-delete'))confirmAction('Xóa hình ảnh?', 'Xóa “'+file.filename+'” và ảnh thu nhỏ. Không thể hoàn tác; bài viết đang dùng ảnh có thể bị mất hình.',async()=>{
    await request(card.dataset.deleteUrl,formData({}));
    card.remove();status('Đã xóa '+file.filename+'. Đang cập nhật thư viện…');location.reload();
   });
  }
  if(button.hasAttribute('data-copy-current'))copy(JSON.parse(current.dataset.file).url,$('[data-detail-status]'),button);
  if(button.hasAttribute('data-close')){button.closest('dialog').close();if(uploaded)location.reload();}
  if(button.hasAttribute('data-cancel'))confirmDialog.close();
  if(button.hasAttribute('data-open-upload'))upload.showModal();
  if(button.hasAttribute('data-reload'))location.reload();
 });
 for(const dialog of root.querySelectorAll('dialog')){
  dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 }
 upload.addEventListener('close',()=>{if(uploaded)location.reload();});
 for(const form of root.querySelectorAll('[data-operation]')){
  form.addEventListener('submit',event=>{
   event.preventDefault();if(busy)return;
   const action=form.dataset.operation, file=JSON.parse(current.dataset.file);
   const values=Object.fromEntries(new FormData(form));
   if(action==='resize'||action==='crop'){
    for(const key of Object.keys(values))values[key]=Number(values[key]);
    if(!Number.isInteger(values.width)||!Number.isInteger(values.height)||values.width<1||values.height<1||values.width>10000||values.height>10000||values.width*values.height>20000000){
     status('Kích thước phải là số nguyên dương, tối đa 10.000 px mỗi cạnh và 20 triệu pixel.',true,$('[data-detail-status]'));return;
    }
    if(action==='crop'&&(!Number.isInteger(values.x)||!Number.isInteger(values.y)||values.x<0||values.y<0||values.x+values.width>file.width||values.y+values.height>file.height)){
     status('Vùng cắt phải nằm trong kích thước ảnh gốc.',true,$('[data-detail-status]'));return;
    }
   }
   const save=async()=>{
    const result=await request(current.dataset[action+'Url'],formData(values));
    if(action==='alt'){
     file.alt=result.alt;current.dataset.file=JSON.stringify(file);
     $('.media-alt',current).textContent=file.alt?'Đã có mô tả ALT':'Chưa có mô tả ALT';
     $('img',current).alt=file.alt||'';status('Đã lưu mô tả ảnh.',false,$('[data-detail-status]'));
    }else location.reload();
   };
   if(action==='alt'){
    setBusy(true);save().catch(error=>status(error.message,true,$('[data-detail-status]'))).finally(()=>setBusy(false));
   }else confirmAction(action==='move'?'Di chuyển ảnh?':'Ghi đè ảnh gốc?',action==='move'?'URL ảnh sẽ thay đổi. Hãy cập nhật các nội dung đang sử dụng URL cũ.':'Thao tác không thể hoàn tác. Hãy tải bản gốc về nếu cần giữ lại.',save);
  });
 }
 const resize=$('[data-operation=resize]');
 $('[data-full-image]').addEventListener('load',event=>{
  if(!current)return;
  const file=JSON.parse(current.dataset.file),image=event.target;
  if(file.width>0&&file.height>0)return;
  file.width=image.naturalWidth;file.height=image.naturalHeight;current.dataset.file=JSON.stringify(file);
  $('[data-file-info]').textContent=file.filename+' · '+file.width+' × '+file.height+' px · '+file.size_formatted;
  $('.media-preview span',current).textContent=file.width+' × '+file.height;
  for(const action of ['resize','crop']){
   const form=$('[data-operation="'+action+'"]');
   if(form){form.elements.width.value=file.width;form.elements.height.value=file.height;}
  }
 });
 if(resize)for(const key of ['width','height'])resize.elements[key].addEventListener('input',()=>{
  if(!$('[data-ratio]').checked||!current)return;
  const file=JSON.parse(current.dataset.file),other=key==='width'?'height':'width',value=Number(resize.elements[key].value);
  if(value>0)resize.elements[other].value=Math.max(1,Math.round(value*file[other]/file[key]));
 });
 for(const img of root.querySelectorAll('img[data-original]'))img.addEventListener('error',()=>{
  if(img.dataset.fallback)return;img.dataset.fallback='1';img.src=img.dataset.original;
 });
 const uploadStatus=$('[data-upload-status]'), submit=$('[data-upload-submit]');
 function renderQueue(){
  const list=$('[data-queue]');list.replaceChildren();
  queue.forEach((item,index)=>{
   const li=document.createElement('li'),text=document.createElement('span'),remove=document.createElement('button');
   text.textContent=item.file.name+' · '+(item.message||'Chờ tải');
   remove.type='button';remove.className='banner-button';remove.textContent='Bỏ chọn';remove.disabled=busy;
   remove.addEventListener('click',()=>{queue.splice(index,1);renderQueue();});
   li.append(text,remove);list.append(li);
  });
  submit.disabled=busy||!queue.some(item=>!item.done);
 }
 function addFiles(files){
  if(busy)return;let rejected=0;
  for(const file of files){
   if(!/\.(jpe?g|png|gif)$/i.test(file.name)||file.size>10485760||file.size===0){rejected++;continue;}
   if(!queue.some(item=>item.file.name===file.name&&item.file.size===file.size&&item.file.lastModified===file.lastModified))queue.push({file});
  }
  uploadStatus.textContent=rejected?rejected+' tệp không hợp lệ (định dạng hoặc dung lượng).':'Đã chọn '+queue.length+' ảnh.';
  renderQueue();
 }
 $('[data-file-input]').addEventListener('change',event=>{addFiles(event.target.files);event.target.value='';});
 const drop=$('.media-drop');
 drop.addEventListener('dragover',event=>{event.preventDefault();drop.dataset.drag='true';});
 drop.addEventListener('dragleave',()=>{drop.dataset.drag='false';});
 drop.addEventListener('drop',event=>{event.preventDefault();drop.dataset.drag='false';addFiles(event.dataTransfer.files);});
 submit.addEventListener('click',async()=>{
  if(busy)return;setBusy(true);renderQueue();
  let failed=0;
  const folder=$('[data-upload-folder]').value,newFolder=$('[data-new-folder]').value;
  for(const item of queue.filter(item=>!item.done)){
   item.message='Đang tải…';renderQueue();
   try {await request(root.dataset.upload,formData({file:item.file,folder,newFolder}));item.done=true;item.message='Đã tải';uploaded=true;}
   catch(error){item.message=error.message;failed++;}
   renderQueue();
  }
  setBusy(false);renderQueue();$('[data-reload]').hidden=!uploaded;
  uploadStatus.textContent=failed?failed+' ảnh chưa tải được. Bạn có thể thử lại hoặc bỏ chọn.':'Đã tải xong. Bấm Cập nhật thư viện để xem ảnh mới.';
 });
}
