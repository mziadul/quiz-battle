<template>
    <div
        class="h-screen w-full bg-slate-100 dark:bg-slate-950 flex font-sans text-slate-800 dark:text-slate-200 antialiased overflow-hidden"
    >
        <AdminSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />

        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
            <AdminHeader @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />

            <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import AdminSidebar from "@/Components/Admin/AdminSidebar.vue";
import AdminHeader from "@/Components/Admin/AdminHeader.vue";

const isSidebarOpen = ref(false);
</script>
