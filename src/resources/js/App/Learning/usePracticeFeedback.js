import { inject, onBeforeUnmount, onMounted, unref, watch } from 'vue';
import { practiceNotificationsKey, practiceStatusNotification } from './practiceNotifications.js';

export const usePracticeFeedback = ({ status, error, source: requestedSource, actions, errorType = 'error' } = {}) => {
  const notifications = inject(practiceNotificationsKey, null);
  if (!notifications) return { enabled: false };
  const source = requestedSource ?? notifications.source();
  let mounted = false;
  const publish = (nextStatus, nextError, previousStatus, previousError) => {
    if (!mounted) return;
    if (nextError) {
      notifications.notify(source, { type: errorType, message: nextError });
    } else if (nextStatus !== previousStatus) {
      const notification = practiceStatusNotification(nextStatus);
      if (notification) notifications.notify(source, { ...notification, actions });
      else if (nextStatus?.kind !== 'draft') notifications.clearSource(source);
    } else if (previousError) notifications.clearSource(source);
  };
  watch([() => unref(status), () => unref(error)], (next, previous) => publish(...next, ...previous), { flush: 'post' });
  onMounted(() => { mounted = true; publish(unref(status), unref(error)); });
  onBeforeUnmount(() => notifications.release(source));
  return { enabled: true };
};
