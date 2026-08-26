<template>
  <div class="tm-layout">
    <!-- Header -->
    <header class="tm-header">
      <div class="tm-page-width">
        <div class="tm-header__container">
          <router-link to="/" class="tm-header__logo" data-testid="logo">
            <span class="tm-header__logo-icon">X</span>
            <span class="tm-header__logo-text">Хабр</span>
          </router-link>

          <div class="tm-header__divider" />

          <nav class="tm-header__nav">
            <router-link
              to="/articles"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'articles' }"
            >Статьи</router-link>
            <router-link
              to="/posts"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'posts' }"
            >Посты</router-link>
            <router-link
              to="/news"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'news' }"
            >Новости</router-link>
            <router-link
              to="/hubs"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'hubs' }"
            >Хабы</router-link>
            <router-link
              to="/companies"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'companies' }"
            >Компании</router-link>
            <router-link
              to="/users"
              class="tm-header__nav-link"
              :class="{ 'tm-header__nav-link--active': activeTab === 'users' }"
            >Авторы</router-link>
          </nav>

          <div class="tm-header__actions">
            <router-link
              v-if="auth.isLoggedIn"
              to="/editor"
              class="tm-header__write-btn"
              data-testid="write-btn"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                <path d="m15 5 4 4"/>
              </svg>
              Написать
            </router-link>

            <router-link
              v-if="auth.isLoggedIn"
              to="/bookmarks"
              class="tm-header__action-btn"
              data-testid="bookmarks-link"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
              </svg>
            </router-link>

            <button
              class="tm-header__action-btn"
              data-testid="theme-toggle"
              @click="cycleTheme"
            >
              <svg v-if="getThemePref() === 'light'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
              </svg>
              <svg v-else-if="getThemePref() === 'dark'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
              </svg>
              <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                <path d="M12 3v1"/>
              </svg>
            </button>

            <template v-if="auth.isLoggedIn">
              <router-link
                :to="`/users/${auth.user?.login}`"
                class="tm-header__user"
                data-testid="user-profile"
              >
                <span class="tm-header__user-name">{{ auth.user?.name }}</span>
                <span class="tm-header__user-avatar">{{ initial }}</span>
              </router-link>
              <button class="tm-header__action-btn" data-testid="logout" @click="onLogout">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                </svg>
              </button>
            </template>

            <template v-else>
              <router-link to="/login" class="tm-header__login-btn" data-testid="login-link">Войти</router-link>
              <router-link to="/register" class="tm-header__login-btn" data-testid="register-link">Регистрация</router-link>
            </template>
          </div>
        </div>
      </div>
    </header>

    <!-- Page content -->
    <div class="tm-page-width">
      <div class="tm-page__wrapper">
        <main class="tm-page__main">
          <router-view />
        </main>
        <aside class="tm-page__sidebar">
          <AppSidebar />
        </aside>
      </div>
    </div>

    <!-- Footer -->
    <footer class="tm-footer">
      <div class="tm-page-width">
        <div class="tm-footer__grid">
          <div>
            <p class="tm-footer__block-title">Ваш аккаунт</p>
            <ul class="tm-footer__list">
              <li class="tm-footer__list-item">
                <router-link to="/login" class="tm-footer__list-link">Войти</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/register" class="tm-footer__list-link">Регистрация</router-link>
              </li>
            </ul>
          </div>
          <div>
            <p class="tm-footer__block-title">Разделы</p>
            <ul class="tm-footer__list">
              <li class="tm-footer__list-item">
                <router-link to="/articles" class="tm-footer__list-link">Статьи</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/posts" class="tm-footer__list-link">Посты</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/news" class="tm-footer__list-link">Новости</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/hubs" class="tm-footer__list-link">Хабы</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/companies" class="tm-footer__list-link">Компании</router-link>
              </li>
              <li class="tm-footer__list-item">
                <router-link to="/users" class="tm-footer__list-link">Авторы</router-link>
              </li>
            </ul>
          </div>
          <div>
            <p class="tm-footer__block-title">Информация</p>
            <ul class="tm-footer__list">
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">О проекте</span>
              </li>
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">Для авторов</span>
              </li>
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">Правила</span>
              </li>
            </ul>
          </div>
          <div>
            <p class="tm-footer__block-title">Услуги</p>
            <ul class="tm-footer__list">
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">Реклама</span>
              </li>
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">Контент-маркетинг</span>
              </li>
              <li class="tm-footer__list-item">
                <span class="tm-footer__list-link">Техническая поддержка</span>
              </li>
            </ul>
          </div>
        </div>
        <div class="tm-footer__bottom">
          © 2026 Habra Open Code — некоммерческий клон habr.com
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getThemePref, setThemePref, type ThemePref } from '@/boot/theme';
import AppSidebar from '@/components/AppSidebar.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const initial = computed(() => auth.user?.name?.charAt(0).toUpperCase() ?? '?');

const activeTab = computed(() => {
  const path = route.path;
  if (path.startsWith('/articles')) return 'articles';
  if (path.startsWith('/posts')) return 'posts';
  if (path.startsWith('/news')) return 'news';
  if (path.startsWith('/hubs')) return 'hubs';
  if (path.startsWith('/companies')) return 'companies';
  if (path.startsWith('/users')) return 'users';
  return '';
});

const nextTheme: Record<ThemePref, ThemePref> = {
  auto: 'light',
  light: 'dark',
  dark: 'auto'
};

function cycleTheme(): void {
  setThemePref(nextTheme[getThemePref()]);
}

onMounted(() => {
  void auth.fetchMe();
});

async function onLogout(): Promise<void> {
  await auth.logout();
  await router.push('/');
}
</script>
