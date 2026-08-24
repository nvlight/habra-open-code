<template>
  <div class="text-grey text-center q-pa-xl">{{ text }}</div>
</template>

<script setup lang="ts">
defineProps<{ text: string }>();
</script>
