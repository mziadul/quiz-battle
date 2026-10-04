<template>
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20 shrink-0 font-sans transition-colors duration-200">
        <div class="flex items-center gap-3">
            <!-- মোবাইলে সাইডবার খোলার হ্যামবার্গার বাটন -->
            <button 
                @click="$emit('toggle-sidebar')" 
                class="lg:hidden p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg focus:outline-none"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <!-- সার্চ ইনপুট (মোবাইলে প্রস্থ অটো-এডজাস্ট হবে) -->
            <div class="relative w-40 sm:w-72 flex items-center">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input 
                    type="text" 
                    placeholder="Search..." 
                    class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition-colors"
                />
            </div>
        </div>

        <!-- ডার্ক মোড টগল ও প্রোফাইল -->
        <div class="flex items-center gap-2 sm:gap-3">
            <button 
                @click="toggleDarkMode" 
                type="button"
                class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                title="Toggle Theme"
            >
                <svg v-if="isDark" class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <svg v-else class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>

            <div class="h-4 w-px bg-slate-200 dark:bg-slate-800"></div>

            <div class="relative">
                <button 
                    @click="isDropdownOpen = !isDropdownOpen" 
                    class="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                >
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">
                        {{ $page.props.auth?.user?.name ? $page.props.auth.user.name.charAt(0) : 'A' }}
                    </div>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 hidden sm:inline-block">
                        {{ $page.props.auth?.user?.name || 'Admin' }}
                    </span>
                </button>

                <div 
                    v-if="isDropdownOpen" 
                    @click="isDropdownOpen = false"
                    class="absolute right-0 mt-2 w-44 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg py-1 z-30 font-sans"
                >
                    <Link href="/profile" class="block px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700">Profile</Link>
                    <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                    <Link href="/logout" method="post" as="button" class="w-full text-left px-3 py-1.5 text-xs text-rose-600 dark:text-rose-400 hover:bg-slate-100 dark:hover:bg-slate-700">Log Out</Link>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

defineEmits(['toggle-sidebar']);

const isDropdownOpen = ref(false);
const isDark = ref(false);

const updateTheme = (dark) => {
    isDark.value = dark;
    if (dark) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const toggleDarkMode = () => updateTheme(!isDark.value);

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        updateTheme(true);
    } else {
        updateTheme(false);
    }
});
</script>