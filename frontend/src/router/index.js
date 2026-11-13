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

// Candidate Pages (P-CAN-01..07)
import CandidateProfilePage from '@/pages/candidate/CandidateProfilePage.vue';
import CandidateCVsPage from '@/pages/candidate/CandidateCVsPage.vue';
import CVExtractionPage from '@/pages/candidate/CVExtractionPage.vue';
import CandidateSkillsPage from '@/pages/candidate/CandidateSkillsPage.vue';
import CandidateApplicationsPage from '@/pages/candidate/CandidateApplicationsPage.vue';
import RecommendationsPage from '@/pages/candidate/RecommendationsPage.vue';
import NotificationsPage from '@/pages/candidate/NotificationsPage.vue';

const routes = [
  // --- PUBLIC ROUTES ---
  {
    path: '/',
    component: PublicLayout,
    children: [
      { path: '', redirect: '/jobs' },
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

  // --- CANDIDATE ROUTES (P-CAN-01..07) ---
  {
    path: '/candidate',
    component: CandidateLayout,
    meta: { requiresAuth: true, roles: ['candidate'] },
    children: [
      { path: '', redirect: '/candidate/profile' },
      {
        path: 'profile',
        name: 'candidate-profile',
        component: CandidateProfilePage,
      },
      {
        path: 'cvs',
        name: 'candidate-cvs',
        component: CandidateCVsPage,
      },
      {
        path: 'cvs/:cvId/extraction',
        name: 'cv-extraction',
        component: CVExtractionPage,
      },
      {
        path: 'skills',
        name: 'candidate-skills',
        component: CandidateSkillsPage,
      },
      {
        path: 'applications',
        name: 'candidate-applications',
        component: CandidateApplicationsPage,
      },
      {
        path: 'recommendations',
        name: 'candidate-recommendations',
        component: RecommendationsPage,
      },
      {
        path: 'notifications',
        name: 'candidate-notifications',
        component: NotificationsPage,
      },
    ],
  },

  // --- CATCH-ALL ---
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
