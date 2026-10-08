<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  questions: Object,
  categories: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category_id || '');

const isModalOpen = ref(false);
const isImportModalOpen = ref(false);
const editingQuestion = ref(null);

const form = useForm({
  category_id: '',
  question_text: '',
  options: {
    a: '',
    b: '',
    c: '',
    d: '',
  },
  correct_option: 'a',
  base_point: 100,
  time_limit_sec: 15,
});

const importForm = useForm({
  csv_file: null,
});

const openCreateModal = () => {
  editingQuestion.value = null;
  form.reset();
  isModalOpen.value = true;
};

const openEditModal = (q) => {
  editingQuestion.value = q;
  form.category_id = q.category_id || '';
  form.question_text = q.question_text;
  form.options = {
    a: q.options?.a || q.options?.A || '',
    b: q.options?.b || q.options?.B || '',
    c: q.options?.c || q.options?.C || '',
    d: q.options?.d || q.options?.D || '',
  };
  form.correct_option = String(q.correct_option).toLowerCase();
  form.base_point = q.base_point || 100;
  form.time_limit_sec = q.time_limit_sec || 15;
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  form.reset();
};

const submitForm = () => {
  if (editingQuestion.value) {
    form.put(`/admin/questions/${editingQuestion.value.id}`, {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post('/admin/questions', {
      onSuccess: () => closeModal(),
    });
  }
};

const submitImportForm = () => {
  importForm.post('/admin/questions/import-csv', {
    onSuccess: () => {
      isImportModalOpen.value = false;
      importForm.reset();
    },
  });
};

const deleteQuestion = (id) => {
  if (confirm('Are you sure you want to delete this question?')) {
    router.delete(`/admin/questions/${id}`);
  }
};

const handleFilter = () => {
  router.get(
    '/admin/questions',
    { search: search.value, category_id: selectedCategory.value },
    { preserveState: true }
  );
};
</script>

<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Question Bank</h1>
          <p class="text-xs text-slate-500">Manage questions manually or bulk upload via CSV</p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Sample CSV Download -->
          <a
            href="/admin/questions/sample-csv"
            class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-bold transition-all"
          >
            📥 Sample CSV
          </a>

          <!-- Import CSV Button -->
          <button
            @click="isImportModalOpen = true"
            class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition-all"
          >
            📤 Import CSV
          </button>

          <!-- Add Single Question -->
          <button
            @click="openCreateModal"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition-all"
          >
            + Add Question
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
        <input
          v-model="search"
          @input="handleFilter"
          type="text"
          placeholder="Search question text..."
          class="w-full sm:w-64 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 focus:outline-none"
        />

        <select
          v-model="selectedCategory"
          @change="handleFilter"
          class="w-full sm:w-48 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 focus:outline-none"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-400 uppercase">
              <th class="p-4">Question</th>
              <th class="p-4">Category</th>
              <th class="p-4">Points</th>
              <th class="p-4">Time Limit</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            <tr v-for="q in questions.data" :key="q.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
              <td class="p-4 font-bold text-slate-800 dark:text-slate-200 max-w-xs truncate">{{ q.question_text }}</td>
              <td class="p-4 text-slate-500">{{ q.category?.name || 'Uncategorized' }}</td>
              <td class="p-4 font-semibold text-amber-500">{{ q.base_point }} pts</td>
              <td class="p-4 text-slate-400">{{ q.time_limit_sec }}s</td>
              <td class="p-4 text-right space-x-2">
                <button @click="openEditModal(q)" class="text-indigo-600 font-bold hover:underline">Edit</button>
                <button @click="deleteQuestion(q.id)" class="text-rose-500 font-bold hover:underline">Delete</button>
              </td>
            </tr>
            <tr v-if="questions.data.length === 0">
              <td colspan="5" class="p-4 text-center text-slate-400 italic">No questions found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Import CSV Modal -->
      <div v-if="isImportModalOpen" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Bulk Upload Questions (CSV)</h3>
            <button @click="isImportModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
          </div>

          <p class="text-xs text-slate-500 leading-relaxed">
            Please make sure your CSV headers match the sample format:
            <code class="bg-slate-100 dark:bg-slate-800 text-indigo-500 px-1 py-0.5 rounded text-[10px] block mt-1">
              category_id, question_text, option_a, option_b, option_c, option_d, correct_option, base_point, time_limit_sec
            </code>
          </p>

          <form @submit.prevent="submitImportForm" class="space-y-4 pt-2">
            <div>
              <input
                type="file"
                accept=".csv"
                @change="importForm.csv_file = $event.target.files[0]"
                required
                class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 dark:file:bg-slate-800 dark:file:text-indigo-400"
              />
              <span v-if="importForm.errors.csv_file" class="text-[10px] text-rose-500 mt-1 block">
                {{ importForm.errors.csv_file }}
              </span>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="isImportModalOpen = false"
                class="px-3 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg text-xs font-bold"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="importForm.processing"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold"
              >
                Upload & Import
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Single Question Create/Edit Modal (Previous Code) -->
      <div v-if="isModalOpen" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
            {{ editingQuestion ? 'Edit Question' : 'Create Question' }}
          </h3>

          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Category</label>
              <select v-model="form.category_id" class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none">
                <option value="">Select Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>

            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Question Text</label>
              <textarea v-model="form.question_text" required rows="2" class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Option A</label>
                <input v-model="form.options.a" type="text" required class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>
              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Option B</label>
                <input v-model="form.options.b" type="text" required class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>
              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Option C</label>
                <input v-model="form.options.c" type="text" class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>
              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Option D</label>
                <input v-model="form.options.d" type="text" class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Correct Answer</label>
                <select v-model="form.correct_option" class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100">
                  <option value="a">A</option>
                  <option value="b">B</option>
                  <option value="c">C</option>
                  <option value="d">D</option>
                </select>
              </div>

              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Base Points</label>
                <input v-model="form.base_point" type="number" required class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>

              <div>
                <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Time (Seconds)</label>
                <input v-model="form.time_limit_sec" type="number" required class="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100" />
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeModal" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg text-xs font-bold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>