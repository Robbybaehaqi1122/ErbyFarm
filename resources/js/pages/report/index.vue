<script setup>
/* eslint-disable camelcase -- Laravel API payloads and responses use snake_case */
import { ref, computed, onMounted } from 'vue'
import { http } from '@/services/http'

const loading = ref(false)
const errorMessage = ref('')
const farms = ref([])

const report = ref({
  meta: {},
  summary: {},
  by_farm: [],
  periods: [],
})

const toDateInput = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const defaultFilters = () => {
  const now = new Date()

  return {
    from: toDateInput(new Date(now.getFullYear(), now.getMonth(), 1)),
    to: toDateInput(now),
    farmId: null,
    groupBy: 'day',
  }
}

const filters = ref(defaultFilters())

const groupOptions = [
  { value: 'day', label: 'Harian' },
  { value: 'month', label: 'Bulanan' },
]

const formatNumber = value => Number(value ?? 0).toLocaleString('id-ID')

const formatDecimal = (value, digits = 2) => Number(value ?? 0).toLocaleString('id-ID', {
  minimumFractionDigits: digits,
  maximumFractionDigits: digits,
})

const formatPercent = value => `${formatDecimal(value, 1)}%`

const formatDate = value => {
  if (!value) {
    return '-'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return `${day}/${month}/${year}`
}

const formatPeriod = value => {
  if (!value) {
    return '-'
  }

  if (report.value.meta?.group_by === 'month') {
    const [year, month] = value.split('-')

    return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString('id-ID', {
      month: 'long',
      year: 'numeric',
    })
  }

  return formatDate(value)
}

const henDayColor = value => {
  if (value >= 80) {
    return 'success'
  }

  if (value >= 60) {
    return 'warning'
  }

  return 'error'
}

const summary = computed(() => report.value.summary ?? {})
const byFarm = computed(() => report.value.by_farm ?? [])
const periods = computed(() => report.value.periods ?? [])

const hasData = computed(() => byFarm.value.length > 0 || periods.value.length > 0)

const reportPeriodLabel = computed(() => {
  const meta = report.value.meta ?? {}

  if (!meta.from || !meta.to) {
    return '-'
  }

  return `${formatDate(meta.from)} – ${formatDate(meta.to)}`
})

const farmHeaders = [
  { title: 'Kandang', key: 'farm_name', align: 'start' },
  { title: 'Hari', key: 'recorded_days', align: 'center' },
  { title: 'Total Telur', key: 'total_eggs', align: 'end' },
  { title: 'Berat Telur', key: 'egg_weight_kg', align: 'end' },
  { title: 'Pakan/Hari', key: 'average_feed_kg', align: 'end' },
  { title: 'Mortalitas', key: 'total_mortality', align: 'center' },
  { title: 'Hen Day', key: 'hen_day', align: 'start', sortable: false },
]

const periodHeaders = [
  { title: 'Periode', key: 'period', align: 'start' },
  { title: 'Telur', key: 'total_eggs', align: 'end' },
  { title: 'Berat Telur', key: 'egg_weight_kg', align: 'end' },
  { title: 'Pakan', key: 'total_feed_kg', align: 'end' },
  { title: 'Air', key: 'total_water_liter', align: 'end' },
  { title: 'Mortalitas', key: 'total_mortality', align: 'center' },
  { title: 'Hen Day', key: 'hen_day', align: 'start', sortable: false },
]

const fetchFarms = async () => {
  try {
    const res = await http.get('/api/farms?per_page=200')

    farms.value = res?.data ?? res ?? []
  } catch (error) {
    console.error(error)
  }
}

const fetchReport = async () => {
  errorMessage.value = ''

  if (filters.value.from && filters.value.to && filters.value.from > filters.value.to) {
    errorMessage.value = 'Tanggal awal tidak boleh melebihi tanggal akhir.'

    return
  }

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

    params.set('group_by', filters.value.groupBy)

    const res = await http.get(`/api/reports?${params.toString()}`)

    report.value = {
      meta: res.meta ?? {},
      summary: res.summary ?? {},
      by_farm: res.by_farm ?? [],
      periods: res.periods ?? [],
    }
  } catch (error) {
    const firstError = Object.values(error.errors ?? {})[0]?.[0]

    errorMessage.value = firstError ?? error.data?.message ?? 'Gagal memuat laporan.'
  } finally {
    loading.value = false
  }
}

const resetFilters = async () => {
  filters.value = defaultFilters()
  await fetchReport()
}

onMounted(() => {
  fetchFarms()
  fetchReport()
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-semibold">
          Laporan & Analitik
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Rekapitulasi performa produksi, pakan, dan mortalitas peternakan.
        </p>
      </div>

      <VChip
        v-if="hasData"
        color="primary"
        variant="tonal"
        prepend-icon="bx-calendar"
      >
        {{ reportPeriodLabel }}
      </VChip>
    </div>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      density="compact"
      closable
      class="mb-6"
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard class="mb-6">
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
              v-model="filters.groupBy"
              :items="groupOptions"
              item-title="label"
              item-value="value"
              label="Kelompok Periode"
              density="compact"
              hide-details
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
            @click="fetchReport"
          >
            Tampilkan Laporan
          </VBtn>
        </div>
      </VCardText>
    </VCard>

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
                {{ formatNumber(summary.total_eggs) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Rata-rata {{ formatDecimal(summary.average_egg_weight_gram, 1) }} g/butir
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
              :color="henDayColor(summary.hen_day)"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="bx-line-chart"
                size="24"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Hen Day (HD)
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatPercent(summary.hen_day) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Rasio produksi harian
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
                Rata-rata Pakan
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatDecimal(summary.average_feed_kg) }} kg
              </div>
              <div class="text-caption text-medium-emphasis">
                per hari • {{ formatDecimal(summary.average_water_liter) }} L air
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
                Total Mortalitas
              </div>
              <div class="text-h5 font-weight-semibold">
                {{ formatNumber(summary.total_mortality) }} ekor
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDecimal(summary.average_mortality, 1) }} ekor/hari
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      class="mb-6"
    >
      Hen Day (HD) = total telur ÷ total populasi hidup harian × 100%.
      Rata-rata pakan dan air dihitung per hari tercatat.
    </VAlert>

    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Rekap per Kandang</span>
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ formatNumber(summary.farm_count) }} kandang
        </VChip>
      </VCardTitle>

      <VDataTable
        :loading="loading"
        :items="byFarm"
        :headers="farmHeaders"
        item-key="farm_id"
        :items-per-page="10"
      >
        <template #item.farm_name="{ item }">
          <span class="font-weight-medium">{{ item.farm_name }}</span>
        </template>

        <template #item.recorded_days="{ item }">
          {{ formatNumber(item.recorded_days) }} hari
        </template>

        <template #item.total_eggs="{ item }">
          <span class="font-weight-medium">{{ formatNumber(item.total_eggs) }}</span> butir
        </template>

        <template #item.egg_weight_kg="{ item }">
          {{ formatDecimal(item.egg_weight_kg) }} kg
        </template>

        <template #item.average_feed_kg="{ item }">
          {{ formatDecimal(item.average_feed_kg) }} kg
        </template>

        <template #item.total_mortality="{ item }">
          <VChip
            :color="item.total_mortality > 0 ? 'error' : 'success'"
            size="small"
            variant="tonal"
          >
            {{ formatNumber(item.total_mortality) }} ekor
          </VChip>
        </template>

        <template #item.hen_day="{ item }">
          <div
            class="d-flex align-center gap-3"
            style="min-width: 150px"
          >
            <VProgressLinear
              :model-value="Math.min(item.hen_day, 100)"
              :color="henDayColor(item.hen_day)"
              height="6"
              rounded
              class="flex-grow-1"
            />
            <span
              class="text-body-2 font-weight-medium text-end"
              style="min-width: 48px"
            >
              {{ formatPercent(item.hen_day) }}
            </span>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <VIcon
              icon="bx-bar-chart-alt-2"
              size="56"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-3">
              Belum ada data pada periode ini.
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Tren {{ report.meta?.group_by === 'month' ? 'Bulanan' : 'Harian' }}</span>
        <VChip
          size="small"
          variant="tonal"
          color="info"
        >
          {{ formatNumber(summary.recorded_days) }} hari tercatat
        </VChip>
      </VCardTitle>

      <VDataTable
        :loading="loading"
        :items="periods"
        :headers="periodHeaders"
        item-key="period"
        :items-per-page="10"
      >
        <template #item.period="{ item }">
          <span class="font-weight-medium">{{ formatPeriod(item.period) }}</span>
        </template>

        <template #item.total_eggs="{ item }">
          <span class="font-weight-medium">{{ formatNumber(item.total_eggs) }}</span> butir
        </template>

        <template #item.egg_weight_kg="{ item }">
          {{ formatDecimal(item.egg_weight_kg) }} kg
        </template>

        <template #item.total_feed_kg="{ item }">
          {{ formatDecimal(item.total_feed_kg) }} kg
        </template>

        <template #item.total_water_liter="{ item }">
          {{ formatDecimal(item.total_water_liter) }} L
        </template>

        <template #item.total_mortality="{ item }">
          {{ formatNumber(item.total_mortality) }} ekor
        </template>

        <template #item.hen_day="{ item }">
          <div
            class="d-flex align-center gap-3"
            style="min-width: 150px"
          >
            <VProgressLinear
              :model-value="Math.min(item.hen_day, 100)"
              :color="henDayColor(item.hen_day)"
              height="6"
              rounded
              class="flex-grow-1"
            />
            <span
              class="text-body-2 font-weight-medium text-end"
              style="min-width: 48px"
            >
              {{ formatPercent(item.hen_day) }}
            </span>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <VIcon
              icon="bx-line-chart"
              size="56"
              color="light-gray"
            />
            <div class="text-subtitle-1 text-medium-emphasis mt-3">
              Belum ada data pada periode ini.
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
