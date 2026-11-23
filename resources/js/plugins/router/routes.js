export const routes = [
  { path: '/', redirect: '/dashboard' },
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/pages/users.vue'),
        meta: { roles: ['admin', 'developer'] },
      },
      {
        path: 'farms',
        name: 'farms',
        component: () => import('@/pages/farm/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator'] },
      },
      {
        path: 'livestocks',
        name: 'livestocks',
        component: () => import('@/pages/livestock/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator'] },
      },
      {
        path: 'productions',
        name: 'productions',
        component: () => import('@/pages/production/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator'] },
      },
      {
        path: 'reports',
        name: 'reports',
        component: () => import('@/pages/report/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator', 'viewer'] },
      },
      {
        path: 'finance',
        name: 'finance',
        component: () => import('@/pages/finance/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator', 'viewer'] },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: () => import('@/pages/inventory/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator', 'viewer'] },
      },
      {
        path: 'health-records',
        name: 'health-records',
        component: () => import('@/pages/health/index.vue'),
        meta: { roles: ['admin', 'developer', 'operator', 'viewer'] },
      },
      {
        path: 'account-settings',
        component: () => import('@/pages/account-settings.vue'),
      },
      {
        path: 'typography',
        component: () => import('@/pages/typography.vue'),
      },
      {
        path: 'icons',
        component: () => import('@/pages/icons.vue'),
      },
      {
        path: 'cards',
        component: () => import('@/pages/cards.vue'),
      },
      {
        path: 'tables',
        component: () => import('@/pages/tables.vue'),
      },
      {
        path: 'form-layouts',
        component: () => import('@/pages/form-layouts.vue'),
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      {
        path: 'login',
        name: 'login',
        component: () => import('@/pages/login.vue'),
        meta: { guestOnly: true },
      },
      {
        path: 'register',
        name: 'register',
        component: () => import('@/pages/register.vue'),
        meta: { guestOnly: true },
      },
      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
      },
    ],
  },
]
