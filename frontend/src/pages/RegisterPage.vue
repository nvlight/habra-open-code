<template>
  <q-card flat class="habr-card" style="max-width: 420px; margin: 0 auto">
    <q-card-section class="q-pb-none">
      <div class="text-h6 text-weight-medium">Регистрация</div>
    </q-card-section>

    <q-card-section>
      <q-form class="q-gutter-y-sm" @submit.prevent="submit">
        <q-input
          v-model="form.name"
          outlined
          dense
          label="Имя"
          :error="errors.name !== undefined"
          :error-message="errors.name"
        />
        <q-input
          v-model="form.login"
          outlined
          dense
          label="Логин"
          :error="errors.login !== undefined"
          :error-message="errors.login"
        />
        <q-input
          v-model="form.email"
          outlined
          dense
          type="email"
          label="Email"
          :error="errors.email !== undefined"
          :error-message="errors.email"
        />
        <q-input
          v-model="form.password"
          outlined
          dense
          type="password"
          label="Пароль (мин. 8 символов)"
          :error="errors.password !== undefined"
          :error-message="errors.password"
        />
        <q-input
          v-model="form.password_confirmation"
          outlined
          dense
          type="password"
          label="Повторите пароль"
          :error="errors.password_confirmation !== undefined"
          :error-message="errors.password_confirmation"
        />

        <div v-if="formError" class="text-negative text-caption">{{ formError }}</div>

        <q-btn
          unelevated
          color="primary"
          no-caps
          label="Создать аккаунт"
          type="submit"
          class="full-width q-mt-sm"
          :loading="loading"
          data-testid="register-submit"
        />
      </q-form>
    </q-card-section>

    <q-card-section class="text-caption text-center">
      Уже есть аккаунт?
      <router-link to="/login" style="color: #159be0">Войдите</router-link>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { AxiosError } from 'axios';
import { useAuthStore, extractValidationErrors } from '@/stores/auth';
import type { ValidationErrorResponse } from '@/types/api';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({
  name: '',
  login: '',
  email: '',
  password: '',
  password_confirmation: ''
});
const errors = ref<Record<string, string>>({});
const formError = ref('');
const loading = ref(false);

async function submit(): Promise<void> {
  loading.value = true;
  errors.value = {};
  formError.value = '';

  try {
    await auth.register({ ...form });
    await router.push('/');
  } catch (error) {
    const axiosError = error as AxiosError<ValidationErrorResponse>;
    errors.value = extractValidationErrors(axiosError);
    formError.value = Object.values(errors.value)[0] ?? axiosError.response?.data?.message ?? 'Ошибка регистрации';
  } finally {
    loading.value = false;
  }
}
</script>
