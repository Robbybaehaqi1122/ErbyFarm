<script setup>
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <VerticalNavSectionTitle :item="{ heading: 'Menu' }" />

  <VerticalNavLink
    :item="{
      title: 'Dashboard',
      to: '/dashboard',
      icon: 'bx-home-smile',
    }"
  />

  <VerticalNavLink
    v-if="auth.isAdmin"
    :item="{
      title: 'Pengguna',
      to: '/users',
      icon: 'bx-group',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator'])"
    :item="{
      title: 'Kandang',
      to: '/farms',
      icon: 'bx-building',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator'])"
    :item="{
      title: 'Populasi Ayam',
      to: '/livestocks',
      icon: 'bx-layer',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator'])"
    :item="{
      title: 'Produksi Harian',
      to: '/productions',
      icon: 'bx-clipboard',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator', 'viewer'])"
    :item="{
      title: 'Laporan & Analitik',
      to: '/reports',
      icon: 'bx-bar-chart-alt-2',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator', 'viewer'])"
    :item="{
      title: 'Keuangan & Biaya',
      to: '/finance',
      icon: 'bx-wallet',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator', 'viewer'])"
    :item="{
      title: 'Manajemen Stok',
      to: '/inventory',
      icon: 'bx-package',
    }"
  />

  <VerticalNavLink
    v-if="auth.hasRole(['admin', 'developer', 'operator', 'viewer'])"
    :item="{
      title: 'Kesehatan & Vaksin',
      to: '/health-records',
      icon: 'bx-health',
    }"
  />
</template>
