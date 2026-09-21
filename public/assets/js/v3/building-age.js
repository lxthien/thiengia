import {parseYear,evaluateYear,followingYears} from './modules/building-age-calculator';
const root=document.querySelector('[data-age-tool]');
if(root){
    const form=root.querySelector('form'),results=root.querySelector('[data-results]'),empty=root.querySelector('[data-empty]');
    const status=root.querySelector('[data-status]'), table=root.querySelector('[data-years]'), fields=['birth','build'];
    let timer;
    const text=(key,value)=>{root.querySelector('[data-output="'+key+'"]').textContent=value;};
    function render(){
        clearTimeout(timer);
        const result=evaluateYear(parseYear(form.elements.birth.value),parseYear(form.elements.build.value));
        fields.forEach(key=>{
            const error=root.querySelector('[data-error="'+key+'"]'),message=result.errors[key];
            error.textContent=message||'';error.hidden=!message;form.elements[key].setAttribute('aria-invalid',String(Boolean(message)));
        });
        const invalid=Object.keys(result.errors).length>0;
        results.hidden=invalid;empty.hidden=!invalid;
        root.querySelector('[data-future]').hidden=invalid;
        if(invalid){status.textContent='Chưa có kết quả: kiểm tra năm sinh và năm xây nhà.';table.replaceChildren();return;}
        text('age',result.age+' tuổi mụ');
        text('birth',result.birthYear+' · '+result.birthCanChi);
        text('build',result.buildYear+' · '+result.buildCanChi);
        text('formula',result.buildYear+' − '+result.birthYear+' + 1 = '+result.age+' tuổi mụ.');
        text('verdict',result.clear?'Không phạm cả ba tiêu chí':'Có tiêu chí cần lưu ý theo cách tra dân gian');
        text('tam',result.tamTai?'Phạm Tam Tai':'Không phạm Tam Tai');
        text('tam-note','Tuổi '+result.birthBranch+' xét các năm '+result.tamTaiBranches.join(', ')+'. Năm đang tra: '+result.buildCanChi+'.');
        text('kim',result.kimLau?result.kimLauName:'Không phạm Kim Lâu');
        text('kim-note',result.age+' chia 9 dư '+result.remainder+'. Quy ước đang dùng: dư 1, 3, 6, 8 thuộc Kim Lâu.');
        const decade=Math.floor(result.age/10)*10;
        text('hoang',result.hoangName+' · '+(result.hoangOc?'Cung kiêng theo quy ước':'Cung thuận theo quy ước'));
        text('hoang-note','Đếm từ mốc '+decade+' tuổi, tiến thêm '+(result.age%10)+' cung. Các cung kiêng: Tam Địa Sát, Ngũ Thọ Tử, Lục Hoang Ốc.');
        for(const [key,bad] of [['tam',result.tamTai],['kim',result.kimLau],['hoang',result.hoangOc]])root.querySelector('[data-check="'+key+'"]').dataset.caution=String(bad);
        const rows=followingYears(result.birthYear,result.buildYear);
        const eligible=rows.filter(r=>r.clear);
        text('future',!rows.length?'Không còn năm trong phạm vi công cụ (đến năm 2100 và tối đa 120 tuổi mụ).':
            'Tra '+rows.length+' năm tiếp theo ('+rows[0].buildYear+'–'+rows.at(-1).buildYear+'). '+
            (eligible.length?'Năm không phạm cả ba tiêu chí: '+eligible.map(r=>r.buildYear).join(', ')+'.':'Không có năm nào trong khoảng này không phạm cả ba tiêu chí.'));
        table.replaceChildren();
        rows.forEach(row=>{
            const tr=document.createElement('tr');
            const yearCell=document.createElement('th');yearCell.scope='row';
            const button=document.createElement('button');button.type='button';button.textContent=row.buildYear+' · '+row.buildCanChi;
            button.setAttribute('aria-label','Xem chi tiết năm '+row.buildYear);
            button.addEventListener('click',()=>{form.elements.build.value=String(row.buildYear);render();const heading=root.querySelector('[data-output=verdict]');heading.focus();});
            yearCell.append(button);tr.append(yearCell);
            for(const value of [row.age,row.tamTai?'Phạm':'Không phạm',row.kimLau?row.kimLauName:'Không phạm',row.hoangName,row.clear?'Không phạm cả ba':'Có tiêu chí cần lưu ý']){
                const cell=document.createElement('td');cell.textContent=String(value);tr.append(cell);
            }
            table.append(tr);
        });
        timer=setTimeout(()=>{status.textContent=result.age+' tuổi mụ. '+root.querySelector('[data-output=verdict]').textContent+'. '+root.querySelector('[data-output=tam]').textContent+'. '+root.querySelector('[data-output=kim]').textContent+'. '+result.hoangName+'.';},200);
    }
    form.addEventListener('input',render);
    form.addEventListener('submit',event=>{event.preventDefault();render();const invalid=form.querySelector('[aria-invalid=true]');if(invalid)invalid.focus();else root.querySelector('[data-output=verdict]').focus();});
    form.addEventListener('reset',()=>setTimeout(render,0));
    render();
}
