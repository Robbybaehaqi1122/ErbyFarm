<script setup>
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import { useAuthStore } from '@/stores/auth'
import logo from '@images/logo.svg?raw'
import authV1BottomShape from '@images/svg/auth-v1-bottom-shape.svg?url'
import authV1TopShape from '@images/svg/auth-v1-top-shape.svg?url'

const router = useRouter()
const auth = useAuthStore()

const form = ref({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
  privacyPolicies: false,
})

const isPasswordVisible = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})

async function onSubmit() {
  errorMessage.value = ''
  fieldErrors.value = {}

  if (!form.value.privacyPolicies) {
    errorMessage.value = 'You must agree to the privacy policy and terms.'

    return
  }

  try {
    await auth.register({
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,

      // Laravel's `confirmed` rule expects this exact field name.
      // eslint-disable-next-line camelcase
      password_confirmation: form.value.passwordConfirmation,
    })

    await router.push('/dashboard')
  }
  catch (error) {
    fieldErrors.value = error.errors ?? {}

    if (!Object.keys(fieldErrors.value).length)
      errorMessage.value = error.data?.message ?? 'Registration failed. Please try again.'
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
      <!-- 👉 Top shape -->
      <VImg
        :src="authV1TopShape"
        class="text-primary auth-v1-top-shape d-none d-sm-block"
      />

      <!-- 👉 Bottom shape -->
      <VImg
        :src="authV1BottomShape"
        class="text-primary auth-v1-bottom-shape d-none d-sm-block"
      />

      <!-- 👉 Auth card -->
      <VCard
        class="auth-card"
        max-width="460"
        :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-0'"
      >
        <VCardItem class="justify-center">
          <RouterLink
            to="/"
            class="app-logo"
          >
            <!-- eslint-disable vue/no-v-html -->
            <div
              class="d-flex"
              v-html="logo"
            />
            <h1 class="app-logo-title">
              sneat
            </h1>
          </RouterLink>
        </VCardItem>

        <VCardText>
          <h4 class="text-h4 mb-1">
            Adventure starts here 🚀
          </h4>
          <p class="mb-0">
            Make your app management easy and fun!
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="onSubmit">
            <VRow>
              <VCol
                v-if="errorMessage"
                cols="12"
              >
                <VAlert
                  type="error"
                  variant="tonal"
                >
                  {{ errorMessage }}
                </VAlert>
              </VCol>

              <!-- Name -->
              <VCol cols="12">
                <VTextField
                  v-model="form.name"
                  autofocus
                  label="Full name"
                  placeholder="John Doe"
                  :error-messages="fieldErrors.name"
                />
              </VCol>
              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  label="Email"
                  type="email"
                  placeholder="johndoe@email.com"
                  :error-messages="fieldErrors.email"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Password"
                  autocomplete="new-password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :error-messages="fieldErrors.password"
                  :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- password confirmation -->
              <VCol cols="12">
                <VTextField
                  v-model="form.passwordConfirmation"
                  label="Confirm password"
                  autocomplete="new-password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                />
              </VCol>

              <VCol cols="12">
                <div class="d-flex align-center">
                  <VCheckbox
                    id="privacy-policy"
                    v-model="form.privacyPolicies"
                    inline
                  />
                  <VLabel
                    for="privacy-policy"
                    style="opacity: 1;"
                  >
                    <span class="me-1 text-high-emphasis">I agree to</span>
                    <a
                      href="javascript:void(0)"
                      class="text-primary"
                    >privacy policy & terms</a>
                  </VLabel>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="auth.loading"
                  :disabled="auth.loading"
                >
                  Sign up
                </VBtn>
              </VCol>

              <!-- login instead -->
              <VCol
                cols="12"
                class="text-center text-base"
              >
                <span>Already have an account?</span>
                <RouterLink
                  class="text-primary ms-1"
                  to="/login"
                >
                  Sign in instead
                </RouterLink>
              </VCol>

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">or</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
