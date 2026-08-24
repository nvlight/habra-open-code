import { defineStore } from 'pinia';
import { computed, ref, type Ref } from 'vue';
import { api } from '@/boot/axios';
import type { SubscriptionGroups } from '@/types/api';

export type SubscribableType = 'user' | 'hub' | 'company';

export const useSubscriptionsStore = defineStore('subscriptions', () => {
  const users = ref<string[]>([]);
  const hubs = ref<string[]>([]);
  const companies = ref<string[]>([]);
  const loaded = ref(false);

  function isSubscribed(type: SubscribableType, key: string | number): boolean {
    return getKeys(type).value.includes(String(key));
  }

  function getKeys(type: SubscribableType): Ref<string[]> {
    if (type === 'user') return users;
    if (type === 'hub') return hubs;
    return companies;
  }

  async function fetch(): Promise<void> {
    const { data } = await api.get<SubscriptionGroups>('/subscriptions');

    users.value = (data.users ?? []).map((u) => u.login);
    hubs.value = (data.hubs ?? []).map((h) => h.alias);
    companies.value = (data.companies ?? []).map((c) => c.slug);
    loaded.value = true;
  }

  async function toggle(type: SubscribableType, key: string | number): Promise<boolean> {
    const keys = getKeys(type);
    const keyString = String(key);
    const wasSubscribed = keys.value.includes(keyString);
    const endpoint = `/subscriptions/${type}/${keyString}`;

    await api[wasSubscribed ? 'delete' : 'post'](endpoint);

    keys.value = wasSubscribed
      ? keys.value.filter((k) => k !== keyString)
      : [...keys.value, keyString];

    return !wasSubscribed;
  }

  const total = computed(() => users.value.length + hubs.value.length + companies.value.length);

  return { users, hubs, companies, loaded, total, isSubscribed, toggle, fetch };
});
