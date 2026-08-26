<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="vertical-align: -2px; color: var(--habr-accent-orange)">
          <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
        </svg>
        Мои закладки
      </h1>
    </div>

    <template v-if="loading">
      <div v-for="i in 3" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <template v-else>
      <PublicationCard v-for="pub in bookmarks" :key="pub.id" :publication="pub" />
      <div v-if="bookmarks.length === 0" class="tm-empty">Закладок пока нет — добавляйте статьи кнопкой «В закладки»</div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';

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
