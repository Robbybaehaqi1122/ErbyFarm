<script setup>
import { http } from '@/services/http'

const roleOptions = [
  { title: 'Admin / Owner', value: 'admin' },
  { title: 'Operator Kandang', value: 'operator' },
  { title: 'Viewer / Staff', value: 'viewer' },
]

const users = ref([])
const loading = ref(false)
const savingId = ref(null)
const errorMessage = ref('')

// Dialog state
const showAddDialog = ref(false)
const showEditDialog = ref(false)
const showDetailDialog = ref(false)
const editingUser = ref(null)
const detailUser = ref(null)
const editForm = ref({})
const editLoading = ref(false)
const addLoading = ref(false)

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
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal menambahkan pengguna.'
  }
  finally {
    addLoading.value = false
  }
}

function openEditDialog(user) {
  editingUser.value = user
  editForm.value = {
    name: user.name,
    email: user.email,
    role: user.roles?.[0] ?? 'viewer',
  }
  showEditDialog.value = true
}

function viewDetail(user) {
  detailUser.value = user
  showDetailDialog.value = true
}

async function updateUser() {
  editLoading.value = true
  errorMessage.value = ''

  try {
    const response = await http.put(`/api/users/${editingUser.value.id}`, editForm.value)

    const index = users.value.findIndex(u => u.id === editingUser.value.id)
    if (index !== -1) {
      Object.assign(users.value[index], response.user)
    }

    showEditDialog.value = false
    editingUser.value = null
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal memperbarui pengguna.'
  }
  finally {
    editLoading.value = false
  }
}

async function updateRole(user, role) {
  savingId.value = user.id
  errorMessage.value = ''

  try {
    const response = await http.patch(`/api/users/${user.id}/role`, { role })

    Object.assign(user, response.user)
  }
  catch (error) {
    errorMessage.value = error.data?.message ?? 'Gagal mengubah role.'
  }
  finally {
    savingId.value = null
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
                :color="user.roles?.[0] === 'admin' ? 'primary' : user.roles?.[0] === 'operator' ? 'warning' : 'info'"
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
                      title="Detail"
                      @click="viewDetail(user)"
                    >
                      Detail
                    </VListItem>
                    <VListItem
                      prepend-icon="bx-pencil"
                      title="Edit"
                      @click="openEditDialog(user)"
                    >
                      Edit
                    </VListItem>
                    <VDivider />
                    <VListItem
                      prepend-icon="bx-trash"
                      title="Hapus"
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

    <!-- 👉 Dialog Edit Pengguna -->
    <VDialog
      v-model="showEditDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>
          Edit Pengguna
        </VCardTitle>

        <VCardText>
          <VForm @submit.prevent="updateUser">
            <VTextField
              v-model="editForm.name"
              label="Nama Lengkap"
              required
              class="mb-3"
            />
            <VTextField
              v-model="editForm.email"
              label="Email"
              type="email"
              required
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
                @click="showEditDialog = false"
              >
                Batal
              </VBtn>
              <VBtn
                variant="tonal"
                color="primary"
                type="submit"
                :loading="editLoading"
              >
                Simpan
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- 👉 Dialog Detail Pengguna -->
    <VDialog
      v-model="showDetailDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>
          Detail Pengguna
        </VCardTitle>

        <VCardText v-if="detailUser">
          <VList>
            <VListItem
              prepend-icon="bx-user"
              title="Nama"
            >
              <template #append>
                <span class="font-weight-medium">{{ detailUser.name }}</span>
              </template>
            </VListItem>
            <VListItem
              prepend-icon="bx-envelope"
              title="Email"
            >
              <template #append>
                <span>{{ detailUser.email }}</span>
              </template>
            </VListItem>
            <VListItem
              prepend-icon="bx-shield"
              title="Role"
            >
              <template #append>
                <VChip
                  :color="
                    detailUser.roles?.[0] === 'admin'
                      ? 'primary'
                      : detailUser.roles?.[0] === 'operator'
                        ? 'warning'
                        : 'info'
                  "
                  variant="tonal"
                  size="small"
                >
                  {{ detailUser.roles?.[0] ?? '-' }}
                </VChip>
              </template>
            </VListItem>
            <VListItem
              prepend-icon="bx-time"
              title="Dibuat pada"
            >
              <template #append>
                <span>{{ detailUser.created_at }}</span>
              </template>
            </VListItem>
          </VList>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="showDetailDialog = false"
          >
            Tutup
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
