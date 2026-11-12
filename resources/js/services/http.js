const csrfCookieName = 'XSRF-TOKEN'
let csrfRequested = false

function readCookie(name) {
  const prefix = `${name}=`
  const cookie = document.cookie.split('; ').find(item => item.startsWith(prefix))

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null
}

async function ensureCsrfCookie() {
  if (csrfRequested)
    return

  await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })

  csrfRequested = true
}

async function request(method, url, options = {}) {
  const { body, headers = {}, csrfRetried = false } = options
  const needsCsrf = !['GET', 'HEAD', 'OPTIONS'].includes(method)

  if (needsCsrf)
    await ensureCsrfCookie()

  const xsrfToken = readCookie(csrfCookieName)

  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body !== undefined && { 'Content-Type': 'application/json' }),
      ...(xsrfToken && { 'X-XSRF-TOKEN': xsrfToken }),
      ...headers,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  // The session (and therefore the CSRF token) may have expired. Refresh the
  // cookie once and replay the request.
  if (response.status === 419 && needsCsrf && !csrfRetried) {
    csrfRequested = false
    await ensureCsrfCookie()

    return request(method, url, { ...options, csrfRetried: true })
  }

  const isJson = (response.headers.get('content-type') ?? '').includes('application/json')
  const payload = isJson ? await response.json() : await response.text()

  if (!response.ok) {
    const error = new Error(payload?.message ?? 'Request failed')

    error.status = response.status
    error.data = payload
    error.errors = payload?.errors ?? {}

    throw error
  }

  return payload
}

export const http = {
  get: (url, options = {}) => request('GET', url, options),
  post: (url, body, options = {}) => request('POST', url, { ...options, body }),
  patch: (url, body, options = {}) => request('PATCH', url, { ...options, body }),
  delete: (url, options = {}) => request('DELETE', url, options),
}
