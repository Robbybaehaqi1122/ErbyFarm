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
const expenses = ref([])
const farms = ref([])
const categoriesSummary = ref([])

const summary = ref({
  total_amount: 0,
  total_records: 0,
  average_amount: 0,
  by_category: [],
})

const page = ref(1)
const itemsPerPage = ref(10)

const editingId = ref(null)
const deletingLabel = ref('')
const dialogTitle = ref('Tambah Pengeluaran')

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
    farmId: null,
    category: null,
    search: '',
  }
}

const filters = ref(defaultFilters())

const emptyForm = () => ({
  farm_id: null,
  category: 'pakan',
  amount: '',
  date: today(),
  description: '',
})

const form = ref(emptyForm())

const categoryMeta = {
  pakan: { label: 'Pakan', icon: 'bx-bowl-hot', color: 'primary' },
  obat_vaksin: { label: 'Obat & Vaksin', icon: 'bx-plus-medical', color: 'info' },
  operasional: { label: 'Operasional', icon: 'bx-building', color: 'warning' },
  gaji: { label: 'Gaji', icon: 'bx-group', color: 'success' },
  lain_lain: { label: 'Lain-lain', icon: 'bx-dots-horizontal-rounded', color: 'secondary' },
}

const categoryOptions = Object.entries(categoryMeta).map(([value, meta]) => ({
  value,
  title: meta.label,
  icon: meta.icon,
  color: meta.color,
}))

const categoryLabel = value => categoryMeta[value]?.label ?? value ?? '-'
const categoryColor = value => categoryMeta[value]?.color ?? 'secondary'
const categoryIcon = value => categoryMeta[value]?.icon ?? 'bx-category'

const farmOptions = computed(() => [
  { id: 'general', name: 'Umum (seluruh farm)' },
  ...farms.value.map(farm => ({ id: farm.id, name: farm.name })),
])

const headers = [
  { title: 'Tanggal', key: 'date', align: 'start' },
  { title: 'Kandang', key: 'farm', align: 'start', sortable: false },
  { title: 'Kategori', key: 'category', align: 'start', sortable: false },
  { title: 'Keterangan', key: 'description', align: 'start', sortable: false },
  { title: 'Nominal', key: 'amount', align: 'end' },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const formatCurrency = value => Number(value ?? 0).toLocaleString('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatDate = value => {
  if (!value) {
    return '-'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

const topCategory = computed(() => summary.value.by_category?.[0] ?? null)

const fetchExpenses = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()

    if (filters.value.from) {
      params.set('from', filters.value.from)
    }

    if (filters.value.to) {
      params.set('to', filters.value.to)
    }

    if (filters.value.farmId) {
      params.set('farm_id', filters.value.farmId)
    }

    if (filters.value.category) {
      params.set('category', filters.value.category)
    }

    if (filters.value.search.trim()) {
      params.set('search', filters.value.search.trim())
    }

    params.set('page', page.value)
    params.set('per_page', itemsPerPage.value)

    const res = await http.get(`/api/expenses?${params.toString()}`)

    expenses.value = res?.data ?? []
    categoriesSummary.value = res?.summary?.by_category ?? []
    summary.value = {
      total_amount: res?.summary?.total_amount ?? 0,
      total_records: res?.summary?.total_records ?? 0,
      average_amount: res?.summary?.average_amount ?? 0,
      by_category: res?.summary?.by_category ?? [],
    }
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

const applyFilters = () => {
  page.value = 1
  fetchExpenses()
}

const resetFilters = () => {
  filters.value = defaultFilters()
  page.value = 1
  fetchExpenses()
}

const onOptionsChange = options => {
  const changed = page.value !== options.page || itemsPerPage.value !== options.itemsPerPage

  page.value = options.page
  itemsPerPage.value = options.itemsPerPage

  if (changed) {
    fetchExpenses()
  }
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Pengeluaran'
  formError.value = ''
  form.value = emptyForm()
  dialog.value = true
}

const openEditDialog = item => {
  editingId.value = item.id
  dialogTitle.value = 'Edit Pengeluaran'
  formError.value = ''
  form.value = {
    farm_id: item.farm_id,
    category: item.category,
    amount: item.amount,
    date: String(item.date ?? '').slice(0, 10),
    description: item.description ?? '',
  }
  dialog.value = true
}

const saveExpense = async () => {
  formError.value = ''

  if (!form.value.category || !form.value.date) {
    formError.value = 'Kategori dan tanggal wajib diisi.'

    return
  }

  if (!form.value.amount || Number(form.value.amount) <= 0) {
    formError.value = 'Nominal pengeluaran harus lebih dari 0.'

    return
  }

  dialogLoading.value = true
  try {
    const payload = {
      farm_id: form.value.farm_id || null,
      category: form.value.category,
      amount: Number(form.value.amount),
      date: form.value.date,
      description: form.value.description?.trim() || null,
    }

    if (editingId.value) {
      await http.patch(`/api/expenses/${editingId.value}`, payload)
    } else {
      await http.post('/api/expenses', payload)
    }

    dialog.value = false
    await fetchExpenses()
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    formError.value = firstError ?? error.data?.message ?? 'Gagal menyimpan data'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = item => {
  editingId.value = item.id
  deletingLabel.value = `${categoryLabel(item.category)} — ${formatCurrency(item.amount)} (${formatDate(item.date)})`
  deleteDialog.value = true
}

const deleteExpense = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/expenses/${editingId.value}`)
    deleteDialog.value = false

    if (expenses.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await fetchExpenses()
  } catch (error) {
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  fetchFarms()
  fetchExpenses()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Keuangan & Biaya
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Catat dan pantau seluruh pengeluaran operasional peternakan.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        @click="openCreateDialog"
      >
        Tambah Pengeluaran
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
                icon="bx-wallet"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total Biaya
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatCurrency(summary.total_amount) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Sesuai filter aktif
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
                icon="bx-receipt"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Jumlah Transaksi
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.total_records) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                transaksi tercatat
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
                icon="bx-calculator"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Rata-rata / Transaksi
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatCurrency(summary.average_amount) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                per pengeluaran
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
              :color="topCategory ? categoryColor(topCategory.category) : 'success'"
              variant="tonal"
              size="48"
            >
              <VIcon
                :icon="topCategory ? categoryIcon(topCategory.category) : 'bx-category'"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Kategori Terbesar
              </div>
              <div class="text-h6 font-weight-semibold">
                {{ topCategory ? categoryLabel(topCategory.category) : '-' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ topCategory ? formatCurrency(topCategory.total_amount) : 'Belum ada data' }}
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
            md="2"
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
            md="2"
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
            md="3"
          >
            <VSelect
              v-model="filters.farmId"
              :items="farmOptions"
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
            md="2"
          >
            <VSelect
              v-model="filters.category"
              :items="categoryOptions"
              item-title="title"
              item-value="value"
              label="Semua Kategori"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              v-model="filters.search"
              label="Cari keterangan / kandang"
              prepend-inner-icon="bx-search"
              density="compact"
              hide-details
              clearable
              @keyup.enter="applyFilters"
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
            Bulan Ini
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

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>Daftar Pengeluaran</span>
            <VChip
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ formatNumber(summary.total_records) }} transaksi
            </VChip>
          </VCardTitle>

          <VDataTableServer
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            :loading="loading"
            :items="expenses"
            :items-length="summary.total_records"
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

            <template #item.farm="{ item }">
              <VChip
                v-if="item.farm"
                size="small"
                variant="tonal"
                color="primary"
              >
                {{ item.farm.name }}
              </VChip>
              <VChip
                v-else
                size="small"
                variant="tonal"
                color="secondary"
              >
                Umum
              </VChip>
            </template>

            <template #item.category="{ item }">
              <VChip
                :color="categoryColor(item.category)"
                size="small"
                variant="tonal"
                :prepend-icon="categoryIcon(item.category)"
              >
                {{ categoryLabel(item.category) }}
              </VChip>
            </template>

            <template #item.description="{ item }">
              <span
                v-if="item.description"
                :title="item.description"
                class="d-inline-block text-truncate"
                style="max-width: 260px"
              >
                {{ item.description }}
              </span>
              <span
                v-else
                class="text-medium-emphasis"
              >-</span>
            </template>

            <template #item.amount="{ item }">
              <span class="font-weight-semibold">{{ formatCurrency(item.amount) }}</span>
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
                  icon="bx-receipt"
                  size="64"
                  color="light-gray"
                />
                <div class="text-subtitle-1 text-medium-emphasis mt-4">
                  Belum ada pengeluaran pada periode ini.
                </div>
                <VBtn
                  v-if="canManage"
                  color="primary"
                  variant="tonal"
                  prepend-icon="bx-plus"
                  class="mt-4"
                  @click="openCreateDialog"
                >
                  Tambah Pengeluaran Pertama
                </VBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardTitle>Rincian per Kategori</VCardTitle>
          <VCardText>
            <div
              v-if="!categoriesSummary.length"
              class="text-center py-8"
            >
              <VIcon
                icon="bx-category"
                size="48"
                color="light-gray"
              />
              <div class="text-body-2 text-medium-emphasis mt-3">
                Belum ada data kategori.
              </div>
            </div>

            <div
              v-for="item in categoriesSummary"
              :key="item.category"
              class="mb-4"
            >
              <div class="d-flex align-center justify-space-between mb-1">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    :icon="categoryIcon(item.category)"
                    :color="categoryColor(item.category)"
                    size="18"
                  />
                  <span class="text-body-2 font-weight-medium">
                    {{ categoryLabel(item.category) }}
                  </span>
                </div>
                <span class="text-body-2 font-weight-semibold">
                  {{ formatCurrency(item.total_amount) }}
                </span>
              </div>

              <VProgressLinear
                :model-value="summary.total_amount ? (item.total_amount / summary.total_amount) * 100 : 0"
                :color="categoryColor(item.category)"
                height="6"
                rounded
              />

              <div class="text-caption text-medium-emphasis mt-1">
                {{ formatNumber(item.total_records) }} transaksi
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

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
            v-model="form.farm_id"
            :items="farms"
            item-title="name"
            item-value="id"
            label="Kandang (opsional)"
            placeholder="Pilih kandang"
            hint="Kosongkan untuk pengeluaran umum seluruh farm."
            persistent-hint
            clearable
            autofocus
          />

          <VSelect
            v-model="form.category"
            :items="categoryOptions"
            item-title="title"
            item-value="value"
            label="Kategori"
            class="mt-4"
            required
          />

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.amount"
                label="Nominal"
                type="number"
                min="0.01"
                step="0.01"
                prefix="Rp"
                placeholder="Misal: 1500000"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.date"
                label="Tanggal Transaksi"
                type="date"
                required
              />
            </VCol>
          </VRow>

          <VTextarea
            v-model="form.description"
            label="Keterangan"
            rows="3"
            auto-grow
            placeholder="Misal: Pembelian pakan layer 50 sak"
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
            @click="saveExpense"
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
        <VCardTitle>Hapus Pengeluaran?</VCardTitle>
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
            @click="deleteExpense"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
