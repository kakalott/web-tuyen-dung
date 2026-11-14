import { createRouter, createWebHistory } from 'vue-router';
import { ensureAuth } from './guards';

import PublicLayout from '@/layouts/PublicLayout.vue';
import CandidateLayout from '@/layouts/CandidateLayout.vue';
import CompanyLayout from '@/layouts/CompanyLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

// Auth Pages (P-AUTH-01..02)
import LoginPage from '@/pages/auth/LoginPage.vue';
import RegisterPage from '@/pages/auth/RegisterPage.vue';

// Public Job Search & Detail (P-PUB-01..02)
import JobsPage from '@/pages/jobs/JobsPage.vue';
import JobDetailPage from '@/pages/jobs/JobDetailPage.vue';

// Error Pages
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

// Company Pages (P-COM-01..07)
import CompanyProfilePage from '@/pages/company/CompanyProfilePage.vue';
import CompanyJobsPage from '@/pages/company/CompanyJobsPage.vue';
import CompanyJobEditorPage from '@/pages/company/CompanyJobEditorPage.vue';
import CompanyJobDetailPage from '@/pages/company/CompanyJobDetailPage.vue';
import CompanyApplicantsPage from '@/pages/company/CompanyApplicantsPage.vue';
import CompanyApplicationDetailPage from '@/pages/company/CompanyApplicationDetailPage.vue';

// Admin Pages (P-ADM-01..07)
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue';
import AdminCompaniesPage from '@/pages/admin/AdminCompaniesPage.vue';
import AdminCompanyDetailPage from '@/pages/admin/AdminCompanyDetailPage.vue';
import AdminJobsPage from '@/pages/admin/AdminJobsPage.vue';
import AdminJobDetailPage from '@/pages/admin/AdminJobDetailPage.vue';
import AdminSkillsPage from '@/pages/admin/AdminSkillsPage.vue';
import AdminLookupsPage from '@/pages/admin/AdminLookupsPage.vue';

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
        path: 'jobs/:jobId',
        name: 'job-detail-public',
        component: JobDetailPage,
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

  // --- COMPANY ROUTES (P-COM-01..07) ---
  {
    path: '/company',
    component: CompanyLayout,
    meta: { requiresAuth: true, roles: ['company'] },
    children: [
      { path: '', redirect: '/company/jobs' },
      {
        path: 'profile',
        name: 'company-profile',
        component: CompanyProfilePage,
      },
      {
        path: 'jobs',
        name: 'company-jobs',
        component: CompanyJobsPage,
      },
      {
        path: 'jobs/new',
        alias: 'jobs/create',
        name: 'company-job-create',
        component: CompanyJobEditorPage,
      },
      {
        path: 'jobs/:jobId',
        name: 'company-job-detail',
        component: CompanyJobDetailPage,
      },
      {
        path: 'jobs/:jobId/edit',
        name: 'company-job-edit',
        component: CompanyJobEditorPage,
      },
      {
        path: 'jobs/:jobId/applications',
        name: 'company-job-applicants',
        component: CompanyApplicantsPage,
      },
      {
        path: 'applications/:id',
        name: 'company-application-detail',
        component: CompanyApplicationDetailPage,
      },
      {
        path: 'notifications',
        name: 'company-notifications',
        component: NotificationsPage,
      },
    ],
  },

  // --- ADMIN ROUTES (P-ADM-01..07) ---
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, roles: ['admin'] },
    children: [
      { path: '', redirect: '/admin/dashboard' },
      {
        path: 'dashboard',
        name: 'admin-dashboard',
        component: AdminDashboardPage,
      },
      {
        path: 'companies',
        name: 'admin-companies',
        component: AdminCompaniesPage,
      },
      {
        path: 'companies/:id',
        name: 'admin-company-detail',
        component: AdminCompanyDetailPage,
      },
      {
        path: 'jobs',
        name: 'admin-jobs',
        component: AdminJobsPage,
      },
      {
        path: 'jobs/:id',
        name: 'admin-job-detail',
        component: AdminJobDetailPage,
      },
      {
        path: 'skills',
        name: 'admin-skills',
        component: AdminSkillsPage,
      },
      {
        path: 'catalog/:catalog',
        name: 'admin-catalog-lookups',
        component: AdminLookupsPage,
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
