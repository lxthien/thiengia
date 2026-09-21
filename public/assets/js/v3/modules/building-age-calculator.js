// Folk conventions, not predictions or technical building criteria.
// https://nhomkinhdaiphuc.com/xem-tuoi-xay-nha.html
// Hoang Oc decade-count convention: https://kienlong.com.vn/cach-tinh-tam-tai-kim-lau-hoang-oc-don-gian-nhat/
export const MIN_YEAR=1900, MAX_YEAR=2100, MIN_AGE=18, MAX_AGE=120;
const STEMS=['Giáp','Ất','Bính','Đinh','Mậu','Kỷ','Canh','Tân','Nhâm','Quý'];
export const BRANCHES=['Tý','Sửu','Dần','Mão','Thìn','Tỵ','Ngọ','Mùi','Thân','Dậu','Tuất','Hợi'];
const TAM_TAI=[[2,3,4],[11,0,1],[8,9,10],[5,6,7]];
export const HOANG_OC=['Nhất Cát','Nhì Nghi','Tam Địa Sát','Tứ Tấn Tài','Ngũ Thọ Tử','Lục Hoang Ốc'];
const KIM_LAU={1:'Kim Lâu Thân',3:'Kim Lâu Thê',6:'Kim Lâu Tử',8:'Kim Lâu Súc'};
const mod=(value,divisor)=>((value%divisor)+divisor)%divisor;
export function parseYear(value){
    const text=String(value).trim();
    return /^\d{4}$/.test(text)?Number(text):NaN;
}
export function canChi(year){
    if(!Number.isInteger(year)) throw new RangeError('Năm phải là số nguyên.');
    return STEMS[mod(year-4,10)]+' '+BRANCHES[mod(year-4,12)];
}
export function hoangOcIndex(age){
    if(!Number.isInteger(age)||age<10||age>MAX_AGE)throw new RangeError('Tuổi ngoài phạm vi đếm cung.');
    return mod(Math.floor(age/10)+age%10-1,6);
}
export function evaluateYear(birthYear,buildYear){
    const errors={};
    if(!Number.isInteger(birthYear)||birthYear<MIN_YEAR||birthYear>MAX_YEAR)errors.birth='Nhập năm sinh âm lịch từ 1900 đến 2100 (4 chữ số).';
    if(!Number.isInteger(buildYear)||buildYear<MIN_YEAR||buildYear>MAX_YEAR)errors.build='Nhập năm xây nhà âm lịch từ 1900 đến 2100 (4 chữ số).';
    if(Object.keys(errors).length)return {errors};
    const age=buildYear-birthYear+1;
    if(age<MIN_AGE||age>MAX_AGE)return {errors:{build:'Phạm vi công cụ: tuổi mụ từ 18 đến 120. Kiểm tra lại năm sinh và năm xây nhà.'}};
    const birthBranch=mod(birthYear-4,12), buildBranch=mod(buildYear-4,12);
    const tamTaiYears=TAM_TAI[birthBranch%4], tamTai=tamTaiYears.includes(buildBranch);
    const remainder=age%9, kimLau=Object.hasOwn(KIM_LAU,remainder), hoangIndex=hoangOcIndex(age);
    const hoangOc=[2,4,5].includes(hoangIndex);
    return {errors:{},birthYear,buildYear,age,birthCanChi:canChi(birthYear),buildCanChi:canChi(buildYear),
        birthBranch:BRANCHES[birthBranch],tamTai,tamTaiBranches:tamTaiYears.map(i=>BRANCHES[i]),
        kimLau,kimLauName:KIM_LAU[remainder]||null,remainder,hoangOc,hoangIndex,hoangName:HOANG_OC[hoangIndex],
        clear:!tamTai&&!kimLau&&!hoangOc};
}
export function followingYears(birthYear,buildYear){
    if(Object.keys(evaluateYear(birthYear,buildYear).errors).length)return [];
    const end=Math.min(buildYear+10,MAX_YEAR,birthYear+MAX_AGE-1), rows=[];
    for(let year=buildYear+1;year<=end;year++)rows.push(evaluateYear(birthYear,year));
    return rows;
}
