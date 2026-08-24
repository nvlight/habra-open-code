import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import VoteArrows from './VoteArrows.vue';

describe('VoteArrows', () => {
  it('renders positive rating with plus sign', () => {
    const wrapper = mount(VoteArrows, { props: { rating: 13, static: true } });

    expect(wrapper.text()).toContain('+13');
  });

  it('marks up arrow active when myVote is 1', () => {
    const wrapper = mount(VoteArrows, { props: { rating: 5, myVote: 1, static: true } });

    const up = wrapper.find('[data-testid="vote-up"]');
    expect(up.classes()).toContain('vote-arrow--active-up');
  });

  it('marks down arrow active when myVote is -1', () => {
    const wrapper = mount(VoteArrows, { props: { rating: 5, myVote: -1, static: true } });

    const down = wrapper.find('[data-testid="vote-down"]');
    expect(down.classes()).toContain('vote-arrow--active-down');
  });

  it('emits vote value on arrow click', async () => {
    const wrapper = mount(VoteArrows, { props: { rating: 0 } });

    await wrapper.find('[data-testid="vote-up"]').trigger('click');
    await wrapper.find('[data-testid="vote-down"]').trigger('click');

    expect(wrapper.emitted<{ value: number }>('vote')).toEqual([[1], [-1]]);
  });
});
