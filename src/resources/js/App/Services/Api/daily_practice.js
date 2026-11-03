import { get, post, patch } from './query';
const root = '/daily/api';
export const accountTransport = {
  profile: () => get(root + '/profile'),
  sync: (payload) => post(root + '/guest-sync',payload,{timeout:30000}),
  today: () => get(root + '/today'),
  history: (page = 1) => get(root + '/history', {params:{page}}),
  loadRun: (id) => get(root + '/runs/' + id),
  updateProfile: (input) => patch(root + '/profile',input),
  call: (action, run, input) => post(action === 'start' ? root + '/runs' : root + '/runs/' + run + '/' + (action === 'answer' ? 'answers' : action),input),
};

export const guestContentTransport = {
  catalog: () => get(root + '/content'),
  session: (id) => get(root + '/content/' + encodeURIComponent(id)),
};

export const vocabularyTransport = {
  list: (filters) => get(root + '/vocabulary',{params:filters}),
  plan: (filters) => get(root + '/vocabulary/test-plan',{params:filters}),
  mark: (id, needs_review) => patch(root + '/vocabulary/' + id,{needs_review}),
};

export const reportPracticeProblem = (payload) => post(root+'/reports',payload);
