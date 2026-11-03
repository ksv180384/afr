import transcription from '@/Admin/Services/Api/transcription.js';
import post from '@/Admin/Services/Api/post.js';
import postComment from '@/Admin/Services/Api/post_comment.js';
import songComment from '@/Admin/Services/Api/song_comment.js';
import dictionary from '@/Admin/Services/Api/dictionary.js';
import user from '@/Admin/Services/Api/user.js';

import dailyPractice from './daily_practice.js';

export default {
  dailyPractice,
  transcription,
  post,
  postComment,
  songComment,
  dictionary,
  user,
}
