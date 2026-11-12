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
            <th style="width: 220px;">
              Role
            </th>
            <th style="width: 100px;">
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
              <VSelect
                :model-value="user.roles[0]"
                :items="roleOptions"
                :loading="savingId === user.id"
                density="compact"
                hide-details
                @update:model-value="role => updateRole(user, role)"
              />
            </td>
            <td>
              <VBtn
                icon="bx-trash"
                variant="text"
                color="error"
                size="small"
                :loading="savingId === user.id"
                @click="removeUser(user)"
              />
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
  </div>
</template>
