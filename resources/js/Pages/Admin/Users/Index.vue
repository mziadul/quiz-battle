<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  users: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const isModalOpen = ref(false);
const editingUser = ref(null);

const form = useForm({
  name: '',
  email: '',
  password: '',
  type: 'player',
});

const openCreateModal = () => {
  editingUser.value = null;
  form.reset();
  form.type = 'player';
  isModalOpen.value = true;
};

const openEditModal = (user) => {
  editingUser.value = user;
  form.name = user.name;
  form.email = user.email;
  form.password = '';
  form.type = user.type || 'player';
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  form.reset();
};

const submitForm = () => {
  if (editingUser.value) {
    form.put(`/admin/users/${editingUser.value.id}`, {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post('/admin/users', {
      onSuccess: () => closeModal(),
    });
  }
};

const deleteUser = (id) => {
  if (confirm('Are you sure you want to delete this user?')) {
    router.delete(`/admin/users/${id}`);
  }
};

const handleFilter = () => {
  router.get(
    '/admin/users',
    { search: search.value, type: selectedType.value },
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
          <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Users & Subscriptions</h1>
          <p class="text-xs text-slate-500">Manage registered accounts, player types, and administrative access</p>
        </div>
        <button
          @click="openCreateModal"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition-all"
        >
          + Add User
        </button>
      </div>

      <!-- Filters Bar -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
        <input
          v-model="search"
          @input="handleFilter"
          type="text"
          placeholder="Search by name or email..."
          class="w-full sm:w-64 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 focus:outline-none"
        />

        <select
          v-model="selectedType"
          @change="handleFilter"
          class="w-full sm:w-48 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 focus:outline-none capitalize"
        >
          <option value="">All User Types</option>
          <option value="player">Player</option>
          <option value="admin">Admin</option>
          <option value="vip">VIP</option>
        </select>
      </div>

      <!-- User Table -->
      <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-400 uppercase">
              <th class="p-4">Name</th>
              <th class="p-4">Email</th>
              <th class="p-4">Type</th>
              <th class="p-4">Joined Date</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
              <td class="p-4 font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-[10px] uppercase">
                  {{ user.name.substring(0, 2) }}
                </div>
                <span>{{ user.name }}</span>
              </td>
              <td class="p-4 text-slate-500">{{ user.email }}</td>
              <td class="p-4">
                <span
                  :class="{
                    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400': user.type === 'admin',
                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': user.type === 'vip',
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': user.type === 'player'
                  }"
                  class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase"
                >
                  {{ user.type || 'player' }}
                </span>
              </td>
              <td class="p-4 text-slate-400">{{ new Date(user.created_at).toLocaleDateString() }}</td>
              <td class="p-4 text-right space-x-2">
                <button @click="openEditModal(user)" class="text-indigo-600 font-bold hover:underline">Edit</button>
                <button @click="deleteUser(user.id)" class="text-rose-500 font-bold hover:underline">Delete</button>
              </td>
            </tr>
            <tr v-if="users.data.length === 0">
              <td colspan="5" class="p-4 text-center text-slate-400 italic">No users found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="isModalOpen" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4">
          <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
            {{ editingUser ? 'Edit User' : 'Create User' }}
          </h3>

          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Full Name</label>
              <input v-model="form.name" type="text" required class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none" />
              <span v-if="form.errors.name" class="text-[10px] text-rose-500 mt-1 block">{{ form.errors.name }}</span>
            </div>

            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">Email Address</label>
              <input v-model="form.email" type="email" required class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none" />
              <span v-if="form.errors.email" class="text-[10px] text-rose-500 mt-1 block">{{ form.errors.email }}</span>
            </div>

            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">User Type</label>
              <select v-model="form.type" required class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none capitalize">
                <option value="player">Player</option>
                <option value="admin">Admin</option>
                <option value="vip">VIP</option>
              </select>
              <span v-if="form.errors.type" class="text-[10px] text-rose-500 mt-1 block">{{ form.errors.type }}</span>
            </div>

            <div>
              <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block mb-1">
                Password {{ editingUser ? '(Leave blank to keep unchanged)' : '' }}
              </label>
              <input v-model="form.password" type="password" :required="!editingUser" class="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none" />
              <span v-if="form.errors.password" class="text-[10px] text-rose-500 mt-1 block">{{ form.errors.password }}</span>
            </div>

            <div class="flex justify-end gap-2 pt-3">
              <button type="button" @click="closeModal" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg text-xs font-bold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold">Save User</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>