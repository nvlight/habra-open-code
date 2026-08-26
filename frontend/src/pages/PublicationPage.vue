<template>
  <div>
    <template v-if="loading">
      <div class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--40"></div>
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--100"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <div v-else-if="notFound" class="tm-empty">
      <div class="text-h6">Публикация не найдена</div>
      <router-link to="/articles" class="text-link">Вернуться в ленту</router-link>
    </div>

    <template v-else-if="publication">
      <!-- Article header -->
      <div class="tm-article-page__header">
        <!-- Type label -->
        <div class="tm-article-page__meta">
          <span class="tm-publication-type" :class="`tm-publication-type--${publication.type}`">
            {{ publication.type_label }}
          </span>
          <span v-if="publication.label_label" class="tm-label" :class="`tm-label--${publication.label}`">
            {{ publication.label_label }}
          </span>
          <span v-if="publication.difficulty_label" class="tm-stats__item" :class="`tm-stats__complexity--${publication.difficulty}`">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
              <circle cx="12" cy="12" r="5"/>
            </svg>
            {{ publication.difficulty_label }}
          </span>
        </div>

        <!-- Title -->
        <h1 class="tm-title tm-title_h1">
          <span class="tm-title__link">{{ publication.title }}</span>
        </h1>

        <!-- Author + date -->
        <div class="tm-article-snippet__meta" style="margin-top: 12px">
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
          <span v-if="publication.published_at" class="tm-article-snippet__datetime">
            {{ formatDate(publication.published_at) }}
          </span>
          <span v-if="publication.reading_time" class="tm-stats__item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            {{ publication.reading_time }} мин чтения
          </span>
          <span v-if="publication.views_count" class="tm-stats__item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            {{ formatCount(publication.views_count) }}
          </span>
        </div>

        <!-- Hubs -->
        <div v-if="publication.hubs.length > 0" class="tm-publication-hubs" style="margin-top: 12px">
          <template v-for="(hub, idx) in publication.hubs" :key="hub.id">
            <router-link :to="`/hubs/${hub.alias}`" class="tm-publication-hub__link">{{ hub.name }}</router-link>
            <span v-if="idx < publication.hubs.length - 1" class="tm-publication-hub__separator">·</span>
          </template>
        </div>

        <!-- Lead -->
        <p v-if="publication.lead" class="pub-lead" style="margin-top: 12px; font-size: 16px">{{ publication.lead }}</p>
      </div>

      <!-- Article body -->
      <div class="tm-article-page__body" style="background: var(--habr-bg-card); padding: 20px; border-radius: 0 0 4px 4px; margin-bottom: 2px">
        <div class="publication-body" v-html="publication.body ?? ''" />
      </div>

      <!-- Actions bar -->
      <div style="background: var(--habr-bg-card); padding: 16px 20px; border-radius: 0 0 4px 4px; margin-bottom: 16px">
        <div class="tm-data-icons">
          <div class="tm-data-icons__item">
            <VoteArrows
              :rating="publication.rating"
              :my-vote="myVote"
              data-testid="pub-vote"
              @vote="votePublication"
            />
          </div>
          <div class="tm-data-icons__item" data-testid="bookmark" @click="toggleBookmark">
            <svg viewBox="0 0 24 24" :fill="bookmarked ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
            </svg>
            <span>{{ formatCount(publication.bookmarks_count) }}</span>
          </div>
          <div class="tm-data-icons__item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
            </svg>
            <span>{{ formatCount(publication.comments_count) }}</span>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="publication.tags.length > 0" class="tm-article-page__tags">
          <router-link
            v-for="tag in publication.tags"
            :key="tag.id"
            :to="`/articles?tag=${tag.name}`"
            class="tm-tag"
          >{{ tag.name }}</router-link>
        </div>
      </div>

      <!-- Comments -->
      <div class="tm-comments">
        <h2 class="tm-comments__title">Комментарии · {{ comments.length }}</h2>

        <div v-if="auth.isLoggedIn" class="tm-comments__form">
          <q-input
            v-model="newCommentBody"
            type="textarea"
            outlined
            autogrow
            placeholder="Написать комментарий…"
            data-testid="comment-input"
          />
          <q-btn
            unelevated
            color="primary"
            no-caps
            label="Отправить"
            class="q-mt-sm"
            :loading="postingComment"
            @click="submitComment(null)"
          />
        </div>

        <div v-else class="tm-comments__login-hint">
          <router-link to="/login" class="text-link">Войдите</router-link>, чтобы комментировать и голосовать.
        </div>

        <CommentTree
          v-for="rootComment in comments"
          :key="rootComment.id"
          :comment="rootComment"
          :my-votes="myCommentVotes"
          @vote="voteComment"
          @reply-added="addReply"
          @delete="deleteComment"
        />

        <div v-if="comments.length === 0" class="tm-comments__empty">
          Комментариев пока нет — будьте первым!
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/boot/axios';
import { Notify } from 'quasar';
import type { Comment, Publication, VoteResult } from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import CommentTree from '@/components/CommentTree.vue';
import { formatCount, formatDate } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const props = defineProps<{ id: string }>();
const auth = useAuthStore();

const publication = ref<Publication | null>(null);
const comments = ref<Comment[]>([]);
const loading = ref(true);
const notFound = ref(false);
const myVote = ref<number | null>(null);
const bookmarked = ref(false);
const myCommentVotes = ref(new Map<number, number>());
const newCommentBody = ref('');
const postingComment = ref(false);

const initial = computed(() => publication.value?.author.name?.charAt(0).toUpperCase() ?? '?');

const avatarColor = computed(() => {
  const colors = [
    'hsl(200, 34%, 50%)', 'hsl(140, 50%, 40%)', 'hsl(30, 96%, 45%)',
    'hsl(270, 50%, 50%)', 'hsl(350, 60%, 50%)', 'hsl(190, 60%, 40%)',
  ];
  const login = publication.value?.author.login ?? '';
  const hash = login.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return colors[hash % colors.length];
});

function removeComment(list: Comment[], target: Comment): boolean {
  const index = list.findIndex((c) => c.id === target.id);
  if (index !== -1) { list.splice(index, 1); return true; }
  return list.some((c) => removeComment(c.replies, target));
}

async function load(): Promise<void> {
  loading.value = true;
  try {
    const [pubResponse, commentsResponse] = await Promise.all([
      api.get<{ data: Publication }>(`/publications/${props.id}`),
      api.get<{ data: Comment[] }>(`/publications/${props.id}/comments`)
    ]);
    publication.value = pubResponse.data.data;
    comments.value = Array.isArray(commentsResponse.data.data) ? commentsResponse.data.data : [];
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    if (status === 404) notFound.value = true;
  } finally {
    loading.value = false;
  }
}

async function votePublication(value: number): Promise<void> {
  if (!auth.isLoggedIn) { Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' }); return; }
  const { data } = await api.post<VoteResult>(`/publications/${props.id}/vote`, { value });
  if (publication.value !== null) {
    publication.value.rating = data.rating;
    publication.value.votes_up = data.votes_up ?? publication.value.votes_up;
    publication.value.votes_down = data.votes_down ?? publication.value.votes_down;
  }
  myVote.value = myVote.value === value ? null : value;
}

async function voteComment(comment: Comment, value: number): Promise<void> {
  if (!auth.isLoggedIn) { Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' }); return; }
  const { data } = await api.post<{ rating: number }>(`/comments/${comment.id}/vote`, { value });
  comment.rating = data.rating;
  if (myCommentVotes.value.get(comment.id) === value) {
    myCommentVotes.value.delete(comment.id);
  } else {
    myCommentVotes.value.set(comment.id, value);
  }
}

async function toggleBookmark(): Promise<void> {
  if (!auth.isLoggedIn) { Notify.create({ type: 'warning', message: 'Войдите, чтобы добавлять в закладки' }); return; }
  await api[bookmarked.value ? 'delete' : 'post'](`/publications/${props.id}/bookmark`);
  bookmarked.value = !bookmarked.value;
  if (publication.value !== null) {
    publication.value.bookmarks_count += bookmarked.value ? 1 : -1;
  }
  Notify.create({ type: 'positive', message: bookmarked.value ? 'Добавлено в закладки' : 'Удалено из закладок' });
}

async function submitComment(parent: Comment | null): Promise<void> {
  const body = parent === null ? newCommentBody.value.trim() : '';
  if (body === '') return;
  postingComment.value = true;
  try {
    const { data } = await api.post<{ data: Comment }>(`/publications/${props.id}/comments`, { body });
    const created = data.data;
    if (created.replies === undefined) created.replies = [];
    comments.value.unshift(created);
    newCommentBody.value = '';
    if (publication.value !== null) publication.value.comments_count += 1;
  } finally {
    postingComment.value = false;
  }
}

async function addReply(parent: Comment, body: string): Promise<void> {
  const { data } = await api.post<{ data: Comment }>(`/publications/${props.id}/comments`, { body, parent_id: parent.id });
  const created = data.data;
  if (created.replies === undefined) created.replies = [];
  parent.replies.push(created);
  if (publication.value !== null) publication.value.comments_count += 1;
}

async function deleteComment(comment: Comment): Promise<void> {
  await api.delete(`/comments/${comment.id}`);
  removeComment(comments.value, comment);
  if (publication.value !== null && publication.value.comments_count > 0) {
    publication.value.comments_count -= 1;
  }
}

onMounted(load);
</script>
