<template>
  <div>
    <q-card v-if="profile !== null" flat class="habr-card q-pa-md q-mb-md">
      <div class="row items-center q-gutter-x-md">
        <div class="col-auto text-center">
          <VoteArrows
            :rating="profile.karma ?? 0"
            :my-vote="myKarmaVote"
            data-testid="karma-vote"
            @vote="voteKarma"
          />
          <div class="text-caption text-dim">карма</div>
        </div>

        <div class="col-auto">
          <q-avatar size="64px" color="primary" text-color="white" class="text-h4">
            {{ profile.name.charAt(0).toUpperCase() }}
          </q-avatar>
        </div>

        <div class="col">
          <div class="text-h6 text-weight-bold">{{ profile.name }}</div>
          <div class="text-caption text-dim">@{{ profile.login }}</div>
          <div v-if="profile.location" class="text-caption text-dim q-mt-xs">
            <q-icon name="place" size="13px" /> {{ profile.location }}
          </div>
          <p v-if="profile.about" class="text-body2 q-mt-sm q-mb-none">{{ profile.about }}</p>

          <div v-if="profile.badges && profile.badges.length > 0" class="row q-gutter-x-xs q-mt-sm">
            <q-badge
              v-for="badge in profile.badges"
              :key="badge.id"
              class="tag-badge q-mx-xs"
              :label="badge.name"
            >
              <q-tooltip>{{ badge.description }}</q-tooltip>
            </q-badge>
          </div>

          <div v-if="profile.company" class="text-caption q-mt-sm">
            Работает в
            <span class="text-weight-medium">{{ profile.company.name }}</span>
          </div>
        </div>

        <div class="col-auto row q-gutter-x-md text-center">
          <div>
            <div class="text-weight-bold">{{ formatCount(profile.publications_count) }}</div>
            <div class="text-caption text-dim">публикаций</div>
          </div>
          <div>
            <div class="text-weight-bold">{{ formatCount(profile.comments_count) }}</div>
            <div class="text-caption text-dim">комментариев</div>
          </div>
          <div>
            <div class="text-weight-bold">{{ formatCount(profile.followers_count) }}</div>
            <div class="text-caption text-dim">подписчиков</div>
          </div>
          <div>
            <div class="text-weight-bold">{{ profile.rating }}</div>
            <div class="text-caption text-dim">рейтинг</div>
          </div>
        </div>

        <div v-if="auth.user?.login !== login" class="col-auto">
          <SubscribeButton type="user" :key-value="login" />
        </div>
      </div>
    </q-card>

    <q-tabs
      v-model="tab"
      no-caps
      dense
      align="left"
      active-color="primary"
      indicator-color="primary"
      class="habr-card panel-card q-mb-md"
      style="border-radius: 4px"
    >
      <q-tab name="publications" label="Публикации" />
      <q-tab name="comments" label="Комментарии" />
      <q-tab name="followers" label="Подписчики" />
      <q-tab name="following" label="Подписки" />
    </q-tabs>

    <template v-if="loading">
      <q-card flat class="habr-card q-pa-lg"><q-skeleton type="text" /></q-card>
    </template>

    <template v-else-if="tab === 'publications'">
      <PublicationCard v-for="publication in publications" :key="publication.id" :publication="publication" />
      <EmptyNote v-if="publications.length === 0" text="Публикаций пока нет" />
    </template>

    <template v-else-if="tab === 'comments'">
      <q-card v-for="comment in userComments" :key="comment.id" flat class="habr-card q-pa-md q-mb-sm">
        <div class="pub-meta q-mb-xs">
          <span>· {{ formatDate(comment.created_at) }}</span>
          <span>· рейтинг {{ comment.rating > 0 ? `+${comment.rating}` : comment.rating }}</span>
        </div>
        <div class="comment-body">{{ comment.body }}</div>
      </q-card>
      <EmptyNote v-if="userComments.length === 0" text="Комментариев пока нет" />
    </template>

    <template v-else>
      <q-card flat class="habr-card q-pa-md q-mb-sm" style="max-width: 480px">
        <router-link
          v-for="userItem in userList"
          :key="userItem.login"
          :to="`/users/${userItem.login}`"
          class="row items-center justify-between q-py-sm"
          style="color: inherit"
        >
          <span>{{ userItem.name }} <span class="text-dim text-caption">@{{ userItem.login }}</span></span>
          <q-badge class="tag-badge" :label="`рейтинг ${userItem.rating}`" />
        </router-link>
        <EmptyNote v-if="userList.length === 0" :text="tab === 'followers' ? 'Подписчиков нет' : 'Ни на кого не подписан'" />
      </q-card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import { Notify } from 'quasar';
import type {
  Author,
  Comment,
  Paginated,
  Publication,
  UserProfile,
} from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
import EmptyNote from '@/components/EmptyNote.vue';
import { usePublicationFeed } from '@/composables/usePublicationFeed';
import { formatCount, formatDate } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const props = defineProps<{ login: string }>();

const auth = useAuthStore();

const profile = ref<UserProfile | null>(null);
const loading = ref(true);
const tab = ref<'publications' | 'comments' | 'followers' | 'following'>('publications');
const myKarmaVote = ref<number | null>(null);

const userComments = ref<Comment[]>([]);
const userList = ref<Author[]>([]);

const { publications, load } = usePublicationFeed(
  (page) => api.get<Paginated<Publication>>(`/users/${props.login}/publications`, {
    params: { page }
  }).then((r) => r.data)
);

async function loadTab(): Promise<void> {
  if (tab.value === 'publications') {
    await load();
    return;
  }

  loading.value = true;

  try {
    const url =
      tab.value === 'comments'
        ? `/users/${props.login}/comments`
        : tab.value === 'followers'
          ? `/users/${props.login}/followers`
          : `/users/${props.login}/following`;

    const { data } = await api.get<Paginated<Comment & Author>>(url);
    const items = Array.isArray(data.data) ? data.data : [];

    if (tab.value === 'comments') {
      userComments.value = items;
    } else {
      userList.value = items;
    }
  } finally {
    loading.value = false;
  }
}

async function voteKarma(value: number): Promise<void> {
  if (!auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' });
    return;
  }

  const userId = profile.value?.id;
  if (userId === undefined) return;

  const { data } = await api.post<{ karma: number }>(`/users/${userId}/karma`, { value });

  if (profile.value !== null) {
    profile.value.karma = data.karma;
  }
  myKarmaVote.value = myKarmaVote.value === value ? null : value;
}

watch(tab, () => void loadTab());

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: UserProfile }>(`/users/${props.login}`);
    profile.value = data.data;
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    Notify.create({
      type: 'negative',
      message: status === 404 ? 'Пользователь не найден' : 'Ошибка загрузки профиля'
    });
    return;
  }

  loading.value = false;
  await loadTab();
});
</script>
