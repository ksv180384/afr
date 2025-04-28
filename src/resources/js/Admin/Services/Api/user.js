import { post } from '@/App/Services/Api/query';

const ban = async (params) => {
  return await post(`/admin/user/ban`, params);
}

export default {
  ban,
};
