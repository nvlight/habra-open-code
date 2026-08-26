<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">Авторы</h1>
    </div>

    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
        <div class="tm-skeleton-line tm-skeleton-line--40"></div>
      </div>
    </template>

    <template v-else>
      <div class="tm-users-list">
        <router-link
          v-for="author in authors"
          :key="author.login"
          :to="`/users/${author.login}`"
          class="tm-users-list__item"
        >
          <span
            class="tm-users-list__avatar"
            :style="{ backgroundColor: getAvatarColor(author.login) }"
          >{{ author.name.charAt(0).toUpperCase() }}</span>
          <span>
            <span class="tm-users-list__name">{{ author.name }}</span>
            <span class="tm-users-list__login">@{{ author.login }}</span>
          </span>
          <span class="tm-users-list__rating">рейтинг {{ author.rating }}</span>
        </router-link>
      </div>

      <div v-if="authors.length === 0" class="tm-empty">Авторы не найдены</div>
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
import type { Author, Paginated } from '@/types/api';

const authors = ref<Author[]>([]);
const meta = ref<Paginated<Author>['meta'] | null>(null);
const page = ref(1);
const loading = ref(true);

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

const avatarColors = [
  'hsl(200, 34%, 50%)',
  'hsl(140, 50%, 40%)',
  'hsl(30, 96%, 45%)',
  'hsl(270, 50%, 50%)',
  'hsl(350, 60%, 50%)',
  'hsl(190, 60%, 40%)',
];

const avatarColorMap: Record<string, string> = {};
function getAvatarColor(login: string): string {
  if (!(login in avatarColorMap)) {
    const hash = login.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    avatarColorMap[login] = avatarColors[hash % avatarColors.length]!;
  }
  return avatarColorMap[login]!;
}

async function load(): Promise<void> {
  const { data } = await api.get<Paginated<Author>>('/users', { params: { page: page.value } });
  authors.value = Array.isArray(data.data) ? data.data : [];
  meta.value = data.meta ?? null;
}

watch(page, () => void load());

onMounted(async () => {
  try {
    await load();
  } finally {
    loading.value = false;
  }
});
</script>
