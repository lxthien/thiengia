// Traditional Eight Mansions convention; not a scientific prediction.
// Trigram bits bottom-to-top: Qian 111, Dui 011, Li 101, Zhen 001,
// Xun 110, Kan 010, Gen 100, Kun 000. Pairing uses changed lines (XOR).
export const MIN_YEAR=1900, MAX_YEAR=2099;
export const DIRECTIONS=[
 {id:'n',name:'Bắc',bits:2,position:2},
 {id:'ne',name:'Đông Bắc',bits:4,position:3},
 {id:'e',name:'Đông',bits:1,position:6},
 {id:'se',name:'Đông Nam',bits:6,position:9},
 {id:'s',name:'Nam',bits:5,position:8},
 {id:'sw',name:'Tây Nam',bits:0,position:7},
 {id:'w',name:'Tây',bits:3,position:4},
 {id:'nw',name:'Tây Bắc',bits:7,position:1}
];
const PALACES={
 1:{name:'Khảm',element:'Thủy',bits:2,east:true},
 2:{name:'Khôn',element:'Thổ',bits:0,east:false},
 3:{name:'Chấn',element:'Mộc',bits:1,east:true},
 4:{name:'Tốn',element:'Mộc',bits:6,east:true},
 6:{name:'Càn',element:'Kim',bits:7,east:false},
 7:{name:'Đoài',element:'Kim',bits:3,east:false},
 8:{name:'Cấn',element:'Thổ',bits:4,east:false},
 9:{name:'Ly',element:'Hỏa',bits:5,east:true}
};
const STARS=[
 {name:'Phục Vị',favorable:true},
 {name:'Họa Hại',favorable:false},
 {name:'Tuyệt Mệnh',favorable:false},
 {name:'Thiên Y',favorable:true},
 {name:'Sinh Khí',favorable:true},
 {name:'Lục Sát',favorable:false},
 {name:'Ngũ Quỷ',favorable:false},
 {name:'Diên Niên',favorable:true}
];
export function parseBirthYear(value){
 const text=String(value).trim();
 return /^\d{4}$/.test(text)?Number(text):NaN;
}
export function digitRoot(value){
 if(!Number.isInteger(value)||value<1)throw new RangeError('Expected positive integer');
 return 1+(value-1)%9;
}
export function palaceForYear(year,sex){
 if(!Number.isInteger(year)||year<MIN_YEAR||year>MAX_YEAR||!['male','female'].includes(sex))throw new RangeError('Invalid birth year or sex');
 // Full-year reduction works across 1900/2000 without resetting the cycle.
 const root=digitRoot(year),raw=digitRoot(sex==='male'?11-root:4+root);
 const number=raw===5?(sex==='male'?2:8):raw;
 return {root,raw,number,...PALACES[number]};
}
export function directionsForPalace(number){
 if(!PALACES[number])throw new RangeError('Unknown palace');
 return DIRECTIONS.map(direction=>({...direction,label:direction.name,...STARS[PALACES[number].bits^direction.bits]}));
}
export function calculateDirection({year,sex,direction=''}){
 const errors={};
 if(!Number.isInteger(year)||year<MIN_YEAR||year>MAX_YEAR)errors.birth='Nhập năm sinh âm lịch từ 1900 đến 2099 (4 chữ số).';
 if(!['male','female'].includes(sex))errors.sex='Chọn Nam hoặc Nữ theo bảng cung phi đang tra.';
 if(direction!==''&&!DIRECTIONS.some(d=>d.id===direction))errors.direction='Chọn một trong tám hướng trong danh sách.';
 if(Object.keys(errors).length)return {errors};
 const palace=palaceForYear(year,sex),directions=directionsForPalace(palace.number);
 return {errors,year,sex,palace,directions,selected:directions.find(d=>d.id===direction)||null,
 favorable:directions.filter(d=>d.favorable),caution:directions.filter(d=>!d.favorable)};
}
