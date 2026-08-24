<template>
  <div>
    <div class="text-h6 text-weight-medium q-mb-md">Авторы</div>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <q-card v-else flat class="habr-card q-pa-md" style="max-width: 560px">
      <router-link
        v-for="author in authors"
        :key="author.login"
        :to="`/users/${author.login}`"
        class="row items-center justify-between q-py-sm"
        style="color: inherit"
      >
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="32px" color="primary" text-color="white">{{ author.name.charAt(0).toUpperCase() }}</q-avatar>
          <span>
            {{ author.name }}
            <span class="text-grey text-caption">@{{ author.login }}</span>
          </span>
        </div>
        <q-badge outline color="grey-7" :label="`рейтинг ${author.rating}`" />
      </router-link>
      <EmptyNote v-if="authors.length === 0" text="Авторы не найдены" />
    </q-card>

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
import { api } from '@/boot/axios';
import type { Author, Paginated } from '@/types/api';
import EmptyNote from '@/components/EmptyNote.vue';

const authors = ref<Author[]>([]);
const meta = ref<Paginated<Author>['meta'] | null>(null);
const page = ref(1);
const loading = ref(true);

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
