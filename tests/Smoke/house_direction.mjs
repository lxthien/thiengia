import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../public/assets/js/v3/modules/house-direction-calculator.js',import.meta.url),'utf8');
const m=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
for(const [year,sex,number] of [[1990,'male',1],[1990,'female',8],[1984,'male',7],[2000,'male',9],[2000,'female',6],[2001,'male',8],[1978,'male',4],[1978,'female',2],[1995,'male',2],[1995,'female',1],[2004,'male',2]]){
 assert.equal(m.palaceForYear(year,sex).number,number);
}
const stars=['Phục Vị','Họa Hại','Tuyệt Mệnh','Thiên Y','Sinh Khí','Lục Sát','Ngũ Quỷ','Diên Niên'];
const tables={1:[0,6,3,4,7,2,1,5],2:[2,4,1,6,5,0,3,7],3:[3,5,0,7,4,1,2,6],4:[4,2,7,0,3,6,5,1],6:[5,3,6,1,2,7,4,0],7:[1,7,2,5,6,3,0,4],8:[6,0,5,2,1,4,7,3],9:[7,1,4,3,0,5,6,2]};
for(const [palace,expected] of Object.entries(tables)){
 const directions=m.directionsForPalace(Number(palace));
 assert.deepEqual(directions.map(d=>d.name),expected.map(i=>stars[i]));
 assert.equal(directions.filter(d=>d.favorable).length,4);
 assert.equal(new Set(directions.map(d=>d.id)).size,8);
}
const reduce=n=>n===0?0:1+(n-1)%9;
for(let year=1900;year<=2099;year++)for(const sex of ['male','female']){
 const last=reduce(year%100);
 let expected=reduce(sex==='male'?(year<2000?10:9)-last:(year<2000?5:6)+last);
 if(expected===0)expected=9;
 if(expected===5)expected=sex==='male'?2:8;
 assert.equal(m.palaceForYear(year,sex).number,expected,year+' '+sex);
}
for(const year of [NaN,1899,2100,1990.5])assert.ok(m.calculateDirection({year,sex:'male'}).errors.birth);
assert.ok(m.calculateDirection({year:1990,sex:'unknown'}).errors.sex);
assert.ok(m.calculateDirection({year:1990,sex:'male',direction:'bad'}).errors.direction);
assert.equal(m.calculateDirection({year:1990,sex:'male',direction:'se'}).selected.name,'Sinh Khí');
assert.equal(m.parseBirthYear(' 1990 '),1990);
for(const value of ['','199x','90','1e03'])assert.ok(Number.isNaN(m.parseBirthYear(value)));
console.log('House direction: 400 year/sex cases, 64 direction mappings and validation passed.');
