<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">Новости</h1>
    </div>

    <div class="tm-tabs">
      <router-link to="/articles" class="tm-tabs__item">Статьи</router-link>
      <router-link to="/posts" class="tm-tabs__item">Посты</router-link>
      <router-link to="/news" class="tm-tabs__item tm-tabs__item--active">Новости</router-link>
      <router-link to="/hubs" class="tm-tabs__item">Хабы</router-link>
      <router-link to="/companies" class="tm-tabs__item">Компании</router-link>
      <router-link to="/users" class="tm-tabs__item">Авторы</router-link>
    </div>

    <div class="tm-filters">
      <div class="tm-filters__row">
        <span class="tm-filters__label">Сортировка:</span>
        <button class="tm-filter-chip" :class="{ 'tm-filter-chip--active': sort === 'new' }" @click="setSort('new')">Новые</button>
        <button class="tm-filter-chip" :class="{ 'tm-filter-chip--active': sort === 'best' }" @click="setSort('best')">Лучшие</button>
      </div>
      <div class="tm-filters__row">
        <span class="tm-filters__label">Минимальный рейтинг:</span>
        <button v-for="r in ratingOptions" :key="r.label" class="tm-filter-chip" :class="{ 'tm-filter-chip--active': minRating === r.value }" @click="setRating(r.value)">{{ r.label }}</button>
      </div>
    </div>

    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--40"></div>
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <template v-else>
      <PublicationCard v-for="pub in publications" :key="pub.id" :publication="pub" />
      <div v-if="publications.length === 0" class="tm-empty">Ничего не найдено</div>
    </template>

    <div v-if="meta && meta.last_page > 1" class="tm-pagination">
      <button class="tm-pagination__btn" :disabled="page <= 1" @click="page--">&laquo;</button>
      <button v-for="p in visiblePages" :key="p" class="tm-pagination__btn" :class="{ 'tm-pagination__btn--active': p === page }" @click="page = p">{{ p }}</button>
      <button class="tm-pagination__btn" :disabled="page >= meta.last_page" @click="page++">&raquo;</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import type { Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';

const sort = ref<'new' | 'best'>('new');
const minRating = ref<number | null>(null);
const page = ref(1);
const publications = ref<Publication[]>([]);
const meta = ref<Paginated<Publication>['meta'] | null>(null);
const loading = ref(false);

const ratingOptions = [
  { label: 'Все', value: null as number | null },
  { label: '≥0', value: 0 },
  { label: '≥10', value: 10 },
  { label: '≥25', value: 25 },
  { label: '≥50', value: 50 },
  { label: '≥100', value: 100 },
];

const visiblePages = computed(() => {
  if (!meta.value) return [];
  const total = meta.value.last_page;
  const current = page.value;
  const pages: number[] = [];
  for (let i = Math.max(1, current - 3); i <= Math.min(total, current + 3); i++) {
    pages.push(i);
  }
  return pages;
});

function setSort(value: 'new' | 'best'): void {
  sort.value = value;
  page.value = 1;
  void load();
}

function setRating(value: number | null): void {
  minRating.value = value;
  page.value = 1;
  void load();
}

async function load(): Promise<void> {
  loading.value = true;
  try {
    const params: Record<string, string | number> = { page: page.value, per_page: 20, sort: sort.value, type: 'news' };
    const { data } = await api.get<Paginated<Publication>>('/publications', { params });
    let items = Array.isArray(data.data) ? data.data : [];
    if (minRating.value !== null) {
      items = items.filter((p) => p.rating >= minRating.value!);
    }
    publications.value = items;
    meta.value = data.meta ?? null;
  } finally {
    loading.value = false;
  }
}

watch(page, () => void load());
onMounted(() => void load());
</script>
