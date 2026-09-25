import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

const apiMock = vi.hoisted(() => ({
  post: vi.fn(),
  get: vi.fn()
}));

vi.mock('@/boot/axios', () => ({
  api: apiMock
}));

import HabrAdminPage from './HabrAdminPage.vue';
import { useAuthStore } from '@/stores/auth';
import type { HabrStats, Paginated, HabrUrl } from '@/types/api';

const DialogStub = {
  props: ['modelValue'],
  template: '<div v-if="modelValue" data-testid="admin-stop-dialog"><slot /></div>'
};

function mountPage(): ReturnType<typeof mount> {
  return mount(HabrAdminPage, { global: { stubs: { 'q-dialog': DialogStub } } });
}

const stats: HabrStats = {
  total: 57462,
  since: '2026-01-01',
  published_since: 44656,
  pending: 1,
  fetched: 57461,
  failed: 0,
  excluded: 0,
  by_status: {},
  by_type: {},
  is_crawling: false,
  cancel_requested: false,
  queued_at: '2026-09-24T03:00:00+00:00',
  last_activity: null
};

const emptyUrls: Paginated<HabrUrl> = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 }
};

describe('HabrAdminPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('shows stats and dispatches jobs for admin user', async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '1', is_admin: true };

    apiMock.get
      .mockResolvedValueOnce({ data: stats })
      .mockResolvedValueOnce({ data: emptyUrls })
      .mockResolvedValue({ data: stats });

    const wrapper = mountPage();
    await flushPromises();

    expect(apiMock.get).toHaveBeenCalledWith('/admin/habr/stats');
    expect(apiMock.get).toHaveBeenCalledWith('/admin/habr/urls', expect.any(Object));
    expect(wrapper.find('[data-testid="admin-stats"]').text()).toContain('57462');

    await wrapper.find('[data-testid="admin-discover-btn"]').trigger('click');
    await flushPromises();
    expect(apiMock.post).toHaveBeenCalledWith('/admin/habr/discover');
    expect(wrapper.find('[data-testid="admin-notice"]').text()).toContain('очередь');

    await wrapper.find('[data-testid="admin-fetch-btn"]').trigger('click');
    await flushPromises();
    expect(apiMock.post).toHaveBeenCalledWith('/admin/habr/fetch');
  });

  it('renders forbidden state for non-admin user', async () => {
    const auth = useAuthStore();
    auth.user = { id: 2, login: 'user', name: 'Пользователь', avatar: null, rating: '1', is_admin: false };

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('[data-testid="admin-forbidden"]').exists()).toBe(true);
    expect(apiMock.get).not.toHaveBeenCalled();
  });

  it('shows crawling status and enables stop button when crawling', async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '1', is_admin: true };

    apiMock.get
      .mockResolvedValueOnce({ data: { ...stats, is_crawling: true } })
      .mockResolvedValueOnce({ data: emptyUrls })
      .mockResolvedValue({ data: stats });

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('[data-testid="admin-status"]').text()).toContain('Парсинг идёт');
    expect(wrapper.find('[data-testid="admin-stop-btn"]').attributes('disabled')).toBeUndefined();
  });

  it('disables stop button and shows idle status when not crawling', async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '1', is_admin: true };

    apiMock.get
      .mockResolvedValueOnce({ data: { ...stats, is_crawling: false } })
      .mockResolvedValueOnce({ data: emptyUrls });

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('[data-testid="admin-status"]').text()).toContain('Парсинг не идёт');
    expect(wrapper.find('[data-testid="admin-stop-btn"]').attributes('disabled')).toBeDefined();
  });

  it('confirms stopping before posting to the stop endpoint', async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, login: 'admin', name: 'Админ', avatar: null, rating: '1', is_admin: true };

    apiMock.get
      .mockResolvedValueOnce({ data: { ...stats, is_crawling: true } })
      .mockResolvedValueOnce({ data: emptyUrls })
      .mockResolvedValue({ data: stats });

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('[data-testid="admin-stop-dialog"]').exists()).toBe(false);

    await wrapper.find('[data-testid="admin-stop-btn"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="admin-stop-dialog"]').exists()).toBe(true);
    expect(apiMock.post).not.toHaveBeenCalled();

    await wrapper.find('[data-testid="admin-stop-confirm"]').trigger('click');
    await flushPromises();
    expect(apiMock.post).toHaveBeenCalledWith('/admin/habr/stop');
    expect(wrapper.find('[data-testid="admin-stop-dialog"]').exists()).toBe(false);
  });
});