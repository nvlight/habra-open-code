<template>
  <div>
    <!-- Hub header -->
    <div v-if="hub" class="tm-articles-list__item">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px">
        <div>
          <h1 class="tm-title tm-title_h1">
            <span class="tm-title__link">{{ hub.name }}</span>
          </h1>
          <p v-if="hub.description" class="pub-lead" style="margin-top: 8px">{{ hub.description }}</p>
          <div class="tm-hub-info__stats" style="margin-top: 8px">
            <span>{{ formatCount(hub.subscribers_count ?? 0) }} подписчиков</span>
            <span>рейтинг {{ hub.rating }}</span>
          </div>
        </div>
        <SubscribeButton type="hub" :key-value="hub.alias" />
      </div>
    </div>

    <template v-if="loading">
      <div v-for="i in 3" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <template v-else>
      <PublicationCard v-for="pub in publications" :key="pub.id" :publication="pub" />
      <div v-if="publications.length === 0" class="tm-empty">В этом хабе пока нет публикаций</div>

      <div v-if="meta && meta.last_page > 1" class="tm-pagination">
        <button class="tm-pagination__btn" :disabled="page <= 1" @click="page--">&laquo;</button>
        <button v-for="p in visiblePages" :key="p" class="tm-pagination__btn" :class="{ 'tm-pagination__btn--active': p === page }" @click="page = p">{{ p }}</button>
        <button class="tm-pagination__btn" :disabled="page >= meta.last_page" @click="page++">&raquo;</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Hub, Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
import { usePublicationFeed } from '@/composables/usePublicationFeed';
import { formatCount } from '@/utils/format';

const props = defineProps<{ alias: string }>();

const hub = ref<Hub | null>(null);
const loading = ref(true);

const { publications, meta, page, load } = usePublicationFeed(
  (pageNo) => api.get<Paginated<Publication>>(`/hubs/${props.alias}/publications`, {
    params: { page: pageNo }
  }).then((r) => r.data)
);

const visiblePages = computed(() => {
  if (!meta.value) return [];
  const total = meta.value.last_page;
  const current = page.value;
  const pages: number[] = [];
  for (let i = Math.max(1, current - 3); i <= Math.min(total, current + 3); i++) pages.push(i);
  return pages;
});

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: Hub }>(`/hubs/${props.alias}`);
    hub.value = data.data;
  } finally {
    loading.value = false;
    await load();
  }
});
</script>
