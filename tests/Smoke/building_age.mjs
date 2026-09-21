import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const src=readFileSync(new URL('../../public/assets/js/v3/modules/building-age-calculator.js',import.meta.url),'utf8');
const {parseYear,canChi,hoangOcIndex,evaluateYear,followingYears}=await import('data:text/javascript;base64,'+Buffer.from(src).toString('base64'));
for(const [year,name]of [[1984,'Giáp Tý'],[1990,'Canh Ngọ'],[2000,'Canh Thìn'],[2024,'Giáp Thìn'],[2025,'Ất Tỵ'],[2026,'Bính Ngọ']])assert.equal(canChi(year),name);
for(let year=1900;year<=2040;year++)assert.equal(canChi(year),canChi(year+60));
for(const [age,index]of [[10,0],[20,1],[30,2],[35,1],[36,2],[40,3],[50,4],[60,5],[70,0],[80,1],[90,2],[100,3],[110,4],[120,5]])assert.equal(hoangOcIndex(age),index);
for(let age=18;age<=120;age++){
 let expected=(Math.floor(age/10)-1)%6;
 for(let j=0;j<age%10;j++)expected=(expected+1)%6;
 assert.equal(hoangOcIndex(age),expected);
 const r=evaluateYear(1980,1980+age-1);
 assert.equal(r.age,age);assert.equal(r.kimLau,[1,3,6,8].includes(age%9));
}
const groups=[{birth:1984,bad:['Dần','Mão','Thìn']},{birth:1985,bad:['Hợi','Tý','Sửu']},{birth:1986,bad:['Thân','Dậu','Tuất']},{birth:1987,bad:['Tỵ','Ngọ','Mùi']}];
for(const {birth,bad}of groups)for(let year=2020;year<2032;year++)assert.equal(evaluateYear(birth,year).tamTai,bad.includes(canChi(year).split(' ')[1]));
let r=evaluateYear(1990,2026);assert.equal(r.age,37);assert.equal(r.tamTai,false);assert.equal(r.kimLauName,'Kim Lâu Thân');assert.equal(r.hoangName,'Tứ Tấn Tài');assert.equal(r.clear,false);
assert.equal(evaluateYear(1990,2023).clear,true);
for(const [birth,build]of [[NaN,2026],[1990,NaN],[1899,2026],[1990,2101],[2000.5,2026],[2027,2026],[2010,2026],[1900,2026]])assert.ok(Object.keys(evaluateYear(birth,build).errors).length);
for(const s of ['', '90','1990abc','1e3','1990.0','<script>'])assert.ok(Number.isNaN(parseYear(s)));
assert.equal(parseYear(' 1990 '),1990);
for(let birth=1900;birth<=2083;birth++)for(let build=birth+17;build<=Math.min(2100,birth+119);build++){
 const value=evaluateYear(birth,build);assert.equal(value.clear,!value.tamTai&&!value.kimLau&&!value.hoangOc);
}
assert.equal(followingYears(1990,2026).length,10);
assert.equal(followingYears(1981,2100).length,0);
assert.equal(followingYears(1900,2018).length,1);
assert.equal(followingYears(2080,2099).length,1);
assert.deepEqual(followingYears(2100,2026),[]);
console.log('PASS: Can Chi fixtures/60-year cycle, four Tam Tai groups, Hoang Oc decade boundaries, Kim Lau, valid year grid, invalid input and future limits.');
