<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="habr-header" style="border-bottom: 1px solid rgba(0, 0, 0, 0.2)">
      <div class="habr-container row items-center q-px-md" style="height: 48px">
        <router-link to="/" class="row items-center q-mr-lg" data-testid="logo">
          <span class="logo-mark">Х</span>
          <span class="logo-text text-weight-bolder">Хабр</span>
        </router-link>

        <nav class="row items-center q-gutter-x-sm">
          <q-btn flat no-caps dense label="Лента" to="/" :class="$route.path === '/' ? 'nav-link nav-link--active' : 'nav-link'" />
          <q-btn flat no-caps dense label="Хабы" to="/hubs" :class="$route.path.startsWith('/hubs') ? 'nav-link nav-link--active' : 'nav-link'" />
          <q-btn flat no-caps dense label="Компании" to="/companies" :class="$route.path.startsWith('/companies') ? 'nav-link nav-link--active' : 'nav-link'" />
          <q-btn flat no-caps dense label="Авторы" to="/users" :class="$route.path.startsWith('/users') ? 'nav-link nav-link--active' : 'nav-link'" />
          <q-btn flat no-caps dense label="Закладки" to="/bookmarks" icon="bookmark_border" :class="$route.path === '/bookmarks' ? 'nav-link nav-link--active' : 'nav-link'" />
        </nav>

        <q-space />

        <q-btn
          flat dense round
          :icon="themeIcon"
          color="white"
          class="q-mr-sm"
          data-testid="theme-toggle"
          @click="cycleTheme"
        >
          <q-tooltip>{{ themeLabel }}</q-tooltip>
        </q-btn>

        <template v-if="auth.isLoggedIn">
          <q-btn
            unelevated no-caps dense color="primary"
            label="Написать" to="/editor" icon="edit"
            class="q-mr-md"
          />
          <span class="header-user cursor-pointer q-mr-sm" @click="router.push(`/users/${auth.user?.login}`)">
            {{ auth.user?.name }}
          </span>
          <q-avatar size="32px" color="accent" text-color="white" class="cursor-pointer">
            {{ initial }}
            <q-menu>
              <q-list style="min-width: 140px">
                <q-item clickable v-close-popup @click="onLogout" data-testid="logout">
                  <q-item-section>Выйти</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-avatar>
        </template>

        <template v-else>
          <q-btn flat no-caps dense label="Войти" to="/login" class="nav-link" data-testid="login-link" />
          <q-btn outline no-caps dense unelevated label="Регистрация" to="/register" class="register-btn" data-testid="register-link" />
        </template>
      </div>
    </q-header>

    <q-page-container>
      <div class="habr-container q-py-lg q-px-md">
        <router-view />
      </div>
    </q-page-container>

    <q-footer class="habr-footer text-caption q-pa-md">
      <div class="text-center">© 2026 Habra Open Code — некоммерческий клон habr.com</div>
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getThemePref, setThemePref, type ThemePref } from '@/boot/theme';

const auth = useAuthStore();
const router = useRouter();

const initial = computed(() => auth.user?.name?.charAt(0).toUpperCase() ?? '?');

const themeIcons: Record<ThemePref, string> = {
  auto: 'brightness_auto',
  light: 'light_mode',
  dark: 'dark_mode'
};

const themeLabels: Record<ThemePref, string> = {
  auto: 'Тема: как в системе',
  light: 'Тема: светлая',
  dark: 'Тема: тёмная'
};

const nextTheme: Record<ThemePref, ThemePref> = {
  auto: 'light',
  light: 'dark',
  dark: 'auto'
};

const themeIcon = computed(() => themeIcons[getThemePref()]);
const themeLabel = computed(() => themeLabels[getThemePref()]);

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

<style lang="scss">
.habr-header {
  background: var(--habr-header-bg);
  color: var(--habr-header-text);

  .logo-mark {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--habr-accent-orange);
    color: #fff;
    font-family: 'Inter', sans-serif;
    font-weight: 700;
    font-size: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
  }

  .logo-text {
    font-size: 20px;
    color: var(--habr-header-text);
    letter-spacing: 0.3px;
  }

  .nav-link {
    color: var(--habr-header-muted);

    &:hover,
    &--active {
      color: var(--habr-header-text);
    }
  }

  .register-btn {
    color: var(--habr-header-text);
    border-color: var(--habr-header-muted);
  }

  .header-user:hover {
    color: #fff;
  }
}

.habr-footer {
  background: var(--habr-header-bg-secondary);
  color: var(--habr-header-muted);
}
</style>
