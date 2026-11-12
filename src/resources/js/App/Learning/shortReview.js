export const usableReviewItems = (source={}) => (Array.isArray(source?.review_items) ? source.review_items : []).filter(item=>{
  if(!item?.id || !item.prompt || typeof item.prompt!=='object' || Array.isArray(item.prompt)) return false;
  if(item.type==='short_answer') return Array.isArray(item.accepted_answers) && item.accepted_answers.length>0;
  if(item.type!=='single_choice' || !Array.isArray(item.choices) || item.choices.length<2) return false;
  const ids=item.choices.map(choice=>choice.id);
  return ids.every(id=>typeof id==='string' && id.length>0) && ids.length===new Set(ids).size && ids.includes(item.correct_choice_id);
});
const normalized = text => (text ?? '').toLocaleLowerCase('fr').replaceAll('’',"'").replaceAll('ʼ',"'")
  .replace(/[\p{P}\p{Z}\s]+/gu,' ').trim().replace(/^(?:le|la|les|un|une|des|l) /u,'');
export const reviewCategory = (item,source) => {
  const prompt=item.prompt ?? {};const text=prompt.audio?.text ?? prompt.text ?? '';
  if(normalized(text)===normalized(source.fr)) return 'meaning';
  if(prompt.text_lang==='ru' && !prompt.audio && /перевод|что означает|значение слова/iu.test(text)) return 'meaning';
  return 'context';
};
export const selectShortReview = (rows,previousKeys=[]) => {
  const fresh=rows.filter(row=>!previousKeys.includes(row.key));const pool=[...(fresh.length ? fresh : rows)]
    .sort((a,b)=>a.tier-b.tier || a.due-b.due || (a.key<b.key ? -1 : a.key>b.key ? 1 : 0));
  const selected=[];const topics=new Set();
  while(pool.length && selected.length<3){const tier=pool[0].tier;const index=pool.findIndex(row=>row.tier===tier && !topics.has(row.topic));
    const row=pool.splice(index<0 ? 0 : index,1)[0];selected.push(row);topics.add(row.topic);}
  return selected;
};
const optionPool = (source, index, previousKind) => {
  const items = usableReviewItems(source);
  const preferred = previousKind ? (previousKind === 'context' ? 'meaning' : 'context') : (index % 2 === 0 ? 'context' : 'meaning');
  const choices = items.filter(item => reviewCategory(item, source) === preferred);
  return choices.length ? choices : items;
};

export const estimateReviewSeconds = (source, index, previousKind) => Math.max(...optionPool(source, index, previousKind).map(item => {
  const base = item.type === 'short_answer' ? 40 : 30;
  if (!item.prompt.audio) return base;
  const words = (item.prompt.audio.text ?? '').trim().split(/\s+/u).filter(Boolean).length;
  return base + Math.max(25, Math.ceil(words * 4 / 3));
}));

export const shortReviewOption = (source, seed, key, index, previousKind) => {
  const pool = optionPool(source, index, previousKind);
  const value = [...new TextEncoder().encode(seed + key)].reduce((sum, byte) => sum + byte, 0);
  return pool[value % pool.length];
};

export const reviewWordCount = total => `${total} ${total === 1 ? 'выражение' : 'выражения'}`;

export const reviewDurationLabel = seconds => {
  if (!(seconds > 0)) return '';
  const minutes = Math.ceil(seconds / 60);
  if (minutes === 1) return 'Около минуты';
  return `Около ${minutes} минут`;
};
