<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">Компании</h1>
    </div>

    <div class="tm-sorting">
      <button class="tm-sorting__option" :class="{ 'tm-sorting__option--active': sortBy === 'name' }" @click="toggleSort('name')">
        Название
        <svg v-if="sortBy === 'name'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
      </button>
      <button class="tm-sorting__option" :class="{ 'tm-sorting__option--active': sortBy === 'rating' }" @click="toggleSort('rating')">
        Рейтинг
        <svg v-if="sortBy === 'rating'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
      </button>
      <button class="tm-sorting__option" :class="{ 'tm-sorting__option--active': sortBy === 'subscribers' }" @click="toggleSort('subscribers')">
        Подписчики
        <svg v-if="sortBy === 'subscribers'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
      </button>
    </div>

    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
        <div class="tm-skeleton-line tm-skeleton-line--100"></div>
      </div>
    </template>

    <div v-else class="tm-companies-list">
      <div v-for="company in sortedCompanies" :key="company.id" class="tm-companies__item">
        <div class="tm-company-logo">
          <span v-if="!company.avatar" style="font-size: 24px; font-weight: 700; color: var(--habr-text-secondary);">
            {{ company.name.charAt(0) }}
          </span>
          <img v-else :src="company.avatar" :alt="company.name" />
        </div>
        <div class="tm-company-info">
          <router-link :to="`/companies/${company.slug}`" class="tm-company-info__title">{{ company.name }}</router-link>
          <p v-if="company.description" class="tm-company-info__description">{{ company.description }}</p>
          <div class="tm-company-info__stats">
            <span>{{ formatCount(company.subscribers_count ?? 0) }} подписчиков</span>
            <span>рейтинг {{ company.rating }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!loading && companies.length === 0" class="tm-empty">Компании не найдены</div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Company, Paginated } from '@/types/api';
import { formatCount } from '@/utils/format';

const companies = ref<Company[]>([]);
const loading = ref(true);
const sortBy = ref<'name' | 'rating' | 'subscribers'>('rating');

const sortedCompanies = computed(() => {
  const items = [...companies.value];
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
    const { data } = await api.get<Paginated<Company>>('/companies', { params: { per_page: 100 } });
    companies.value = Array.isArray(data.data) ? data.data : [];
  } finally {
    loading.value = false;
  }
});
</script>
