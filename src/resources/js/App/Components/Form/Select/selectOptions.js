export const selectOptions = nodes => (Array.isArray(nodes) ? nodes : [nodes]).flatMap(node => {
  if (!node || typeof node !== 'object') return [];
  if (node.props && Object.hasOwn(node.props, 'value')) {
    const label = String(node.props.label ?? (typeof node.children === 'string' ? node.children : node.props.value) ?? '');
    return [{ value:node.props.value, label, searchText:node.props.searchText ?? node.props['search-text'] ?? label,
      disabled:node.props.disabled != null && node.props.disabled !== false }];
  }
  return Array.isArray(node.children) ? selectOptions(node.children) : [];
});
const normalize = value => String(value).normalize('NFKD').replace(/\p{Diacritic}/gu, '').replaceAll('−', '-').toLocaleLowerCase('ru');
export const filterSelectOptions = (options, query) => {
  const terms = normalize(query).trim().split(/\s+/).filter(Boolean);
  return options.filter(option => terms.every(term => normalize(option.label + ' ' + (option.searchText ?? '')).includes(term)));
};
