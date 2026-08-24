<template>
  <div>
    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg">
        <q-skeleton type="text" width="60%" />
        <q-skeleton type="text" width="90%" class="q-mt-sm" />
        <q-skeleton type="rect" height="120px" class="q-mt-md" />
      </q-card>
    </template>

    <q-card v-else-if="notFound" flat class="habr-card q-pa-xl text-center">
      <div class="text-h6">Публикация не найдена</div>
      <q-btn flat color="primary" label="Вернуться в ленту" to="/" class="q-mt-md" />
    </q-card>

    <template v-else-if="publication">
      <q-card flat class="habr-card q-pa-md q-mb-md">
        <div class="row q-gutter-x-sm">
          <div class="col-auto">
            <VoteArrows
              :rating="publication.rating"
              :my-vote="myVote"
              data-testid="pub-vote"
              @vote="votePublication"
            />
          </div>

          <div class="col">
            <div class="row items-center q-gutter-x-sm q-mb-xs">
              <span class="badge-type">{{ publication.type_label }}</span>
              <span v-if="publication.label_label" class="badge-type badge-type--green">{{ publication.label_label }}</span>
              <span v-if="publication.difficulty_label" class="badge-type">{{ publication.difficulty_label }}</span>
            </div>

            <h1 class="text-h5 text-weight-bold q-my-sm">{{ publication.title }}</h1>

            <p v-if="publication.lead" class="text-body2 text-grey-8">{{ publication.lead }}</p>

            <div class="pub-meta">
              <span>{{ publication.author.name }}</span>
              <span
                v-for="item in publication.hubs"
                :key="item.id"
              >· {{ item.name }}</span>
              <span>· {{ publication.reading_time }} мин</span>
              <span>· {{ formatCount(publication.views_count) }} просмотров</span>
              <span v-if="publication.published_at">· {{ formatDate(publication.published_at) }}</span>
            </div>

            <q-separator class="q-my-md" />

            <div class="publication-body" v-html="publication.body ?? ''" />

            <div class="row items-center q-mt-md q-gutter-x-sm">
              <q-btn
                flat dense no-caps size="sm"
                :icon="bookmarked ? 'bookmark' : 'bookmark_border'"
                :label="`В закладки · ${formatCount(publication.bookmarks_count)}`"
                :color="bookmarked ? 'primary' : 'grey-7'"
                data-testid="bookmark"
                @click="toggleBookmark"
              />
              <q-icon name="chat_bubble_outline" size="16px" color="grey-7" />
              <span class="text-caption text-grey-7">{{ formatCount(publication.comments_count) }}</span>
              <div v-if="publication.tags.length > 0" class="row q-gutter-x-xs q-ml-sm">
                <q-badge
                  v-for="tag in publication.tags"
                  :key="tag.id"
                  outline
                  color="grey-7"
                  :label="tag.name"
                  class="q-mx-xs"
                />
              </div>
            </div>
          </div>
        </div>
      </q-card>

      <q-card flat class="habr-card q-pa-md">
        <div class="text-subtitle1 text-weight-medium q-mb-md">
          Комментарии · {{ comments.length }}
        </div>

        <q-form v-if="auth.isLoggedIn" class="q-mb-lg" @submit.prevent="submitComment(null)">
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
            type="submit"
            class="q-mt-sm"
            :loading="postingComment"
          />
        </q-form>

        <div v-else class="text-caption q-mb-lg">
          <router-link to="/login" style="color: #159be0">Войдите</router-link>,
          чтобы комментировать и голосовать.
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

        <div v-if="comments.length === 0" class="text-grey text-center q-py-lg">
          Комментариев пока нет — будьте первым!
        </div>
      </q-card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
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

function removeComment(list: Comment[], target: Comment): boolean {
  const index = list.findIndex((c) => c.id === target.id);
  if (index !== -1) {
    list.splice(index, 1);
    return true;
  }

  return list.some((c) => removeComment(c.replies, target));
}

async function load(): Promise<void> {
  loading.value = true;

  try {
    const [pubResponse, commentsResponse] = await Promise.all([
      api.get<Publication>(`/publications/${props.id}`),
      api.get<Comment[]>(`/publications/${props.id}/comments`)
    ]);
    publication.value = pubResponse.data;
    comments.value = commentsResponse.data;
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    if (status === 404) {
      notFound.value = true;
    }
  } finally {
    loading.value = false;
  }
}

async function votePublication(value: number): Promise<void> {
  if (!auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' });
    return;
  }

  const { data } = await api.post<VoteResult>(`/publications/${props.id}/vote`, { value });

  if (publication.value !== null) {
    publication.value.rating = data.rating;
    publication.value.votes_up = data.votes_up ?? publication.value.votes_up;
    publication.value.votes_down = data.votes_down ?? publication.value.votes_down;
  }
  myVote.value = myVote.value === value ? null : value;
}

async function voteComment(comment: Comment, value: number): Promise<void> {
  if (!auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' });
    return;
  }

  const { data } = await api.post<{ rating: number }>(`/comments/${comment.id}/vote`, { value });
  comment.rating = data.rating;

  if (myCommentVotes.value.get(comment.id) === value) {
    myCommentVotes.value.delete(comment.id);
  } else {
    myCommentVotes.value.set(comment.id, value);
  }
}

async function toggleBookmark(): Promise<void> {
  if (!auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы добавлять в закладки' });
    return;
  }

  await api[bookmarked.value ? 'delete' : 'post'](`/publications/${props.id}/bookmark`);
  bookmarked.value = !bookmarked.value;

  if (publication.value !== null) {
    publication.value.bookmarks_count += bookmarked.value ? 1 : -1;
  }

  Notify.create({
    type: 'positive',
    message: bookmarked.value ? 'Добавлено в закладки' : 'Удалено из закладок'
  });
}

async function submitComment(parent: Comment | null): Promise<void> {
  const body = parent === null ? newCommentBody.value.trim() : '';

  if (body === '') {
    return;
  }

  postingComment.value = true;

  try {
    const { data } = await api.post<Comment>(`/publications/${props.id}/comments`, { body });

    if (data.replies === undefined) {
      data.replies = [];
    }
    comments.value.unshift(data);

    newCommentBody.value = '';
    if (publication.value !== null) {
      publication.value.comments_count += 1;
    }
  } finally {
    postingComment.value = false;
  }
}

async function addReply(parent: Comment, body: string): Promise<void> {
  const { data } = await api.post<Comment>(`/publications/${props.id}/comments`, {
    body,
    parent_id: parent.id
  });

  if (data.replies === undefined) {
    data.replies = [];
  }
  parent.replies.push(data);

  if (publication.value !== null) {
    publication.value.comments_count += 1;
  }
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
