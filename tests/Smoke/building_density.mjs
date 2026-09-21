import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../public/assets/js/v3/modules/building-density-calculator.js',import.meta.url),'utf8');
const {maximumDensity,calculateDensity,parseDecimal,DENSITY_TABLE}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
for(const [area,density] of DENSITY_TABLE)assert.equal(maximumDensity(area).density,density);
for(const [area,density] of [[1,100],[89.9,100],[95,95],[150,80],[250,65],[400,55],[750,45],[1001,40],[1000000,40]])assert.equal(maximumDensity(area).density,density);
let last=100;
for(let area=1;area<=10000;area++){const m=maximumDensity(area).density;assert.ok(m<=last&&m>=40);last=m;}
const result=calculateDensity({area:100,floors:3,density:0});
assert.equal(result.footprint,90);assert.equal(result.floorArea,270);assert.equal(result.far,2.7);
assert.equal(calculateDensity({area:150,floors:3,density:0}).footprint,120);
assert.equal(calculateDensity({area:100,floors:3,density:80}).openArea,20);
assert.equal(calculateDensity({area:100,floors:3,density:95}).exceedsDensity,true);
assert.equal(calculateDensity({area:90,floors:7,density:0}).exceedsFar,false);
assert.equal(calculateDensity({area:90,floors:8,density:0}).exceedsFar,true);
for(const area of [NaN,Infinity,0,-1,1000001])assert.ok(calculateDensity({area,floors:3,density:0}).errors.area);
for(const floors of [NaN,0,1.5,101])assert.ok(calculateDensity({area:100,floors,density:0}).errors.floors);
for(const density of [NaN,-1,101,Infinity])assert.ok(calculateDensity({area:100,floors:3,density}).errors.density);
assert.equal(parseDecimal('150,5'),150.5);
for(const s of ['', '1e3', '12abc','1,000.5','-1'])assert.ok(Number.isNaN(parseDecimal(s)));
console.log('PASS density: all table anchors, interpolation, 10,000 monotonicity checks, formulas, thresholds and validation.');

