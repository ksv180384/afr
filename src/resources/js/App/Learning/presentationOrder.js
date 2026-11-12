// Stable per presentation, including reloads. Only display order changes.
export const presentationOrder = (items, seed) => {
  const result = [...items];
  let state = 2166136261;
  for (const character of String(seed)) state = Math.imul(state ^ character.codePointAt(0), 16777619) >>> 0;
  const random = () => {
    state += 0x6D2B79F5;
    let value = Math.imul(state ^ (state >>> 15), 1 | state);
    value ^= value + Math.imul(value ^ (value >>> 7), 61 | value);
    return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
  };
  for (let index = result.length - 1; index > 0; index--) {
    const other = Math.floor(random() * (index + 1));
    [result[index], result[other]] = [result[other], result[index]];
  }
  return result;
};
