<template>
  <div class="tm-votes-meter" :class="{ 'tm-votes-meter--vertical': vertical }">
    <span
      class="tm-votes-meter__btn"
      :class="upClass"
      data-testid="vote-up"
      @click="emit('vote', 1)"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 15l-6-6-6 6"/>
      </svg>
    </span>
    <span class="tm-votes-meter__value">{{ displayRating }}</span>
    <span
      class="tm-votes-meter__btn"
      :class="downClass"
      data-testid="vote-down"
      @click="emit('vote', -1)"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </span>
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
  { myVote: null, static: false, vertical: false }
);

const emit = defineEmits<{ vote: [value: number] }>();

const displayRating = computed(() => {
  if (props.rating > 0) return `+${props.rating}`;
  return String(props.rating);
});

const upClass = computed(() => {
  const classes: string[] = [];
  if (props.myVote === 1) classes.push('tm-votes-meter__btn--active-up');
  if (props.static) classes.push('tm-votes-meter__btn--static');
  return classes;
});

const downClass = computed(() => {
  const classes: string[] = [];
  if (props.myVote === -1) classes.push('tm-votes-meter__btn--active-down');
  if (props.static) classes.push('tm-votes-meter__btn--static');
  return classes;
});
</script>

<style scoped>
.tm-votes-meter--vertical {
  flex-direction: column;
  gap: 2px;
}
</style>
