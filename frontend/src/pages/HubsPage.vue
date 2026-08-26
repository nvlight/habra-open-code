<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">Хабы</h1>
    </div>

    <!-- Sorting -->
    <div class="tm-sorting">
      <button
        class="tm-sorting__option"
        :class="{ 'tm-sorting__option--active': sortBy === 'name' }"
        @click="toggleSort('name')"
      >
        Название
        <svg v-if="sortBy === 'name'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12l7 7 7-7"/>
        </svg>
      </button>
      <button
        class="tm-sorting__option"
        :class="{ 'tm-sorting__option--active': sortBy === 'rating' }"
        @click="toggleSort('rating')"
      >
        Рейтинг
        <svg v-if="sortBy === 'rating'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12l7 7 7-7"/>
        </svg>
      </button>
      <button
        class="tm-sorting__option"
        :class="{ 'tm-sorting__option--active': sortBy === 'subscribers' }"
        @click="toggleSort('subscribers')"
      >
        Подписчики
        <svg v-if="sortBy === 'subscribers'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12l7 7 7-7"/>
        </svg>
      </button>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
        <div class="tm-skeleton-line tm-skeleton-line--100"></div>
      </div>
    </template>

    <!-- Hub list -->
    <div v-else class="tm-hubs-list">
      <div v-for="hub in sortedHubs" :key="hub.id" class="tm-hubs-list__item">
        <div class="tm-hub-icon">{{ hub.name.charAt(0) }}</div>
        <div class="tm-hub-info">
          <router-link :to="`/hubs/${hub.alias}`" class="tm-hub-info__title">{{ hub.name }}</router-link>
          <p v-if="hub.description" class="tm-hub-info__description">{{ hub.description }}</p>
          <div class="tm-hub-info__stats">
            <span>{{ formatCount(hub.subscribers_count) }} подписчиков</span>
            <span>рейтинг {{ hub.rating }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!loading && hubs.length === 0" class="tm-empty">Хабы не найдены</div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Hub, Paginated } from '@/types/api';
import { formatCount } from '@/utils/format';

const hubs = ref<Hub[]>([]);
const loading = ref(true);
const sortBy = ref<'name' | 'rating' | 'subscribers'>('rating');

const sortedHubs = computed(() => {
  const items = [...hubs.value];
  switch (sortBy.value) {
    case 'name':
      return items.sort((a, b) => a.name.localeCompare(b.name, 'ru'));
    case 'rating':
      return items.sort((a, b) => parseFloat(b.rating ?? '0') - parseFloat(a.rating ?? '0'));
    case 'subscribers':
      return items.sort((a, b) => (b.subscribers_count ?? 0) - (a.subscribers_count ?? 0));
    default:
      return items;
  }
});

function toggleSort(field: 'name' | 'rating' | 'subscribers'): void {
  sortBy.value = field;
}

onMounted(async () => {
  try {
    const { data } = await api.get<Paginated<Hub>>('/hubs', { params: { per_page: 100 } });
    hubs.value = Array.isArray(data.data) ? data.data : [];
  } finally {
    loading.value = false;
  }
});
</script>
