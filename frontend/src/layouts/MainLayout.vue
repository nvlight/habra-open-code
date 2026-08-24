<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="bg-white text-dark" style="border-bottom: 1px solid #e3e7e8">
      <div class="habr-container row items-center q-px-md" style="height: 48px">
        <router-link to="/" class="text-weight-bolder text-h6 q-mr-lg" style="color: #e8591c">
          Хабр
        </router-link>

        <nav class="row items-center q-gutter-x-sm">
          <q-btn flat no-caps dense label="Лента" to="/" :color="$route.path === '/' ? 'primary' : 'dark'" />
          <q-btn flat no-caps dense disable label="Хабы" class="text-grey-6" />
          <q-btn flat no-caps dense disable label="Компании" class="text-grey-6" />
          <q-btn flat no-caps dense disable label="Пользователи" class="text-grey-6" />
        </nav>

        <q-space />

        <template v-if="auth.isLoggedIn">
          <span class="text-caption q-mr-md">{{ auth.user?.name }}</span>
          <q-avatar size="32px" color="primary" text-color="white" class="cursor-pointer">
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
          <q-btn flat no-caps dense label="Войти" to="/login" data-testid="login-link" />
          <q-btn outline no-caps dense unelevated label="Регистрация" to="/register"
            style="color: #e8591c" data-testid="register-link" />
        </template>
      </div>
    </q-header>

    <q-page-container>
      <div class="habr-container q-py-lg q-px-md">
        <router-view />
      </div>
    </q-page-container>

    <q-footer class="bg-transparent text-grey text-caption q-pa-md">
      <div class="text-center">© 2026 Habra Open Code — некоммерческий клон habr.com</div>
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const initial = computed(() => auth.user?.name?.charAt(0).toUpperCase() ?? '?');

onMounted(() => {
  void auth.fetchMe();
});

async function onLogout(): Promise<void> {
  await auth.logout();
  await router.push('/');
}
</script>
