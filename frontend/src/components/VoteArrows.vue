<template>
  <div class="row items-center q-gutter-xs" :class="vertical ? 'column' : ''">
    <span
      class="vote-arrow"
      :class="arrowClass(1)"
      data-testid="vote-up"
      @click="emit('vote', 1)"
    >▲</span>
    <span class="text-weight-bold text-subtitle2">{{ displayRating }}</span>
    <span
      class="vote-arrow"
      :class="arrowClass(-1)"
      data-testid="vote-down"
      @click="emit('vote', -1)"
    >▼</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    rating: number;
    myVote?: number | null;
    static?: boolean;
    vertical?: boolean;
  }>(),
  { myVote: null, static: false, vertical: true }
);

const emit = defineEmits<{ vote: [value: number] }>();

const displayRating = computed(() => (props.rating > 0 ? `+${props.rating}` : String(props.rating)));

function arrowClass(value: number): string {
  if (props.myVote === value) {
    return value === 1 ? 'vote-arrow--active-up' : 'vote-arrow--active-down';
  }

  return props.static === true ? 'vote-arrow--static' : '';
}
</script>
