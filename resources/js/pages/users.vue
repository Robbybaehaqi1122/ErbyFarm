<script setup>
import { http } from '@/services/http'

const roleOptions = [
  { title: 'Superadmin / Developer', value: 'developer' },
  { title: 'Admin / Owner', value: 'admin' },
  { title: 'Operator Kandang', value: 'operator' },
  { title: 'Viewer / Staff', value: 'viewer' },
]

const users = ref([])
const loading = ref(false)
const savingId = ref(null)
const errorMessage = ref('')
const showSnackbar = ref(false)
const snackbarMessage = ref('')

function notifySuccess(message) {
  snackbarMessage.value = message
  showSnackbar.value = true
}

// Dialog state
const showAddDialog = ref(false)
const editForm = ref({})
const showEditAccountDialog = ref(false)
const editAccountUser = ref(null)
const editAccountForm = ref({})
const editAccountLoading = ref(false)
const addLoading = ref(false)
const deactivatingId = ref(null)

const timezones = [
  '(GMT-11:00) International Date Line West',
  '(GMT-11:00) Midway Island',
  '(GMT-10:00) Hawaii',
  '(GMT-09:00) Alaska',
  '(GMT-08:00) Pacific Time (US & Canada)',
  '(GMT-07:00) Mountain Time (US & Canada)',
  '(GMT-06:00) Central Time (US & Canada)',
  '(GMT-05:00) Eastern Time (US & Canada)',
  '(GMT-04:00) Atlantic Time (Canada)',
  '(GMT-03:00) Brasilia',
  '(GMT-02:00) Mid-Atlantic',
  '(GMT-01:00) Azores',
  '(GMT+00:00) London',
  '(GMT+01:00) Berlin',
  '(GMT+02:00) Athens',
  '(GMT+03:00) Moscow',
  '(GMT+04:00) Dubai',
  '(GMT+05:00) Karachi',
  '(GMT+06:00) Dhaka',
  '(GMT+07:00) Jakarta',
  '(GMT+08:00) Singapore',
  '(GMT+09:00) Tokyo',
  '(GMT+10:00) Sydney',
]

const currencies = ['USD', 'EUR', 'GBP', 'AUD', 'BRL', 'CAD', 'CNY', 'IDR']

const countries = ['USA', 'Canada', 'UK', 'Indonesia', 'India', 'Australia']

const languages = ['English', 'Indonesian', 'Arabic', 'Hindi', 'Urdu']

const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
})

async function loadUsers(page = 1) {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await http.get(`/api/users?page=${page}`)

    users.value = response.data
    pagination.value = {
      currentPage: response.meta.current_page,
      lastPage: response.meta.last_page,
      total: response.meta.total,
    }
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal memuat pengguna.'
  }
  finally {
    loading.value = false
  }
}

function openAddDialog() {
  editForm.value = { name: '', email: '', password: '', passwordConfirmation: '', role: 'viewer' }
  showAddDialog.value = true
}

async function addUser() {
  addLoading.value = true
  errorMessage.value = ''

  try {
    const passwordConfirmKey = 'password_confirmation'

    const payload = {
      ...editForm.value,
      [passwordConfirmKey]: editForm.value.passwordConfirmation,
    }

    delete payload.passwordConfirmation

    const response = await http.post('/api/users', payload)

    users.value.unshift(response.user)
    pagination.value.total += 1
    showAddDialog.value = false
    notifySuccess('Pengguna berhasil ditambahkan.')
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal menambahkan pengguna.'
  }
  finally {
    addLoading.value = false
  }
}

function openEditAccountDialog(user) {
  editAccountUser.value = user
  editAccountForm.value = {
    name: user.name,
    email: user.email,
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
    role: user.roles?.[0] ?? 'viewer',
  }
  showEditAccountDialog.value = true
}

async function saveEditAccount() {
  editAccountLoading.value = true
  errorMessage.value = ''

  try {
    const payload = {
      ...editAccountForm.value,
      ['zip_code']: editAccountForm.value.zipCode,
      ['avatar_url']: editAccountForm.value.avatarUrl,
      ['is_active']: editAccountForm.value.isActive,
    }

    delete payload.zipCode
    delete payload.avatarUrl
    delete payload.isActive

    const response = await http.put(`/api/users/${editAccountUser.value.id}`, payload)

    const index = users.value.findIndex(u => u.id === editAccountUser.value.id)
    if (index !== -1) {
      Object.assign(users.value[index], response.user)
    }

    showEditAccountDialog.value = false
    editAccountUser.value = null
    notifySuccess('Perubahan akun berhasil disimpan.')
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal memperbarui akun.'
  }
  finally {
    editAccountLoading.value = false
  }
}

async function toggleAccountActive() {
  if (!editAccountUser.value)
    return

  const nextActive = !editAccountForm.value.isActive
  const action = nextActive ? 'Aktifkan' : 'Nonaktifkan'

  if (!window.confirm(`${action} akun ${editAccountUser.value.name}?`))
    return

  deactivatingId.value = editAccountUser.value.id
  errorMessage.value = ''

  try {
    const response = await http.put(`/api/users/${editAccountUser.value.id}`, { ['is_active']: nextActive })

    const index = users.value.findIndex(u => u.id === editAccountUser.value.id)
    if (index !== -1) {
      Object.assign(users.value[index], response.user)
    }

    editAccountForm.value.isActive = nextActive
    notifySuccess(`Akun ${editAccountUser.value.name} berhasil ${action.toLowerCase()}.`)
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? `Gagal ${action.toLowerCase()} akun.`
  }
  finally {
    deactivatingId.value = null
  }
}

async function removeUser(user) {
  if (!window.confirm(`Hapus pengguna ${user.name}?`))
    return

  savingId.value = user.id
  errorMessage.value = ''

  try {
    await http.delete(`/api/users/${user.id}`)

    users.value = users.value.filter(item => item.id !== user.id)
    pagination.value.total -= 1
    notifySuccess('Pengguna berhasil dihapus.')
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal menghapus pengguna.'
  }
  finally {
    savingId.value = null
  }
}

onMounted(() => loadUsers())
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Pengguna
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Kelola akun dan role akses farm.
        </p>
      </div>

      <VBtn
        variant="tonal"
        color="primary"
        prepend-icon="bx-plus"
        :loading="loading"
        @click="openAddDialog"
      >
        Tambah Pengguna
      </VBtn>

      <VChip
        color="primary"
        variant="tonal"
      >
        {{ pagination.total }} pengguna
      </VChip>
    </div>

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

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th style="width: 170px;">
              Role
            </th>
            <th style="width: 140px;">
              Aksi
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
          >
            <td class="font-weight-medium">
              {{ user.name }}
            </td>
            <td>{{ user.email }}</td>
            <td>
              <VChip
                :color="
                  user.roles?.[0] === 'admin'
                    ? 'primary'
                    : user.roles?.[0] === 'operator'
                      ? 'warning'
                      : 'info'
                "
                variant="tonal"
                size="small"
              >
                {{ user.roles?.[0] ?? '-' }}
              </VChip>
            </td>
            <td>
              <VBtn
                variant="tonal"
                color="primary"
                size="small"
                prepend-icon="bx-dots-vertical-rounded"
              >
                Aksi

                <VMenu activator="parent">
                  <VList>
                    <VListItem
                      prepend-icon="bx-user"
                      @click="openEditAccountDialog(user)"
                    >
                      Edit Account
                    </VListItem>
                    <VDivider />
                    <VListItem
                      prepend-icon="bx-trash"
                      @click="removeUser(user)"
                    >
                      Hapus
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
            </td>
          </tr>

          <tr v-if="!loading && !users.length">
            <td
              colspan="4"
              class="text-center text-medium-emphasis py-8"
            >
              Belum ada pengguna.
            </td>
          </tr>
        </tbody>
      </VTable>

      <VCardText
        v-if="pagination.lastPage > 1"
        class="d-flex justify-end"
      >
        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            size="small"
            :disabled="pagination.currentPage <= 1 || loading"
            @click="loadUsers(pagination.currentPage - 1)"
          >
            Sebelumnya
          </VBtn>
          <span class="text-body-2">
            {{ pagination.currentPage }} / {{ pagination.lastPage }}
          </span>
          <VBtn
            variant="tonal"
            size="small"
            :disabled="pagination.currentPage >= pagination.lastPage || loading"
            @click="loadUsers(pagination.currentPage + 1)"
          >
            Berikutnya
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 Dialog Tambah Pengguna -->
    <VDialog
      v-model="showAddDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>
          Tambah Pengguna
        </VCardTitle>

        <VCardText>
          <VForm @submit.prevent="addUser">
            <VTextField
              v-model="editForm.name"
              label="Nama Lengkap"
              required
              :error-messages="editForm.name ? null : 'Nama wajib diisi'"
              class="mb-3"
            />
            <VTextField
              v-model="editForm.email"
              label="Email"
              type="email"
              required
              :error-messages="editForm.email ? null : 'Email wajib diisi'"
              class="mb-3"
            />
            <VTextField
              v-model="editForm.password"
              label="Password"
              type="password"
              required
              :rules="[(v) => !!v || 'Password wajib diisi', (v) => (v && v.length >= 8) || 'Minimal 8 karakter']"
              class="mb-3"
            />
            <VTextField
              v-model="editForm.passwordConfirmation"
              label="Konfirmasi Password"
              type="password"
              required
              :error-messages="editForm.passwordConfirmation === editForm.password ? null : 'Password tidak cocok'"
              class="mb-3"
            />
            <VSelect
              v-model="editForm.role"
              label="Role"
              :items="roleOptions"
              item-title="title"
              item-value="value"
              required
              class="mb-3"
            />

            <div class="d-flex justify-end gap-2">
              <VBtn
                variant="tonal"
                @click="showAddDialog = false"
              >
                Batal
              </VBtn>
              <VBtn
                variant="tonal"
                color="primary"
                type="submit"
                :loading="addLoading"
              >
                Simpan
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- 👉 Dialog Edit Account Detail -->
    <VDialog
      v-model="showEditAccountDialog"
      max-width="700"
    >
      <VCard>
        <VCardTitle>
          Edit Account
        </VCardTitle>

        <VCardText>
          <VForm @submit.prevent="saveEditAccount">
            <VRow>
              <!-- Avatar -->
              <VCol
                cols="12"
                md="4"
              >
                <VCard class="pa-4 text-center">
                  <VAvatar
                    size="100"
                    class="mb-4"
                    :image="
                      editAccountForm.avatarUrl || '@images/avatars/avatar-1.png'
                    "
                  />
                  <VBtn
                    variant="tonal"
                    color="primary"
                    prepend-icon="bx-cloud-upload"
                    @click.self="
                      editAccountForm.avatarUrl = prompt('Masukkan URL avatar:') || editAccountForm.avatarUrl
                    "
                  >
                    Upload Foto
                  </VBtn>
                </VCard>
              </VCol>

              <!-- Form fields -->
              <VCol
                cols="12"
                md="8"
              >
                <VTextField
                  v-model="editAccountForm.name"
                  label="Nama Lengkap"
                  required
                  class="mb-3"
                />
                <VTextField
                  v-model="editAccountForm.email"
                  label="Email"
                  type="email"
                  required
                  class="mb-3"
                />
                <VTextField
                  v-model="editAccountForm.organization"
                  label="Organisasi"
                  class="mb-3"
                />
                <VTextField
                  v-model="editAccountForm.phone"
                  label="Nomor Telepon"
                  class="mb-3"
                />
                <VTextField
                  v-model="editAccountForm.address"
                  label="Alamat"
                  class="mb-3"
                />
                <VRow>
                  <VCol cols="6">
                    <VTextField
                      v-model="editAccountForm.state"
                      label="State"
                      class="mb-3"
                    />
                  </VCol>
                  <VCol cols="6">
                    <VTextField
                      v-model="editAccountForm.zipCode"
                      label="Kode Pos"
                      class="mb-3"
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol cols="6">
                    <VSelect
                      v-model="editAccountForm.country"
                      label="Country"
                      :items="countries"
                      class="mb-3"
                    />
                  </VCol>
                  <VCol cols="6">
                    <VSelect
                      v-model="editAccountForm.language"
                      label="Language"
                      :items="languages"
                      class="mb-3"
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol cols="6">
                    <VSelect
                      v-model="editAccountForm.timezone"
                      label="Timezone"
                      :items="timezones"
                      class="mb-3"
                    />
                  </VCol>
                  <VCol cols="6">
                    <VSelect
                      v-model="editAccountForm.currency"
                      label="Currency"
                      :items="currencies"
                      class="mb-3"
                    />
                  </VCol>
                </VRow>
                <div class="d-flex align-center gap-3 mb-3">
                  <VChip
                    :color="editAccountForm.isActive ? 'success' : 'error'"
                    variant="tonal"
                    size="small"
                  >
                    {{ editAccountForm.isActive ? 'Akun Aktif' : 'Akun Nonaktif' }}
                  </VChip>
                  <VBtn
                    variant="tonal"
                    :color="editAccountForm.isActive ? 'error' : 'success'"
                    :prepend-icon="editAccountForm.isActive ? 'bx-power-off' : 'bx-check-circle'"
                    :loading="deactivatingId === editAccountUser?.id"
                    @click="toggleAccountActive"
                  >
                    {{ editAccountForm.isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                  </VBtn>
                </div>
                <VSelect
                  v-model="editAccountForm.role"
                  label="Role"
                  :items="roleOptions"
                  item-title="title"
                  item-value="value"
                  required
                  class="mb-3"
                />

                <div class="d-flex justify-end gap-2">
                  <VBtn
                    variant="tonal"
                    @click="showEditAccountDialog = false"
                  >
                    Batal
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    color="primary"
                    type="submit"
                    :loading="editAccountLoading"
                  >
                    Simpan Perubahan
                  </VBtn>
                </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Notifikasi aksi berhasil -->
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
  </div>
</template>
