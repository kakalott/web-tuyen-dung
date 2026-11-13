import { createRouter, createWebHistory } from 'vue-router';
import { ensureAuth } from './guards';

import PublicLayout from '@/layouts/PublicLayout.vue';
import CandidateLayout from '@/layouts/CandidateLayout.vue';
import CompanyLayout from '@/layouts/CompanyLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

import LoginPage from '@/pages/auth/LoginPage.vue';
import RegisterPage from '@/pages/auth/RegisterPage.vue';
import JobsPage from '@/pages/jobs/JobsPage.vue';
import NotFoundPage from '@/pages/errors/NotFoundPage.vue';
import ForbiddenPage from '@/pages/errors/ForbiddenPage.vue';

const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      {
        path: '',
        redirect: '/jobs',
      },
      {
        path: 'jobs',
        name: 'jobs',
        component: JobsPage,
        meta: { requiresAuth: false },
      },
      {
        path: 'login',
        name: 'login',
        component: LoginPage,
        meta: { guestOnly: true },
      },
      {
        path: 'register',
        name: 'register',
        component: RegisterPage,
        meta: { guestOnly: true },
      },
      {
        path: '403',
        name: 'forbidden',
        component: ForbiddenPage,
      },
      {
        path: '404',
        name: 'not-found',
        component: NotFoundPage,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/404',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(ensureAuth);

export default router;
