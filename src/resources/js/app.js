import '../css/app.css';
import '../css/practice.css';
import './bootstrap';
import GuestSync from './App/Components/Learning/GuestSync.vue';
import PracticeToasts from './App/Components/Learning/PracticeToasts.vue';
import { createPracticeNotifications, practiceNotificationsKey } from './App/Learning/practiceNotifications.js';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { createNotivue } from 'notivue'

import 'notivue/notification.css' // Only needed if using built-in notifications
import 'notivue/animations.css' // Only needed if using built-in animations
import 'notivue/notification-progress.css';

const notivue = createNotivue({
  position: 'top-right',
});

const pinia = createPinia();

createInertiaApp({
    title: (title) => title ? `${title} | ApprendreFr` : 'ApprendreFr',
    resolve: (name) =>
        resolvePageComponent(
            `./App/Pages/${name}.vue`,
            import.meta.glob('./App/Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const practiceNotifications = createPracticeNotifications();
        return createApp({ render: () => [h(App, props), h(GuestSync), h(PracticeToasts, { notifications: practiceNotifications })] })
            .provide(practiceNotificationsKey, practiceNotifications)
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .use(notivue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
