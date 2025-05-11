import { post } from '@/App/Services/Api/query';

const toggleShow = async (id) => {
  return await post(`/admin/songs-comment/toggle-show/${id}`);
}

export default {
  toggleShow,
};
