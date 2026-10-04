<template>
    <AdminLayout>
        <!-- পেজ হেডার & বাটন -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800 mb-8">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Question Bank</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage and organize all test questions in the system.</p>
            </div>
            <button class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create New Question</span>
            </button>
        </div>

        <!-- কনভেনショナル টেবিল কার্ড -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">All Questions (1,240)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Question Title</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 font-mono text-[11px] font-semibold text-slate-400">#101</td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200">What is the capital of France?</td>
                            <td class="px-6 py-4 text-slate-500 font-medium">Geography</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800">
                                    Active
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button class="text-indigo-600 hover:text-indigo-800 font-bold">Edit</button>
                                <button class="text-rose-600 hover:text-rose-800 font-bold">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>