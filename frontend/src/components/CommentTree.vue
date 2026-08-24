<template>
  <div class="q-pl-none" :class="depth > 0 ? 'q-ml-xl q-pl-md' : ''">
    <div class="row no-wrap q-gutter-x-sm">
      <div class="col-auto">
        <VoteArrows
          vertical
          :rating="comment.rating"
          :my-vote="myVotes.get(comment.id) ?? null"
          @vote="(value) => emit('vote', comment, value)"
        />
      </div>

      <div class="col">
        <div class="pub-meta">
          <span class="text-weight-medium text-dark">{{ comment.author?.name ?? 'Гость' }}</span>
          <span>· {{ formatDate(comment.created_at) }}</span>
          <template v-if="isMine">
            <q-btn flat dense size="11px" color="negative" label="Удалить" @click="emit('delete', comment)" />
          </template>
          <q-btn
            v-else-if="auth.isLoggedIn"
            flat dense size="11px" color="primary"
            label="Ответить"
            data-testid="reply"
            @click="replying = !replying"
          />
        </div>

        <div class="comment-body q-mt-xs">{{ comment.body }}</div>

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

    <q-separator spaced />

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
  if (body === '') {
    return;
  }

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
