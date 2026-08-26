<template>
  <div>
    <div class="text-h6 text-weight-medium q-mb-md">Хабы</div>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <div v-else class="row q-col-gutter-md">
      <div v-for="hub in hubs" :key="hub.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat class="habr-card full-height">
          <q-card-section>
            <router-link :to="`/hubs/${hub.alias}`" class="text-weight-medium text-h6 text-link">
              {{ hub.name }}
            </router-link>
            <p class="text-caption text-dim q-mt-xs">{{ hub.description }}</p>
          </q-card-section>
          <q-card-section class="q-pt-none row justify-between text-caption text-dim">
            <span>{{ formatCount(hub.subscribers_count) }} подписчиков</span>
            <span>рейтинг {{ hub.rating }}</span>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Hub, Paginated } from '@/types/api';
import { formatCount } from '@/utils/format';

const hubs = ref<Hub[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get<Paginated<Hub>>('/hubs', { params: { per_page: 100 } });
    hubs.value = Array.isArray(data.data) ? data.data : [];
    hubs.value.sort((a, b) => a.name.localeCompare(b.name, 'ru'));
  } finally {
    loading.value = false;
  }
});
</script>
