<script setup>
import { http } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import avatar1 from '@images/avatars/avatar-1.png'

const auth = useAuthStore()
const refInputEl = ref()
const form = ref({})
const savedUser = ref(null)
const saving = ref(false)
const deactivating = ref(false)
const confirmDeactivate = ref(false)
const errorMessage = ref('')
const showSnackbar = ref(false)
const snackbarMessage = ref('')

const roleLabels = {
  developer: 'Superadmin / Developer',
  admin: 'Admin / Owner',
  operator: 'Operator Kandang',
  viewer: 'Viewer / Staff',
}

const roleLabel = computed(() => {
  const role = auth.roles[0]

  return role ? roleLabels[role] ?? role : 'Member'
})

const avatarSrc = computed(() => form.value.avatarUrl || avatar1)

const timezones = [
  '(GMT+07:00) Jakarta',
  '(GMT+08:00) Singapore',
  '(GMT+09:00) Tokyo',
  '(GMT+00:00) London',
  '(GMT+01:00) Berlin',
  '(GMT-05:00) Eastern Time (US & Canada)',
  '(GMT-08:00) Pacific Time (US & Canada)',
]

const currencies = ['IDR', 'USD', 'EUR', 'GBP', 'AUD', 'BRL', 'CAD', 'CNY']
const countries = ['Indonesia', 'USA', 'Canada', 'UK', 'India', 'Australia']
const languages = ['Indonesian', 'English', 'Arabic', 'Hindi', 'Urdu']

function fillForm(user) {
  if (!user)
    return

  savedUser.value = { ...user }
  form.value = {
    name: user.name ?? '',
    email: user.email ?? '',
    organization: user.organization ?? '',
    phone: user.phone ?? '',
    address: user.address ?? '',
    state: user.state ?? '',
    zipCode: user.zip_code ?? '',
    country: user.country ?? '',
    language: user.language ?? '',
    timezone: user.timezone ?? '',
    currency: user.currency ?? '',
    avatarUrl: user.avatar_url ?? '',
    isActive: user.is_active ?? true,
  }
}

function notifySuccess(message) {
  snackbarMessage.value = message
  showSnackbar.value = true
}

function changeAvatar(event) {
  const file = event.target.files?.[0]

  if (!file)
    return

  if (file.size > 800 * 1024) {
    errorMessage.value = 'Ukuran gambar maksimal 800 KB.'

    return
  }

  const fileReader = new FileReader()

  fileReader.onload = () => {
    if (typeof fileReader.result === 'string')
      form.value.avatarUrl = fileReader.result
  }
  fileReader.readAsDataURL(file)
}

function resetAvatar() {
  form.value.avatarUrl = savedUser.value?.avatar_url ?? ''
}

function resetForm() {
  fillForm(savedUser.value)
  errorMessage.value = ''
}

async function saveProfile() {
  saving.value = true
  errorMessage.value = ''

  try {
    const payload = {
      ...form.value,
      ['zip_code']: form.value.zipCode,
      ['avatar_url']: form.value.avatarUrl,
    }

    delete payload.zipCode
    delete payload.avatarUrl
    delete payload.isActive

    const response = await http.put('/api/auth/profile', payload)

    auth.user = response.user
    fillForm(response.user)
    notifySuccess('Profil berhasil disimpan.')
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal menyimpan profil.'
  }
  finally {
    saving.value = false
  }
}

async function deactivateAccount() {
  deactivating.value = true
  errorMessage.value = ''

  try {
    const response = await http.put('/api/auth/profile', { ['is_active']: false })

    auth.user = response.user
    fillForm(response.user)
    confirmDeactivate.value = false
    notifySuccess('Akun berhasil dinonaktifkan.')
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal menonaktifkan akun.'
  }
  finally {
    deactivating.value = false
  }
}

onMounted(async () => {
  await auth.fetchUser({ force: true })
  fillForm(auth.user)
})

watch(() => auth.user, user => fillForm(user))
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VAlert
        v-if="errorMessage"
        type="error"
        variant="tonal"
        class="mb-4"
        closable
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <VCard title="Account Details">
        <VCardText class="d-flex flex-wrap align-center gap-6">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded="lg"
            size="100"
            :image="avatarSrc"
          />

          <!-- 👉 Upload Photo -->
          <form class="d-flex flex-column justify-center gap-5">
            <div class="d-flex flex-wrap gap-2">
              <VBtn
                color="primary"
                @click="refInputEl?.click()"
              >
                <VIcon
                  icon="bx-cloud-upload"
                  class="d-sm-none"
                />
                <span class="d-none d-sm-block">Upload new photo</span>
              </VBtn>

              <input
                ref="refInputEl"
                type="file"
                name="file"
                accept=".jpeg,.png,.jpg,GIF"
                hidden
                @input="changeAvatar"
              >

              <VBtn
                type="reset"
                color="error"
                variant="tonal"
                @click="resetAvatar"
              >
                <span class="d-none d-sm-block">Reset</span>
                <VIcon
                  icon="bx-refresh"
                  class="d-sm-none"
                />
              </VBtn>
            </div>

            <p class="text-body-1 mb-0">
              Allowed JPG, GIF or PNG. Max size of 800K
            </p>
          </form>
        </VCardText>

        <VDivider />

        <VCardText>
          <VForm @submit.prevent="saveProfile">
            <VRow>
              <!-- 👉 Name -->
              <VCol
                md="6"
                cols="12"
              >
                <VTextField
                  v-model="form.name"
                  label="Nama Lengkap"
                  required
                />
              </VCol>

              <!-- 👉 Email -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.email"
                  label="E-mail"
                  type="email"
                  required
                />
              </VCol>

              <!-- 👉 Role (read only) -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  :model-value="roleLabel"
                  label="Role"
                  readonly
                  hint="Role diubah oleh admin di menu Pengguna"
                  persistent-hint
                />
              </VCol>

              <!-- 👉 Status (read only) -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  :model-value="form.isActive ? 'Aktif' : 'Nonaktif'"
                  label="Status Akun"
                  readonly
                />
              </VCol>

              <!-- 👉 Organization -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.organization"
                  label="Organization"
                />
              </VCol>

              <!-- 👉 Phone -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.phone"
                  label="Phone Number"
                />
              </VCol>

              <!-- 👉 Address -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.address"
                  label="Address"
                />
              </VCol>

              <!-- 👉 State -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.state"
                  label="State"
                />
              </VCol>

              <!-- 👉 Zip Code -->
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.zipCode"
                  label="Zip Code"
                />
              </VCol>

              <!-- 👉 Country -->
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.country"
                  label="Country"
                  :items="countries"
                />
              </VCol>

              <!-- 👉 Language -->
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.language"
                  label="Language"
                  :items="languages"
                />
              </VCol>

              <!-- 👉 Timezone -->
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.timezone"
                  label="Timezone"
                  :items="timezones"
                  :menu-props="{ maxHeight: 200 }"
                />
              </VCol>

              <!-- 👉 Currency -->
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.currency"
                  label="Currency"
                  :items="currencies"
                  :menu-props="{ maxHeight: 200 }"
                />
              </VCol>

              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn
                  type="submit"
                  color="primary"
                  :loading="saving"
                >
                  Save changes
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                  @click.prevent="resetForm"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <!-- 👉 Deactivate Account -->
      <VCard title="Deactivate Account">
        <VCardText>
          <div>
            <VCheckbox
              v-model="confirmDeactivate"
              label="I confirm my account deactivation"
            />
          </div>

          <VBtn
            :disabled="!confirmDeactivate"
            color="error"
            class="mt-3"
            :loading="deactivating"
            @click="deactivateAccount"
          >
            Deactivate Account
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <VSnackbar
      v-model="showSnackbar"
      :timeout="4000"
      color="success"
    >
      {{ snackbarMessage }}
      <template #append>
        <VBtn
          icon
          color="white"
          size="small"
          @click="showSnackbar = false"
        >
          ×
        </VBtn>
      </template>
    </VSnackbar>
  </VRow>
</template>
