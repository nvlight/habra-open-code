import { ref, watch, type Ref } from 'vue';
import { api } from '@/boot/axios';
import type { Paginated, Publication } from '@/types/api';

export function usePublicationFeed(
  fetcher: (page: number) => Promise<Paginated<Publication>>,
  deps: ReadonlyArray<Ref<unknown>> = []
) {
  const publications = ref<Publication[]>([]);
  const meta = ref<Paginated<Publication>['meta'] | null>(null);
  const loading = ref(false);
  const page = ref(1);

  async function load(): Promise<void> {
    loading.value = true;

    try {
      const data = await fetcher(page.value);
      publications.value = Array.isArray(data.data) ? data.data : [];
      meta.value = data.meta ?? null;
    } finally {
      loading.value = false;
    }
  }

  if (deps.length > 0) {
    watch(deps, () => {
      page.value = 1;
      void load();
    });
  }

  watch(page, () => {
    void load();
  });

  return { publications, meta, loading, page, load };
}
