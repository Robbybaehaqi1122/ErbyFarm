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
const items = ref([])
const totalItems = ref(0)

const summary = ref({
  total_items: 0,
  low_stock_items: 0,
  out_of_stock_items: 0,
  category_count: 0,
  by_category: [],
})

const page = ref(1)
const itemsPerPage = ref(10)

const editingId = ref(null)
const deletingLabel = ref('')
const dialogTitle = ref('Tambah Barang')

const defaultFilters = () => ({
  search: '',
  category: null,
  stockStatus: null,
})

const filters = ref(defaultFilters())

const emptyForm = () => ({
  item_name: '',
  category: 'pakan',
  stock_quantity: '',
  unit: 'kg',
  minimum_stock: '',
})

const form = ref(emptyForm())

const categoryMeta = {
  pakan: { label: 'Pakan', icon: 'bx-bowl-hot', color: 'primary' },
  obat: { label: 'Obat', icon: 'bx-plus-medical', color: 'info' },
  vitamin: { label: 'Vitamin', icon: 'bx-capsule', color: 'success' },
  perlengkapan: { label: 'Perlengkapan', icon: 'bx-wrench', color: 'warning' },
}

const statusMeta = {
  out: { label: 'Stok Habis', icon: 'bx-x-circle', color: 'error' },
  low: { label: 'Stok Menipis', icon: 'bx-error-circle', color: 'warning' },
  safe: { label: 'Aman', icon: 'bx-check-circle', color: 'success' },
}

const categoryOptions = Object.entries(categoryMeta).map(([value, meta]) => ({
  value,
  title: meta.label,
}))

const statusOptions = [
  { value: null, title: 'Semua Status' },
  { value: 'low', title: 'Stok Menipis' },
  { value: 'out', title: 'Stok Habis' },
  { value: 'safe', title: 'Aman' },
]

const unitOptions = ['kg', 'sak', 'botol', 'pack', 'pcs', 'liter']

const categoryLabel = value => categoryMeta[value]?.label ?? value ?? '-'
const categoryColor = value => categoryMeta[value]?.color ?? 'secondary'
const categoryIcon = value => categoryMeta[value]?.icon ?? 'bx-package'

const itemStatus = item => {
  const stock = Number(item.stock_quantity ?? 0)

  if (stock <= 0) {
    return 'out'
  }

  if (stock <= Number(item.minimum_stock ?? 0)) {
    return 'low'
  }

  return 'safe'
}

const statusLabel = item => statusMeta[itemStatus(item)]?.label ?? '-'
const statusColor = item => statusMeta[itemStatus(item)]?.color ?? 'secondary'
const statusIcon = item => statusMeta[itemStatus(item)]?.icon ?? 'bx-check-circle'

const headers = [
  { title: 'Nama Barang', key: 'item_name', align: 'start' },
  { title: 'Kategori', key: 'category', align: 'start', sortable: false },
  { title: 'Stok Saat Ini', key: 'stock_quantity', align: 'end' },
  { title: 'Stok Minimal', key: 'minimum_stock', align: 'end' },
  { title: 'Status', key: 'status', align: 'center', sortable: false },
  { title: 'Aksi', key: 'actions', align: 'center', sortable: false },
]

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatQuantity = value => Number(value ?? 0).toLocaleString('id-ID', {
  maximumFractionDigits: 2,
})

const alertCount = computed(() => summary.value.low_stock_items + summary.value.out_of_stock_items)

const fetchItems = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()

    if (filters.value.search.trim()) {
      params.set('search', filters.value.search.trim())
    }

    if (filters.value.category) {
      params.set('category', filters.value.category)
    }

    if (filters.value.stockStatus) {
      params.set('stock_status', filters.value.stockStatus)
    }

    params.set('page', page.value)
    params.set('per_page', itemsPerPage.value)

    const res = await http.get(`/api/inventories?${params.toString()}`)

    items.value = res?.data ?? []
    totalItems.value = res?.total ?? 0
    summary.value = {
      total_items: res?.summary?.total_items ?? 0,
      low_stock_items: res?.summary?.low_stock_items ?? 0,
      out_of_stock_items: res?.summary?.out_of_stock_items ?? 0,
      category_count: res?.summary?.category_count ?? 0,
      by_category: res?.summary?.by_category ?? [],
    }
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  page.value = 1
  fetchItems()
}

const resetFilters = () => {
  filters.value = defaultFilters()
  page.value = 1
  fetchItems()
}

const showLowStock = () => {
  filters.value.stockStatus = 'low'
  page.value = 1
  fetchItems()
}

const onOptionsChange = options => {
  const changed = page.value !== options.page || itemsPerPage.value !== options.itemsPerPage

  page.value = options.page
  itemsPerPage.value = options.itemsPerPage

  if (changed) {
    fetchItems()
  }
}

const openCreateDialog = () => {
  editingId.value = null
  dialogTitle.value = 'Tambah Barang'
  formError.value = ''
  form.value = emptyForm()
  dialog.value = true
}

const openEditDialog = item => {
  editingId.value = item.id
  dialogTitle.value = 'Edit Barang'
  formError.value = ''
  form.value = {
    item_name: item.item_name,
    category: item.category,
    stock_quantity: item.stock_quantity,
    unit: item.unit,
    minimum_stock: item.minimum_stock,
  }
  dialog.value = true
}

const saveItem = async () => {
  formError.value = ''

  if (!form.value.item_name.trim() || !form.value.unit.trim()) {
    formError.value = 'Nama barang dan satuan wajib diisi.'

    return
  }

  if (Number(form.value.stock_quantity ?? 0) < 0 || Number(form.value.minimum_stock ?? 0) < 0) {
    formError.value = 'Jumlah stok tidak boleh negatif.'

    return
  }

  dialogLoading.value = true
  try {
    const payload = {
      item_name: form.value.item_name.trim(),
      category: form.value.category,
      stock_quantity: Number(form.value.stock_quantity || 0),
      unit: form.value.unit.trim(),
      minimum_stock: Number(form.value.minimum_stock || 0),
    }

    if (editingId.value) {
      await http.patch(`/api/inventories/${editingId.value}`, payload)
    } else {
      await http.post('/api/inventories', payload)
    }

    dialog.value = false
    await fetchItems()
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    formError.value = firstError ?? error.data?.message ?? 'Gagal menyimpan data'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = item => {
  editingId.value = item.id
  deletingLabel.value = item.item_name
  deleteDialog.value = true
}

const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await http.delete(`/api/inventories/${editingId.value}`)
    deleteDialog.value = false

    if (items.value.length === 1 && page.value > 1) {
      page.value -= 1
    }

    await fetchItems()
  } catch (error) {
    alert(error.data?.message ?? 'Gagal menghapus data')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(fetchItems)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Manajemen Stok
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Pantau persediaan pakan, obat, vitamin, dan perlengkapan gudang.
        </p>
      </div>

      <VBtn
        v-if="canManage"
        color="primary"
        variant="tonal"
        prepend-icon="bx-plus"
        @click="openCreateDialog"
      >
        Tambah Barang
      </VBtn>
    </div>

    <VAlert
      v-if="alertCount > 0"
      type="warning"
      variant="tonal"
      class="mb-6"
    >
      <div class="d-flex align-center flex-wrap gap-4">
        <span>
          <strong>{{ formatNumber(summary.low_stock_items) }} barang menipis</strong>
          dan <strong>{{ formatNumber(summary.out_of_stock_items) }} barang habis</strong>.
          Segera lakukan pengadaan ulang.
        </span>
        <VBtn
          size="small"
          variant="text"
          color="warning"
          @click="showLowStock"
        >
          Lihat stok menipis
        </VBtn>
      </div>
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
                icon="bx-package"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total Barang
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.total_items) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                item di gudang
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
                icon="bx-error-circle"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Stok Menipis
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.low_stock_items) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                di bawah batas minimal
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
                icon="bx-x-circle"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Stok Habis
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.out_of_stock_items) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                perlu pengadaan
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
                icon="bx-category"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Kategori Barang
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.category_count) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                jenis kategori
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
            md="4"
          >
            <VTextField
              v-model="filters.search"
              label="Cari nama barang"
              prepend-inner-icon="bx-search"
              density="compact"
              hide-details
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
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
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.stockStatus"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              label="Status Stok"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="2"
            class="d-flex align-center justify-end gap-2"
          >
            <VBtn
              icon
              variant="text"
              color="medium-emphasis"
              title="Reset filter"
              @click="resetFilters"
            >
              <VIcon icon="bx-reset" />
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="bx-filter-alt"
              :loading="loading"
              @click="applyFilters"
            >
              Filter
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Daftar Stok Gudang</span>
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ formatNumber(totalItems) }} barang
        </VChip>
      </VCardTitle>

      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        :loading="loading"
        :items="items"
        :items-length="totalItems"
        :headers="headers"
        item-key="id"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 25, title: '25' },
          { value: 50, title: '50' },
        ]"
        @update:options="onOptionsChange"
      >
        <template #item.item_name="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="categoryColor(item.category)"
              variant="tonal"
              size="36"
            >
              <VIcon
                :icon="categoryIcon(item.category)"
                size="18"
              />
            </VAvatar>
            <span class="font-weight-medium">{{ item.item_name }}</span>
          </div>
        </template>

        <template #item.category="{ item }">
          <VChip
            :color="categoryColor(item.category)"
            size="small"
            variant="tonal"
          >
            {{ categoryLabel(item.category) }}
          </VChip>
        </template>

        <template #item.stock_quantity="{ item }">
          <span class="font-weight-semibold">{{ formatQuantity(item.stock_quantity) }}</span>
          <span class="text-medium-emphasis"> {{ item.unit }}</span>
        </template>

        <template #item.minimum_stock="{ item }">
          <span class="text-medium-emphasis">
            {{ formatQuantity(item.minimum_stock) }} {{ item.unit }}
          </span>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="statusColor(item)"
            size="small"
            variant="tonal"
            :prepend-icon="statusIcon(item)"
          >
            {{ statusLabel(item) }}
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
              icon="bx-package"
              size="64"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-4">
              Belum ada barang pada filter ini.
            </div>
            <VBtn
              v-if="canManage"
              color="primary"
              variant="tonal"
              prepend-icon="bx-plus"
              class="mt-4"
              @click="openCreateDialog"
            >
              Tambah Barang Pertama
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
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

          <VTextField
            v-model="form.item_name"
            label="Nama Barang"
            placeholder="Misal: Pakan Layer BR-1"
            autofocus
            required
          />

          <VSelect
            v-model="form.category"
            :items="categoryOptions"
            item-title="title"
            item-value="value"
            label="Kategori"
            class="mt-2"
            required
          />

          <VRow>
            <VCol cols="8">
              <VTextField
                v-model="form.stock_quantity"
                label="Stok Saat Ini"
                type="number"
                min="0"
                step="0.01"
                placeholder="Misal: 25"
              />
            </VCol>

            <VCol cols="4">
              <VSelect
                v-model="form.unit"
                :items="unitOptions"
                label="Satuan"
                required
              />
            </VCol>
          </VRow>

          <VTextField
            v-model="form.minimum_stock"
            label="Stok Minimal"
            type="number"
            min="0"
            step="0.01"
            hint="Peringatan muncul saat stok menyentuh atau di bawah angka ini."
            persistent-hint
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
            @click="saveItem"
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
        <VCardTitle>Hapus Barang?</VCardTitle>
        <VCardText>
          Barang <strong>{{ deletingLabel }}</strong> akan dihapus permanen dari gudang.
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
            @click="deleteItem"
          >
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
