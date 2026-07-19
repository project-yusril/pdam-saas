<template>
  <AppLayout page-title="Peta Jaringan">
    <div class="bg-white rounded-xl border p-6">
      <h3 class="font-semibold text-gray-800 mb-3">Pipa & Pelanggan — Status Tunggakan</h3>
      <LeafletMap />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import LeafletMap from '../../components/shared/LeafletMap.vue';
</script>
