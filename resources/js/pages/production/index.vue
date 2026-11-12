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

// Data
const productions = ref([])
const livestocks = ref([])
const editingId = ref(null)
const deletingLabel = ref('')
const dialogTitle = ref('Tambah Produksi Harian')

const toDateInput = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const today = () => toDateInput(new Date())

const defaultFilters = () => {
  const now = new Date()

  return {
    from: toDateInput(new Date(now.getFullYear(), now.getMonth(), 1)),
    to: toDateInput(now),
    livestockId: null,
  }
}

const filters = ref(defaultFilters())

const emptyForm = () => ({
  livestock_id: null,
  date: today(),
  egg_count: '',
  egg_weight_kg: '',
  feed_consumed_kg: '',
  water_consumed_liter: '',
  mortality: 0,
})

const form = ref(emptyForm())

const livestockStatusLabels = {
  active: 'Aktif',
  culled: 'Afkir',
  empty: 'Kosong',
}

const headers = [
  { title: 'Tanggal', key: 'date', align: 'start' },
  { title: 'Kandang', key: 'farm', align: 'start', sortable: false },
  { title: 'Telur', key: 'egg_count', align: 'end' },
  { title: 'Berat Telur', key: 'egg_weight_kg', align: 'end' },
  { title: 'Pakan', key: 'feed_consumed_kg', align: 'end' },
  { title: 'Air', key: 'water_consumed_liter', align: 'end' },
  { title: 'Mortalitas', key: 'mortality', align: 'center' },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatDecimal = (value, digits = 2) => Number(value ?? 0).toLocaleString('id-ID', {
  minimumFractionDigits: digits,
  maximumFractionDigits: digits,
})

const formatDate = value => {
  if (!value) {
    return '-'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

const averageEggWeight = item => {
  const eggCount = Number(item.egg_count ?? 0)

  if (!eggCount) {
    return 0
  }

  return ((Number(item.egg_weight_kg ?? 0) * 1000) / eggCount).toFixed(1)
}

const livestockOptions = computed(() => livestocks.value.map(item => ({
  id: item.id,
  title: item.farm?.name ?? `Populasi #${item.id}`,
  subtitle: `Batch ${formatDate(item.entry_date)} • ${formatNumber(item.current_population)} ekor • ${livestockStatusLabels[item.status] ?? item.status}`,
  status: item.status,
})))

const activeLivestocks = computed(() => livestocks.value.filter(item => item.status === 'active'))

const filteredProductions = computed(() => productions.value.filter(item => {
  const date = String(item.date ?? '').slice(0, 10)
  const matchesLivestock = !filters.value.livestockId || item.livestock_id === filters.value.livestockId
  const matchesFrom = !filters.value.from || date >= filters.value.from
  const matchesTo = !filters.value.to || date <= filters.value.to

  return matchesLivestock && matchesFrom && matchesTo
}))

const totals = computed(() => filteredProductions.value.reduce((result, item) => {
  result.eggs += Number(item.egg_count ?? 0)
  result.eggWeight += Number(item.egg_weight_kg ?? 0)
  result.feed += Number(item.feed_consumed_kg ?? 0)
  result.water += Number(item.water_consumed_liter ?? 0)
  result.mortality += Number(item.mortality ?? 0)

  return result
}, {
  eggs: 0,
  eggWeight: 0,
  feed: 0,
  water: 0,
  mortality: 0,
}))

const averageEggWeightTotal = computed(() => {
  if (!totals.value.eggs) {
    return 0
  }

  return (totals.value.eggWeight * 1000) / totals.value.eggs
})

const selectedLivestock = computed(() => livestocks.value.find(item => item.id === form.value.livestock_id))

const projectedPopulation = computed(() => {
  const livestock = selectedLivestock.value

  if (!livestock) {
    return null
  }

  let population = Number(livestock.current_population ?? 0)
  const original = productions.value.find(item => item.id === editingId.value)

  if (original && original.livestock_id === livestock.id) {
    population += Number(original.mortality ?? 0)
  }

  return Math.max(0, population - Number(form.value.mortality ?? 0))
})

const fetchProductions = async () => {
  loading.value = true
  try {
    const res = await http.get('/api/daily-productions?per_page=500')

    productions.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const fetchLivestocks = async () => {
  try {
    const res = await http.get('/api/livestocks?per_page=200')

    livestocks.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  }
}

const resetFilters = () => {
  filters.value = defaultFilters()
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Produksi Harian'
  formError.value = ''
  form.value = emptyForm()

  if (activeLivestocks.value.length === 1) {
    form.value.livestock_id = activeLivestocks.value[0].id
  }

  dialog.value = true
}

const openEditDialog = item => {
  editingId.value = item.id
  dialogTitle.value = 'Edit Produksi Harian'
  formError.value = ''
  form.value = {
    livestock_id: item.livestock_id,
    date: String(item.date ?? '').slice(0, 10),
    egg_count: item.egg_count,
    egg_weight_kg: item.egg_weight_kg,
    feed_consumed_kg: item.feed_consumed_kg,
    water_consumed_liter: item.water_consumed_liter,
    mortality: item.mortality,
  }
  dialog.value = true
}

const saveProduction = async () => {
  formError.value = ''

  if (!form.value.livestock_id || !form.value.date) {
    formError.value = 'Kandang dan tanggal pencatatan wajib diisi.'

    return
  }

  dialogLoading.value = true
  try {
    const payload = {
      livestock_id: form.value.livestock_id,
      date: form.value.date,
      egg_count: parseInt(form.value.egg_count || 0, 10),
      egg_weight_kg: parseFloat(form.value.egg_weight_kg || 0),
      feed_consumed_kg: parseFloat(form.value.feed_consumed_kg || 0),
      water_consumed_liter: parseFloat(form.value.water_consumed_liter || 0),
      mortality: parseInt(form.value.mortality || 0, 10),
    }

    if (editingId.value) {
      await http.patch(`/api/daily-productions/${editingId.value}`, payload)
    } else {
      await http.post('/api/daily-productions', payload)
    }

    dialog.value = false
    await Promise.all([fetchProductions(), fetchLivestocks()])
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    formError.value = firstError ?? error.data?.message ?? 'Gagal menyimpan data'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = item => {
  editingId.value = item.id
  deletingLabel.value = `${item.livestock?.farm?.name ?? 'populasi'} (${formatDate(item.date)})`
  deleteDialog.value = true
}

const deleteProduction = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/daily-productions/${editingId.value}`)
    deleteDialog.value = false
    await Promise.all([fetchProductions(), fetchLivestocks()])
  } catch (error) {
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  fetchProductions()
  fetchLivestocks()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Produksi Harian
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Catat produksi telur, pakan, air, dan mortalitas harian.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        :disabled="!activeLivestocks.length"
        @click="openCreateDialog"
      >
        Tambah Produksi
      </VBtn>
    </div>

    <VAlert
      v-if="!loading && !activeLivestocks.length"
      type="info"
      variant="tonal"
      class="mb-6"
    >
      Belum ada populasi ayam aktif. Tambahkan data populasi terlebih dahulu sebelum mencatat produksi harian.
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
                Total Telur
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.eggs) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDecimal(averageEggWeightTotal, 1) }} g/butir
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
                icon="bx-package"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Berat Telur
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatDecimal(totals.eggWeight) }} kg
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
                icon="bx-bowl-hot"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Pakan Terpakai
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatDecimal(totals.feed) }} kg
              </div>
              <div class="text-caption text-medium-emphasis">
                Air {{ formatDecimal(totals.water) }} L
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
                icon="bx-first-aid"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Mortalitas
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(totals.mortality) }} ekor
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
            sm="6"
            md="3"
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
            md="3"
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
              v-model="filters.livestockId"
              :items="livestockOptions"
              item-title="title"
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
            md="2"
            class="d-flex align-center justify-end"
          >
            <VBtn
              variant="text"
              color="medium-emphasis"
              prepend-icon="bx-reset"
              @click="resetFilters"
            >
              Bulan Ini
            </VBtn>
            <VBtn
              icon
              variant="text"
              color="primary"
              title="Muat ulang"
              :loading="loading"
              @click="fetchProductions"
            >
              <VIcon icon="bx-refresh" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTable
        :loading="loading"
        :items="filteredProductions"
        :headers="headers"
        item-key="id"
        :items-per-page="10"
      >
        <template #item.date="{ item }">
          <span class="font-weight-medium">{{ formatDate(item.date) }}</span>
        </template>

        <template #item.farm="{ item }">
          <div class="font-weight-medium">
            {{ item.livestock?.farm?.name ?? '-' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            Batch {{ formatDate(item.livestock?.entry_date) }} • {{ formatNumber(item.livestock?.current_population) }} ekor hidup
          </div>
        </template>

        <template #item.egg_count="{ item }">
          <span class="font-weight-medium">{{ formatNumber(item.egg_count) }}</span> butir
        </template>

        <template #item.egg_weight_kg="{ item }">
          <div>{{ formatDecimal(item.egg_weight_kg) }} kg</div>
          <div class="text-caption text-medium-emphasis">
            {{ averageEggWeight(item) }} g/butir
          </div>
        </template>

        <template #item.feed_consumed_kg="{ item }">
          {{ formatDecimal(item.feed_consumed_kg) }} kg
        </template>

        <template #item.water_consumed_liter="{ item }">
          {{ formatDecimal(item.water_consumed_liter) }} L
        </template>

        <template #item.mortality="{ item }">
          <VChip
            :color="item.mortality > 0 ? 'error' : 'success'"
            size="small"
            variant="tonal"
          >
            {{ formatNumber(item.mortality) }} ekor
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
              icon="bx-clipboard"
              size="64"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-4">
              Belum ada catatan produksi pada periode ini.
            </div>
            <VBtn
              v-if="canManage && activeLivestocks.length"
              color="primary"
              variant="tonal"
              prepend-icon="bx-plus"
              class="mt-4"
              @click="openCreateDialog"
            >
              Tambah Produksi Pertama
            </VBtn>
          </div>
        </template>
      </VDataTable>
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
            label="Kandang / Populasi"
            placeholder="Pilih kandang"
            required
            autofocus
          >
            <template #item="{ props, item }">
              <VListItem
                v-bind="props"
                :subtitle="item.raw.subtitle"
                :disabled="item.raw.status !== 'active' && item.raw.id !== form.livestock_id"
              />
            </template>
          </VSelect>

          <VTextField
            v-model="form.date"
            label="Tanggal Pencatatan"
            type="date"
            class="mt-2"
            required
          />

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.egg_count"
                label="Jumlah Telur"
                type="number"
                min="0"
                suffix="butir"
                placeholder="Misal: 4200"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.egg_weight_kg"
                label="Berat Telur"
                type="number"
                min="0"
                step="0.01"
                suffix="kg"
                placeholder="Misal: 125.50"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.feed_consumed_kg"
                label="Konsumsi Pakan"
                type="number"
                min="0"
                step="0.01"
                suffix="kg"
                placeholder="Misal: 480.25"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.water_consumed_liter"
                label="Konsumsi Air"
                type="number"
                min="0"
                step="0.01"
                suffix="liter"
                placeholder="Misal: 900"
              />
            </VCol>
          </VRow>

          <VTextField
            v-model="form.mortality"
            label="Mortalitas / Afkir"
            type="number"
            min="0"
            suffix="ekor"
            hint="Populasi hidup kandang otomatis berkurang sesuai angka mortalitas."
            persistent-hint
          />

          <VAlert
            v-if="projectedPopulation !== null"
            :type="projectedPopulation > 0 ? 'info' : 'warning'"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            Perkiraan populasi hidup setelah pencatatan:
            <strong>{{ formatNumber(projectedPopulation) }} ekor</strong>
          </VAlert>
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
            @click="saveProduction"
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
        <VCardTitle>Hapus Catatan Produksi?</VCardTitle>
        <VCardText>
          Catatan produksi <strong>{{ deletingLabel }}</strong> akan dihapus permanen.
          Populasi hidup kandang akan dikembalikan sesuai angka mortalitas catatan ini.
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
            @click="deleteProduction"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
