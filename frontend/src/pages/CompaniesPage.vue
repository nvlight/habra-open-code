<template>
  <div>
    <div class="text-h6 text-weight-medium q-mb-md">Компании</div>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <div v-else class="row q-col-gutter-md">
      <div v-for="company in companies" :key="company.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat class="habr-card full-height">
          <q-card-section>
            <router-link :to="`/companies/${company.slug}`" class="text-weight-medium text-h6 text-link">
              {{ company.name }}
            </router-link>
            <p class="text-caption text-dim q-mt-xs">{{ company.description }}</p>
          </q-card-section>
          <q-card-section class="q-pt-none row justify-between text-caption text-dim">
            <span>{{ formatCount(company.subscribers_count ?? 0) }} подписчиков</span>
            <span>рейтинг {{ company.rating }}</span>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Company, Paginated } from '@/types/api';
import { formatCount } from '@/utils/format';

const companies = ref<Company[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get<Paginated<Company>>('/companies', { params: { per_page: 100 } });
    companies.value = Array.isArray(data.data) ? data.data : [];
  } finally {
    loading.value = false;
  }
});
</script>
