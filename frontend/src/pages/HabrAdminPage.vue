<template>
  <div>
    <div class="tm-section-name">
      <h1 class="tm-section-name__text">Админ: архив habr.com</h1>
    </div>

    <div v-if="!auth.user?.is_admin" class="tm-empty" data-testid="admin-forbidden">
      Доступ только для администратора
    </div>

    <template v-else>
      <div class="tm-admin__bar">
        <button
          class="tm-admin__btn tm-admin__btn--primary"
          data-testid="admin-discover-btn"
          :disabled="busy.discover"
          @click="runDiscover"
        >
          {{ busy.discover ? 'Запуск...' : 'Разовая выгрузка URL (sitemap)' }}
        </button>
        <button
          class="tm-admin__btn"
          data-testid="admin-fetch-btn"
          :disabled="busy.fetch"
          @click="runFetch"
        >
          {{ busy.fetch ? 'Запуск...' : 'Разовая загрузка контента' }}
        </button>
        <button
          class="tm-admin__btn tm-admin__btn--danger"
          data-testid="admin-stop-btn"
          :disabled="!isCrawling || busy.stop"
          @click="stopConfirmOpen = true"
        >
          {{ busy.stop ? 'Остановка...' : 'Остановить' }}
        </button>
        <span v-if="notice" class="tm-admin__notice" data-testid="admin-notice">{{ notice }}</span>
      </div>

      <div
        v-if="stats"
        class="tm-admin__status"
        :class="statusClass"
        data-testid="admin-status"
      >
        <span class="tm-admin__status-dot"></span>
        <span class="tm-admin__status-text">{{ statusText }}</span>
        <span v-if="stats.cancel_requested" class="tm-admin__status-sub">(останавливается…)</span>
        <span v-if="stats.queued_at" class="tm-admin__status-sub">
          сессия с {{ shortDateTime(stats.queued_at) }}
        </span>
      </div>

      <div v-if="stats" class="tm-admin__stats" data-testid="admin-stats">
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.total }}</span>
          <span class="tm-admin__stat-label">всего записей</span>
        </div>
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.published_since }}</span>
          <span class="tm-admin__stat-label">опубликовано с {{ stats.since }}</span>
        </div>
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.pending }}</span>
          <span class="tm-admin__stat-label">pending</span>
        </div>
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.fetched }}</span>
          <span class="tm-admin__stat-label">fetched</span>
        </div>
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.failed }}</span>
          <span class="tm-admin__stat-label">failed</span>
        </div>
        <div class="tm-admin__stat">
          <span class="tm-admin__stat-value">{{ stats.excluded }}</span>
          <span class="tm-admin__stat-label">excluded</span>
        </div>
      </div>

      <div class="tm-admin__table-wrap">
        <table class="tm-admin__table" data-testid="admin-url-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Тип</th>
              <th>Заголовок</th>
              <th>Статус</th>
              <th>Дата</th>
              <th>URL</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in urls" :key="item.source_id">
              <td class="tm-admin__mono">{{ item.source_id }}</td>
              <td>
                <span class="tm-badge">{{ item.type }}</span>
              </td>
              <td class="tm-admin__title">{{ item.title ?? '—' }}</td>
              <td>
                <span class="tm-admin__status tm-admin__status--{{ item.status }}" data-testid="admin-url-status">
                  {{ item.status }}
                </span>
              </td>
              <td class="tm-admin__mono">{{ item.published_at ? shortDate(item.published_at) : '—' }}</td>
              <td class="tm-admin__url">
                <a :href="item.url" target="_blank" rel="noopener noreferrer">{{ shortUrl(item.url) }}</a>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="urls.length === 0" class="tm-empty">Нет записей</div>
      </div>

      <div v-if="meta && meta.last_page > 1" class="tm-pagination">
        <button class="tm-pagination__btn" :disabled="page <= 1" @click="page--">&laquo;</button>
        <button
          v-for="p in visiblePages"
          :key="p"
          class="tm-pagination__btn"
          :class="{ 'tm-pagination__btn--active': p === page }"
          @click="page = p"
        >{{ p }}</button>
        <button class="tm-pagination__btn" :disabled="page >= meta.last_page" @click="page++">&raquo;</button>
      </div>

      <q-dialog v-model="stopConfirmOpen">
        <div class="tm-admin__dialog" data-testid="admin-stop-dialog">
          <p class="tm-admin__dialog-title">Остановить парсинг?</p>
          <p class="tm-admin__dialog-text">
            Уже загруженное сохранится. Незагруженное останется в списке и подхватится при следующем запуске.
          </p>
          <div class="tm-admin__dialog-actions">
            <button
              class="tm-admin__btn"
              data-testid="admin-stop-cancel"
              @click="stopConfirmOpen = false"
            >
              Отмена
            </button>
            <button
              class="tm-admin__btn tm-admin__btn--danger"
              data-testid="admin-stop-confirm"
              @click="runStop"
            >
              Остановить
            </button>
          </div>
        </div>
      </q-dialog>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/boot/axios';
import { useAuthStore } from '@/stores/auth';
import type { HabrStats, HabrUrl, Paginated } from '@/types/api';

const auth = useAuthStore();

const stats = ref<HabrStats | null>(null);
const urls = ref<HabrUrl[]>([]);
const meta = ref<Paginated<HabrUrl>['meta'] | null>(null);
const page = ref(1);
const notice = ref('');
const stopConfirmOpen = ref(false);
const busy = ref({ discover: false, fetch: false, stop: false });

const isCrawling = computed(() => stats.value?.is_crawling === true);
const cancelRequested = computed(() => stats.value?.cancel_requested === true);

const statusText = computed(() => {
  if (cancelRequested.value) return 'Останавливается…';
  return isCrawling.value ? 'Парсинг идёт' : 'Парсинг не идёт';
});

const statusClass = computed(() => {
  if (cancelRequested.value) return 'tm-admin__status--cancelling';
  return isCrawling.value ? 'tm-admin__status--running' : 'tm-admin__status--idle';
});

const visiblePages = computed(() => {
  if (!meta.value) return [];
  const total = meta.value.last_page;
  const current = page.value;
  const pages: number[] = [];
  for (let i = Math.max(1, current - 3); i <= Math.min(total, current + 3); i++) {
    pages.push(i);
  }
  return pages;
});

function shortDate(iso: string): string {
  return iso.slice(0, 10);
}

function shortDateTime(iso: string): string {
  return iso.slice(0, 16).replace('T', ' ');
}

function shortUrl(url: string): string {
  return url.replace('https://habr.com', '');
}

async function loadStats(): Promise<void> {
  const { data } = await api.get<HabrStats>('/admin/habr/stats');
  stats.value = data;
}

async function loadUrls(): Promise<void> {
  const { data } = await api.get<Paginated<HabrUrl>>('/admin/habr/urls', { params: { page: page.value } });
  urls.value = Array.isArray(data.data) ? data.data : [];
  meta.value = data.meta ?? null;
}

async function runDiscover(): Promise<void> {
  busy.value.discover = true;
  notice.value = '';
  try {
    await api.post('/admin/habr/discover');
    notice.value = 'Сбор URL поставлен в очередь';
    await loadStats();
  } catch {
    notice.value = 'Не удалось поставить задачу';
  } finally {
    busy.value.discover = false;
  }
}

async function runFetch(): Promise<void> {
  busy.value.fetch = true;
  notice.value = '';
  try {
    await api.post('/admin/habr/fetch');
    notice.value = 'Загрузка контента поставлена в очередь';
    await loadStats();
  } catch {
    notice.value = 'Не удалось поставить задачу';
  } finally {
    busy.value.fetch = false;
  }
}

async function runStop(): Promise<void> {
  stopConfirmOpen.value = false;
  busy.value.stop = true;
  notice.value = '';
  try {
    await api.post('/admin/habr/stop');
    notice.value = 'Парсинг остановлен';
    await loadStats();
  } catch {
    notice.value = 'Не удалось остановить парсинг';
  } finally {
    busy.value.stop = false;
  }
}

watch(page, () => void loadUrls());

onMounted(async () => {
  if (!auth.user?.is_admin) return;
  await Promise.all([loadStats(), loadUrls()]);
});
</script>

<style scoped>
.tm-admin__bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.tm-admin__btn {
  padding: 9px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--habr-text-main);
  border: 1px solid var(--habr-border);
  background: var(--habr-bg-card);
  cursor: pointer;
  transition: all 0.15s;
}

.tm-admin__btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.tm-admin__btn--primary {
  background: var(--habr-link);
  border-color: var(--habr-link);
  color: #fff;
}

.tm-admin__btn--primary:disabled {
  background: var(--habr-link);
}

.tm-admin__btn--danger {
  color: #fff;
  background: #d32f2f;
  border-color: #d32f2f;
}

.tm-admin__btn--danger:disabled {
  background: #d32f2f;
  border-color: #d32f2f;
}

.tm-admin__notice {
  font-size: 13px;
  color: var(--habr-text-secondary);
}

.tm-admin__status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  margin-bottom: 20px;
  border-radius: 8px;
  border: 1px solid var(--habr-border);
  background: var(--habr-bg-card);
  font-size: 14px;
}

.tm-admin__status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--habr-text-inactive);
}

.tm-admin__status--running .tm-admin__status-dot {
  background: var(--habr-link);
  animation: tm-admin-pulse 1.2s ease-in-out infinite;
}

.tm-admin__status--cancelling .tm-admin__status-dot {
  background: #f57c00;
}

.tm-admin__status-text {
  font-weight: 600;
  color: var(--habr-text-main);
}

.tm-admin__status-sub {
  font-size: 12px;
  color: var(--habr-text-secondary);
}

.tm-admin__dialog {
  background: var(--habr-bg-card);
  border: 1px solid var(--habr-border);
  border-radius: 8px;
  padding: 24px;
  max-width: 420px;
  width: 90vw;
}

.tm-admin__dialog-title {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 700;
  color: var(--habr-text-main);
}

.tm-admin__dialog-text {
  margin: 0 0 20px;
  font-size: 14px;
  color: var(--habr-text-secondary);
}

.tm-admin__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

@keyframes tm-admin-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}

.tm-admin__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.tm-admin__stat {
  background: var(--habr-bg-card);
  border: 1px solid var(--habr-border);
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tm-admin__stat-value {
  font-size: 26px;
  font-weight: 700;
  color: var(--habr-text-main);
  font-variant-numeric: tabular-nums;
}

.tm-admin__stat-label {
  font-size: 12px;
  color: var(--habr-text-secondary);
}

.tm-admin__table-wrap {
  background: var(--habr-bg-card);
  border: 1px solid var(--habr-border);
  border-radius: 8px;
  overflow-x: auto;
}

.tm-admin__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.tm-admin__table th {
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid var(--habr-border);
  color: var(--habr-text-secondary);
  font-weight: 600;
  white-space: nowrap;
}

.tm-admin__table td {
  padding: 8px 12px;
  border-bottom: 1px solid var(--habr-border);
  color: var(--habr-text-main);
  vertical-align: top;
}

.tm-admin__table tr:last-child td {
  border-bottom: none;
}

.tm-admin__title {
  max-width: 340px;
}

.tm-admin__url {
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--habr-link);
}

.tm-admin__url a {
  color: inherit;
  text-decoration: none;
}

.tm-admin__mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  white-space: nowrap;
}

.tm-admin__status {
  font-size: 12px;
}

.tm-admin__status--pending {
  color: var(--habr-text-secondary);
}

.tm-admin__status--fetched {
  color: var(--habr-link);
}

.tm-admin__status--failed {
  color: var(--habr-danger, #d32f2f);
}

.tm-admin__status--excluded {
  color: var(--habr-text-inactive);
}
</style>