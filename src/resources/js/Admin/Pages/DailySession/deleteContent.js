import { ElMessageBox } from 'element-plus';
import api from '@/Admin/Services/Api';
export async function deleteContent(kind, id, title) {
  try { await ElMessageBox.confirm('Удалить «' + title + '» полностью? Вложенные занятия, связанные прохождения и результаты будут удалены. Личные слова останутся. При удалении уровня пользователи перейдут на ближайший оставшийся уровень.', 'Полное удаление', { confirmButtonText: 'Удалить полностью', cancelButtonText: 'Отмена', type: 'warning' }); } catch { return null; }
  return api.dailyPractice.action('manage/' + kind + '/' + encodeURIComponent(id) + '/delete', { confirmed: true });
}
