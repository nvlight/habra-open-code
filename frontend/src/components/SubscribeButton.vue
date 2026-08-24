<template>
  <q-btn
    :outline="!subscribed"
    unelevated
    no-caps
    dense
    :loading="busy"
    :color="subscribed ? 'grey-7' : 'primary'"
    :label="buttonLabel"
    data-testid="subscribe"
    @click="onToggle"
  />
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Notify } from 'quasar';
import { useAuthStore } from '@/stores/auth';
import { useSubscriptionsStore, type SubscribableType } from '@/stores/subscriptions';

const props = withDefaults(
  defineProps<{
    type: SubscribableType;
    keyValue: string | number;
    label?: string;
  }>(),
  { label: '' }
);

const auth = useAuthStore();
const subscriptions = useSubscriptionsStore();
const busy = ref(false);

const subscribed = computed(() => subscriptions.isSubscribed(props.type, props.keyValue));

const fallbackLabels: Record<SubscribableType, string> = {
  user: 'Подписаться',
  hub: 'Подписаться на хаб',
  company: 'Подписаться на компанию'
};

const buttonLabel = computed(() => {
  if (props.label !== '') {
    return props.label;
  }
  return subscribed.value ? 'Отписаться' : fallbackLabels[props.type];
});

async function onToggle(): Promise<void> {
  if (!auth.isLoggedIn) {
    Notify.create({ type: 'warning', message: 'Войдите, чтобы подписываться' });
    return;
  }

  busy.value = true;

  try {
    const nowSubscribed = await subscriptions.toggle(props.type, props.keyValue);
    Notify.create({ type: 'positive', message: nowSubscribed ? 'Подписка оформлена' : 'Вы отписались' });
  } finally {
    busy.value = false;
  }
}

onMounted(() => {
  if (auth.isLoggedIn && !subscriptions.loaded) {
    void subscriptions.fetch();
  }
});
</script>
