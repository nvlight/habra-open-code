<template>
  <div>
    <!-- Company header -->
    <div v-if="company" class="tm-articles-list__item">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px">
        <div>
          <h1 class="tm-title tm-title_h1">
            <span class="tm-title__link">{{ company.name }}</span>
          </h1>
          <p v-if="company.description" class="pub-lead" style="margin-top: 8px">{{ company.description }}</p>

          <div class="tm-hub-info__stats" style="margin-top: 8px">
            <span v-if="company.location">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -1px">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
              </svg>
              {{ company.location }}
            </span>
            <span v-if="company.size">{{ company.size }}</span>
            <span v-if="company.founded_at">с {{ company.founded_at.slice(0, 4) }}</span>
            <a v-if="company.website" :href="company.website" target="_blank" rel="noopener" class="text-link">
              {{ company.website.replace(/^https?:\/\//, '') }}
            </a>
          </div>

          <div v-if="company.industries && company.industries.length > 0" style="margin-top: 8px">
            <span v-for="industry in company.industries" :key="industry.id" class="tm-badge" style="margin-right: 6px">
              {{ industry.name }}
            </span>
          </div>

          <div v-if="company.representative" style="margin-top: 8px; font-size: 13px; color: var(--habr-text-secondary)">
            Представитель:
            <router-link :to="`/users/${company.representative.login}`" class="text-link">
              {{ company.representative.name }}
            </router-link>
          </div>

          <div class="tm-hub-info__stats" style="margin-top: 8px">
            <span>{{ formatCount(company.subscribers_count ?? 0) }} подписчиков</span>
            <span>рейтинг {{ company.rating }}</span>
          </div>
        </div>
        <SubscribeButton type="company" :key-value="company.slug" />
      </div>
    </div>

    <!-- Tabs -->
    <div class="tm-tabs">
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'publications' }" @click="tab = 'publications'">Публикации</button>
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'employees' }" @click="tab = 'employees'">Сотрудники</button>
    </div>

    <template v-if="loading">
      <div v-for="i in 3" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <template v-else-if="tab === 'publications'">
      <PublicationCard v-for="pub in publications" :key="pub.id" :publication="pub" />
      <div v-if="publications.length === 0" class="tm-empty">Компания пока ничего не опубликовала</div>

      <div v-if="meta && meta.last_page > 1" class="tm-pagination">
        <button class="tm-pagination__btn" :disabled="page <= 1" @click="page--">&laquo;</button>
        <button v-for="p in visiblePages" :key="p" class="tm-pagination__btn" :class="{ 'tm-pagination__btn--active': p === page }" @click="page = p">{{ p }}</button>
        <button class="tm-pagination__btn" :disabled="page >= meta.last_page" @click="page++">&raquo;</button>
      </div>
    </template>

    <template v-else>
      <div class="tm-users-list">
        <router-link
          v-for="employee in employees"
          :key="employee.login"
          :to="`/users/${employee.login}`"
          class="tm-users-list__item"
        >
          <span class="tm-users-list__avatar" :style="{ backgroundColor: getAvatarColor(employee.login) }">
            {{ employee.name.charAt(0).toUpperCase() }}
          </span>
          <span>
            <span class="tm-users-list__name">{{ employee.name }}</span>
            <span class="tm-users-list__login">@{{ employee.login }}</span>
          </span>
          <span class="tm-users-list__rating">рейтинг {{ employee.rating }}</span>
        </router-link>
      </div>
      <div v-if="employees.length === 0" class="tm-empty">Сотрудников нет</div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import type { Author, Company, Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
import { usePublicationFeed } from '@/composables/usePublicationFeed';
import { formatCount } from '@/utils/format';

const props = defineProps<{ slug: string }>();

const company = ref<Company | null>(null);
const loading = ref(true);
const tab = ref<'publications' | 'employees'>('publications');
const employees = ref<Author[]>([]);

const avatarColors = [
  'hsl(200, 34%, 50%)', 'hsl(140, 50%, 40%)', 'hsl(30, 96%, 45%)',
  'hsl(270, 50%, 50%)', 'hsl(350, 60%, 50%)', 'hsl(190, 60%, 40%)',
];

const avatarColorMap: Record<string, string> = {};
function getAvatarColor(login: string): string {
  if (!(login in avatarColorMap)) {
    const hash = login.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    avatarColorMap[login] = avatarColors[hash % avatarColors.length]!;
  }
  return avatarColorMap[login]!;
}

const { publications, meta, page, load } = usePublicationFeed(
  (pageNo) => api.get<Paginated<Publication>>(`/companies/${props.slug}/publications`, {
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

async function loadEmployees(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await api.get<Paginated<Author>>(`/companies/${props.slug}/employees`);
    employees.value = Array.isArray(data.data) ? data.data : [];
  } finally {
    loading.value = false;
  }
}

watch(tab, () => {
  if (tab.value === 'employees' && employees.value.length === 0) void loadEmployees();
});

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: Company }>(`/companies/${props.slug}`);
    company.value = data.data;
  } finally {
    loading.value = false;
    await load();
  }
});
</script>
