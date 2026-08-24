<template>
  <div>
    <div class="text-h6 text-weight-medium q-mb-md">
      <q-icon name="bookmark" size="20px" color="primary" class="q-mr-xs" /> Мои закладки
    </div>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <template v-else>
      <PublicationCard v-for="publication in bookmarks" :key="publication.id" :publication="publication" />
      <EmptyNote v-if="bookmarks.length === 0" text="Закладок пока нет — добавляйте статьи кнопкой «В закладки»" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import EmptyNote from '@/components/EmptyNote.vue';

const bookmarks = ref<Publication[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get<Paginated<Publication>>('/bookmarks');
    bookmarks.value = Array.isArray(data.data) ? data.data : [];
  } finally {
    loading.value = false;
  }
});
</script>
