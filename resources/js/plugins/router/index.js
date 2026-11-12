import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async to => {
  const auth = useAuthStore()

  await auth.fetchUser()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.guestOnly && auth.isAuthenticated)
    return { name: 'dashboard' }

  if (to.meta.roles?.length && !auth.hasRole(to.meta.roles))
    return { name: 'dashboard' }

  return true
})

export default function (app) {
  app.use(router)
}
export { router }
