import {parseBirthYear,calculateDirection} from './modules/house-direction-calculator';
const root=document.querySelector('[data-direction-tool]');
if(root){
 const form=root.querySelector('form'),results=root.querySelector('[data-results]'),empty=root.querySelector('[data-empty]');
 const grid=root.querySelector('[data-compass]'),body=root.querySelector('[data-directions]'),status=root.querySelector('[data-status]');
 let timer;
 function render(){
  clearTimeout(timer);
  const result=calculateDirection({year:parseBirthYear(form.elements.birth.value),sex:form.elements.sex.value,direction:form.elements.direction.value});
  for(const key of ['birth','sex','direction']){
   const message=result.errors[key],box=root.querySelector('[data-error="'+key+'"]');
   form.elements[key].setAttribute('aria-invalid',String(Boolean(message)));box.textContent=message||'';box.hidden=!message;
  }
  const invalid=Object.keys(result.errors).length>0;
  results.hidden=invalid;root.querySelector('[data-all-directions]').hidden=invalid;empty.hidden=!invalid;
  if(invalid){grid.replaceChildren();body.replaceChildren();status.textContent='Chưa có kết quả: kiểm tra thông tin nhập.';return;}
  const output=(key,value)=>{root.querySelector('[data-output="'+key+'"]').textContent=value;};
  const p=result.palace;
  output('palace','Cung '+p.name);
  output('group',(p.east?'Đông':'Tây')+' tứ mệnh · Hành '+p.element+' của cung phi');
  output('formula','Rút gọn tổng chữ số năm '+result.year+' được '+p.root+'. '+
   (result.sex==='male'?'Nam: 11 − '+p.root:'Nữ: 4 + '+p.root)+'; rút gọn về '+p.raw+'. '+
   (p.raw===5?'Số 5 quy về '+(result.sex==='male'?'Khôn (2)':'Cấn (8)')+'.':'Quái số '+p.number+' ứng với cung '+p.name+'.'));
  output('favorable',result.favorable.map(d=>d.label).join(', '));
  output('caution',result.caution.map(d=>d.label).join(', '));
  const chosen=root.querySelector('[data-selected]');
  chosen.dataset.caution=String(result.selected&&!result.selected.favorable);
  // Direction and star have separate names, never infer a physical bearing.
  output('selected',result.selected?result.selected.label+': '+result.selected.name+' · '+(result.selected.favorable?'Nhóm thuận theo quy ước':'Nhóm cần cân nhắc theo quy ước'):'Chọn hướng nhà để xem kết quả riêng.');
  grid.replaceChildren();
  const center=document.createElement('div');center.className='direction-center';center.textContent=p.name;grid.append(center);
  result.directions.forEach(d=>{
   const button=document.createElement('button');button.type='button';button.style.gridArea=Math.ceil(d.position/3)+' / '+((d.position-1)%3+1);
   button.dataset.caution=String(!d.favorable);button.setAttribute('aria-pressed',String(d.id===form.elements.direction.value));
   button.setAttribute('aria-label',d.label+', '+d.name+', '+(d.favorable?'nhóm thuận':'nhóm cần cân nhắc'));
   const name=document.createElement('strong');name.textContent=d.label;
   const star=document.createElement('span');star.textContent=d.name;button.append(name,star);
   button.addEventListener('click',()=>{form.elements.direction.value=d.id;render();grid.querySelector('[aria-pressed=true]')?.focus({preventScroll:true});});
   grid.append(button);
  });
  body.replaceChildren();
  result.directions.forEach(d=>{
   const row=document.createElement('tr'),heading=document.createElement('th');heading.scope='row';heading.textContent=d.label;row.append(heading);
   for(const text of [d.name,d.favorable?'Nhóm thuận':'Nhóm cần cân nhắc']){
    const cell=document.createElement('td');cell.textContent=text;row.append(cell);
   }
   body.append(row);
  });
  timer=setTimeout(()=>{status.textContent='Cung '+p.name+'. '+(p.east?'Đông':'Tây')+' tứ mệnh. '+root.querySelector('[data-output=selected]').textContent;},200);
 }
 form.addEventListener('input',render);form.addEventListener('change',render);
 form.addEventListener('submit',event=>{event.preventDefault();render();const invalid=form.querySelector('[aria-invalid=true]');if(invalid)invalid.focus();else root.querySelector('[data-output=palace]').focus();});
 form.addEventListener('reset',()=>setTimeout(render,0));
 render();
}
