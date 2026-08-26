<template>
  <div class="tm-auth">
    <h2 class="tm-auth__title">Вход</h2>

    <q-form class="q-gutter-y-sm" @submit.prevent="submit">
      <q-input v-model="form.login" outlined dense label="Логин или email" data-testid="login-field" />
      <q-input v-model="form.password" outlined dense type="password" label="Пароль" data-testid="password-field" />

      <div v-if="formError" class="text-negative text-caption">{{ formError }}</div>

      <q-btn unelevated color="primary" no-caps label="Войти" type="submit" class="full-width q-mt-sm" :loading="loading" data-testid="login-submit" />
    </q-form>

    <div class="tm-auth__footer">
      Нет аккаунта?
      <router-link to="/register" class="text-link">Зарегистрируйтесь</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Notify } from 'quasar';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const form = reactive({ login: '', password: '' });
const formError = ref('');
const loading = ref(false);

async function submit(): Promise<void> {
  loading.value = true;
  formError.value = '';
  try {
    await auth.login(form.login, form.password);
    Notify.create({ type: 'positive', message: `С возвращением, ${auth.user?.name}!` });
    await router.push('/');
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    formError.value = status === 401 ? 'Неверный логин или пароль' : 'Ошибка входа, попробуйте позже';
  } finally {
    loading.value = false;
  }
}
</script>
