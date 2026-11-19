import { get, post, patch } from './query';
const root = '/daily/api';
export const accountTransport = {
  profile: () => get(root + '/profile'),
  sync: (payload) => post(root + '/guest-sync',payload,{timeout:30000}),
  today: (selection = {}) => get(root + '/today', {params: selection}),
  reviewPlan: (filters = {}) => get(root + '/vocabulary/review-plan', {params: filters}),
  history: (page = 1) => get(root + '/history', {params:{page}}),
  loadRun: (id) => get(root + '/runs/' + id),
  updateReviewSettings: (input) => patch(root + '/review-settings',input),
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
  markDifficult: (id, is_difficult) => patch(root + '/vocabulary/' + id,{is_difficult}),
};

export const reportPracticeProblem = (payload) => post(root+'/reports',payload);

export const practiceRouteTransport = {
  search: (q, completed) => get(root + '/route/search', {params: {q, completed: Number(completed)}}),
  reference: (path) => get(root + '/route/reference', {params: {path}}),
};
