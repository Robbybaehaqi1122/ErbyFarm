<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/services/http'

const auth = useAuthStore()
const canManage = computed(() => auth.hasRole(['admin', 'operator']))

// Dialog states
const dialog = ref(false)
const deleteDialog = ref(false)
const loading = ref(false)
const dialogLoading = ref(false)
const deleteLoading = ref(false)

// Table data
const farms = ref([])
const editingId = ref(null)
const dialogTitle = ref('Tambah Kandang')

const form = ref({
  id: null,
  name: '',
  capacity: '',
  status: 'active',
  notes: '',
})

const statusOptions = [
  { value: 'active', label: 'Aktif' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'inactive', label: 'Tidak Aktif' },
]

const statusColors = {
  active: 'success',
  maintenance: 'warning',
  inactive: 'error',
}

const headers = [
  { title: 'Nama Kandang', key: 'name', align: 'start' },
  { title: 'Kapasitas', key: 'capacity', align: 'center' },
  { title: 'Status', key: 'status', align: 'center' },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const fetchFarms = async () => {
  loading.value = true
  try {
    const res = await http.get('/api/farms')

    farms.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Kandang'
  form.value = { id: null, name: '', capacity: '', status: 'active', notes: '' }
  dialog.value = true
}

const openEditDialog = farm => {
  editingId.value = farm.id
  dialogTitle.value = 'Edit Kandang'
  form.value = { ...farm }
  dialog.value = true
}

const saveFarm = async () => {
  dialogLoading.value = true
  try {
    const payload = {
      name: form.value.name,
      capacity: parseInt(form.value.capacity, 10),
      status: form.value.status,
      notes: form.value.notes,
    }

    if (editingId.value) {
      await http.patch(`/api/farms/${editingId.value}`, payload)
    } else {
      await http.post('/api/farms', payload)
    }

    dialog.value = false
    await fetchFarms()
  } catch (error) {
    console.error(error)
    alert(error.data?.message ?? 'Gagal menyimpan data')
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = farm => {
  editingId.value = farm.id
  deleteDialog.value = true
}

const deleteFarm = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/farms/${editingId.value}`)
    deleteDialog.value = false
    await fetchFarms()
  } catch (error) {
    console.error(error)
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => fetchFarms())
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Kandang
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Kelola data kandang ayam petelur.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        @click="openCreateDialog"
      >
        Tambah Kandang
      </VBtn>
    </div>

    <VCard>
      <VDataTable
        :loading="loading"
        :items="farms"
        :headers="headers"
        item-key="id"
        show-select
      >
        <template #item.name="{ item }">
          <div class="font-weight-medium">
            {{ item.name }}
          </div>
        </template>

        <template #item.capacity="{ item }">
          <span class="text-center">{{ item.capacity }}</span> ekor
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="statusColors[item.status]"
            size="small"
            variant="tonal"
          >
            {{ { active: 'Aktif', maintenance: 'Maintenance', inactive: 'Tidak Aktif' }[item.status] }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <VBtn
              v-if="canManage"
              icon
              size="small"
              variant="outlined"
              color="primary"
              @click="openEditDialog(item)"
            >
              <VIcon icon="bx-pencil" />
            </VBtn>
            <VBtn
              v-if="canManage"
              icon
              size="small"
              variant="outlined"
              color="error"
              @click="confirmDelete(item)"
            >
              <VIcon icon="bx-trash" />
            </VBtn>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-12">
            <VIcon
              icon="bx-farm"
              size="64"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-4">
              Belum ada data kandang.
            </div>
            <VBtn
              v-if="canManage"
              color="primary"
              variant="tonal"
              prepend-icon="bx-plus"
              class="mt-4"
              @click="openCreateDialog"
            >
              Tambah Kandang Pertama
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Add / Edit Dialog -->
    <VDialog
      v-model="dialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>

        <VCardText>
          <VTextField
            v-model="form.name"
            label="Nama Kandang"
            placeholder="Misal: Kandang A1"
            required
            autofocus
          />
          <VTextField
            v-model="form.capacity"
            label="Kapasitas Maksimal"
            type="number"
            placeholder="Misal: 5000"
            required
          />
          <VSelect
            v-model="form.status"
            :items="statusOptions"
            item-title="label"
            item-value="value"
            label="Status"
            required
          />
          <VTextarea
            v-model="form.notes"
            label="Catatan"
            placeholder="Catatan tambahan tentang kandang ini"
            rows="3"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Batal
          </VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="saveFarm"
          >
            Simpan
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog
      v-model="deleteDialog"
      max-width="400"
    >
      <VCard>
        <VCardTitle>Hapus Kandang?</VCardTitle>
        <VCardText>
          Data kandang akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="deleteDialog = false"
          >
            Batal
          </VBtn>
          <VBtn
            color="error"
            :loading="deleteLoading"
            @click="deleteFarm"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
