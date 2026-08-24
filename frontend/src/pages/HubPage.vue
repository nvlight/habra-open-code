<template>
  <div>
    <q-card flat class="habr-card q-pa-md q-mb-md">
      <div class="row items-center q-gutter-x-md">
        <div class="col">
          <div class="text-h5 text-weight-bold">{{ hub?.name }}</div>
          <p v-if="hub?.description" class="text-body2 text-grey-8 q-mt-xs q-mb-none">
            {{ hub.description }}
          </p>
          <div class="text-caption text-grey q-mt-sm">
            {{ formatCount(hub?.subscribers_count ?? 0) }} подписчиков · рейтинг {{ hub?.rating }}
          </div>
        </div>
        <div v-if="hub" class="col-auto">
          <SubscribeButton type="hub" :key-value="hub.alias" />
        </div>
      </div>
    </q-card>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <template v-else>
      <PublicationCard v-for="publication in publications" :key="publication.id" :publication="publication" />
      <EmptyNote v-if="publications.length === 0" text="В этом хабе пока нет публикаций" />

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
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import type { Hub, Paginated, Publication } from '@/types/api';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
import EmptyNote from '@/components/EmptyNote.vue';
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
