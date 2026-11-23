import { http } from '@/services/http'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)
  const loading = ref(false)

  let initialization = null

  const isAuthenticated = computed(() => Boolean(user.value))
  const roles = computed(() => user.value?.roles ?? [])
  const isAdmin = computed(() => roles.value.includes('admin') || roles.value.includes('developer'))

  async function fetchUser({ force = false } = {}) {
    if (initialized.value && !force)
      return user.value

    if (initialization && !force)
      return initialization

    initialization = (async () => {
      try {
        const response = await http.get('/api/auth/me')

        user.value = response.user
      }
      catch (error) {
        if (error.status !== 401)
          console.error(error)

        user.value = null
      }
      finally {
        initialized.value = true
        initialization = null
      }

      return user.value
    })()

    return initialization
  }

  async function login(credentials) {
    loading.value = true

    try {
      const response = await http.post('/api/auth/login', credentials)

      user.value = response.user
      initialized.value = true

      return user.value
    }
    finally {
      loading.value = false
    }
  }

  async function register(payload) {
    loading.value = true

    try {
      const response = await http.post('/api/auth/register', payload)

      user.value = response.user
      initialized.value = true

      return user.value
    }
    finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true

    try {
      await http.post('/api/auth/logout')
    }
    catch (error) {
      if (error.status !== 401)
        console.error(error)
    }
    finally {
      user.value = null
      initialized.value = true
      loading.value = false
    }
  }

  function hasRole(...allowed) {
    return allowed.flat().some(role => roles.value.includes(role))
  }

  return {
    user,
    roles,
    isAuthenticated,
    isAdmin,
    initialized,
    loading,
    fetchUser,
    login,
    register,
    logout,
    hasRole,
  }
})
