import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  delete: vi.fn()
}));

vi.mock('@/boot/axios', () => ({
  api: apiMock
}));

import { useSubscriptionsStore } from './subscriptions';

describe('subscriptions store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetch maps natural keys from grouped response', async () => {
    apiMock.get.mockResolvedValueOnce({
      data: {
        users: [{ login: 'SLY_G', rating: '1' }],
        hubs: [{ alias: 'python' }],
        companies: [{ slug: 'timeweb' }]
      }
    });

    const store = useSubscriptionsStore();
    await store.fetch();

    expect(store.users).toEqual(['SLY_G']);
    expect(store.hubs).toEqual(['python']);
    expect(store.companies).toEqual(['timeweb']);
    expect(store.loaded).toBe(true);
    expect(store.total).toBe(3);
  });

  it('isSubscribed checks by type and key', async () => {
    apiMock.get.mockResolvedValueOnce({
      data: { users: [], hubs: [{ alias: 'python' }], companies: [] }
    });

    const store = useSubscriptionsStore();
    await store.fetch();

    expect(store.isSubscribed('hub', 'python')).toBe(true);
    expect(store.isSubscribed('hub', 'php')).toBe(false);
    expect(store.isSubscribed('user', 'SLY_G')).toBe(false);
  });

  it('toggle subscribes when not subscribed', async () => {
    apiMock.get.mockResolvedValueOnce({ data: { users: [], hubs: [], companies: [] } });
    apiMock.post.mockResolvedValueOnce({ data: {} });

    const store = useSubscriptionsStore();
    await store.fetch();

    const result = await store.toggle('hub', 'python');

    expect(apiMock.post).toHaveBeenCalledWith('/subscriptions/hub/python');
    expect(result).toBe(true);
    expect(store.isSubscribed('hub', 'python')).toBe(true);
  });

  it('toggle unsubscribes when already subscribed', async () => {
    apiMock.get.mockResolvedValueOnce({
      data: { users: [], hubs: [{ alias: 'python' }], companies: [] }
    });
    apiMock.delete.mockResolvedValueOnce({ data: {} });

    const store = useSubscriptionsStore();
    await store.fetch();

    const result = await store.toggle('company', 'timeweb');
    void result;

    apiMock.delete.mockClear();

    const unsubscribed = await store.toggle('hub', 'python');

    expect(apiMock.delete).toHaveBeenCalledWith('/subscriptions/hub/python');
    expect(unsubscribed).toBe(false);
    expect(store.isSubscribed('hub', 'python')).toBe(false);
  });
});
