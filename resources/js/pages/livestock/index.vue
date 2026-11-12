<script setup>
/* eslint-disable camelcase -- Laravel API payloads and responses use snake_case */
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
const formError = ref('')

// Table data
const livestocks = ref([])
const farms = ref([])
const editingId = ref(null)
const deletingName = ref('')
const dialogTitle = ref('Tambah Populasi Ayam')

const filters = ref({
  search: '',
  farmId: null,
  status: null,
})

const today = () => new Date().toISOString().slice(0, 10)

const emptyForm = () => ({
  id: null,
  farm_id: null,
  initial_population: '',
  current_population: '',
  entry_date: today(),
  age_weeks: '',
  status: 'active',
})

const form = ref(emptyForm())

const statusOptions = [
  { value: 'active', label: 'Aktif' },
  { value: 'culled', label: 'Afkir' },
  { value: 'empty', label: 'Kosong' },
]

const statusLabels = {
  active: 'Aktif',
  culled: 'Afkir',
  empty: 'Kosong',
}

const statusColors = {
  active: 'success',
  culled: 'error',
  empty: 'secondary',
}

const headers = [
  { title: 'Kandang', key: 'farm', align: 'start', sortable: false },
  { title: 'Populasi', key: 'population', align: 'start', sortable: false },
  { title: 'Tanggal Masuk', key: 'entry_date', align: 'center' },
  { title: 'Umur', key: 'age_weeks', align: 'center' },
  { title: 'Status', key: 'status', align: 'center' },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const filteredLivestocks = computed(() => {
  const search = filters.value.search.trim().toLowerCase()

  return livestocks.value.filter(item => {
    const matchesSearch = !search || (item.farm?.name ?? '').toLowerCase().includes(search)
    const matchesFarm = !filters.value.farmId || item.farm_id === filters.value.farmId
    const matchesStatus = !filters.value.status || item.status === filters.value.status

    return matchesSearch && matchesFarm && matchesStatus
  })
})

const totals = computed(() => {
  const active = livestocks.value.filter(item => item.status === 'active')

  return {
    current: active.reduce((sum, item) => sum + item.current_population, 0),
    initial: active.reduce((sum, item) => sum + item.initial_population, 0),
    flocks: active.length,
    farms: new Set(active.map(item => item.farm_id)).size,
  }
})

const hasFilters = computed(() => Boolean(filters.value.search || filters.value.farmId || filters.value.status))

const survivalRate = item => {
  if (!item.initial_population) {
    return 0
  }

  return Math.round((item.current_population / item.initial_population) * 100)
}

const survivalColor = rate => {
  if (rate >= 95) {
    return 'success'
  }

  if (rate >= 85) {
    return 'warning'
  }

  return 'error'
}

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatDate = value => {
  if (!value) {
    return '-'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

const fetchLivestocks = async () => {
  loading.value = true
  try {
    const res = await http.get('/api/livestocks?per_page=200')

    livestocks.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const fetchFarms = async () => {
  try {
    const res = await http.get('/api/farms?per_page=200')

    farms.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  }
}

const resetFilters = () => {
  filters.value = { search: '', farmId: null, status: null }
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Populasi Ayam'
  formError.value = ''
  form.value = emptyForm()

  if (farms.value.length === 1) {
    form.value.farm_id = farms.value[0].id
  }

  dialog.value = true
}

const openEditDialog = item => {
  editingId.value = item.id
  dialogTitle.value = 'Edit Populasi Ayam'
  formError.value = ''
  form.value = {
    id: item.id,
    farm_id: item.farm_id,
    initial_population: item.initial_population,
    current_population: item.current_population,
    entry_date: String(item.entry_date ?? '').slice(0, 10),
    age_weeks: item.age_weeks,
    status: item.status,
  }
  dialog.value = true
}

const saveLivestock = async () => {
  formError.value = ''

  if (!form.value.farm_id || !form.value.initial_population || !form.value.entry_date) {
    formError.value = 'Kandang, populasi awal, dan tanggal masuk wajib diisi.'

    return
  }

  dialogLoading.value = true
  try {
    const initialPopulation = parseInt(form.value.initial_population, 10)

    const currentPopulation = form.value.current_population === '' || form.value.current_population === null
      ? initialPopulation
      : parseInt(form.value.current_population, 10)

    const payload = {
      farm_id: form.value.farm_id,
      initial_population: initialPopulation,
      current_population: currentPopulation,
      entry_date: form.value.entry_date,
      status: form.value.status,
    }

    if (form.value.age_weeks !== '' && form.value.age_weeks !== null) {
      payload.age_weeks = parseInt(form.value.age_weeks, 10)
    }

    if (editingId.value) {
      await http.patch(`/api/livestocks/${editingId.value}`, payload)
    } else {
      await http.post('/api/livestocks', payload)
    }

    dialog.value = false
    await fetchLivestocks()
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    formError.value = firstError ?? error.data?.message ?? 'Gagal menyimpan data'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = item => {
  editingId.value = item.id
  deletingName.value = item.farm?.name ?? 'populasi ini'
  deleteDialog.value = true
}

const deleteLivestock = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/livestocks/${editingId.value}`)
    deleteDialog.value = false
    await fetchLivestocks()
  } catch (error) {
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  fetchLivestocks()
  fetchFarms()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Populasi Ayam
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Pantau populasi, umur, dan status ayam di setiap kandang.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        :disabled="!farms.length"
        @click="openCreateDialog"
      >
        Tambah Populasi
      </VBtn>
    </div>

    <VAlert
      v-if="!loading && !farms.length"
      type="info"
      variant="tonal"
      class="mb-6"
    >
      Belum ada kandang. Tambahkan data kandang terlebih dahulu sebelum mencatat populasi ayam.
    </VAlert>

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
                icon="bx-layer"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Populasi Hidup
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.current) }}
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
                icon="bx-spreadsheet"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Populasi Awal
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.initial) }}
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
              color="success"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-list-check"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Batch Aktif
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.flocks) }}
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
                icon="bx-buildings"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Kandang Terpakai
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.farms) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <VTextField
              v-model="filters.search"
              label="Cari kandang"
              prepend-inner-icon="bx-search"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.farmId"
              :items="farms"
              item-title="name"
              item-value="id"
              label="Semua Kandang"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.status"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              label="Semua Status"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="1"
            class="d-flex align-center justify-end"
          >
            <VBtn
              v-if="hasFilters"
              icon
              variant="text"
              color="medium-emphasis"
              title="Reset filter"
              @click="resetFilters"
            >
              <VIcon icon="bx-reset" />
            </VBtn>
            <VBtn
              icon
              variant="text"
              color="primary"
              title="Muat ulang"
              :loading="loading"
              @click="fetchLivestocks"
            >
              <VIcon icon="bx-refresh" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTable
        :loading="loading"
        :items="filteredLivestocks"
        :headers="headers"
        item-key="id"
        :items-per-page="10"
      >
        <template #item.farm="{ item }">
          <div class="font-weight-medium">
            {{ item.farm?.name ?? '-' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            Kapasitas {{ formatNumber(item.farm?.capacity) }} ekor
          </div>
        </template>

        <template #item.population="{ item }">
          <div
            class="d-flex align-center justify-space-between mb-1"
            style="min-width: 140px"
          >
            <span class="font-weight-medium">{{ formatNumber(item.current_population) }}</span>
            <span class="text-caption text-medium-emphasis">
              dari {{ formatNumber(item.initial_population) }}
            </span>
          </div>
          <VProgressLinear
            :model-value="survivalRate(item)"
            :color="survivalColor(survivalRate(item))"
            height="6"
            rounded
          />
          <div class="text-caption text-medium-emphasis mt-1">
            SR {{ survivalRate(item) }}%
          </div>
        </template>

        <template #item.entry_date="{ item }">
          {{ formatDate(item.entry_date) }}
        </template>

        <template #item.age_weeks="{ item }">
          {{ item.age_weeks }} minggu
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="statusColors[item.status]"
            size="small"
            variant="tonal"
          >
            {{ statusLabels[item.status] }}
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
              icon="bx-layer"
              size="64"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-4">
              {{ hasFilters ? 'Tidak ada populasi yang cocok dengan filter.' : 'Belum ada data populasi ayam.' }}
            </div>
            <VBtn
              v-if="!hasFilters && canManage && farms.length"
              color="primary"
              variant="tonal"
              prepend-icon="bx-plus"
              class="mt-4"
              @click="openCreateDialog"
            >
              Tambah Populasi Pertama
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Add / Edit Dialog -->
    <VDialog
      v-model="dialog"
      max-width="560"
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
            v-model="form.farm_id"
            :items="farms"
            item-title="name"
            item-value="id"
            label="Kandang"
            placeholder="Pilih kandang"
            required
            autofocus
          >
            <template #item="{ props, item }">
              <VListItem
                v-bind="props"
                :subtitle="`Kapasitas ${formatNumber(item.raw.capacity)} ekor`"
              />
            </template>
          </VSelect>

          <VRow class="mt-1">
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.initial_population"
                label="Populasi Awal"
                type="number"
                min="1"
                placeholder="Misal: 5000"
                required
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.current_population"
                label="Populasi Saat Ini"
                type="number"
                min="0"
                placeholder="Sama dengan populasi awal"
                hint="Kosongkan jika belum ada pengurangan."
                persistent-hint
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.entry_date"
                label="Tanggal Masuk"
                type="date"
                required
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.age_weeks"
                label="Umur (minggu)"
                type="number"
                min="0"
                placeholder="Hitung otomatis"
                hint="Kosongkan untuk dihitung dari tanggal masuk."
                persistent-hint
              />
            </VCol>
          </VRow>

          <VSelect
            v-model="form.status"
            :items="statusOptions"
            item-title="label"
            item-value="value"
            label="Status"
            class="mt-2"
            required
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
            @click="saveLivestock"
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
        <VCardTitle>Hapus Populasi?</VCardTitle>
        <VCardText>
          Data populasi ayam untuk <strong>{{ deletingName }}</strong> akan dihapus secara permanen.
          Tindakan ini tidak dapat dibatalkan.
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
            @click="deleteLivestock"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
