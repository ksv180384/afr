import { get } from '@/App/Services/Api/query';
import { objToUrlParams } from '@/Helpers/helper';

const filter = async (params) => {
  const paramPage = objToUrlParams(params);
  return await get(`/admin/dictionary/search${paramPage}`);
}

export default {
  filter,
};
