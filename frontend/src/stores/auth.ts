import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '@/boot/axios';
import type { AuthResponse, User, ValidationErrorResponse } from '@/types/api';
import type { AxiosError } from 'axios';

const TOKEN_KEY = 'token';

function readToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const token = ref<string | null>(readToken());
  const loaded = ref(false);

  const isLoggedIn = computed(() => token.value !== null);

  function setSession(newToken: string, newUser: User): void {
    token.value = newToken;
    user.value = newUser;
    localStorage.setItem(TOKEN_KEY, newToken);
  }

  function clearSession(): void {
    token.value = null;
    user.value = null;
    localStorage.removeItem(TOKEN_KEY);
  }

  async function login(loginName: string, password: string): Promise<void> {
    const { data } = await api.post<AuthResponse>('/auth/login', {
      login: loginName,
      password
    });
    setSession(data.token, data.user);
  }

  async function register(payload: {
    name: string;
    login: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<void> {
    const { data } = await api.post<AuthResponse>('/auth/register', payload);
    setSession(data.token, data.user);
  }

  async function fetchMe(): Promise<void> {
    if (token.value === null) {
      loaded.value = true;
      return;
    }

    try {
      const { data } = await api.get<User>('/me');
      user.value = data;
    } catch {
      clearSession();
    } finally {
      loaded.value = true;
    }
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout');
    } catch {
      // token may already be revoked; local cleanup still applies
    }
    clearSession();
  }

  return {
    user,
    token,
    loaded,
    isLoggedIn,
    login,
    register,
    fetchMe,
    logout,
    clearSession
  };
});

export function extractValidationErrors(
  error: AxiosError<ValidationErrorResponse>
): Record<string, string> {
  const errors = error.response?.data?.errors ?? {};
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? ''])
  ) as Record<string, string>;
}
