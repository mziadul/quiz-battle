<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  categories: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const isModalOpen = ref(false);
const editingCategory = ref(null);

const form = useForm({
  name: '',
  description: '',
  is_active: true,
});

const openCreateModal = () => {
  editingCategory.value = null;
  form.reset();
  isModalOpen.value = true;
};

const openEditModal = (category) => {
  editingCategory.value = category;
  form.name = category.name;
  form.description = category.description || '';
  form.is_active = Boolean(category.is_active);
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  form.reset();
};

const submitForm = () => {
  if (editingCategory.value) {
    form.put(`/admin/categories/${editingCategory.value.id}`, {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post('/admin/categories', {
      onSuccess: () => closeModal(),
    });
  }
};

const deleteCategory = (id) => {
  if (confirm('Are you sure you want to delete this category?')) {
    router.delete(`/admin/categories/${id}`);
  }
};

const handleSearch = () => {
  router.get('/admin/categories', { search: search.value }, { preserveState: true });
};
</script>

<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Question Categories</h1>
          <p class="text-xs text-slate-500">Manage quiz categories and general details</p>
        </div>
        <button
          @click="openCreateModal"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition-all"
        >
          + Add Category
        </button>
      </div>

      <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
        <input
          v-model="search"
          @input="handleSearch"
          type="text"
          placeholder="Search categories..."
          class="w-full sm:w-64 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 focus:outline-none"
        />
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-400 uppercase">
              <th class="p-4">Name</th>
              <th class="p-4">Slug</th>
              <th class="p-4">Questions</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
              <td class="p-4 font-bold text-slate-800 dark:text-slate-200">{{ cat.name }}</td>
              <td class="p-4 text-slate-500">{{ cat.slug }}</td>
              <td class="p-4 font-semibold text-indigo-500">{{ cat.questions_count || 0 }}</td>
              <td class="p-4">
                <span
                  :class="cat.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                  class="px-2 py-0.5 rounded-md text-[10px] font-extrabold"
                >
                  {{ cat.is_active ? 'ACTIVE' : 'INACTIVE' }}
                </span>
              </td>
              <td class="p-4 text-right space-x-2">
                <button @click="openEditModal(cat)" class="text-indigo-600 font-bold hover:underline">Edit</button>
                <button @click="deleteCategory(cat.id)" class="text-rose-500 font-bold hover:underline">Delete</button>
              </td>
            </tr>
            <tr v-if="categories.data.length === 0">
              <td colspan="5" class="p-4 text-center text-slate-400 italic">No categories found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal -->
      <div v-if="isModalOpen" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4">
          <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
            {{ editingCategory ? 'Edit Category' : 'Create Category' }}
          </h3>

          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Category Name</label>
              <input v-model="form.name" type="text" required class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none" />
            </div>

            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="cat_status" class="rounded text-indigo-600 focus:ring-0" />
              <label for="cat_status" class="text-xs font-bold text-slate-600 dark:text-slate-400">Active Category</label>
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