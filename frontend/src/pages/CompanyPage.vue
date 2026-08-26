<template>
  <div>
    <q-card flat class="habr-card q-pa-md q-mb-md">
      <div class="row items-center q-gutter-x-md">
        <div class="col">
          <div class="text-h5 text-weight-bold">{{ company?.name }}</div>
          <p v-if="company?.description" class="text-body2 text-dim q-mt-xs q-mb-none">
            {{ company.description }}
          </p>

          <div class="row q-gutter-x-md text-caption text-dim q-mt-sm">
            <span v-if="company?.location"><q-icon name="place" size="13px" /> {{ company.location }}</span>
            <span v-if="company?.size"><q-icon name="groups" size="13px" /> {{ company.size }}</span>
            <span v-if="company?.founded_at">с {{ company.founded_at.slice(0, 4) }}</span>
            <a
              v-if="company?.website"
              :href="company.website"
              target="_blank"
              rel="noopener"
              class="text-link"
            >{{ company.website.replace(/^https?:\/\//, '') }}</a>
          </div>

          <div v-if="company?.industries && company.industries.length > 0" class="row q-gutter-x-xs q-mt-sm">
            <q-badge v-for="industry in company.industries" :key="industry.id" class="tag-badge" :label="industry.name" class="q-mx-xs" />
          </div>

          <div v-if="company?.representative" class="text-caption q-mt-sm">
            Представитель:
            <router-link :to="`/users/${company.representative.login}`" class="text-link">
              {{ company.representative.name }}
            </router-link>
          </div>
        </div>

        <div v-if="company" class="col-auto column items-end q-gutter-y-sm">
          <SubscribeButton type="company" :key-value="company.slug" />
          <div class="text-caption text-dim">
            {{ formatCount(company.subscribers_count ?? 0) }} подписчиков · рейтинг {{ company.rating }}
          </div>
        </div>
      </div>
    </q-card>

    <q-tabs
      v-model="tab"
      no-caps
      dense
      align="left"
      active-color="primary"
      indicator-color="primary"
      class="habr-card panel-card q-mb-md"
      style="border-radius: 4px"
    >
      <q-tab name="publications" label="Публикации" />
      <q-tab name="employees" label="Сотрудники" />
    </q-tabs>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <template v-else-if="tab === 'publications'">
      <PublicationCard v-for="publication in publications" :key="publication.id" :publication="publication" />
      <EmptyNote v-if="publications.length === 0" text="Компания пока ничего не опубликовала" />
    </template>

    <template v-else>
      <q-card flat class="habr-card q-pa-md q-mb-sm" style="max-width: 480px">
        <router-link
          v-for="employee in employees"
          :key="employee.login"
          :to="`/users/${employee.login}`"
          class="row items-center justify-between q-py-sm"
          style="color: inherit"
        >
          <span>{{ employee.name }} <span class="text-dim text-caption">@{{ employee.login }}</span></span>
          <q-badge class="tag-badge" :label="`рейтинг ${employee.rating}`" />
        </router-link>
        <EmptyNote v-if="employees.length === 0" text="Сотрудников нет" />
      </q-card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import type { Author, Company, Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
import EmptyNote from '@/components/EmptyNote.vue';
import { usePublicationFeed } from '@/composables/usePublicationFeed';
import { formatCount } from '@/utils/format';

const props = defineProps<{ slug: string }>();

const company = ref<Company | null>(null);
const loading = ref(true);
const tab = ref<'publications' | 'employees'>('publications');
const employees = ref<Author[]>([]);

const { publications, meta, page, load } = usePublicationFeed(
  (pageNo) => api.get<Paginated<Publication>>(`/companies/${props.slug}/publications`, {
    params: { page: pageNo }
  }).then((r) => r.data)
);

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
  if (tab.value === 'employees' && employees.value.length === 0) {
    void loadEmployees();
  }
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
