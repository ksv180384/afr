import { post } from '@/App/Services/Api/query';

const ban = async (params) => {
  return await post(`/admin/user/ban`, params);
}

const toggleEmailVerification = async (params) => {
  return await post(`/admin/user/toggle-email-verification`, params);
}

export default {
  ban,
  toggleEmailVerification,
};
