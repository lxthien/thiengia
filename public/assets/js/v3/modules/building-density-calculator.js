// QCVN 01:2021/BXD, Table 2.8. Reviewed 2026-09-20.
// Reference tool: https://tramnhadat.com/cong-cu/tinh-mat-do-xay-dung/
// General table only; no automatic existing-urban-area exceptions or permit inference.
export const DENSITY_TABLE = [[90,100],[100,90],[200,70],[300,60],[500,50],[1000,40]];
export function parseDecimal(value) {
    const text=String(value).trim().replace(',', '.');
    return /^\d+(?:\.\d+)?$/.test(text) && Number.isFinite(Number(text)) ? Number(text) : NaN;
}
export function maximumDensity(area) {
    if (!Number.isFinite(area) || area<=0) throw new RangeError('Diện tích phải lớn hơn 0.');
    if(area<=90) return {density:100,lower:null,upper:90};
    for(let i=1;i<DENSITY_TABLE.length;i++){
        const [upper,highDensity]=DENSITY_TABLE[i], [lower,lowDensity]=DENSITY_TABLE[i-1];
        if(area<=upper) return {density:lowDensity+(area-lower)*(highDensity-lowDensity)/(upper-lower),lower,upper};
    }
    return {density:40,lower:1000,upper:null};
}
export function calculateDensity({area,floors,density}) {
    const errors={};
    if(!Number.isFinite(area)||area<=0||area>1000000) errors.area='Nhập diện tích lớn hơn 0 và không quá 1.000.000 m².';
    if(!Number.isInteger(floors)||floors<1||floors>100) errors.floors='Nhập số tầng nguyên từ 1 đến 100. Đây là số tầng giả định, không phải số tầng được phép.';
    if(!Number.isFinite(density)||density<0||density>100) errors.density='Nhập mật độ từ 0 đến 100%; số 0 dùng mức tra bảng.';
    if(Object.keys(errors).length) return {errors};
    const table=maximumDensity(area), usedDensity=density===0?table.density:density;
    const footprint=area*usedDensity/100, floorArea=footprint*floors, far=floorArea/area;
    return {errors,area,floors,...table,usedDensity,footprint,openArea:area-footprint,floorArea,far,
        exceedsDensity:usedDensity-table.density>1e-9,exceedsFar:far-7>1e-9};
}
