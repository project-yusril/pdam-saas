<template>
    <ApiErrorBanner />
    <RouterView />
</template>

<script setup>
import { RouterView } from 'vue-router';
import ApiErrorBanner from './components/shared/ApiErrorBanner.vue';
</script>
