<template>
  <div class="tm-editor">
    <h2 class="tm-editor__title">{{ isEdit ? 'Редактирование публикации' : 'Новая публикация' }}</h2>

    <q-form class="q-gutter-y-md" @submit.prevent="submit">
      <q-input
        v-model="form.title"
        outlined
        label="Заголовок *"
        :error="errors.title !== undefined"
        :error-message="errors.title"
        data-testid="editor-title"
      />

      <q-input
        v-model="form.lead"
        outlined
        type="textarea"
        autogrow
        label="Краткое описание"
        :error="errors.lead !== undefined"
        :error-message="errors.lead"
      />

      <div>
        <div class="text-caption text-dim q-mb-xs">Текст публикации (markdown/html) *</div>
        <q-input
          v-model="form.body"
          outlined
          type="textarea"
          filled
          :input-style="{ minHeight: '220px', fontFamily: 'monospace' }"
          :error="errors.body !== undefined"
          :error-message="errors.body"
          data-testid="editor-body"
        />
      </div>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-sm-4">
          <q-select v-model="form.type" :options="typeOptions" emit-value map-options outlined dense label="Тип *" />
        </div>
        <div class="col-12 col-sm-4">
          <q-select v-model="form.difficulty" :options="difficultyOptions" emit-value map-options outlined dense label="Сложность" clearable />
        </div>
        <div class="col-12 col-sm-4">
          <q-select v-model="form.label" :options="labelOptions" emit-value map-options outlined dense label="Метка" clearable />
        </div>
      </div>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-sm-6">
          <q-select
            v-model="form.hubs"
            :options="hubOptions"
            emit-value
            map-options
            use-chips
            multiple
            outlined
            dense
            label="Хабы (до 5)"
            :error="errors.hubs !== undefined"
            :error-message="errors.hubs"
            data-testid="editor-hubs"
          />
        </div>
        <div class="col-12 col-sm-6">
          <q-select
            v-model="tagList"
            :options="tagSuggestions"
            use-input
            use-chips
            hide-selected
            fill-input
            input-debounce="0"
            new-value-mode="add-unique"
            outlined
            dense
            label="Теги (Enter для добавления, до 10)"
            @new-value="addTag"
            @filter="filterTags"
            data-testid="editor-tags"
          />
        </div>
      </div>

      <div v-if="!isEdit" class="row q-gutter-x-sm items-center">
        <span class="text-caption text-dim">Статус:</span>
        <q-btn-toggle
          v-model="form.status"
          :options="[{ label: 'Черновик', value: 'draft' }, { label: 'Песочница', value: 'sandbox' }]"
          unelevated no-caps dense flat toggle-color="primary"
        />
      </div>

      <div v-if="isEdit && publication" class="row items-center q-gutter-x-sm">
        <span class="text-caption text-dim">Статус:</span>
        <q-badge :color="statusColor" :label="publication.status" />
        <q-btn v-if="publication.status !== 'published'" flat no-caps dense size="sm" color="positive" label="Опубликовать" data-testid="publish-button" @click="publish" />
        <q-btn flat no-caps dense size="sm" color="negative" label="Удалить" data-testid="delete-button" @click="remove" />
        <router-link v-if="publication.status === 'published'" :to="`/publications/${publication.id}`" class="text-link text-caption">Открыть страницу →</router-link>
      </div>

      <div v-if="formError" class="text-negative text-caption">{{ formError }}</div>

      <div class="row q-gutter-x-sm">
        <q-btn unelevated color="primary" no-caps :label="isEdit ? 'Сохранить' : 'Создать'" type="submit" :loading="saving" data-testid="editor-submit" />
        <q-btn flat no-caps label="Отмена" @click="router.back()" />
      </div>
    </q-form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { AxiosError } from 'axios';
import { Notify } from 'quasar';
import { api } from '@/boot/axios';
import { extractValidationErrors, useAuthStore } from '@/stores/auth';
import type { Hub, Paginated, Publication, ValidationErrorResponse } from '@/types/api';

const router = useRouter();
const auth = useAuthStore();
const props = defineProps<{ id?: string }>();

const isEdit = computed(() => props.id !== undefined);
const statusColor = computed(() => {
  if (publication.value === null) return 'grey';
  if (publication.value.status === 'published') return 'positive';
  return publication.value.status === 'sandbox' ? 'warning' : 'secondary';
});

const publication = ref<Publication | null>(null);
const saving = ref(false);
const formError = ref('');
const errors = ref<Record<string, string>>({});
const hubsAll = ref<Hub[]>([]);
const hubOptions = ref<{ label: string; value: number }[]>([]);
const tagSuggestions = ref<string[]>([]);

const form = reactive({
  title: '', lead: '', body: '', type: 'article', status: 'draft',
  difficulty: null as string | null, label: null as string | null,
  is_translation: false, source_url: null, original_author: null,
  hubs: [] as number[], tags: [] as string[]
});
const tagList = ref<string[]>([]);

const typeOptions = [{ label: 'Статья', value: 'article' }, { label: 'Пост', value: 'post' }, { label: 'Новость', value: 'news' }];
const difficultyOptions = [{ label: 'Простая', value: 'easy' }, { label: 'Средняя', value: 'medium' }, { label: 'Сложная', value: 'hard' }];
const labelOptions = [
  { label: 'Туториал', value: 'tutorial' }, { label: 'Кейс', value: 'case' }, { label: 'Аналитика', value: 'analytics' },
  { label: 'Мнение', value: 'opinion' }, { label: 'Обзор', value: 'review' }, { label: 'Дайджест', value: 'digest' },
  { label: 'Ретроспектива', value: 'retrospective' }
];

function addTag(input: string, update: (callback: () => void) => void): void {
  update(() => { const tag = input.trim().toLowerCase(); if (tag !== '' && !tagList.value.includes(tag)) tagList.value.push(tag); });
}
function filterTags(input: string, update: (callback: () => void) => void): void {
  update(() => { tagSuggestions.value = []; void input; });
}

async function loadHubs(): Promise<void> {
  const { data } = await api.get<Paginated<Hub>>('/hubs', { params: { per_page: 100 } });
  hubsAll.value = Array.isArray(data.data) ? data.data : [];
  hubOptions.value = [...hubsAll.value].sort((a, b) => a.name.localeCompare(b.name, 'ru')).map((h) => ({ label: h.name, value: h.id }));
}

async function loadExisting(): Promise<void> {
  if (props.id === undefined) return;
  try {
    const { data } = await api.get<{ data: Publication }>(`/publications/${props.id}`);
    const pub = data.data;
    publication.value = pub;
    Object.assign(form, {
      title: pub.title, lead: pub.lead ?? '', body: pub.body ?? '', type: pub.type,
      difficulty: pub.difficulty, label: pub.label, is_translation: pub.is_translation,
      source_url: null, original_author: pub.original_author ?? null,
      hubs: pub.hubs.map((h) => h.id), tags: []
    });
    tagList.value = pub.tags.map((t) => t.name);
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    Notify.create({ type: 'negative', message: status === 404 ? 'Публикация не найдена' : 'Ошибка загрузки' });
    await router.push('/');
  }
}

async function submit(): Promise<void> {
  saving.value = true; errors.value = {}; formError.value = '';
  const payload = { ...form, lead: form.lead === '' ? null : form.lead, difficulty: form.difficulty, label: form.label, tags: tagList.value };
  try {
    if (isEdit.value && props.id !== undefined) {
      await api.put(`/publications/${props.id}`, payload);
      Notify.create({ type: 'positive', message: 'Сохранено' });
    } else {
      const { data } = await api.post<{ data: Publication }>('/publications', payload);
      Notify.create({ type: 'positive', message: 'Публикация создана' });
      await router.push(`/editor/${data.data.id}`);
      return;
    }
  } catch (error) {
    const axiosError = error as AxiosError<ValidationErrorResponse>;
    errors.value = extractValidationErrors(axiosError);
    formError.value = Object.values(errors.value)[0] ?? axiosError.response?.data?.message ?? 'Ошибка сохранения';
  } finally {
    saving.value = false;
  }
}

async function publish(): Promise<void> {
  if (props.id === undefined) return;
  await api.post(`/publications/${props.id}/publish`);
  Notify.create({ type: 'positive', message: 'Опубликовано!' });
  if (publication.value !== null) publication.value.status = 'published';
}

async function remove(): Promise<void> {
  if (props.id === undefined) return;
  await api.delete(`/publications/${props.id}`);
  Notify.create({ type: 'info', message: 'Публикация удалена' });
  await router.push('/');
}

watch(() => props.id, (newId, oldId) => { if (newId !== undefined && newId !== oldId) void loadExisting(); });

onMounted(async () => {
  if (!auth.isLoggedIn) { await router.push('/login'); return; }
  await loadHubs();
  await loadExisting();
});
</script>
