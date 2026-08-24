import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('@/pages/FeedPage.vue') },
      {
        path: 'publications/:id',
        component: () => import('@/pages/PublicationPage.vue'),
        props: true
      },
      { path: 'users', component: () => import('@/pages/UsersPage.vue') },
      { path: 'users/:login', component: () => import('@/pages/UserPage.vue'), props: true },
      { path: 'hubs', component: () => import('@/pages/HubsPage.vue') },
      { path: 'hubs/:alias', component: () => import('@/pages/HubPage.vue'), props: true },
      { path: 'companies', component: () => import('@/pages/CompaniesPage.vue') },
      { path: 'companies/:slug', component: () => import('@/pages/CompanyPage.vue'), props: true },
      {
        path: 'editor/:id?',
        component: () => import('@/pages/EditorPage.vue'),
        props: true,
        meta: { requiresAuth: true }
      },
      {
        path: 'bookmarks',
        component: () => import('@/pages/BookmarksPage.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'login',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { guestOnly: true }
      },
      {
        path: 'register',
        component: () => import('@/pages/RegisterPage.vue'),
        meta: { guestOnly: true }
      }
    ]
  },

  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue')
  }
];

export default routes;
