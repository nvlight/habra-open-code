import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const apiMock = vi.hoisted(() => ({
  post: vi.fn(),
  get: vi.fn()
}));

vi.mock('@/boot/axios', () => ({
  api: apiMock
}));

import { useAuthStore } from './auth';

const authResponse = {
  data: {
    user: { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '100' },
    token: 'test-token'
  }
};

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('login stores token and user', async () => {
    apiMock.post.mockResolvedValueOnce(authResponse);
    const auth = useAuthStore();

    await auth.login('admin', 'password');

    expect(apiMock.post).toHaveBeenCalledWith('/auth/login', {
      login: 'admin',
      password: 'password'
    });
    expect(auth.token).toBe('test-token');
    expect(auth.user?.login).toBe('admin');
    expect(auth.isLoggedIn).toBe(true);
    expect(localStorage.getItem('token')).toBe('test-token');
  });

  it('register delegates to /auth/register', async () => {
    apiMock.post.mockResolvedValueOnce(authResponse);
    const auth = useAuthStore();

    await auth.register({
      name: 'Иван',
      login: 'ivan',
      email: 'i@t.dev',
      password: 'secret1234',
      password_confirmation: 'secret1234'
    });

    expect(apiMock.post).toHaveBeenCalledWith('/auth/register', expect.any(Object));
    expect(auth.isLoggedIn).toBe(true);
  });

  it('fetchMe loads current user when token exists', async () => {
    localStorage.setItem('token', 'existing');
    apiMock.get.mockResolvedValueOnce({
      data: { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '1' }
    });
    const auth = useAuthStore();

    await auth.fetchMe();

    expect(apiMock.get).toHaveBeenCalledWith('/me');
    expect(auth.user?.name).toBe('Админ');
    expect(auth.loaded).toBe(true);
  });

  it('fetchMe clears session on failure', async () => {
    localStorage.setItem('token', 'revoked');
    apiMock.get.mockRejectedValueOnce(new Error('401'));
    const auth = useAuthStore();

    await auth.fetchMe();

    expect(auth.user).toBeNull();
    expect(auth.token).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
    expect(auth.loaded).toBe(true);
  });

  it('fetchMe skips request without token', async () => {
    const auth = useAuthStore();

    await auth.fetchMe();

    expect(apiMock.get).not.toHaveBeenCalled();
    expect(auth.loaded).toBe(true);
  });

  it('logout revokes token server-side and clears session', async () => {
    apiMock.post.mockResolvedValueOnce(authResponse);
    const auth = useAuthStore();
    await auth.login('admin', 'password');

    apiMock.post.mockResolvedValueOnce({ data: {} });
    await auth.logout();

    expect(apiMock.post).toHaveBeenLastCalledWith('/auth/logout');
    expect(auth.token).toBeNull();
    expect(auth.isLoggedIn).toBe(false);
  });
});
