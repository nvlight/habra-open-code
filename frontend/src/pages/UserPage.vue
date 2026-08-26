<template>
  <div>
    <!-- Profile card -->
    <div v-if="profile" class="tm-profile">
      <div class="tm-profile__header">
        <div class="tm-profile__karma">
          <VoteArrows
            :rating="profile.karma ?? 0"
            :my-vote="myKarmaVote"
            vertical
            data-testid="karma-vote"
            @vote="voteKarma"
          />
          <span class="tm-profile__karma-label">карма</span>
        </div>

        <span
          class="tm-profile__avatar"
          :style="{ backgroundColor: avatarColor }"
        >{{ profile.name.charAt(0).toUpperCase() }}</span>

        <div class="tm-profile__info">
          <h1 class="tm-profile__name">{{ profile.name }}</h1>
          <div class="tm-profile__login">@{{ profile.login }}</div>
          <div v-if="profile.location" class="tm-profile__location">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -1px">
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            {{ profile.location }}
          </div>
          <p v-if="profile.about" class="tm-profile__about">{{ profile.about }}</p>

          <div v-if="profile.badges && profile.badges.length > 0" class="tm-profile__badges">
            <span v-for="badge in profile.badges" :key="badge.id" class="tm-badge">
              {{ badge.name }}
            </span>
          </div>

          <div v-if="profile.company" class="tm-profile__company">
            Работает в <span class="text-weight-medium">{{ profile.company.name }}</span>
          </div>
        </div>

        <div v-if="auth.user?.login !== login" style="flex-shrink: 0">
          <SubscribeButton type="user" :key-value="login" />
        </div>
      </div>

      <div class="tm-profile__stats">
        <div class="tm-profile__stat">
          <div class="tm-profile__stat-value">{{ formatCount(profile.publications_count) }}</div>
          <div class="tm-profile__stat-label">публикаций</div>
        </div>
        <div class="tm-profile__stat">
          <div class="tm-profile__stat-value">{{ formatCount(profile.comments_count) }}</div>
          <div class="tm-profile__stat-label">комментариев</div>
        </div>
        <div class="tm-profile__stat">
          <div class="tm-profile__stat-value">{{ formatCount(profile.followers_count) }}</div>
          <div class="tm-profile__stat-label">подписчиков</div>
        </div>
        <div class="tm-profile__stat">
          <div class="tm-profile__stat-value">{{ profile.rating }}</div>
          <div class="tm-profile__stat-label">рейтинг</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tm-tabs">
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'publications' }" @click="tab = 'publications'">Публикации</button>
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'comments' }" @click="tab = 'comments'">Комментарии</button>
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'followers' }" @click="tab = 'followers'">Подписчики</button>
      <button class="tm-tabs__item" :class="{ 'tm-tabs__item--active': tab === 'following' }" @click="tab = 'following'">Подписки</button>
    </div>

    <!-- Tab content -->
    <template v-if="loading">
      <div v-for="i in 3" :key="i" class="tm-skeleton-card">
        <div class="tm-skeleton-line tm-skeleton-line--80"></div>
        <div class="tm-skeleton-line tm-skeleton-line--60"></div>
      </div>
    </template>

    <template v-else-if="tab === 'publications'">
      <PublicationCard v-for="pub in publications" :key="pub.id" :publication="pub" />
      <div v-if="publications.length === 0" class="tm-empty">Публикаций пока нет</div>
    </template>

    <template v-else-if="tab === 'comments'">
      <div v-for="comment in userComments" :key="comment.id" class="tm-articles-list__item">
        <div class="pub-meta q-mb-xs">
          <span>{{ formatDate(comment.created_at) }}</span>
          <span>· рейтинг {{ comment.rating > 0 ? `+${comment.rating}` : comment.rating }}</span>
        </div>
        <div class="comment-body">{{ comment.body }}</div>
      </div>
      <div v-if="userComments.length === 0" class="tm-empty">Комментариев пока нет</div>
    </template>

    <template v-else>
      <div class="tm-users-list">
        <router-link
          v-for="userItem in userList"
          :key="userItem.login"
          :to="`/users/${userItem.login}`"
          class="tm-users-list__item"
        >
          <span class="tm-users-list__avatar" :style="{ backgroundColor: getAvatarColor(userItem.login) }">
            {{ userItem.name.charAt(0).toUpperCase() }}
          </span>
          <span>
            <span class="tm-users-list__name">{{ userItem.name }}</span>
            <span class="tm-users-list__login">@{{ userItem.login }}</span>
          </span>
          <span class="tm-users-list__rating">рейтинг {{ userItem.rating }}</span>
        </router-link>
      </div>
      <div v-if="userList.length === 0" class="tm-empty">
        {{ tab === 'followers' ? 'Подписчиков нет' : 'Ни на кого не подписан' }}
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import { Notify } from 'quasar';
import type { Author, Comment, Paginated, Publication, UserProfile } from '@/types/api';
import VoteArrows from '@/components/VoteArrows.vue';
import PublicationCard from '@/components/PublicationCard.vue';
import SubscribeButton from '@/components/SubscribeButton.vue';
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

const avatarColors = [
  'hsl(200, 34%, 50%)', 'hsl(140, 50%, 40%)', 'hsl(30, 96%, 45%)',
  'hsl(270, 50%, 50%)', 'hsl(350, 60%, 50%)', 'hsl(190, 60%, 40%)',
];

const avatarColor = computed(() => {
  return getAvatarColor(props.login);
});

const avatarColorMap: Record<string, string> = {};
function getAvatarColor(login: string): string {
  if (!(login in avatarColorMap)) {
    const hash = login.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    avatarColorMap[login] = avatarColors[hash % avatarColors.length]!;
  }
  return avatarColorMap[login]!;
}

const { publications, load } = usePublicationFeed(
  (page) => api.get<Paginated<Publication>>(`/users/${props.login}/publications`, {
    params: { page }
  }).then((r) => r.data)
);

async function loadTab(): Promise<void> {
  if (tab.value === 'publications') { await load(); return; }
  loading.value = true;
  try {
    const url = tab.value === 'comments'
      ? `/users/${props.login}/comments`
      : tab.value === 'followers'
        ? `/users/${props.login}/followers`
        : `/users/${props.login}/following`;
    const { data } = await api.get<Paginated<Comment & Author>>(url);
    const items = Array.isArray(data.data) ? data.data : [];
    if (tab.value === 'comments') { userComments.value = items; }
    else { userList.value = items; }
  } finally {
    loading.value = false;
  }
}

async function voteKarma(value: number): Promise<void> {
  if (!auth.isLoggedIn) { Notify.create({ type: 'warning', message: 'Войдите, чтобы голосовать' }); return; }
  const userId = profile.value?.id;
  if (userId === undefined) return;
  const { data } = await api.post<{ karma: number }>(`/users/${userId}/karma`, { value });
  if (profile.value !== null) profile.value.karma = data.karma;
  myKarmaVote.value = myKarmaVote.value === value ? null : value;
}

watch(tab, () => void loadTab());

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: UserProfile }>(`/users/${props.login}`);
    profile.value = data.data;
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    Notify.create({ type: 'negative', message: status === 404 ? 'Пользователь не найден' : 'Ошибка загрузки профиля' });
    return;
  }
  loading.value = false;
  await loadTab();
});
</script>
