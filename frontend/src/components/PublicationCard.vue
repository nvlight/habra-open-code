<template>
  <article class="tm-articles-list__item" data-navigatable>
    <!-- Publication type label -->
    <div v-if="publication.type" class="tm-publication-type" :class="`tm-publication-type--${publication.type}`">
      {{ publication.type_label }}
    </div>

    <!-- Meta: author + date -->
    <div class="tm-article-snippet__meta">
      <div class="tm-article-snippet__author">
        <router-link :to="`/users/${publication.author.login}`">
          <span
            class="tm-article-snippet__avatar"
            :style="{ backgroundColor: avatarColor }"
          >{{ initial }}</span>
        </router-link>
        <router-link
          :to="`/users/${publication.author.login}`"
          class="tm-article-snippet__username"
        >{{ publication.author.name }}</router-link>
      </div>
      <router-link
        v-if="publication.published_at"
        :to="`/publications/${publication.id}`"
        class="tm-article-snippet__datetime"
      >{{ formatDate(publication.published_at) }}</router-link>
    </div>

    <!-- Title -->
    <h2 class="tm-title tm-title_h2">
      <router-link :to="`/publications/${publication.id}`" class="tm-title__link">
        {{ publication.title }}
      </router-link>
    </h2>

    <!-- Stats: complexity + reading time + views -->
    <div class="tm-stats">
      <span v-if="publication.difficulty" class="tm-stats__item" :class="`tm-stats__complexity--${publication.difficulty}`">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="12" r="5"/>
        </svg>
        <span>{{ publication.difficulty_label }}</span>
      </span>
      <span class="tm-stats__item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>{{ publication.reading_time }} мин</span>
      </span>
      <span class="tm-stats__item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        <span>{{ formatCount(publication.views_count) }}</span>
      </span>
    </div>

    <!-- Hubs -->
    <div v-if="publication.hubs.length > 0" class="tm-publication-hubs">
      <template v-for="(hub, index) in publication.hubs" :key="hub.id">
        <router-link :to="`/hubs/${hub.alias}`" class="tm-publication-hub__link">
          {{ hub.name }}
        </router-link>
        <span v-if="index < publication.hubs.length - 1" class="tm-publication-hub__separator">·</span>
      </template>
    </div>

    <!-- Labels -->
    <div v-if="hasLabels" class="tm-article-labels">
      <span v-if="publication.label_label" class="tm-label" :class="`tm-label--${publication.label}`">
        {{ publication.label_label }}
      </span>
      <span v-if="publication.is_translation" class="tm-label tm-label--tutorial">Перевод</span>
    </div>

    <!-- Lead -->
    <p v-if="publication.lead" class="pub-lead">{{ publication.lead }}</p>

    <!-- Footer: vote + bookmark + share + comments -->
    <div class="tm-data-icons">
      <div class="tm-data-icons__item" data-testid="card-vote">
        <VoteArrows
          :rating="publication.rating"
          static
        />
      </div>
      <div class="tm-data-icons__item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
        </svg>
        <span>{{ formatCount(publication.bookmarks_count) }}</span>
      </div>
      <router-link
        :to="`/publications/${publication.id}`"
        class="tm-data-icons__item tm-data-icons__item--comments"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
        </svg>
        <span>{{ formatCount(publication.comments_count) }}</span>
      </router-link>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Publication } from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import { formatCount, formatDate } from '@/utils/format';

const props = defineProps<{ publication: Publication }>();

const initial = computed(() => props.publication.author.name?.charAt(0).toUpperCase() ?? '?');

const avatarColor = computed(() => {
  const colors = [
    'hsl(200, 34%, 50%)',
    'hsl(140, 50%, 40%)',
    'hsl(30, 96%, 45%)',
    'hsl(270, 50%, 50%)',
    'hsl(350, 60%, 50%)',
    'hsl(190, 60%, 40%)',
  ];
  const hash = props.publication.author.login.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return colors[hash % colors.length];
});

const hasLabels = computed(() => {
  return props.publication.label_label || props.publication.is_translation;
});
</script>
