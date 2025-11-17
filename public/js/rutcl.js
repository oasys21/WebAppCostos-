function validaRUT(rut) {
  if (!rut) return false;
  rut = rut.replace(/\./g,'').replace(/-/g,'').toUpperCase();
  if (rut.length < 2) return false;
  const dv = rut.slice(-1);
  const num = rut.slice(0, -1);
  if (!/^\d+$/.test(num)) return false;
  let s=0, m=2;
  for (let i = num.length - 1; i >= 0; i--) {
    s += parseInt(num[i], 10) * m;
    m = (m === 7) ? 2 : m + 1;
  }
  const r = 11 - (s % 11);
  const dv_calc = (r === 11) ? '0' : (r === 10 ? 'K' : String(r));
  return dv_calc === dv;
}
