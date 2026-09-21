import {calculateDensity,parseDecimal} from './modules/building-density-calculator';
const root=document.querySelector('[data-density-tool]');
if(root){
    const form=root.querySelector('form'), results=root.querySelector('[data-results]'), empty=root.querySelector('[data-empty]');
    const status=root.querySelector('[data-status]'), warning=root.querySelector('[data-warning]');
    const fields=['area','floors','density'], format=n=>new Intl.NumberFormat('vi-VN',{maximumFractionDigits:2}).format(n);
    let timer;
    function update(){
        const result=calculateDensity(Object.fromEntries(fields.map(key=>[key,parseDecimal(form.elements[key].value)])));
        fields.forEach(key=>{
            const message=result.errors[key], input=form.elements[key], error=root.querySelector('[data-error="'+key+'"]');
            input.setAttribute('aria-invalid',String(Boolean(message)));error.textContent=message||'';error.hidden=!message;
        });
        clearTimeout(timer);
        if(Object.keys(result.errors).length){results.hidden=true;empty.hidden=false;status.textContent='Chưa có kết quả: kiểm tra các trường nhập.';return;}
        results.hidden=false;empty.hidden=true;
        const outputs={max:format(result.density)+'%',used:format(result.usedDensity)+'%',footprint:format(result.footprint)+' m²',
            open:format(result.openArea)+' m²',floorArea:format(result.floorArea)+' m²',far:format(result.far)+' lần'};
        for(const [key,value] of Object.entries(outputs))root.querySelector('[data-output="'+key+'"]').textContent=value;
        root.querySelector('[data-formula]').textContent=
            format(result.area)+' m² × '+format(result.usedDensity)+'% = '+format(result.footprint)+' m² chiếm đất. '+
            format(result.footprint)+' m² × '+result.floors+' tầng = '+format(result.floorArea)+' m² sàn giả định; chia '+format(result.area)+' m² đất = '+format(result.far)+' lần.';
        root.querySelector('[data-interpolation]').textContent=result.lower===null?'Diện tích không quá 90 m²: mức tra bảng 100%.':
            result.upper===null?'Diện tích từ 1.000 m²: mức tra bảng 40%.':
            'Tra trong khoảng '+format(result.lower)+'–'+format(result.upper)+' m²; nội suy tuyến tính cho kết quả '+format(result.density)+'%.';
        const warnings=[];
        if(result.exceedsDensity)warnings.push('Mật độ dự kiến vượt mức của Bảng 2.8. Công cụ giữ nguyên số bạn nhập để so sánh, không xác nhận phương án được phép.');
        if(result.exceedsFar)warnings.push('Hệ số sử dụng đất dự kiến vượt 7 lần. Cần điều chỉnh phương án và kiểm tra chỉ tiêu quy hoạch.');
        warning.textContent=warnings.join(' ');warning.hidden=!warnings.length;
        root.querySelector('[data-result-title]').textContent=warnings.length?'Phương án cần kiểm tra lại':'Ước tính theo dữ liệu nhập';
        timer=setTimeout(()=>{status.textContent='Mật độ tra bảng '+outputs.max+'. Diện tích chiếm đất '+outputs.footprint+'. Tổng sàn giả định '+outputs.floorArea+'. Hệ số sử dụng đất '+outputs.far+'. '+warnings.join(' ');},200);
    }
    form.addEventListener('input',update);
    form.addEventListener('submit',event=>{event.preventDefault();update();const invalid=form.querySelector('[aria-invalid=true]');if(invalid)invalid.focus();});
    form.addEventListener('reset',()=>setTimeout(update,0));
    root.querySelectorAll('[data-preset]').forEach(button=>button.addEventListener('click',()=>{form.elements.area.value=button.dataset.preset;update();}));
    update();
}
