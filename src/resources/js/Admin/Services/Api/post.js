import { post } from '@/App/Services/Api/query';

const updateStatus = async (id, params) => {
  return await post(`/admin/post/update-status/${id}`, params);
}

export default {
  updateStatus,
};
