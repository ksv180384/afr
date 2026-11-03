import { get, post } from '@/Admin/Services/Api/query';
const base = '/admin/daily-practice';
export default {
  action: (path, data = {}) => post(base + '/' + path, data),
  template: (name) => get(base + '/templates/' + name),
  diff: (id) => get(base + '/catalog/' + id + '/diff'),
  upload: (path, file) => {
    const data = new FormData();
    data.append('file', file);
    return post(base + '/' + path, data, { headers: { 'Content-Type': undefined } });
  },
};
