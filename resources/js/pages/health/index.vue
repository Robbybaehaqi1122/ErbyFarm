<script setup>
/* eslint-disable camelcase -- Laravel API payloads and responses use snake_case */
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/services/http'

const auth = useAuthStore()
const canManage = computed(() => auth.hasRole(['admin', 'operator']))
const canDelete = computed(() => auth.isAdmin)

// Dialog states
const dialog = ref(false)
const deleteDialog = ref(false)
const loading = ref(false)
const dialogLoading = ref(false)
const deleteLoading = ref(false)
const formError = ref('')

// Data
const records = ref([])
const totalRecords = ref(0)

const summary = ref({
  total_records: 0,
  livestock_count: 0,
  vaksinasi_count: 0,
  pengobatan_count: 0,
  vitamin_count: 0,
  culling_count: 0,
})

const page = ref(1)
const itemsPerPage = ref(10)

const editingId = ref(null)
const deletingLabel = ref('')
const dialogTitle = ref('Tambah Catatan Kesehatan')

const defaultFilters = () => ({
  livestockId: null,
  treatmentType: null,
  from: '',
  to: '',
  search: '',
})

const filters = ref(defaultFilters())

const emptyForm = () => ({
  livestock_id: null,
  treatment_type: 'vaksinasi',
  medicine_name: '',
  dosage: '',
  date: '',
  notes: '',
})

const form = ref(emptyForm())

const today = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const livestockOptions = ref([])

const headers = [
  { title: 'Tanggal', key: 'date', align: 'start' },
  { title: 'Populasi', key: 'livestock', align: 'start', sortable: false },
  { title: 'Jenis Perawatan', key: 'treatment_type', align: 'start', sortable: false },
  { title: 'Obat / Vaksin', key: 'medicine_name', align: 'start', sortable: false },
  { title: 'Dosis', key: 'dosage', align: 'start', sortable: false },
  { title: 'Catatan', key: 'notes', align: 'start', sortable: false },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatDate = value => {
  if (!value) {
    return '-'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

const treatmentTypeMeta = {
  vaksinasi: { label: 'Vaksinasi', icon: 'bx-injection', color: 'info' },
  pengobatan: { label: 'Pengobatan', icon: 'bx-plus-medical', color: 'primary' },
  vitamin: { label: 'Vitamin', icon: 'bx-capsule', color: 'success' },
  culling: { label: 'Culling', icon: 'bx-minus-circle', color: 'error' },
}

const treatmentTypeLabel = value => treatmentTypeMeta[value]?.label ?? value ?? '-'
const treatmentTypeColor = value => treatmentTypeMeta[value]?.color ?? 'secondary'
const treatmentTypeIcon = value => treatmentTypeMeta[value]?.icon ?? 'bx-clipboard'

const treatmentTypeOptions = [
  { value: 'vaksinasi', title: 'Vaksinasi' },
  { value: 'pengobatan', title: 'Pengobatan' },
  { value: 'vitamin', title: 'Vitamin' },
  { value: 'culling', title: 'Culling' },
]

const fetchRecords = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()

    if (filters.value.livestockId) {
      params.set('livestock_id', filters.value.livestockId)
    }

    if (filters.value.treatmentType) {
      params.set('treatment_type', filters.value.treatmentType)
    }

    if (filters.value.from) {
      params.set('from', filters.value.from)
    }

    if (filters.value.to) {
      params.set('to', filters.value.to)
    }

    if (filters.value.search.trim()) {
      params.set('search', filters.value.search.trim())
    }

    params.set('page', page.value)
    params.set('per_page', itemsPerPage.value)

    const res = await http.get(`/api/health-records?${params.toString()}`)

    records.value = res?.data ?? []
    totalRecords.value = res?.total ?? 0
    summary.value = {
      total_records: res?.summary?.total_records ?? 0,
      livestock_count: res?.summary?.livestock_count ?? 0,
      vaksinasi_count: res?.summary?.vaksinasi_count ?? 0,
      pengobatan_count: res?.summary?.pengobatan_count ?? 0,
      vitamin_count: res?.summary?.vitamin_count ?? 0,
      culling_count: res?.summary?.culling_count ?? 0,
    }
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  page.value = 1
  fetchRecords()
}

const resetFilters = () => {
  filters.value = defaultFilters()
  page.value = 1
  fetchRecords()
}

const onOptionsChange = options => {
  const changed = page.value !== options.page || itemsPerPage.value !== options.itemsPerPage

  page.value = options.page
  itemsPerPage.value = options.itemsPerPage

  if (changed) {
    fetchRecords()
  }
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Catatan Kesehatan'
  formError.value = ''
  form.value = emptyForm()
  dialog.value = true
}

const openEditDialog = item => {
  editingId.value = item.id
  dialogTitle.value = 'Edit Catatan Kesehatan'
  formError.value = ''
  form.value = {
    livestock_id: item.livestock_id,
    treatment_type: item.treatment_type,
    medicine_name: item.medicine_name ?? '',
    dosage: item.dosage ?? '',
    date: String(item.date ?? '').slice(0, 10),
    notes: item.notes ?? '',
  }
  dialog.value = true
}

const saveRecord = async () => {
  formError.value = ''

  if (!form.value.livestock_id || !form.value.treatment_type || !form.value.date) {
    formError.value = 'Populasi, jenis perawatan, dan tanggal wajib diisi.'

    return
  }

  dialogLoading.value = true
  try {
    const payload = {
      livestock_id: form.value.livestock_id,
      treatment_type: form.value.treatment_type,
      medicine_name: form.value.medicine_name?.trim() || null,
      dosage: form.value.dosage?.trim() || null,
      date: form.value.date,
      notes: form.value.notes?.trim() || null,
    }

    if (editingId.value) {
      await http.patch(`/api/health-records/${editingId.value}`, payload)
    } else {
      await http.post('/api/health-records', payload)
    }

    dialog.value = false
    await fetchRecords()
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    formError.value = firstError ?? error.data?.message ?? 'Gagal menyimpan data'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = item => {
  editingId.value = item.id
  deletingLabel.value = `${treatmentTypeLabel(item.treatment_type)} — ${formatDate(item.date)}`
  deleteDialog.value = true
}

const deleteRecord = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/health-records/${editingId.value}`)
    deleteDialog.value = false

    if (records.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await fetchRecords()
  } catch (error) {
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(async () => {
  try {
    const res = await http.get('/api/livestocks?per_page=200')

    livestockOptions.value = (res?.data ?? res ?? []).map(item => ({
      id: item.id,
      title: item.item_name ?? item.name ?? `Populasi #${item.id}`,
      subtitle: `${item.farm?.name ?? '-'} • Batch ${formatDate(item.entry_date)} • ${item.current_population ?? 0} ekor hidup`,
    }))
  } catch (error) {
    console.error(error)
  }

  fetchRecords()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Kesehatan & Vaksinasi
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Pantau riwayat vaksinasi, pengobatan, vitamin, dan culling tiap populasi.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        @click="openCreateDialog"
      >
        Tambah Catatan
      </VBtn>
    </div>

    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-health"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total Catatan
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.total_records) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                riwayat kesehatan
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="info"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-injection"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Vaksinasi
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.vaksinasi_count) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                catatan vaksinasi
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="warning"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-plus-medical"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Pengobatan
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.pengobatan_count) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                catatan pengobatan
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="error"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-minus-circle"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Culling
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.culling_count) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                catatan afkir
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="filters.from"
              label="Dari Tanggal"
              type="date"
              density="compact"
              hide-details
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="filters.to"
              label="Sampai Tanggal"
              type="date"
              density="compact"
              hide-details
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VSelect
              v-model="filters.treatmentType"
              :items="treatmentTypeOptions"
              item-title="title"
              item-value="value"
              label="Semua Jenis Perawatan"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
        </VRow>

        <div class="d-flex align-center justify-end gap-2 mt-4">
          <VBtn
            variant="text"
            color="medium-emphasis"
            prepend-icon="bx-reset"
            @click="resetFilters"
          >
            Reset
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="bx-filter-alt"
            :loading="loading"
            @click="applyFilters"
          >
            Terapkan Filter
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Riwayat Kesehatan & Vaksinasi</span>
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ formatNumber(totalRecords) }} catatan
        </VChip>
      </VCardTitle>

      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        :loading="loading"
        :items="records"
        :items-length="totalRecords"
        :headers="headers"
        item-key="id"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 25, title: '25' },
          { value: 50, title: '50' },
        ]"
        @update:options="onOptionsChange"
      >
        <template #item.date="{ item }">
          <span class="font-weight-medium">{{ formatDate(item.date) }}</span>
        </template>

        <template #item.livestock="{ item }">
          <div class="font-weight-medium">
            {{ item.livestock?.farm?.name ?? '-' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.livestock?.current_population ?? 0 }} ekor hidup
          </div>
        </template>

        <template #item.treatment_type="{ item }">
          <VChip
            :color="treatmentTypeColor(item.treatment_type)"
            size="small"
            variant="tonal"
            :prepend-icon="treatmentTypeIcon(item.treatment_type)"
          >
            {{ treatmentTypeLabel(item.treatment_type) }}
          </VChip>
        </template>

        <template #item.medicine_name="{ item }">
          <span v-if="item.medicine_name">{{ item.medicine_name }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >-</span>
        </template>

        <template #item.dosage="{ item }">
          <span v-if="item.dosage">{{ item.dosage }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >-</span>
        </template>

        <template #item.notes="{ item }">
          <span
            v-if="item.notes"
            :title="item.notes"
            class="d-inline-block text-truncate"
            style="max-width: 260px"
          >
            {{ item.notes }}
          </span>
          <span
            v-else
            class="text-medium-emphasis"
          >-</span>
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
              v-if="canDelete"
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
              icon="bx-health"
              size="64"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-4">
              Belum ada catatan kesehatan pada periode ini.
            </div>
            <VBtn
              v-if="canManage"
              color="primary"
              variant="tonal"
              prepend-icon="bx-plus"
              class="mt-4"
              @click="openCreateDialog"
            >
              Tambah Catatan Pertama
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Add / Edit Dialog -->
    <VDialog
      v-model="dialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>

        <VCardText>
          <VAlert
            v-if="formError"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ formError }}
          </VAlert>

          <VSelect
            v-model="form.livestock_id"
            :items="livestockOptions"
            item-title="title"
            item-value="id"
            label="Populasi Ayam"
            placeholder="Pilih populasi"
            required
            autofocus
          >
            <template #item="props">
              <VListItem
                v-bind="props"
                :title="props.item.title"
                :subtitle="props.item.subtitle"
              />
            </template>
          </VSelect>

          <VSelect
            v-model="form.treatment_type"
            :items="treatmentTypeOptions"
            item-title="title"
            item-value="value"
            label="Jenis Perawatan"
            class="mt-2"
            required
          />

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.medicine_name"
                label="Obat / Vaksin"
                placeholder="Misal: Vaksin ND"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.dosage"
                label="Dosis"
                placeholder="Misal: 0.5 ml/ekor"
              />
            </VCol>
          </VRow>

          <VTextField
            v-model="form.date"
            label="Tanggal Pelaksanaan"
            type="date"
            required
          />

          <VTextarea
            v-model="form.notes"
            label="Catatan Tambahan"
            rows="3"
            auto-grow
            placeholder="Misal: Gejala klinis, waktu pemulihan, dll."
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
            @click="saveRecord"
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
        <VCardTitle>Hapus Catatan Kesehatan?</VCardTitle>
        <VCardText>
          Catatan <strong>{{ deletingLabel }}</strong> akan dihapus permanen.
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
            @click="deleteRecord"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
