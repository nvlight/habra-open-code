<template>
  <div>
    <q-tabs
      v-model="mode"
      no-caps
      dense
      align="left"
      active-color="primary"
      indicator-color="primary"
      class="bg-white habr-card q-mb-md"
      style="border-radius: 4px"
    >
      <q-tab name="new" label="Новые" />
      <q-tab name="best" label="Лучшие" />
      <q-tab name="my" label="Моя" data-testid="my-feed-tab" />
    </q-tabs>

    <div v-if="mode !== 'my'" class="row q-col-gutter-sm q-mb-md">
      <div class="col-auto" style="min-width: 180px">
        <q-select
          v-model="type"
          :options="typeOptions"
          emit-value
          map-options
          outlined
          dense
          label="Тип"
          options-dense
        />
      </div>
      <div class="col-auto" style="min-width: 220px">
        <q-select
          v-model="hub"
          :options="hubOptions"
          emit-value
          map-options
          outlined
          dense
          label="Хаб"
          options-dense
          use-input
          input-debounce="0"
          @filter="filterHubs"
        />
      </div>
    </div>

    <template v-if="loading">
      <q-card flat class="habr-card q-mb-md q-pa-lg">
        <q-skeleton type="text" width="40%" />
        <q-skeleton type="text" width="90%" class="q-mt-sm" />
        <q-skeleton type="text" width="70%" />
      </q-card>
    </template>

    <template v-else>
      <PublicationCard v-for="publication in publications" :key="publication.id" :publication="publication" />

      <q-card v-if="publications.length === 0" flat class="habr-card q-pa-xl text-center text-grey">
        Ничего не найдено
      </q-card>
    </template>

    <div v-if="meta && meta.last_page > 1" class="row justify-center q-mt-md">
      <q-pagination
        v-model="page"
        :max="meta.last_page"
        direction-links
        boundary-numbers
        color="grey-8"
        active-color="primary"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar';
import { api } from '@/boot/axios';
import type { HubRef, Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const mode = ref<'new' | 'best' | 'my'>('new');
const type = ref<string | null>(null);
const hub = ref<string | null>(null);
const page = ref(1);

const publications = ref<Publication[]>([]);
const meta = ref<Paginated<Publication>['meta'] | null>(null);
const loading = ref(false);

const hubsAll = ref<HubRef[]>([]);
const hubsFiltered = ref<HubRef[]>([]);

const typeOptions = [
  { label: 'Все типы', value: null },
  { label: 'Статьи', value: 'article' },
  { label: 'Посты', value: 'post' },
  { label: 'Новости', value: 'news' }
];

const hubOptions = ref(
  hubsFiltered.value.map((h) => ({ label: h.name, value: h.alias }))
);

async function load(): Promise<void> {
  if (mode.value === 'my' && !auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы видеть личную ленту' });
    mode.value = 'new';
    return;
  }

  loading.value = true;

  try {
    const params: Record<string, string | number> = {
      page: page.value,
      per_page: 20,
      sort: mode.value === 'best' ? 'best' : 'new'
    };

    const endpoint = mode.value === 'my' ? '/feed' : '/publications';

    if (mode.value !== 'my') {
      if (type.value !== null) {
        params.type = type.value;
      }
      if (hub.value !== null) {
        params.hub = hub.value;
      }
    }

    const { data } = await api.get<Paginated<Publication>>(endpoint, { params });
    publications.value = Array.isArray(data.data) ? data.data : [];
    meta.value = data.meta ?? null;
  } finally {
    loading.value = false;
  }
}

watch([mode, type, hub], () => {
  page.value = 1;
  void load();
});

watch(page, () => {
  void load();
});

function filterHubs(input: string, update: (callback: () => void) => void): void {
  update(() => {
    const needle = input.toLowerCase();
    hubOptions.value = hubsAll.value
      .filter((h) => h.name.toLowerCase().includes(needle))
      .map((h) => ({ label: h.name, value: h.alias }));
  });
}

onMounted(async () => {
  void load();

  try {
    const { data } = await api.get<Paginated<HubRef>>('/hubs');
    hubsAll.value = [...data.data].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
  } catch {
    Notify.create({ type: 'negative', message: 'Не удалось загрузить список хабов' });
  }
});
</script>
