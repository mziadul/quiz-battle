<script setup>
import { ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

defineProps({
    isOpen: Boolean,
});

defineEmits(["close"]);

const page = usePage();

// ইউজারের বর্তমান পেজ ক্যাটাগরি বা কোয়েশ্চনে থাকলে অটো ড্রপডাউন খোলা থাকবে
const isQuizMenuOpen = ref(
    page.url.startsWith("/admin/categories") ||
        page.url.startsWith("/admin/questions")
);
</script>

<template>
    <div>
        <!-- Mobile Overlay -->
        <div
            v-if="isOpen"
            @click="$emit('close')"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 lg:hidden transition-opacity"
        ></div>

        <!-- Sidebar -->
        <aside
            class="fixed lg:static inset-y-0 left-0 w-64 bg-slate-900 text-slate-300 flex flex-col h-full z-50 transform transition-transform duration-300 ease-in-out border-r border-slate-800 shrink-0 font-sans select-none"
            :class="
                isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            "
        >
            <!-- Header -->
            <div
                class="h-16 flex items-center justify-between px-5 border-b border-slate-800/80 shrink-0"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-sm"
                    >
                        Q
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="font-bold text-white text-sm tracking-tight leading-none"
                        >
                            QuizBattle
                        </span>
                        <span
                            class="text-[10px] text-slate-400 font-medium mt-1"
                            >Console</span
                        >
                    </div>
                </div>

                <button
                    @click="$emit('close')"
                    class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3 py-5 space-y-6 overflow-y-auto">
                <!-- Core Operations Group -->
                <div>
                    <p
                        class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2"
                    >
                        Core Operations
                    </p>
                    <div class="space-y-1">
                        <Link
                            href="/admin"
                            @click="$emit('close')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-all"
                            :class="
                                $page.url === '/admin'
                                    ? 'bg-indigo-600 text-white font-bold shadow-sm'
                                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                            "
                        >
                            <svg
                                class="w-4 h-4 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                                />
                            </svg>
                            <span>Dashboard</span>
                        </Link>
                    </div>
                </div>

                <!-- Management Group -->
                <div>
                    <p
                        class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2"
                    >
                        Management
                    </p>
                    <div class="space-y-1">
                        <!-- Collapsible Quiz Parent Menu -->
                        <div>
                            <button
                                @click="isQuizMenuOpen = !isQuizMenuOpen"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:bg-slate-800/60 hover:text-slate-200"
                                :class="{
                                    'text-indigo-400 font-bold':
                                        $page.url.startsWith('/admin/categories') ||
                                        $page.url.startsWith('/admin/questions'),
                                }"
                            >
                                <div class="flex items-center gap-3">
                                    <svg
                                        class="w-4 h-4 shrink-0"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <span>Quiz</span>
                                </div>
                                <svg
                                    class="w-3.5 h-3.5 transition-transform duration-200"
                                    :class="isQuizMenuOpen ? 'rotate-90' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </button>

                            <!-- Quiz Sub-Menu Options -->
                            <div
                                v-show="isQuizMenuOpen"
                                class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1"
                            >
                                <Link
                                    href="/admin/categories"
                                    @click="$emit('close')"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-all"
                                    :class="
                                        $page.url.startsWith('/admin/categories')
                                            ? 'bg-indigo-600 text-white font-bold shadow-sm'
                                            : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                                    "
                                >
                                    <span>Categories</span>
                                </Link>

                                <Link
                                    href="/admin/questions"
                                    @click="$emit('close')"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-all"
                                    :class="
                                        $page.url.startsWith('/admin/questions')
                                            ? 'bg-indigo-600 text-white font-bold shadow-sm'
                                            : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                                    "
                                >
                                    <span>Questions</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Users & Subscriptions Link -->
                        <Link
                            href="/admin/users"
                            @click="$emit('close')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-all"
                            :class="
                                $page.url.startsWith('/admin/users')
                                    ? 'bg-indigo-600 text-white font-bold shadow-sm'
                                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                            "
                        >
                            <svg
                                class="w-4 h-4 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
                            <span>Users & Subscriptions</span>
                        </Link>
                    </div>
                </div>
            </nav>
        </aside>
    </div>
</template>