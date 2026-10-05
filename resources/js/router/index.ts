import { createRouter, createWebHistory } from 'vue-router';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: () => import('@/pages/HomePage.vue') },
        { path: '/quizzes', name: 'quizzes', component: () => import('@/pages/QuizListPage.vue') },
        { path: '/play/:gameId', name: 'game', component: () => import('@/pages/GamePage.vue'), props: true },
        {
            path: '/play/:gameId/result',
            name: 'result',
            component: () => import('@/pages/GameResultPage.vue'),
            props: true,
        },
        { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
    ],
    scrollBehavior: () => ({ top: 0 }),
});
