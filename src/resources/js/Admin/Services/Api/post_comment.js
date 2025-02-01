import { post } from '@/App/Services/Api/query';

const toggleShow = async (id) => {
  return await post(`/admin/post-comment/toggle-show/${id}`);
}

export default {
  toggleShow,
};
