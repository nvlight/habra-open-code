<template>
  <q-card flat class="habr-card q-mb-md">
    <q-card-section class="row q-pa-md q-gutter-x-sm">
      <div class="col-auto">
        <VoteArrows :rating="publication.rating" static />
        <div class="text-caption text-dim text-center">{{ publication.votes_up }}↑ {{ publication.votes_down }}↓</div>
      </div>

      <div class="col">
        <div class="row items-center q-gutter-x-sm q-mb-xs">
          <span class="badge-type" :class="typeBadgeClass">{{ publication.type_label }}</span>
          <span v-if="publication.label_label" class="badge-type badge-type--green">{{ publication.label_label }}</span>
          <span v-if="publication.difficulty_label" class="badge-type">{{ publication.difficulty_label }}</span>
          <span v-if="publication.is_translation" class="badge-type">Перевод</span>
        </div>

        <div class="pub-title">
          <router-link :to="`/publications/${publication.id}`">{{ publication.title }}</router-link>
        </div>

        <p class="pub-lead q-my-sm">{{ publication.lead }}</p>

        <div class="pub-meta">
          <router-link :to="`/users/${publication.author.login}`" class="text-link">
            {{ publication.author.name }}
          </router-link>
          <router-link
            v-for="item in publication.hubs"
            :key="item.id"
            :to="`/hubs/${item.alias}`"
            class="text-dim"
          >· {{ item.name }}</router-link>
          <span>· {{ publication.reading_time }} мин</span>
          <span>· {{ formatCount(publication.views_count) }} просмотров</span>
          <span>· {{ formatDate(publication.published_at) }}</span>
        </div>

        <div class="pub-meta q-mt-sm">
          <q-icon name="chat_bubble_outline" size="14px" />
          <span>{{ formatCount(publication.comments_count) }}</span>
          <q-icon name="bookmark_border" size="14px" class="q-ml-md" />
          <span>{{ formatCount(publication.bookmarks_count) }}</span>
        </div>
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Publication } from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import { formatCount, formatDate } from '@/utils/format';

const props = defineProps<{ publication: Publication }>();

const typeBadgeClass = computed(() => {
  if (props.publication.type === 'news') {
    return 'badge-type--news';
  }
  if (props.publication.type === 'post') {
    return 'badge-type--green';
  }

  return '';
});
</script>
