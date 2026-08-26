<template>
  <div class="tm-comment" :class="{ 'tm-comment__replies': depth > 0 }">
    <div style="display: flex; gap: 12px">
      <div style="flex-shrink: 0">
        <VoteArrows
          :rating="comment.rating"
          :my-vote="myVotes.get(comment.id) ?? null"
          vertical
          @vote="(value) => emit('vote', comment, value)"
        />
      </div>

      <div style="flex: 1; min-width: 0">
        <div class="tm-comment__header">
          <router-link
            v-if="comment.author"
            :to="`/users/${comment.author.login}`"
            class="tm-comment__author"
          >{{ comment.author.name }}</router-link>
          <span class="tm-comment__date">{{ formatDate(comment.created_at) }}</span>
        </div>

        <div class="tm-comment__body">{{ comment.body }}</div>

        <div class="tm-comment__actions">
          <template v-if="isMine">
            <button class="tm-comment__delete-btn" @click="emit('delete', comment)">Удалить</button>
          </template>
          <template v-else-if="auth.isLoggedIn">
            <button class="tm-comment__reply-btn" data-testid="reply" @click="replying = !replying">
              Ответить
            </button>
          </template>
        </div>

        <q-form v-if="replying" class="q-mt-sm" @submit.prevent="submitReply">
          <q-input
            v-model="replyBody"
            type="textarea"
            outlined
            dense
            autogrow
            placeholder="Ваш ответ…"
            data-testid="reply-input"
          />
          <div class="q-mt-xs q-gutter-x-sm">
            <q-btn unelevated color="primary" size="sm" label="Отправить" type="submit" :loading="sending" />
            <q-btn flat size="sm" label="Отмена" @click="replying = false" />
          </div>
        </q-form>
      </div>
    </div>

    <CommentTree
      v-for="reply in comment.replies"
      :key="reply.id"
      :comment="reply"
      :depth="depth + 1"
      :my-votes="myVotes"
      @vote="(...args) => emit('vote', ...args)"
      @reply-added="(...args) => emit('reply-added', ...args)"
      @delete="(...args) => emit('delete', ...args)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { Comment } from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import { formatDate } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

defineOptions({ name: 'CommentTree' });

const props = withDefaults(
  defineProps<{
    comment: Comment;
    depth?: number;
    myVotes: Map<number, number>;
  }>(),
  { depth: 0 }
);

const emit = defineEmits<{
  vote: [comment: Comment, value: number];
  'reply-added': [parent: Comment, body: string];
  delete: [comment: Comment];
}>();

const auth = useAuthStore();
const replying = ref(false);
const replyBody = ref('');
const sending = ref(false);

const isMine = auth.user !== null && props.comment.author !== undefined && auth.user.id === props.comment.author.id;

async function submitReply(): Promise<void> {
  const body = replyBody.value.trim();
  if (body === '') return;
  sending.value = true;
  try {
    emit('reply-added', props.comment, body);
    replying.value = false;
    replyBody.value = '';
  } finally {
    sending.value = false;
  }
}
</script>
